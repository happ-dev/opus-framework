<?php

/**
 * @Project: Opus
 * @Version: 1.0
 * @Author: Tomasz Ułazowski
 * @Date:   2026-02-13 08:31:06
 * @Last Modified by:   Tomasz Ułazowski
 * @Last Modified time: 2026-08-18 16:54:24
 **/

namespace Opus\controller\login;

use Opus\config\Config;
use Opus\config\EncryptStorageConfig;
use Opus\controller\cli\CliColor;
use Opus\controller\request\Request;
use Opus\controller\lang\Lang;
use Opus\storage\db\Db;
use Opus\controller\exception\ControllerException;
use Opus\controller\exception\LogHandlerException;
use Opus\storage\exception\StorageException;

abstract class AbstractLogin
{
	/**
	 * Handles different types of login processes based on the login type
	 *
	 * @param string $loginType Type of login to process:
	 * 					- TYPE_LOGIN_PAGE: Web page login
	 * 					- TYPE_LOGIN_CLI: Command line interface login
	 * 					- TYPE_LOGIN_API: API login
	 *
	 * @throws ControllerException When invalid login type is provided
	 * @return void
	 *
	 * @see Login::TYPE_LOGIN_PAGE
	 * @see Login::TYPE_LOGIN_CLI
	 * @see Login::TYPE_LOGIN_API
	 */
	abstract public static function login(string $loginType);

	/**
	 * Logs out the current user and destroys their session
	 *
	 * @param string $logoutType Type of login to process:
	 * 					- TYPE_LOGIN_PAGE: Web page login
	 * 					- TYPE_LOGIN_CLI: Command line interface login
	 * 					- TYPE_LOGIN_API: API login
	 *
	 * This method:
	 * - Clears all session data
	 * - Destroys the session
	 * - Resets session variables to default values
	 * - Redirects to the main page
	 *
	 * @return void
	 *
	 * @see session_unset()
	 * @see session_destroy()
	 * @see Request::url()
	 */
	abstract public static function logout(string $logoutType);

	/**
	 * Reloads user configuration settings
	 *
	 * Creates a temporary login instance to refresh user configuration
	 * settings without requiring a full re-login. This is typically used
	 * when user settings have been modified and need to be reflected
	 * in the current session.
	 *
	 * @return void
	 *
	 * @see Login::reloadUserConfig()
	 */
	abstract public static function reloadConfig();

	const TYPE_LOGIN_PAGE = 'login_page';
	const TYPE_LOGIN_CLI = 'login_cli';
	const TYPE_LOGIN_API = 'login_api';

	const VALID_LOGIN_REGEX = ['options' => ['regexp' => '/^[a-zA-Z][a-zA-Z0-9_-]{0,20}$/']];

	/**
	 * Retrieves and validates user login from POST data
	 *
	 * @return string Validated login value
	 * @throws ControllerException If login format is invalid
	 */
	protected function getUserLoginFromPage()
	{
		$login = Request::post('opus-login-input', FILTER_VALIDATE_REGEXP, self::VALID_LOGIN_REGEX);

		if ($login === false) {
			throw new ControllerException(
				'controller\login\getUserLogin',
				['message' => Request::post('opus-login-input')]
			);
		}

		return $login;
	}

	/**
	 * Retrieves user password from POST data
	 *
	 * @return string User password
	 */
	protected function getUserPasswordFromPage()
	{
		$password = Request::post('opus-login-password');
		return $password;
	}

	/**
	 * Retrieves and validates user login from CLI
	 *
	 * @return string Validated login value
	 * @throws ControllerException If login format is invalid or input fails
	 */
	protected function getUserLoginFromCli()
	{
		echo CliColor::write(
			Lang::getInstance()->get('controller.login.user'),
			CliColor::COLOR_LIGHT_CYAN,
			null,
			false
		);
		$stdin = STDIN;  // Using PHP's built-in STDIN constant
		$input = trim(fgets($stdin));
		$login = filter_var($input, FILTER_VALIDATE_REGEXP, self::VALID_LOGIN_REGEX);

		if ($login === false) {
			throw new ControllerException(
				'controller\login\getUserLogin',
				['message' => $input],
				ControllerException::TYPE_CLI_EXCEPTION
			);
		}

		return $login;
	}

	/**
	 * Retrieves user password from CLI
	 *
	 * @return string User password
	 */
	protected function getUserPasswordFromCli()
	{
		echo CliColor::write(
			Lang::getInstance()->get('controller.login.password'),
			CliColor::COLOR_LIGHT_CYAN,
			null,
			false
		);
		system('stty -echo');
		$objStdin = fopen('php://stdin', 'r');
		$input = fgets($objStdin, 11);
		fclose($objStdin);
		system('stty echo');
		echo PHP_EOL;
		return rtrim($input);
	}

	/**
	 * Checks if the provided user exists in the database
	 *
	 * @param string $user User login
	 * @param string $storageException Storage exception type
	 * @return bool True if user exists, false otherwise
	 * @throws StorageException If there's an issue with the database operation
	 */
	protected function findUser(string $user, string $storageException = StorageException::TYPE_PAGE_EXCEPTION): bool
	{
		$result = Db::dbExecute(
			[
				'prepare' => 'SELECT TRUE AS "bool" FROM users.users WHERE login = :user',
				':user' => $user
			],
			null,
			$storageException
		);

		return (isset($result[0]['bool']) && $result[0]['bool'] === true)
			? (bool) $result[0]['bool']
			: false;
	}

	/**
	 * Returns auth type for user
	 *
	 * @param string $user User login
	 * @param string $storageException Storage exception type
	 * @return string|false 'local', 'ldap' or false if user not found
	 */
	protected function getAuthType(string $user, string $storageException = StorageException::TYPE_PAGE_EXCEPTION): string|false
	{
		$result = Db::dbExecute(
			[
				'prepare' => 'SELECT auth_type FROM users.users WHERE login = :user',
				':user' => $user
			],
			null,
			$storageException
		);

		return $result[0]['auth_type'] ?? false;
	}

	/**
	 * Checks if the provided user account is enabled
	 *
	 * @param string $user User login
	 * @param string $storageException Storage exception type
	 * @return bool True if user account is enabled, false otherwise
	 * @throws StorageException If there's an issue with the database operation
	 */
	protected function isUserAccountEnabled(string $user, string $storageException = StorageException::TYPE_PAGE_EXCEPTION): bool
	{
		$result = Db::dbExecute(
			[
				'prepare' => 'SELECT TRUE AS "bool" FROM users.users WHERE login = :user AND active = TRUE',
				':user' => $user
			],
			null,
			$storageException
		);

		return (isset($result[0]['bool']) && $result[0]['bool'] === true)
			? $result[0]['bool']
			: false;
	}

	/**
	 * Authenticates user against LDAP and returns attributes with level
	 *
	 * @param string $user sAMAccountName
	 * @param string $pass User password
	 * @return object|false {attrs: stdClass, level: int} or false
	 */
	protected function authenticateLdap(string $user, string $pass): object|false
	{
		$config = clone Config::getConfig()->auth;
		$config->bind_password = EncryptStorageConfig::decrypt($config->bind_password);

		$ldap = new LdapAuth($config);

		if (!$ldap->authenticate($user, $pass)) {
			return false;
		}

		$level = $ldap->resolveLevel($ldap->getUserGroups());

		if ($level === false) {
			return false;
		}

		return (object) ['attrs' => $ldap->getUserAttributes(), 'level' => $level];
	}

	/**
	 * Checks if the provided password matches the user's stored password
	 *
	 * @param string $user User login
	 * @param string $pass User password
	 * @param string $storageException Storage exception type
	 * @return bool True if password matches, false otherwise
	 * @throws StorageException If there's an issue with the database operation
	 */
	protected function isPasswordMatches(
		string $user,
		string $pass,
		string $storageException = StorageException::TYPE_PAGE_EXCEPTION
	): bool {
		$hashPass = hash('sha256', $pass);
		$result = Db::dbExecute(
			[
				'prepare' => 'SELECT TRUE AS "bool" FROM users.users WHERE login = :user AND password = :pass',
				':user' => $user,
				':pass' => $hashPass
			],
			null,
			$storageException
		);

		return (isset($result[0]['bool']) && $result[0]['bool'] === true)
			? $result[0]['bool']
			: false;
	}

	/**
	 * Syncs LDAP user to local database (insert or update)
	 *
	 * @param string $user Login
	 * @param object $attrs Attributes from LdapAuth::getUserAttributes()
	 * @param int $level Resolved glevel
	 * @param string $storageException Storage exception type
	 */
	protected function syncLdapUser(
		string $user,
		object $attrs,
		int $level,
		string $storageException = StorageException::TYPE_PAGE_EXCEPTION
	): void {
		Db::dbExecute(
			[
				'prepare' => <<<SQL
					INSERT INTO users.users
						(login, ulevel, active, password, lastname, firstname, email, homephone, cellphone, lang, auth_type)
					VALUES
						(:login, :ulevel, TRUE, '', :lastname, :firstname, :email, :homephone, :cellphone, :lang, 'ldap')
					ON CONFLICT (login) DO UPDATE SET
						ulevel = :ulevel,
						lastname = :lastname,
						firstname = :firstname,
						email = :email,
						homephone = :homephone,
						cellphone = :cellphone
				SQL,
				':login' => $user,
				':ulevel' => $level,
				':lastname' => $attrs->lastname ?? '',
				':firstname' => $attrs->firstname ?? '',
				':email' => $attrs->email,
				':homephone' => $attrs->homephone,
				':cellphone' => $attrs->cellphone,
				':lang' => $attrs->lang ?? Config::getConfig()->langs[0]
			],
			null,
			$storageException
		);
	}

	/**
	 * Sets the logged-in user session variables
	 *
	 * @param string $user User login
	 * @param string $pass User password
	 * @param string $storageException Storage exception type
	 * @throws StorageException If there's an issue with the database operation
	 */
	protected function setLogged(
		string $user,
		string $pass,
		string $storageException = StorageException::TYPE_PAGE_EXCEPTION
	): void {
		$lang = Request::post('opus-login-lang');
		$hashPass = hash('sha256', $pass);
		$result = Db::dbExecute(
			[
				'prepare' => <<<SQL
					SELECT
						login, ulevel, lastname, firstname, email, homephone, cellphone, lang, auth_type
					FROM users.users WHERE login = :user AND password = :pass
				SQL,
				':user' => $user,
				':pass' => $hashPass
			],
			null,
			$storageException
		);

		// Set $_SESSION variables
		$_SESSION['logged'] = true;
		$_SESSION['login'] = $result[0]['login'];
		$_SESSION['level'] = $result[0]['ulevel'];
		$_SESSION['lastname'] = $result[0]['lastname'];
		$_SESSION['firstname'] = $result[0]['firstname'];
		$_SESSION['email'] = $result[0]['email'];
		$_SESSION['homephone'] = $result[0]['homephone'];
		$_SESSION['cellphone'] = $result[0]['cellphone'];
		$_SESSION['lang'] = $result[0]['lang'];
		$_SESSION['auth_type'] = $result[0]['auth_type'];
		$_SESSION['id'] = hash('sha256', $result[0]['login'] . date('Y-m-d H:i:s'));
		$_SESSION['csrf'] = [bin2hex(random_bytes(32))];
	}

	/**
	 * Sets session variables by login only (without password verification)
	 *
	 * @param string $user User login
	 * @param string $storageException Storage exception type
	 */
	protected function setLoggedByLogin(
		string $user,
		string $storageException = StorageException::TYPE_PAGE_EXCEPTION
	): void {
		$result = Db::dbExecute(
			[
				'prepare' => <<<SQL
					SELECT
						login, ulevel, lastname, firstname, email, homephone, cellphone, lang, auth_type
					FROM users.users WHERE login = :user
				SQL,
				':user' => $user
			],
			null,
			$storageException
		);

		$_SESSION['logged'] = true;
		$_SESSION['login'] = $result[0]['login'];
		$_SESSION['level'] = $result[0]['ulevel'];
		$_SESSION['lastname'] = $result[0]['lastname'];
		$_SESSION['firstname'] = $result[0]['firstname'];
		$_SESSION['email'] = $result[0]['email'];
		$_SESSION['homephone'] = $result[0]['homephone'];
		$_SESSION['cellphone'] = $result[0]['cellphone'];
		$_SESSION['lang'] = $result[0]['lang'];
		$_SESSION['auth_type'] = $result[0]['auth_type'];
		$_SESSION['id'] = hash('sha256', $result[0]['login'] . date('Y-m-d H:i:s'));
		$_SESSION['csrf'] = [bin2hex(random_bytes(32))];
	}

	/**
	 * Processes login based on auth type
	 *
	 * @param string $user User login
	 * @param string $pass User password
	 * @param string $storageEx Storage exception type
	 * @param string $controllerEx Controller exception type
	 */
	private function processLogin(
		string $user,
		string $pass,
		string $storageEx = StorageException::TYPE_PAGE_EXCEPTION,
		string $controllerEx = ControllerException::TYPE_PAGE_EXCEPTION
	): void {
		$authType = $this->getAuthType($user, $storageEx);

		match (true) {
			$authType === 'local' => $this->processLocalLogin($user, $pass, $storageEx, $controllerEx),
			$authType === 'ldap' => $this->processLdapLogin($user, $pass, $storageEx, $controllerEx),
			$authType === false && Config::getConfig()->auth->driver === 'ldap'
				=> $this->processFirstLdapLogin($user, $pass, $storageEx, $controllerEx),
			default => throw new ControllerException(
				'controller\login\findUser',
				['message' => $user, 'details' => Config::getConfig()->email],
				$controllerEx
			)
		};
	}

	/**
	 * @param string $user User login
	 * @param string $pass User password
	 * @param string $storageEx Storage exception type
	 * @param string $controllerEx Controller exception type
	 */
	private function processLocalLogin(string $user, string $pass, string $storageEx, string $controllerEx): void
	{
		if (!$this->isUserAccountEnabled($user, $storageEx)) {
			throw new ControllerException(
				'controller\login\isUserAccountEnabled',
				['message' => $user, 'details' => Config::getConfig()->email],
				$controllerEx
			);
		}
		if (!$this->isPasswordMatches($user, $pass, $storageEx)) {
			throw new ControllerException('controller\login\isPasswordMatches', null, $controllerEx);
		}
		$this->setLogged($user, $pass, $storageEx);
	}

	/**
	 * @param string $user User login
	 * @param string $pass User password
	 * @param string $storageEx Storage exception type
	 * @param string $controllerEx Controller exception type
	 */
	private function processLdapLogin(string $user, string $pass, string $storageEx, string $controllerEx): void
	{
		if (!$this->isUserAccountEnabled($user, $storageEx)) {
			throw new ControllerException(
				'controller\login\isUserAccountEnabled',
				['message' => $user, 'details' => Config::getConfig()->email],
				$controllerEx
			);
		}
		$ldapResult = $this->authenticateLdap($user, $pass);
		if ($ldapResult === false) {
			throw new ControllerException('controller\login\isPasswordMatches', null, $controllerEx);
		}
		$this->syncLdapUser($user, $ldapResult->attrs, $ldapResult->level, $storageEx);
		$this->setLoggedByLogin($user, $storageEx);
	}

	/**
	 * @param string $user User login
	 * @param string $pass User password
	 * @param string $storageEx Storage exception type
	 * @param string $controllerEx Controller exception type
	 */
	private function processFirstLdapLogin(string $user, string $pass, string $storageEx, string $controllerEx): void
	{
		$ldapResult = $this->authenticateLdap($user, $pass);
		if ($ldapResult === false) {
			throw new ControllerException('controller\login\isPasswordMatches', null, $controllerEx);
		}
		$this->syncLdapUser($user, $ldapResult->attrs, $ldapResult->level, $storageEx);
		$this->setLoggedByLogin($user, $storageEx);
	}

	/**
	 * Handles the login page process
	 *
	 * @throws ControllerException If there's an issue during the login process
	 */
	protected function loginPage(): void
	{
		$user = $this->getUserLoginFromPage();
		$pass = $this->getUserPasswordFromPage();
		$this->processLogin($user, $pass);
		header('Location: ' . Request::url('index.php?page=main'));
		exit;
	}

	/**
	 * Handles the login CLI process
	 *
	 * @throws ControllerException If there's an issue during the login process
	 */
	protected function loginCli(): void
	{
		$user = $this->getUserLoginFromCli();
		$pass = $this->getUserPasswordFromCli();
		$this->processLogin($user, $pass, StorageException::TYPE_CLI_EXCEPTION, ControllerException::TYPE_CLI_EXCEPTION);
	}

	/**
	 * Handles the login API process
	 *
	 * @throws ControllerException If there's an issue during the login process
	 */
	protected function loginApi(): void
	{
		$this->processLogin(
			$_SERVER['PHP_AUTH_USER'],
			$_SERVER['PHP_AUTH_PW'],
			StorageException::TYPE_API_EXCEPTION,
			ControllerException::TYPE_API_EXCEPTION
		);
	}

	/**
	 * Updates user's password in the database
	 *
	 * @param string $user User login
	 * @param string $pass New password
	 * @throws StorageException If there's an issue with the database operation
	 */
	protected function updateUserPassword(string $user, string $pass): void
	{
		Db::dbTransactions(
			[
				[
					'prepare' => 'UPDATE users.users SET (password) = ROW (:pass) WHERE login = :user',
					'params' => [':pass', ':user'],
					':pass' => [hash('sha256', $pass)],
					':user' => [$user]
				],
				LogHandlerException::createLogTransactionParams(
					'Opus\controller\login\updateUserPassword',
					'User password: ' . $user . ' has been updated.',
					null,
					LogHandlerException::LOG_TYPE_APP
				)
			]
		);
	}

	/**
	 * Updates user's password by user ID and logs the administrative action
	 *
	 * This method performs a database transaction to update a user's password using their
	 * unique ID and creates an audit log entry indicating the password was changed by an
	 * administrator. The password is hashed using SHA-256 before storage.
	 *
	 * @param string $id The unique user ID (id__users)
	 * @param string $pass The new plain text password to be hashed and stored
	 * @return string|false user login or false if login with new password is not found
	 * @throws StorageException If there's an issue with the database transaction
	 */
	final protected function updateIdPassword(string $id, string $pass): string|false
	{
		Db::dbTransactions(
			[
				[
					'prepare' => <<<SQL
						UPDATE users.users SET (password) = ROW (:pass) WHERE id__users = :id__users
					SQL,
					':pass' => [hash('sha256', $pass)],
					':id__users' => [$id]
				],
				LogHandlerException::createLogTransactionParams(
					'Opus\controller\login\updateUserPassword',
					'User\'s password: ' . $id . ' has been changed by the administrator.',
					null,
					LogHandlerException::LOG_TYPE_APP
				)
			]
		);

		$result = Db::dbExecute(
			[
				'prepare' => <<<SQL
					SELECT login FROM users.users WHERE id__users = :id__users AND password = :pass
				SQL,
				':id__users' => $id,
				':pass' => hash('sha256', $pass)
			]
		);

		return (isset($result[0]['login']) && !is_null($result[0]['login']))
			? $result[0]['login']
			: false;
	}

	/**
	 * Reloads user configuration from database and updates session variables
	 *
	 * Fetches current user data from database and refreshes all session variables
	 * including user details and generates new CSRF token.
	 *
	 * @return void
	 * @throws ControllerException If user is not logged in
	 * @throws StorageException If there's an issue with the database operation
	 */
	final protected function reloadUserConfig(): void
	{
		if ($_SESSION['logged'] === false) {
			throw new ControllerException(
				'controller\login\reloadUserConfig',
				[],
				ControllerException::TYPE_API_STRONG_EXCEPTION
			);
		}

		$result = Db::dbExecute(
			[
				'prepare' => <<<SQL
					SELECT
						login, ulevel, lastname, firstname, email, homephone, cellphone, lang, auth_type
					FROM users.users WHERE login = :user
				SQL,
				':user' => $_SESSION['login']
			]
		);

		$_SESSION['login'] = $result[0]['login'];
		$_SESSION['level'] = $result[0]['ulevel'];
		$_SESSION['lastname'] = $result[0]['lastname'];
		$_SESSION['firstname'] = $result[0]['firstname'];
		$_SESSION['email'] = $result[0]['email'];
		$_SESSION['homephone'] = $result[0]['homephone'];
		$_SESSION['cellphone'] = $result[0]['cellphone'];
		$_SESSION['lang'] = $result[0]['lang'];
		$_SESSION['auth_type'] = $result[0]['auth_type'];
		$_SESSION['csrf'][] = bin2hex(random_bytes(32));
		$_SESSION['csrf'] = array_slice($_SESSION['csrf'], -5);
	}
}
