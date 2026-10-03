<?php

/**
 * @Project: Opus
 * @Version: 1.0
 * @Author: Tomasz Ułazowski
 * @Date:   2026-08-18 16:00:00
 * @Last Modified by:   Tomasz Ułazowski
 * @Last Modified time: 2026-08-18 20:26:31
 **/

namespace Opus\apps\profile\src;

use stdClass;
use Opus\config\Config;
use Opus\controller\InterfaceApiController;
use Opus\controller\exception\ControllerException;
use Opus\controller\lang\Lang;
use Opus\controller\login\Login;
use Opus\controller\request\Request;
use Opus\html\form\StandardForm;
use Opus\storage\db\Db;

/**
 * Handles edit profile API — serves form and processes data update.
 *
 * @implements InterfaceApiController
 */
class EditProfileApi implements InterfaceApiController
{
	const REQUEST_EDIT_PROFILE_FORM = 'edit-profile-form';
	const REQUEST_EDIT_PROFILE_SAVE = 'edit-profile-save';

	private object $requestData;
	private object $postData;

	public function __construct()
	{
		$this->requestData = new stdClass();
		$this->requestData->request = Request::get('request');
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
		if (($_SESSION['auth_type'] ?? 'local') === 'ldap') {
			echo json_encode([
				'header' => $this->header(),
				'body' => '<div class="alert alert-warning m-3">' . Lang::getInstance()->get('profile.ldap.readonly') . '</div>'
			]);
			return;
		}

		echo match ($this->requestData->request) {
			self::REQUEST_EDIT_PROFILE_FORM => json_encode([
				'header' => $this->header(),
				'body' => $this->body()
			]),

			self::REQUEST_EDIT_PROFILE_SAVE => $this->saveProfile(),

			default => throw new ControllerException(
				'controller\asyncEvent\validateConfig\param',
				[
					'message' => ['request', $this->requestData->request],
					'details' => ['profile', 'editProfile']
				],
				ControllerException::TYPE_API_EXCEPTION
			)
		};
	}

	/**
	 * Returns modal header configuration.
	 *
	 * @return array{text: string, icon: string}
	 */
	private function header(): array
	{
		return [
			'text' => Lang::getInstance()->get('profile.modal.edit.header'),
			'icon' => 'bi-person-badge',
			'additionalText' => $_SESSION['login']
		];
	}

	/**
	 * Builds edit profile form with StandardForm, arranged in two rows.
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
		$standardForm->buildForm();

		$left = $right = $bottom = '';
		foreach ($standardForm->data as $row) {
			if (($row['template']['type'] ?? null) === 'password') {
				$bottom .= $row['el'];
			} elseif (($row['template']['attribute'] ?? null) === 'readonly') {
				$left .= $row['el'];
			} else {
				$right .= $row['el'];
			}
		}

		return <<<HTML
		<div class="row">
			<div class="col-md-6">{$left}</div>
			<div class="col-md-6">{$right}</div>
		</div>
		<div class="row">
			<div class="col-md-6 offset-md-3">{$bottom}</div>
		</div>
		HTML;
	}

	/**
	 * Sets form template — readonly fields and editable fields.
	 *
	 * @return void
	 */
	private function formTemplate(): void
	{
		$this->requestData->template = [
			'login' => [
				'attribute' => 'readonly'
			],
			'active' => [
				'attribute' => 'readonly'
			],
			'ulevel' => [
				'attribute' => 'readonly'
			],
			'gname' => [
				'attribute' => 'readonly'
			],
			'lastname' => [
				'attribute' => 'readonly'
			],
			'firstname' => [
				'attribute' => 'readonly'
			],
			'password' => [
				'type' => 'password',
				'required' => true
			],
			'lang' => [
				'attribute' => 'select',
				'text' => Config::getConfig('langs'),
				'select-opus' => true,
			]
		];
	}

	/**
	 * Sets form options — database config and query for current user.
	 *
	 * @return void
	 */
	private function formOptions(): void
	{
		$this->requestData->options = (object) [
			'db' => (object) [
				'scheme' => 'users',
				'table' => 'userdata',
				'columns' => ['login', 'active', 'ulevel', 'gname', 'lastname', 'firstname', 'email', 'homephone', 'cellphone', 'lang', 'password'],
				'execute' => [
					'prepare' => <<<SQL
					SELECT * FROM users.userdata
					WHERE login = :login
					SQL,
					':login' => $_SESSION['login']
				]
			],
			'elements' => (object) [
				'required' => false
			]
		];
	}

	/**
	 * Validates current password and updates user profile data.
	 *
	 * @return string JSON response with success status and message.
	 * @throws ControllerException When password doesn't match or update fails.
	 */
	private function saveProfile(): string
	{
		$this->postData = Request::getBody();

		$passwordMatch = Login::passwordMatches($_SESSION['login'], $this->postData->input_password);
		if ($passwordMatch !== true) {
			throw new ControllerException(
				'controller\login\isPasswordMatches',
				null,
				ControllerException::TYPE_API_EXCEPTION
			);
		}

		Db::dbTransactions([
			[
				'prepare' => 'UPDATE users.users SET'
					. ' (email, homephone, cellphone, lang) = (:email, :homephone, :cellphone, :lang)'
					. ' WHERE login = :user',
				'params' => [':email', ':homephone', ':cellphone', ':lang', ':user'],
				':email' => [$this->postData->input_email],
				':homephone' => [$this->postData->input_homephone],
				':cellphone' => [$this->postData->input_cellphone],
				':lang' => [$this->postData->input_lang],
				':user' => [$_SESSION['login']],
			]
		]);

		Login::reloadConfig();
		return json_encode([
			'success' => true,
			'message' => Lang::getInstance()->get('profile.alert.edit.success')
		]);
	}
}
