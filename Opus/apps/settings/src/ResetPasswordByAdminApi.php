<?php

/**
 * @Project: Opus
 * @Version: 1.0
 * @Author: Tomasz Ułazowski
 * @Date:   2026-08-15 22:25:28
 * @Last Modified by:   Tomasz Ułazowski
 * @Last Modified time: 2026-08-18 10:03:25
 **/

namespace Opus\apps\settings\src;

use stdClass;
use Opus\config\Config;
use Opus\controller\request\Request;
use Opus\controller\InterfaceApiController;
use Opus\controller\exception\ControllerException;
use Opus\controller\lang\Lang;
use Opus\controller\login\Login;
use Opus\html\form\StandardForm;
use Opus\storage\db\Db;

/**
 * Handles admin password reset API — serves form and processes password change.
 *
 * @implements InterfaceApiController
 */
class ResetPasswordByAdminApi implements InterfaceApiController
{
	const REQUEST_RESET_PASSWORD_BY_ADMIN_FORM = 'reset-password-by-admin-form';
	const REQUEST_RESET_PASSWORD_BY_ADMIN_SAVE = 'reset-password-by-admin-save';

	private object $requestData;
	private object $postData;

	public function __construct()
	{
		$this->requestData = new stdClass();
		$this->requestData->request = Request::get('request');
		$this->requestData->accountId = Request::get('id');
		$this->requestData->data = [];
	}

	/**
	 * Routes API action based on request type (form or save).
	 *
	 * @return void
	 * @throws ControllerException When request type is unknown.
	 */
	public function apiAction(): void
	{
		if ($this->isLdapAccount()) {
			echo json_encode([
				'header' => $this->header(),
				'body' => '<div class="alert alert-warning m-3">' . Lang::getInstance()->get('settings.ldap.readonly') . '</div>'
			]);
			return;
		}

		echo match ($this->requestData->request) {
			self::REQUEST_RESET_PASSWORD_BY_ADMIN_FORM => json_encode([
				'header' => $this->header(),
				'body' => $this->body()
			]),

			self::REQUEST_RESET_PASSWORD_BY_ADMIN_SAVE => $this->resetPasswordByAdmin(),

			default => throw new ControllerException(
				'controller\asyncEvent\validateConfig\param',
				[
					'message' => ['request', $this->requestData->request],
					'details' => ['settings', 'resetPasswordByAdmin']
				],
				ControllerException::TYPE_API_EXCEPTION
			)
		};
	}

	/**
	 * Returns modal header configuration with orange warning style.
	 *
	 * @return array{text: string, icon: string, class: string, shadow: string}
	 */
	private function header(): array
	{
		return [
			'text' => Lang::getInstance()->get('settings.modal.text.reset.password'),
			'icon' => Config::getConfig('settings')->tableEvent->users_dt->buttons->password->icon,
			'class' => 'modal-header-opus-orange bs-opus-orange',
			'shadow' => 'bs-opus-orange-3d'
		];
	}

	/**
	 * Builds password reset form with readonly login and password fields.
	 *
	 * @return string Rendered HTML form
	 */
	private function body(): string
	{
		$this->formTemplate();
		$this->formOptions();
		$standardForm = new StandardForm(
			$this->requestData->data,
			$this->requestData->template,
			$this->requestData->options
		);

		return $standardForm->buildForm()->get();
	}

	/**
	 * Sets form template — readonly login field and password with confirm.
	 *
	 * @return void
	 */
	private function formTemplate(): void
	{
		$this->requestData->template = [
			'login' => [
				'attribute' => 'readonly'
			],
			'password' => [
				'type' => 'password',
				'confirm' => true
			]
		];
	}

	/**
	 * Sets form options — database config and query for user lookup.
	 *
	 * @return void
	 */
	private function formOptions(): void
	{
		$this->requestData->options = (object) [
			'db' => (object) [
				'scheme' => 'users',
				'table' => 'users',
				'columns' => ['id__users', 'login', 'password'],
				'execute' => [
					'prepare' => <<<SQL
					SELECT * FROM users.users
					WHERE id__users = :id__users
					SQL,
					':id__users' => $this->requestData->accountId
				]
			]
		];
	}

	/**
	 * Executes password reset via Login service.
	 *
	 * @return string JSON response with success status and message.
	 * @throws ControllerException When password reset fails.
	 */
	private function resetPasswordByAdmin(): string
	{
		$this->postData = Request::getBody();
		$login = Login::resetPasswordByAdmin($this->postData->input_id__users, $this->postData->input_password);

		if ($login === false) {
			throw new ControllerException(
				'apps\settings\resetPassword',
				['message' => $this->postData->input_id__users],
				ControllerException::TYPE_API_EXCEPTION
			);
		}

		return json_encode([
			'success' => true,
			'message' => Lang::getInstance()->get('settings.alert.password.changed'),
			'details' => Lang::getInstance()->get('settings.alert.password.user.id')
				. <<<HTML
				<span class="fw-bolder me-1">{$this->postData->input_id__users}</span>
				HTML
		]);
	}

	/**
	 * Checks if the target account is LDAP-managed
	 *
	 * @return bool
	 */
	private function isLdapAccount(): bool
	{
		$result = Db::dbExecute([
			'prepare' => 'SELECT auth_type FROM users.users WHERE id__users = :id',
			':id' => $this->requestData->accountId
		]);

		return ($result[0]['auth_type'] ?? 'local') === 'ldap';
	}
}
