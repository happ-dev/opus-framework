<?php

/**
 * @Project: Opus
 * @Version: 1.0
 * @Author: Tomasz Ułazowski
 * @Date:   2026-05-15 15:24:12
 * @Last Modified by:   Tomasz Ułazowski
 * @Last Modified time: 2026-08-27 19:37:36
 **/

namespace Opus\controller\event;

use Exception;
use Opus\controller\InterfaceController;
use Opus\controller\login\Login;
use Opus\controller\event\download\Download;
use Opus\controller\event\upload\UploadEvent;
use Opus\apps\setup\src\SetupController;

class EventException extends Exception implements InterfaceController
{
	public function __construct(public readonly string $request)
	{
		parent::__construct($request);
	}

	public function indexAction(): mixed
	{
		// configuring only app with a view,
		// other should be added in the config class
		// or global config file
		return match ($this->request) {
			'login' => (function () {
				Login::login(Login::TYPE_LOGIN_PAGE);
				return null;
			})(),
			'logout' => (function () {
				Login::logout(Login::TYPE_LOGIN_PAGE);
				return null;
			})(),
			'download' => Download::downloadFile()
		};
	}

	public function apiAction(): mixed
	{
		return match ($this->request) {
			'asyncevent' => AsyncEvent::doAsyncEvent(),
			'tableevent' => TableEvent::doTableEvent(),
			'injectevent' => InjectEventHtml::doInjectEvent(),
			'uploadevent' => UploadEvent::doUploadEvent(),
			'asyncselect' => AsyncSelect::doAsyncSelect()
		};
	}

	public function cliAction(): mixed
	{
		return null;
	}

	public function asyncAction(): mixed
	{
		return match ($this->request) {
			'asyncpage' => AsyncPage::doAsyncPage()
		};
	}
}
