<?php

/**
 * @Project: Opus
 * @Version: 1.0
 * @Author: Tomasz Ułazowski
 * @Date:   2026-08-15 15:28:58
 * @Last Modified by:   Tomasz Ułazowski
 * @Last Modified time: 2026-10-03 12:17:35
 **/

namespace Opus\apps\settings\src;

use stdClass;
use Opus\config\Config;
use Opus\controller\auth\Authorization;
use Opus\controller\event\TableEventValidate;
use Opus\controller\lang\Lang;
use Opus\controller\InterfaceIndexController;
use Opus\html\form\StandardFormElements;
use Opus\html\table\Table;
use Opus\html\modal\Modal;
use Opus\html\buttons\Buttons;
use Opus\html\form\Form;
use Opus\view\view\View;

/**
 * Settings page controller — user management.
 * Builds users DataTable with filters and modals.
 *
 * @implements InterfaceIndexController
 */
class SettingsController implements InterfaceIndexController
{
	/**
	 * Returns View with users table and modals (reset password, groups).
	 *
	 * @return View
	 */
	public function indexAction()
	{
		return new View([
			'table' => $this->table(),
			'modals' => (object) [
				'reset' => $this->resetByAdminModal(),
				'groups' => $this->groupsModal()
			]
		]);
	}

	/**
	 * Builds thead filter elements (inputs, selects) for DataTable columns.
	 *
	 * @return object
	 */
	private function theadElements(): object
	{
		$options = $elements = new stdClass();
		$options->size = 'sm';
		$options->required = false;
		$options->placeholder = true;
		$options->class = 'search-filter';

		// input_settings-login
		$elements->login = [
			'attname' => 'settings-login',
			'comment' => 'opus.db.users.login'
		];
		StandardFormElements::standardTypeValue($elements->login, $options);

		// input_settings-active
		$elements->active = [
			'attname' => 'settings-active',
			'comment' => 'opus.db.users.active'
		];
		StandardFormElements::booleanValue($elements->active, $options);

		// input_settings-auth_type
		$elements->authType = [
			'attname' => 'settings-auth-type',
			'comment' => 'opus.db.users.auth_type',
			'template' => [
				'text' => ['LDAP', 'local'],
				'value' => ['ldap', 'local'],
				'select-opus' => true,
				'multiple' => true
			]
		];
		StandardFormElements::selectValue($elements->authType, $options);

		// input_settings-gname
		$elements->gname = [
			'attname' => 'settings-gname',
			'comment' => 'opus.db.groups.gname',
			'template' => [
				'text' => <<<SQL
				SELECT gname FROM groups.groups ORDER BY glevel ASC
				SQL,
				'select-opus' => true,
				'multiple' => true
			]
		];
		StandardFormElements::selectValue($elements->gname, $options);

		// input_settings-lastname
		$elements->lastname = [
			'attname' => 'settings-lastname',
			'comment' => 'opus.db.users.lastname'
		];
		StandardFormElements::standardTypeValue($elements->lastname, $options);

		// input_settings-firstname
		$elements->firstname = [
			'attname' => 'settings-firstname',
			'comment' => 'opus.db.users.firstname'
		];
		StandardFormElements::standardTypeValue($elements->firstname, $options);

		// input_settings-email
		$elements->email = [
			'attname' => 'settings-email',
			'comment' => 'opus.db.users.email'
		];
		StandardFormElements::standardTypeValue($elements->email, $options);

		// input_settings-lang
		$elements->lang = [
			'attname' => 'settings-lang',
			'comment' => 'opus.db.users.lang',
			'template' => [
				'text' => Config::getConfig('langs'),
				'select-opus' => true,
				'multiple' => true
			]
		];
		StandardFormElements::selectValue($elements->lang, $options);

		return $elements;
	}
	/**
	 * Builds users DataTable with authorization-based button visibility.
	 *
	 * @return Table
	 */
	private function table(): Table
	{
		$table = new Table();
		$elements = $this->theadElements();
		$lang = Lang::getInstance();
		$table->addTable([
			'attributes' => [
				'class' => 'table table-sm table-hover table-striped',
				'id' => 'id_settings-users-dt',
				'data-add' => Authorization::accessTableEventButtons(
					'settings',
					'users_dt',
					TableEventValidate::EDITOR_STRATEGY_ADD
				),
				'data-show' => Authorization::accessTableEventButtons(
					'settings',
					'users_dt',
					TableEventValidate::EDITOR_STRATEGY_SHOW
				),
				'data-edit' => Authorization::accessTableEventButtons(
					'settings',
					'users_dt',
					TableEventValidate::EDITOR_STRATEGY_EDIT
				),
				'data-delete' => Authorization::accessTableEventButtons(
					'settings',
					'users_dt',
					TableEventValidate::EDITOR_STRATEGY_DELETE
				)
			],
			'thead' => [
				'id__users',									//  0 id__users
				$elements->login['el'],							//  1 login
				$elements->active['el'],						//  2 active
				$elements->authType['el'],						//  3 auth_type
				'ulevel',										//  4 ulevel
				$elements->gname['el'],							//  5 gname
				'password',										//  6 password
				$elements->lastname['el'],						//  7 lastname
				$elements->firstname['el'],						//  8 firstname
				$elements->email['el'],							//  9 email
				$lang->get('settings.table.thead.phone'),		// 10 homephone
				'cellphone',									// 11 cellphone
				$elements->lang['el']							// 12 lang
			],
			'tfoot' => [
				'id__users',								//  0 id__users
				$lang->get('opus.db.users.login'),			//  1 login
				$lang->get('opus.db.users.active'),			//  2 active
				$lang->get('opus.db.users.auth_type'),		//  3 auth_type
				$lang->get('opus.db.users.ulevel'),			//  4 ulevel
				$lang->get('opus.db.groups.gname'),			//  5 gname
				$lang->get('opus.db.users.password'),		//  6 password
				$lang->get('opus.db.users.lastname'),		//  7 lastname
				$lang->get('opus.db.users.firstname'),		//  8 firstname
				$lang->get('opus.db.users.email'),			//  9 email
				$lang->get('settings.table.thead.phone'),	// 10 homephone
				$lang->get('opus.db.users.cellphone'),		// 11 cellphone
				$lang->get('opus.db.users.lang')			// 12 lang
			],
			'cname' => false,
			'tbody' => false
		]);

		return $table;
	}

	/**
	 * Builds reset password modal with orange header and form buttons.
	 *
	 * @return Modal
	 */
	private function resetByAdminModal(): Modal
	{
		$buttons = new Buttons();
		$modalButtons = $buttons->modalButtons('opus-settings-reset-password-by-admin');

		$options = new stdClass();
		$options->shadow = 'bs-opus-orange-3d';
		$options->headerClass ??= 'modal-header-opus-orange bs-opus-orange';
		$options->form = true;
		$options->centered = true;
		$options->footer = $modalButtons->getElement('submit-btn-opus-settings-reset-password-by-admin')
			. $modalButtons->getElement('cancel-btn-opus-settings-reset-password-by-admin')
			. $modalButtons->getElement('close-btn-opus-settings-reset-password-by-admin');

		$modal = new Modal();
		$modal->addModal('opus-settings-reset-password-by-admin', $options);
		return $modal;
	}

	/**
	 * Builds groups modal with close button.
	 *
	 * @return Modal
	 */
	private function groupsModal(): Modal
	{
		$buttons = new Buttons();
		$form = new Form();
		$form->addElement($buttons->closeButton(
			'opus-settings-groups',
			['data-bs-dismiss' => 'modal']
		));

		$options = new stdClass();
		$options->footer = $form->getElement('close-btn-opus-settings-groups');

		$modal = new Modal();
		$modal->addModal('opus-settings-groups', $options);
		return $modal;
	}
}
