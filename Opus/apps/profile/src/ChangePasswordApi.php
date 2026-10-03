<?php

/**
 * @Project: Opus
 * @Version: 1.0
 * @Author: Tomasz Ułazowski
 * @Date:   2026-08-18 16:00:00
 * @Last Modified by:   Tomasz Ułazowski
 * @Last Modified time: 2026-08-18 18:38:52
 **/

namespace Opus\apps\profile\src;

use stdClass;
use Opus\controller\InterfaceApiController;
use Opus\controller\exception\ControllerException;
use Opus\controller\lang\Lang;
use Opus\controller\login\Login;
use Opus\controller\request\Request;
use Opus\html\form\StandardForm;

/**
 * Handles change password API — serves form and processes password update.
 *
 * @implements InterfaceApiController
 */
class ChangePasswordApi implements InterfaceApiController
{
	const REQUEST_CHANGE_PASSWORD_FORM = 'change-password-form';
	const REQUEST_CHANGE_PASSWORD_SAVE = 'change-password-save';

	private object $requestData;
	private object $postData;

	public function __construct()
	{
		$this->requestData = new stdClass();
		$this->requestData->request = Request::get('request');
	}

	/**
	 * Routes API action based on request type (form or save).
	 *
	 * @return void
	 * @throws ControllerException When request type is unknown.
	 */
	public function apiAction(): void
	{
		if (($_SESSION['auth_type'] ?? 'local') === 'ldap') {
			echo json_encode([
				'header' => $this->header(),
				'body' => '<div class="alert alert-warning m-3">' . Lang::getInstance()->get('profile.ldap.readonly') . '</div>'
			]);
			return;
		}

		echo match ($this->requestData->request) {
			self::REQUEST_CHANGE_PASSWORD_FORM => json_encode([
				'header' => $this->header(),
				'body' => $this->body()
			]),

			self::REQUEST_CHANGE_PASSWORD_SAVE => $this->changePassword(),

			default => throw new ControllerException(
				'controller\asyncEvent\validateConfig\param',
				[
					'message' => ['request', $this->requestData->request],
					'details' => ['profile', 'changePassword']
				],
				ControllerException::TYPE_API_EXCEPTION
			)
		};
	}

	/**
	 * Returns modal header configuration with orange warning style.
	 *
	 * @return array{text: string, icon: string, class: string, shadow: string, additionalText: string}
	 */
	private function header(): array
	{
		return [
			'text' => Lang::getInstance()->get('profile.modal.password.header'),
			'icon' => 'bi-braces-asterisk',
			'class' => 'modal-header-opus-orange bs-opus-orange',
			'shadow' => 'bs-opus-orange-3d',
			'additionalText' => $_SESSION['login']
		];
	}

	/**
	 * Builds change password form with current password and new password + confirm.
	 *
	 * @return string Rendered HTML form
	 */
	private function body(): string
	{
		$this->formData();
		$this->formTemplate();

		$standardForm = new StandardForm(
			$this->requestData->data,
			$this->requestData->template
		);

		return $standardForm->buildForm()->get();
	}

	/**
	 * Sets form data — readonly login, current password, new password.
	 *
	 * @return void
	 */
	private function formData(): void
	{
		$this->requestData->data = [
			['attname' => 'login', 'comment' => 'opus.db.users.login', 'type' => 'character', 'value' => $_SESSION['login']],
			['attname' => 'current_password', 'comment' => 'profile.form.current.password', 'type' => 'character'],
			['attname' => 'password', 'comment' => 'opus.db.users.password', 'type' => 'character']
		];
	}

	/**
	 * Sets form template — readonly login, current password, new password with confirm.
	 *
	 * @return void
	 */
	private function formTemplate(): void
	{
		$this->requestData->template = [
			'login' => [
				'attribute' => 'readonly'
			],
			'current_password' => [
				'type' => 'password'
			],
			'password' => [
				'type' => 'password',
				'confirm' => true
			]
		];
	}

	/**
	 * Validates current password and updates to new password.
	 *
	 * @return string JSON response with success status and message.
	 * @throws ControllerException When current password doesn't match.
	 */
	private function changePassword(): string
	{
		$this->postData = Request::getBody();

		$passwordMatch = Login::passwordMatches($_SESSION['login'], $this->postData->input_current_password);
		if ($passwordMatch !== true) {
			throw new ControllerException(
				'controller\login\isPasswordMatches',
				null,
				ControllerException::TYPE_API_EXCEPTION
			);
		}
		Login::updatePassword($_SESSION['login'], $this->postData->input_password);

		return json_encode([
			'success' => true,
			'message' => Lang::getInstance()->get('profile.alert.password.success')
		]);
	}
}
