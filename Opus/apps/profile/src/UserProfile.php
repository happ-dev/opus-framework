<?php

/**
 * @Project: Opus
 * @Version: 1.0
 * @Author: Tomasz Ułazowski
 * @Date:   2026-08-18 16:00:00
 * @Last Modified by:   Tomasz Ułazowski
 * @Last Modified time: 2026-10-03 12:44:18
 **/

namespace Opus\apps\profile\src;

use Opus\controller\lang\Lang;
use Opus\html\buttons\Buttons;
use Opus\html\form\Form;
use Opus\html\table\Table;
use Opus\storage\db\Db;

/**
 * Builds user profile display table with current user data.
 */
class UserProfile
{
	private array $columns = [
		'login',
		'ulevel',
		'gname',
		'active',
		'auth_type',
		'password',
		'lastname',
		'firstname',
		'email',
		'homephone',
		'cellphone',
		'lang'
	];
	private object $lang;
	private object $form;

	public function __construct()
	{
		$this->lang = Lang::getInstance();
		$this->form = new Form();
	}

	/**
	 * Retrieves current user data from database with column metadata.
	 *
	 * @return array
	 */
	private function getUserData(): array
	{
		$tableDetails = Db::dbGetTableDetails('users', 'userdata', $this->columns);

		$result = Db::dbExecute([
			'prepare' => 'SELECT ' . implode(', ', $this->columns) . ' FROM users.userdata WHERE login = :login',
			':login' => $_SESSION['login']
		]);

		for ($i = 0; $i < count($tableDetails); $i++) {
			$tableDetails[$i]['value'] = $result[0][$tableDetails[$i]['attname']];
			$tableDetails[$i]['comment'] = $this->lang->get($tableDetails[$i]['comment']);
		}

		return $tableDetails;
	}

	/**
	 * Formats user data for display — password becomes button, booleans become badges.
	 *
	 * @param array &$data
	 * @return void
	 */
	private function formatUserData(array &$data): void
	{
		$isLdap = ($_SESSION['auth_type'] ?? 'local') === 'ldap';

		$this->form->addElement(Buttons::standardButton(
			'profile-change-password',
			(object) [
				'text' => 'profile.button.change.password',
				'icon' => 'bi-braces-asterisk',
				'variant' => 'warning',
				'size' => 'sm',
				'disabled' => $isLdap,
				'attributes' => $isLdap ? [] : [
					'data-bs-toggle' => 'modal',
					'data-bs-target' => '#id__opus-profile-change-password'
				]
			]
		));

		foreach ($data as $index => $value) {
			if ($value['attname'] === 'password') {
				$data[$index]['value'] = $this->form->getElement('standard-btn-profile-change-password');
			} elseif ($value['type'] === 'boolean') {
				$data[$index]['value'] = $value['value'] === true
					? '<span class="badge bg-success text-wrap fs-6 fst-italic">' . $this->lang->get('event.message.true') . '</span>'
					: '<span class="badge bg-danger text-wrap fs-6 fst-italic">' . $this->lang->get('event.message.false') . '</span>';
			}
		}
	}

	/**
	 * Builds table header with title and edit profile button.
	 *
	 * @return array
	 */
	private function thead(): array
	{
		$isLdap = ($_SESSION['auth_type'] ?? 'local') === 'ldap';

		$this->form->addElement(Buttons::standardButton(
			'profile-edit',
			(object) [
				'text' => 'profile.button.edit',
				'icon' => 'bi-pencil-square',
				'variant' => 'primary',
				'size' => 'sm',
				'disabled' => $isLdap,
				'attributes' => $isLdap ? [] : [
					'data-bs-toggle' => 'modal',
					'data-bs-target' => '#id__opus-profile-edit'
				]
			]
		));

		$thead = '<h5 class="pt-2"><span class="me-1 ms-2 badge bg-opus-black bs-opus-black fs-5">'
			. '<i class="bi bi-person-badge"></i></span>'
			. $this->lang->get('profile.table.title') . '</h5>';

		$button = '<h5 class="pt-2">' . $this->form->getElement('standard-btn-profile-edit') . '</h5>';

		return [$thead, $button];
	}

	/**
	 * Creates and returns the user profile table.
	 *
	 * @return Table
	 */
	public static function build(): Table
	{
		$profile = new self();
		$userData = $profile->getUserData();
		$profile->formatUserData($userData);

		$table = new Table();
		$table->addTable([
			'attributes' => [
				'class' => 'table table-hover table-striped',
				'id' => 'id_profile-dt'
			],
			'cname' => ['comment', 'value'],
			'thead' => $profile->thead(),
			'tbody' => $userData,
			'tfoot' => false
		]);

		return $table;
	}
}
