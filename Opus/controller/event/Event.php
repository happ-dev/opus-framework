<?php

/**
 * @Project: Opus
 * @Version: 1.0
 * @Author: Tomasz Ułazowski
 * @Date:   2026-02-09 13:24:42
 * @Last Modified by:   Tomasz Ułazowski
 * @Last Modified time: 2026-08-22 22:33:14
 **/

namespace Opus\controller\event;

use Opus\config\Config;
use Opus\controller\request\Request;
use Opus\controller\exception\ControllerException;

class Event
{
	const TYPE_PAGE = Request::TYPE_PAGE;
	const TYPE_ASYNC_PAGE = Request::TYPE_ASYNC_PAGE;
	const TYPE_API = Request::TYPE_API;
	const TYPE_CLI = Request::TYPE_CLI;

	// Restricted applications by type
	// Do not put your application names here
	const TYPE_APPS = [
		self::TYPE_PAGE => [
			'login',		// EventException -> Login::login(Login::TYPE_LOGIN_PAGE)
			'logout',		// EventException -> Login::logout()
			'download'		// EventException -> Download::downloadFile()
		],
		self::TYPE_API => [
			'asyncevent',	// EventException -> AsyncEvent::doAsyncEvent()
			'tableevent',	// EventException -> TableEvent::doTableEvent()
			'injectevent',	// EventException -> InjectEvent::doInjectEvent()
			'uploadevent',	// EventException -> UploadEvent::doUploadEvent()
			'asyncselect'	// EventException -> AsyncSelect::doAsyncSelect()
		],
		self::TYPE_ASYNC_PAGE => [
			'asyncpage'		// EventException -> AsyncPage::doAsyncPage()
		],
		self::TYPE_CLI => []
	];

	/**
	 * Creates an application object instance based on configuration
	 *
	 * Retrieves the class name from the application's configuration
	 * and instantiates a new object of that class.
	 *
	 * @param string $app Application identifier/name
	 * @return object Instantiated application object
	 */
	private function createAppObject(string $app): object
	{
		$class = Config::getConfig($app)->app->class;
		return new $class();
	}

	/**
	 * Creates and processes a new event with type-specific validation
	 *
	 * Factory method that handles three types of events:
	 * - PAGE and API events: validates the request parameter against TYPE_APPS
	 * - ASYNC_PAGE events: validates the request parameter against TYPE_APPS
	 * - CLI events: validates the app parameter against TYPE_APPS
	 *
	 * @param string $type Event type (TYPE_PAGE, TYPE_ASYNC_PAGE, TYPE_API, or TYPE_CLI)
	 * @param string|null $app Application identifier to be validated for CLI events
	 * @param string|null $request Request identifier to be validated for PAGE/ASYNC_PAGE/API events
	 *
	 * @throws EventException When:
	 *      - PAGE/API: request matches restricted types in TYPE_APPS
	 * 		- ASYNC_PAGE: request matches restricted types in TYPE_APPS
	 *      - CLI: app matches restricted types in TYPE_APPS
	 * @return mixed Returns the result of:
	 *      - Normal flow: indexAction(), apiAction(), asyncAction() or cliAction() from app object
	 *      - Exception flow: corresponding action from EventException handler
	 *
	 * @see Event::TYPE_APPS
	 * @see Event::createAppObject()
	 */
	public static function newEvent(string $type, ?string $app, ?string $request): mixed
	{
		$event = new self();

		try {

			if ($type !== self::TYPE_CLI && in_array($request, self::TYPE_APPS[$type])) {
				throw new EventException($request);
			} elseif ($type === self::TYPE_CLI && in_array($app, self::TYPE_APPS[$type])) {
				throw new EventException($app);
			}

			$obj = $event->createAppObject($app);

			return match ($type) {
				self::TYPE_PAGE => $obj->indexAction(),
				self::TYPE_API => $obj->apiAction(),
				self::TYPE_CLI => $obj->cliAction(),
				default => throw new ControllerException(
					'controller\event\UnknownType',
					['message' => $type]
				)
			};
		} catch (EventException $eventException) {
			return match ($type) {
				self::TYPE_PAGE => $eventException->indexAction(),
				self::TYPE_API => $eventException->apiAction(),
				self::TYPE_CLI => $eventException->cliAction(),
				self::TYPE_ASYNC_PAGE => $eventException->asyncAction()
			};
		}
	}
}
