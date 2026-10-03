<?php

/**
 * @Project: Opus
 * @Version: 1.0
 * @Author: Tomasz Ułazowski
 * @Date:   2026-10-05 12:00:00
 * @Last Modified by:   Tomasz Ułazowski
 * @Last Modified time: 2026-10-03 09:53:48
 **/

namespace Opus\controller\login;

use Exception;
use stdClass;
use Opus\storage\db\Db;

class LdapAuth
{
	private object $authConfig;
	private ?\LDAP\Connection $connection = null;
	private ?array $searchResult = null;

	/**
	 * @param object $authConfig Auth config from Config::getConfig()->auth
	 */
	public function __construct(object $authConfig)
	{
		$this->authConfig = $authConfig;
	}

	public function __destruct()
	{
		if ($this->connection) {
			@ldap_unbind($this->connection);
		}
	}

	/**
	 * Authenticates user against LDAP server
	 * 1. Service account bind (search)
	 * 2. Find user by sAMAccountName
	 * 3. User bind (password verification)
	 *
	 * @param string $user sAMAccountName
	 * @param string $password User password
	 * @return bool
	 */
	public function authenticate(string $user, string $password): bool
	{
		try {
			ldap_set_option(null, LDAP_OPT_X_TLS_CACERTFILE, $this->authConfig->ca_cert);

			$this->connection = ldap_connect($this->authConfig->host, $this->authConfig->port);

			if ($this->connection === false) {
				return false;
			}

			ldap_set_option($this->connection, LDAP_OPT_PROTOCOL_VERSION, 3);
			ldap_set_option($this->connection, LDAP_OPT_REFERRALS, 0);

			// service account bind
			if (!@ldap_bind($this->connection, $this->authConfig->bind_dn, $this->authConfig->bind_password)) {
				return false;
			}

			// search for user
			$filter = sprintf('(sAMAccountName=%s)', ldap_escape($user, '', LDAP_ESCAPE_FILTER));
			$attrs = ['dn', 'sn', 'givenName', 'mail', 'homePhone', 'mobile', 'preferredLanguage', 'memberOf'];

			$search = @ldap_search($this->connection, $this->authConfig->user_dn, $filter, $attrs);

			if ($search === false || ldap_count_entries($this->connection, $search) !== 1) {
				return false;
			}

			$entry = ldap_first_entry($this->connection, $search);
			$this->searchResult = ldap_get_attributes($this->connection, $entry);
			$userDn = ldap_get_dn($this->connection, $entry);

			// user bind — password verification
			if (!@ldap_bind($this->connection, $userDn, $password)) {
				$this->searchResult = null;
				return false;
			}

			return true;
		} catch (Exception) {
			return false;
		}
	}

	/**
	 * Returns user attributes from LDAP search result
	 *
	 * @return stdClass {lastname, firstname, email, homephone, cellphone, lang}
	 */
	public function getUserAttributes(): stdClass
	{
		$attrs = new stdClass();
		$attrs->lastname = $this->getAttr('sn');
		$attrs->firstname = $this->getAttr('givenName');
		$attrs->email = $this->getAttr('mail');
		$attrs->homephone = $this->getAttr('homePhone');
		$attrs->cellphone = $this->getAttr('mobile');
		$attrs->lang = $this->getAttr('preferredLanguage');

		return $attrs;
	}

	/**
	 * Returns list of AD group names from memberOf attribute
	 *
	 * @return array CN names of groups
	 */
	public function getUserGroups(): array
	{
		if (!isset($this->searchResult['memberOf'])) {
			return [];
		}

		$groups = [];

		for ($i = 0; $i < $this->searchResult['memberOf']['count']; $i++) {
			if (preg_match('/^CN=([^,]+)/i', $this->searchResult['memberOf'][$i], $matches)) {
				$groups[] = $matches[1];
			}
		}

		return $groups;
	}

	/**
	 * Resolves highest access level from AD groups via group_map
	 *
	 * @param array $adGroups AD group CN names
	 * @return int|false Highest glevel or false if no match
	 */
	public function resolveLevel(array $adGroups): int|false
	{
		$groupMap = (array) $this->authConfig->group_map;
		$mapped = array_filter(
			array_map(fn($g) => $groupMap[$g] ?? null, $adGroups)
		);

		if (empty($mapped)) {
			return false;
		}

		$params = ['prepare' => ''];
		$placeholders = [];

		foreach (array_values($mapped) as $i => $gname) {
			$key = ':g' . $i;
			$placeholders[] = $key;
			$params[$key] = $gname;
		}

		$params['prepare'] = 'SELECT MAX(glevel) AS maxlevel FROM groups.groups WHERE gname IN (' . implode(',', $placeholders) . ')';
		$result = Db::dbExecute($params);

		return isset($result[0]['maxlevel']) ? (int) $result[0]['maxlevel'] : false;
	}

	/**
	 * Extracts single attribute value from LDAP search result
	 *
	 * @param string $name LDAP attribute name
	 * @return ?string
	 */
	private function getAttr(string $name): ?string
	{
		return (isset($this->searchResult[$name][0]) && $this->searchResult[$name][0] !== '')
			? $this->searchResult[$name][0]
			: null;
	}
}
