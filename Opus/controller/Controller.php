<?php

/**
 * @Project: Opus
 * @Version: 1.0
 * @Author: Tomasz Ułazowski
 * @Date:   2026-02-09 09:54:34
 * @Last Modified by:   Tomasz Ułazowski
 * @Last Modified time: 2026-08-22 22:41:44
 **/

namespace Opus\controller;

use Exception;
use Opus\config\Config;
use Opus\controller\request\Request;
use Opus\controller\cli\CliColor;
use Opus\controller\cli\CliArguments;
use Opus\controller\event\Event;
use Opus\controller\exception\ControllerException;
use Opus\storage\exception\StorageException;
use Opus\view\layout\Layout;

class Controller extends AbstractController
{
	/**
	 * Starts the application and routes requests to appropriate handlers
	 *
	 * This method determines the request type (CLI, API, async page, or standard page)
	 * and dispatches it to the corresponding initialization method. For CLI requests,
	 * it validates the application configuration and type before execution.
	 *
	 * @throws Exception If CLI application is invalid or has incorrect type
	 * @return void
	 */
	final public static function run(): void
	{
		$argvCli = $_SERVER['argv'] ?? null;
		$controller = new self();

		// Handle CLI requests
		// if (!is_null($argvCli)) { // zamienic po testach klasy Config
		if (!is_null($argvCli) && count($argvCli) > 1) {
			$app = $argvCli[1];
			$config = Config::getConfig($app);

			if (is_null($config)) {
				throw new Exception(
					'Invalid parameter: ' . CliColor::write($app, CliColor::COLOR_LIGHT_RED, null, false)
				);
			}

			if ($config->app->type !== Request::TYPE_CLI) {
				throw new Exception(
					'App: '
						. CliColor::write($app, CliColor::COLOR_LIGHT_RED, null, false)
						. ' is of the type: '
						. CliColor::write($config->app->type, CliColor::COLOR_LIGHT_GREEN, null, false)
				);
			}

			$controller->initCli($argvCli);
			return;
		}

		// Handle web requests
		$requestApi = Request::fromUrl(Request::TYPE_API);
		$requestPage = Request::fromUrl(Request::TYPE_PAGE);
		$requestAsyncPage = Request::fromUrl(Request::TYPE_ASYNC_PAGE);

		match (true) {
			$requestApi !== '/' => $controller->initApi($requestApi),
			$requestAsyncPage !== '/' => $controller->initAsyncPage($requestAsyncPage),
			default => (function () use ($controller, $requestPage) {
				$controller->setAppByRequest($requestPage, Request::TYPE_PAGE) ?? Config::OPUS_MAIN_APP;
				$controller->initApp($requestPage);
			})()
		};
	}

	protected function initApp(?string $request = null): void
	{
		// Define action variable for use in finally block
		$action = null;

		try {
			// Initialize environment
			$this->setServerTimezone();

			// Process requests and load application resources
			$this->pageRequest($request);
			$this->loadAppClasses();
			$this->loadAppLibs();

			// Create and dispatch event
			$action = Event::newEvent(Event::TYPE_PAGE, self::$app, $request);
		} catch (ControllerException | StorageException $event) {		// Handle application exceptions
			self::setErrorIndex();
			$action = $event->indexAction();
		} catch (Exception $event) {									// Handle general exceptions
			echo $event->getMessage() . PHP_EOL;
		} finally {														// Always render the layout
			$this->setLayout();
			new Layout($action, self::getLayout());
		}
	}

	protected function initAsyncPage(string $request): void
	{
		try {
			// Initialize environment
			$this->setServerTimezone();

			// Process async page request and load application resources
			$app = $this->asyncRequest($request);
			$this->loadAppClasses();
			$this->loadAppLibs();

			// Create and dispatch event
			$action = Event::newEvent(Event::TYPE_ASYNC_PAGE, $app, $request);
			echo $action;
		} catch (ControllerException | StorageException $event) {
			echo $event->asyncAction();
		} catch (Exception $event) {
			echo $event->getMessage() . PHP_EOL;
		}
	}

	protected function initApi(?string $request): mixed
	{
		try {
			// Initialize environment
			$this->setServerTimezone();

			// Process API request and load application resources
			$app = $this->apiRequest($request);
			$this->loadAppClasses();
			$this->loadAppLibs();

			// Create and dispatch event
			$action = Event::newEvent(Event::TYPE_API, $app, $request);
			return $action;
		} catch (ControllerException | StorageException $event) {
			echo $event->apiAction();
			return null;
		} catch (Exception $event) {
			echo $event->getMessage() . PHP_EOL;
			return null;
		}
	}

	protected function initCli(array $app): void
	{
		try {
			// Initialize environment
			$this->setServerTimezone();

			// Process CLI request and load application resources
			$this->cliRequest($app[1]);
			CliArguments::check($app);
			$this->setApi($app[1]);
			$this->loadAppClasses();
			$this->loadAppLibs();

			// Create and dispatch event
			$action = Event::newEvent(Event::TYPE_CLI, $app[1], null);
		} catch (ControllerException | StorageException $event) {
			echo $event->cliAction();
		} catch (Exception $event) {
			echo $event->getMessage() . PHP_EOL;
		}
	}
}
