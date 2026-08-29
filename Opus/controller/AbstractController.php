<?php

/**
 * @Project: Opus
 * @Version: 1.0
 * @Author: Tomasz Ułazowski
 * @Date:   2026-02-07 17:25:15
 * @Last Modified by:   Tomasz Ułazowski
 * @Last Modified time: 2026-08-27 19:11:45
 **/

namespace Opus\controller;

use stdClass;
use Exception;
use Opus\config\Config;
use Opus\config\ValidateGlobalConfig;
use Opus\controller\exception\ControllerException;
use Opus\controller\request\Request;
use Opus\controller\event\Event;
use Opus\controller\auth\Authorization;
use Opus\controller\login\Login;

abstract class AbstractController
{
	use TraitController;

	/**
	 * Function starts the application according to the given parameters,
	 * selects whether it should be page, api or cli
	 *
	 * @throws Exception if the given parameters are incorrect
	 */
	abstract public static function run(): void;

	/**
	 * Initializes and runs a web application
	 *
	 * This method handles the complete lifecycle of a web application request:
	 * 1. Processes the page and subpage requests
	 * 2. Loads application libraries and classes
	 * 3. Creates and dispatches the appropriate event
	 * 4. Handles exceptions by showing error pages
	 * 5. Renders the layout with the action result
	 *
	 * @param string|null $request The request path or null for default route
	 * @return void
	 */
	abstract protected function initApp(?string $request = null): void;

	/**
	 * Initializes and runs an asynchronous page request
	 *
	 * This method handles async page requests that are loaded dynamically via JavaScript.
	 * It processes the request, loads necessary resources, and returns HTML content
	 * to be injected into the page without a full page reload.
	 *
	 * @param string $request The async page request identifier
	 * @return void Outputs HTML content to be loaded via JavaScript
	 */
	abstract protected function initAsyncPage(string $request): void;

	/**
	 * Initializes and runs an API request
	 *
	 * This method handles the complete lifecycle of an API request:
	 * 1. Processes the API request and determines the target application
	 * 2. Loads application libraries and classes
	 * 3. Creates and dispatches the appropriate event
	 * 4. Handles exceptions by returning appropriate API responses
	 *
	 * @param string|null $request The request path or null for default route
	 * @return mixed The action result to be returned as API response
	 */
	abstract protected function initApi(?string $request): mixed;

	/**
	 * Initializes and runs a command-line interface (CLI) application
	 *
	 * This method handles the complete lifecycle of a CLI application:
	 * 1. Processes the CLI request and validates authorization
	 * 2. Parses and processes command line arguments
	 * 3. Loads application libraries and classes
	 * 4. Creates and dispatches the appropriate event
	 * 5. Handles exceptions by returning appropriate CLI responses
	 *
	 * @param array $app The command line arguments array
	 * @return void
	 */
	abstract protected function initCli(array $app): void;

	const APP_CONFIG_CLASSES = ['options' => ['regexp' => '/^(apps\/)+[^_]+[\w\D]+$/']];
	const APP_CONFIG_LIBS = ['options' => ['regexp' => '/^(apps\/)+[^_]+[\w\D]+$/']];

	private static object $layout;
	private static object $index;
	private static object $libs;
	protected static ?string $app;
	private static string $serverTimezone;

	/**
	 * Initializes session with default values
	 *
	 * Starts PHP session and sets default session variables if not already set.
	 * Default values include: login status, user level, and language preference.
	 *
	 * Note: This function should be called at the very beginning of index.php
	 *
	 * @return void
	 */
	final public static function session(): void
	{
		session_start();

		if (!isset($_SESSION['logged'])) {
			$_SESSION['login'] = 'NoLogged';
			$_SESSION['logged'] = false;
			$_SESSION['level'] = '0';
			$_SESSION['lang'] = 'en';
			$_SESSION['csrf'] = [bin2hex(random_bytes(32))];
		}
	}

	/**
	 * Recursively scans directories for files of a specific type
	 *
	 * This method searches for files of a specified type (php, phtml, js) in the application
	 * directories, applying filters to include or exclude files based on configuration.
	 * It handles both local app files and vendor-provided app files.
	 *
	 * @param array &$indexes Reference to an array where found file paths will be stored
	 * @param string $app The application identifier
	 * @param string $scanDir The relative directory path to scan
	 * @param string $fileType The file extension to look for ('php', 'phtml', 'js')
	 * @param bool $subDir Whether to scan subdirectories recursively
	 * @return void
	 */
	private static function scanFiles(
		array &$indexes,
		string $app,
		string $scanDir,
		string $fileType,
		bool $subDir = false
	): void {
		// Define regex for matching file types and directories
		$fileTypeRegex = ['options' => ['regexp' => '/^[A-Za-z0-9_.-]+(.' . $fileType . ')/']];
		$dirRegex = ['options' => ['regexp' => '/^[A-Za-z0-9_-]/']];

		// Closure to generate regex for files to exclude based on config
		$notLoading = function ($key) use ($app) {
			$keys = array_keys((array) Config::getConfig($app)->$key);
			$lastKey = end($keys);
			$noLoadRegex = null;

			foreach (Config::getConfig($app)->$key as $index => $value) {
				$char = ($index === $lastKey) ? null : '|';
				$noLoadRegex .= str_replace('/', '\/', Config::getConfig($app)->$key->$index) . $char;
			}

			return [
				'options' => [
					'regexp' => '/(' . str_replace('.', '\.', $noLoadRegex) . ')$/'
				]
			];
		};

		// Define regex filters based on file type
		$regex = match ($fileType) {
			'phtml' => (object) [
				'loading' => $fileTypeRegex,
				'notLoading' => $notLoading('view')
			],
			'php' => (object) [
				'loading' => $fileTypeRegex,
				'notLoading' => ['options' => ['regexp' => '/(vendor\/opus\/apps)/']]
			],
			'js' => (object) [
				'loading' => $fileTypeRegex,
				'notLoading' => $notLoading('js')
			],
			default => ['options' => ['regexp' => '/^[A-Za-z0-9_.-]+(.)/']],
		};

		try {
			// Try to locate files in local apps directory
			$dir = __DIR__ . '/../../../apps/' . $app . $scanDir;

			if (file_exists($dir) === false) {
				throw new Exception($app);
			}
		} catch (Exception $ex) {
			// Fall back to vendor apps directory
			$dir = __DIR__ . '/../../../vendor/Opus/apps/' . $ex->getMessage() . $scanDir;
		} finally {
			$files = (file_exists($dir) !== false) ? scandir($dir) : false;

			if ($files !== false) {
				foreach ($files as $file) {

					if (			// Check if file matches criteria and should be included
						filter_var($file, FILTER_VALIDATE_REGEXP, $regex->loading) !== false
						&& filter_var($dir . '/' . $file, FILTER_VALIDATE_REGEXP, $regex->notLoading) === false
					) {
						array_push($indexes, $dir . '/' . $file);
					} elseif (		// Check if it's a directory that should be recursively scanned
						filter_var($file, FILTER_VALIDATE_REGEXP, $dirRegex) !== false
						&& is_dir($dir . '/' . $file) === true
						&& $subDir === true
					) {
						self::scanFiles($indexes, $app, $scanDir . '/' . $file, $fileType);
					}
				}
			}
		}
	}

	/**
	 * Returns the layout object containing view and JS file paths
	 *
	 * @return object Layout object with 'index' and 'js' properties
	 */
	public static function getLayout(): object
	{
		return self::$layout;
	}

	/**
	 * Initializes and validates layout configuration
	 *
	 * Loads view and JS layout file paths from main application configuration,
	 * verifies their existence, and stores them in the static layout object along
	 * with language locale, page title, and favicon icon.
	 *
	 * @return void
	 * @throws Exception If layout view or JS file does not exist
	 */
	protected function setLayout(): void
	{
		self::$layout ??= new stdClass();

		$viewLayout = Config::getConfig(Config::OPUS_MAIN_APP)->view->layout;
		$jsLayout = Config::getConfig(Config::OPUS_MAIN_APP)->js->layout;

		if (!file_exists($viewLayout)) {
			throw new Exception('Controller::layout->view: File could not be found: ' . PHP_EOL . $viewLayout);
		}

		if (!file_exists($jsLayout)) {
			throw new Exception('Controller::layout->js: File could not be found: ' . PHP_EOL . $jsLayout);
		}

		self::$layout->index = $viewLayout;
		self::$layout->js = $jsLayout;
		self::$layout->lang = match (true) {
			str_contains($_SESSION['lang'], '-') => $_SESSION['lang'],
			isset(ValidateGlobalConfig::ALLOWED_LANGUAGES[$_SESSION['lang']])
			=> $_SESSION['lang'] . '-' . ValidateGlobalConfig::ALLOWED_LANGUAGES[$_SESSION['lang']][0],
			default => 'en-US'
		};
		self::$layout->title = Config::getConfig()->title;
		self::$layout->icon = Config::getConfig()->icon;
		self::$layout->vendor = Config::getConfig()->vendor;
		self::$layout->appVendor = self::$index->vendor;
		self::$layout->navbar ??= new stdClass();
		self::$layout->navbar->brandIcon = Config::getConfig('navbar')->brand_icon;
		self::$layout->navbar->brandText = Config::getConfig('navbar')->brand_text;
		self::$layout->opusLib = $this->createJsLib(true);
		self::$layout->opusCss = $this->copyCssFile();
		self::$layout->appLib = $this->createJsLib(false);
		self::$layout->appCss = $this->copyAppCssFile();
		self::$layout->appJs = self::$index->js;
	}

	/**
	 * Initializes error page file paths from configuration
	 *
	 * Loads error view and JS file paths from main application configuration
	 * and stores them in the static index object.
	 *
	 * @return void
	 */
	public static function setErrorIndex(): void
	{
		self::$index ??= new stdClass();
		self::$index->index = Config::getConfig(Config::OPUS_MAIN_APP)->view->error;

		if (!file_exists(self::$index->index)) {
			throw new Exception('Controller::view->index: File could not be found: ' . PHP_EOL . self::$index->index);
		}

		self::$index->js = Config::getConfig(Config::OPUS_MAIN_APP)->js->error;

		if (!file_exists(self::$index->js)) {
			throw new Exception('Controller::js->index: File could not be found: ' . PHP_EOL . self::$index->js);
		}
	}

	/**
	 * Retrieves the current application identifier
	 *
	 * This method returns the identifier of the application
	 * that is currently being executed.
	 *
	 * @return string|null The current application identifier or null if not set
	 */
	public static function getApp(): ?string
	{
		return self::$app;
	}

	/**
	 * Determines which application should handle a given request
	 *
	 * This method searches through the configured applications to find one that:
	 * 1. Matches the specified request type (e.g., PAGE, API)
	 * 2. Has a route that matches the provided request path
	 *
	 * When a matching application is found, it sets the static $app property
	 * and returns the application identifier. If no match is found, it returns null.
	 *
	 * @param string $request The request path to match against application routes
	 * @param string $type The type of request (e.g., PAGE, API)
	 * @return string|null The matching application identifier or null if no match found
	 */
	protected function setAppByRequest(string $request, string $type): ?string
	{
		// Reset application to null before searching
		self::$app = null;

		// Search through all configured applications
		foreach (Config::getConfig()->apps as $value) {

			if (Config::getConfig($value)->app->type === $type && in_array($request, Config::getConfig($value)->route)) {
				self::$app = $value;
				break;
			}
		}

		return self::$app;
	}

	/**
	 * Initializes application-specific file paths and indexes
	 *
	 * Loads and stores paths for the current application's view, JS, classes, libraries,
	 * modals, and offcanvas components by scanning respective directories.
	 *
	 * @return void
	 */
	protected function setApp(?bool $mainApp = false): void
	{
		self::$index ??= new stdClass();
		self::$libs ??= new stdClass();
		self::$index->app = $mainApp === true ? Config::OPUS_MAIN_APP : self::$app;
		self::$index->index = Config::getConfig(self::$index->app)->view->index;
		self::$index->css = Config::getConfig(self::$index->app)->view->css ?? null;
		self::$index->js = Config::getConfig(self::$index->app)->js->index;
		self::$index->vendor = Config::getConfig(self::$index->app)->vendor ?? [];
		self::$index->classes = [];
		self::$index->indexesModals = [];
		self::$index->indexesOffcanvas = [];
		self::$libs->app = [];
		self::$libs->js = [];
		self::scanFiles(self::$index->classes, self::$index->app, '/src', 'php', true);
		self::scanFiles(self::$libs->app, self::$index->app, '/libs', 'php', true);
		self::scanFiles(self::$index->indexesModals, self::$index->app, '/view/modals', 'phtml');
		self::scanFiles(self::$index->indexesOffcanvas, self::$index->app, '/view/offcanvas', 'phtml');
		self::scanFiles(self::$libs->js, self::$index->app, '/libs', 'js');
	}

	public static function getAppIndex(): object
	{
		return self::$index;
	}

	/**
	 * Aggregates JavaScript library files into a single output string.
	 *
	 * Includes multiple JavaScript library files, captures their output, removes comments,
	 * and prepends a language configuration variable.
	 *
	 * @param array $libs Array of file paths to JavaScript library files to be aggregated
	 *
	 * @return string|null The aggregated JavaScript content with comments removed and language
	 *                     configuration prepended, or null if the libs array is empty or
	 *                     the resulting content is empty after processing
	 */
	private function agregateJsLibs(array $libs): ?string
	{
		if (empty($libs)) {
			return null;
		}

		// Start output buffering to capture included content
		ob_start();

		// Include each library file if it exists
		foreach ($libs as $lib) {
			file_exists($lib) ? include $lib : null;
		}

		// Get captured content and clean up
		$content = trim(ob_get_clean());

		return empty($content)
			? null
			: trim(preg_replace(
				self::JS_PATTERN_COMMENTS,
				self::JS_REPLACEMENT_COMMENTS,
				$content
			));
	}

	/**
	 * Creates a JavaScript library file in the public vendor directory
	 *
	 * Aggregates JS source files into a single output file (either opus.js or {app}.lib.js).
	 * In 'dev' mode, the file is recreated on every request.
	 * In 'prod' mode, the file is regenerated only if missing or older than one week.
	 *
	 * @param bool $opus If true, creates opus.js from core JS libs; otherwise creates {app}.lib.js from app libs
	 * @return string|null The relative path for the <head> section or null if no content was generated
	 * @throws Exception If the file was expected to be created but does not exist
	 */
	private function createJsLib(bool $opus = false): ?string
	{
		self::$libs ??= new stdClass();
		self::$libs->js ??= [];

		$libFile = new stdClass();
		$libFile->dir = __DIR__ . '/../../../public/vendor/opus/';
		$libFile->name = $opus === true
			? 'opus.js'
			: self::$app . '.lib.js';
		$libFile->headPath = 'opus/' . $libFile->name;
		$libFile->fullPath = $libFile->dir . $libFile->name;
		$libFile->success = false;

		// Ensure the directory exists
		if (!is_dir($libFile->dir)) {
			mkdir($libFile->dir, 0777, true);
		}

		// Create or overwrite the library file
		match (Config::getConfig()->role) {
			'dev' => (function () use (&$libFile, $opus) {
				@unlink($libFile->fullPath);
				$content = $this->agregateJsLibs($opus === true ? self::JS_OPUS_LIBS : self::$libs->js);

				if (!is_null($content)) {
					file_put_contents($libFile->fullPath, $content);
					$libFile->success = true;
				}
			})(),

			default => (function () use (&$libFile, $opus) {
				$oneWeekAgo = time() - 7 * 24 * 60 * 60;

				if (!file_exists($libFile->fullPath) || filemtime($libFile->fullPath) < $oneWeekAgo) {
					@unlink($libFile->fullPath);
					$content = $this->agregateJsLibs($opus === true ? self::JS_OPUS_LIBS : self::$libs->js);

					if (!is_null($content)) {
						file_put_contents($libFile->fullPath, $content);
						$libFile->success = true;
					}
				}
			})()
		};

		if ($libFile->success === true && !file_exists($libFile->fullPath)) {
			throw new Exception('Controller::createJsLib: File could not be found: ' . $libFile->fullPath);
		}

		return $libFile->success === true ? $libFile->headPath : null;
	}

	/**
	 * Copies the opus.css file to the public vendor directory with comment removal
	 *
	 * Reads the source CSS file, strips comments using JS_PATTERN_COMMENTS,
	 * and writes the cleaned content to public/vendor/opus/.
	 * In 'dev' mode, the file is recreated on every request.
	 * In 'prod' mode, the file is regenerated only if missing or older than one week.
	 *
	 * @return string|null The relative path for the <head> section or null if the file could not be created
	 * @throws Exception If the file was expected to be created but does not exist
	 */
	private function copyCssFile(): ?string
	{
		$cssFile = new stdClass();
		$cssFile->css = 'opus.css';
		$cssFile->opusDir = __DIR__ . '/../../../vendor/Opus/css/';
		$cssFile->publicDir = __DIR__ . '/../../../public/vendor/opus/';
		$cssFile->fullPath = $cssFile->publicDir . $cssFile->css;
		$cssFile->headPath = 'opus/' . $cssFile->css;
		$cssFile->success = false;

		// Ensure the directory exists
		if (!is_dir($cssFile->publicDir)) {
			mkdir($cssFile->publicDir, 0777, true);
		}

		// Closure: read source CSS, strip comments, write to public dir
		$processAndWrite = function () use (&$cssFile): void {
			@unlink($cssFile->fullPath);
			$content = @file_get_contents($cssFile->opusDir . $cssFile->css);

			if ($content !== false) {
				$content = trim(preg_replace(self::JS_PATTERN_COMMENTS, self::JS_REPLACEMENT_COMMENTS, $content));

				if (!empty($content)) {
					file_put_contents($cssFile->fullPath, $content);
					$cssFile->success = true;
				}
			}
		};

		// Create or overwrite the opus.css file
		match (Config::getConfig()->role) {
			'dev' => $processAndWrite(),
			default => (function () use (&$cssFile, $processAndWrite) {
				$oneWeekAgo = time() - 7 * 24 * 60 * 60;

				if (!file_exists($cssFile->fullPath) || filemtime($cssFile->fullPath) < $oneWeekAgo) {
					$processAndWrite();
				}
			})()
		};

		if ($cssFile->success === true && !file_exists($cssFile->fullPath)) {
			throw new Exception('Controller::copyCssFile: File could not be found: ' . $cssFile->fullPath);
		}

		return $cssFile->success === true ? $cssFile->headPath : null;
	}

	/**
	 * Copies the application CSS file to the public vendor directory with comment removal
	 *
	 * Reads the source app CSS file, strips comments, and writes the cleaned content
	 * to public/vendor/opus/. In 'dev' mode, the file is recreated on every request.
	 * In 'prod' mode, the file is regenerated only if missing or older than one week.
	 *
	 * @return string|null The relative path for the <head> section or null if no app CSS configured
	 * @throws Exception If the file was expected to be created but does not exist
	 */
	private function copyAppCssFile(): ?string
	{
		if (is_null(self::$index->css ?? null)) {
			return null;
		}

		$cssFile = new stdClass();
		$cssFile->source = self::$index->css;
		$cssFile->name = self::$app . '.css';
		$cssFile->publicDir = __DIR__ . '/../../../public/vendor/opus/';
		$cssFile->fullPath = $cssFile->publicDir . $cssFile->name;
		$cssFile->headPath = 'opus/' . $cssFile->name;
		$cssFile->success = false;

		if (!is_dir($cssFile->publicDir)) {
			mkdir($cssFile->publicDir, 0777, true);
		}

		$processAndWrite = function () use (&$cssFile): void {
			@unlink($cssFile->fullPath);
			$content = @file_get_contents($cssFile->source);

			if ($content !== false) {
				$content = trim(preg_replace(self::JS_PATTERN_COMMENTS, self::JS_REPLACEMENT_COMMENTS, $content));

				if (!empty($content)) {
					file_put_contents($cssFile->fullPath, $content);
					$cssFile->success = true;
				}
			}
		};

		match (Config::getConfig()->role) {
			'dev' => $processAndWrite(),
			default => (function () use (&$cssFile, $processAndWrite) {
				$oneWeekAgo = time() - 7 * 24 * 60 * 60;

				if (!file_exists($cssFile->fullPath) || filemtime($cssFile->fullPath) < $oneWeekAgo) {
					$processAndWrite();
				}
			})()
		};

		if ($cssFile->success === true && !file_exists($cssFile->fullPath)) {
			throw new Exception('Controller::copyAppCssFile: File could not be found: ' . $cssFile->fullPath);
		}

		return $cssFile->success === true ? $cssFile->headPath : null;
	}

	/**
	 * Initializes application-specific file paths for API, CLI, and async page requests
	 *
	 * Loads and stores paths for the application's classes and libraries by scanning
	 * respective directories. This method is used by initApi, initCli, and initAsyncPage
	 * to prepare the application environment without loading view-related files.
	 *
	 * @param string|null $app The application identifier or null for main application
	 * @return void
	 *
	 * @see AbstractController::initApi()
	 * @see AbstractController::initCli()
	 * @see AbstractController::initAsyncPage()
	 */
	protected function setApi(?string $app): void
	{
		self::$index ??= new stdClass();
		self::$libs ??= new stdClass();
		self::$index->app = is_null($app) ? Config::OPUS_MAIN_APP : $app;
		self::$index->classes = [];
		self::$libs->app = [];
		self::scanFiles(self::$index->classes, self::$index->app, '/src', 'php', true);
		self::scanFiles(self::$libs->app, self::$index->app, '/libs', 'php', true);
	}

	/**
	 * Processes a page request for a specific application
	 *
	 * This method handles page requests by setting up the appropriate view files,
	 * loading resources, and checking user authorization based on the request type.
	 *
	 * @param string $request The request path
	 * @throws Exception If required view or JavaScript files cannot be found
	 * @throws ControllerException If the requested route is invalid
	 * @return void
	 */
	protected function pageRequest(string $request): void
	{
		// Determine the request scenario
		$scenario = match (true) {
			self::$app === Config::OPUS_MAIN_APP => 'main_app',
			in_array($request, Event::TYPE_APPS['page']) => 'internal_app',
			is_null(self::$app) || !in_array($request, Config::getConfig(self::$app)->route) => 'invalid_route',
			default => 'standard_app'
		};

		if ($scenario === 'main_app') {				// Handle main application special pages
			$this->setApp(true);

			if (!file_exists(self::$index->index)) {
				throw new Exception('Controller::view->index: File could not be found: ' . PHP_EOL . self::$index->index);
			}

			if (!file_exists(self::$index->js)) {
				throw new Exception('Controller::js->index: File could not be found: ' . PHP_EOL . self::$index->js);
			}
		} elseif ($scenario === 'internal_app') {	// don't load anything - login/logout/download end with exit
		} elseif ($scenario === 'invalid_route') {	// Handle invalid routes
			self::setErrorIndex();

			throw new ControllerException(
				'controller\request\route',
				['message' => Request::uri()]
			);
		} else {									// Handle standard application pages
			$this->setApp();

			if (!file_exists(self::$index->index) && !is_null(self::$index->index)) {
				throw new ControllerException(
					'controller\request\file',
					['details' => self::$index->index]
				);
			}

			if (!file_exists(self::$index->js) && !is_null(self::$index->js)) {
				throw new ControllerException(
					'controller\request\file',
					['details' => self::$index->js]
				);
			}

			Authorization::access((object)['app' => self::$app]);
		}
	}

	/**
	 * Processes an API request
	 *
	 * This method handles API requests by:
	 * 1. Determining the type of API request (asyncevent, tableevent)
	 * 2. Extracting the target application and event from the URL
	 * 3. Validating that all required parameters are present
	 * 4. Checking if authentication is required and handling login if needed
	 * 5. Verifying user authorization for the requested operation
	 *
	 * @param string $request The request type (asyncevent, tableevent, etc.)
	 * @throws ControllerException If the request is invalid or unauthorized
	 * @return string The application identifier that will handle the request
	 */
	protected function apiRequest(string $request): string
	{
		$accessObj = new stdClass();

		// Validate request type
		if (!in_array($request, Event::TYPE_APPS['api'])) {
			throw new ControllerException(
				'controller\request\route',
				['message' => Request::uri()],
				ControllerException::TYPE_API_EXCEPTION
			);
		}

		// Extract target application from URL
		$asyncApp = Request::fromUrl('app');

		if ($asyncApp === '/' || $asyncApp === false) {
			throw new ControllerException(
				'controller\request\async',
				['message' => ['app', Request::uri()]],
				ControllerException::TYPE_API_EXCEPTION
			);
		}

		// Extract target event from URL
		$apiEvent = Request::fromUrl('event');

		if ($apiEvent === '/' || $apiEvent === false || empty($apiEvent)) {
			throw new ControllerException(
				'controller\request\async',
				['message' => ['event', Request::uri()]],
				ControllerException::TYPE_API_EXCEPTION
			);
		}

		// Set target application and event
		$accessObj->app = $asyncApp;

		if ($request === 'asyncevent') {
			$accessObj->asyncEvent = $apiEvent;
		} elseif ($request === 'tableevent') {
			$accessObj->tableEvent = $apiEvent;
		}

		// Handle authentication for protected async events
		if (($request === 'asyncevent')
			&& (Config::getConfig($accessObj->app)->asyncEvent->{$accessObj->asyncEvent}->access > 0)
			&& ($_SESSION['logged'] === false)
		) {
			Login::login(Login::TYPE_LOGIN_API);
		}

		// Set exception type and check authorization
		$accessObj->except = ControllerException::TYPE_API_EXCEPTION;
		Authorization::access($accessObj);
		$this->setApi($accessObj->app);
		return $accessObj->app;
	}

	/**
	 * Processes an asynchronous page request
	 *
	 * This method handles async page requests by:
	 * 1. Extracting the target application from the URL
	 * 2. Validating the request type and application
	 * 3. Verifying user authorization for the requested page
	 *
	 * @param string $request The async page request identifier
	 * @throws ControllerException If the request is invalid or unauthorized
	 * @return string The application identifier that will handle the request
	 */
	protected function asyncRequest(string $request): string
	{
		$accessObj = new stdClass();
		$accessObj->asyncPage = Request::fromUrl('event');
		$app = Request::fromUrl('app');

		// Handle direct async page calls (no app specified in URL)
		if (($app === '/' || $app === false)
			&& in_array($request, Event::TYPE_APPS['apage'])
		) {
			$accessObj->app = Config::OPUS_MAIN_APP;
		} elseif (($app === '/' || $app === false)
			&& !in_array($request, Event::TYPE_APPS['apage'])
		) {		// Handle invalid API routes
			throw new ControllerException(
				'controller\request\route',
				['message' => Request::uri()],
				ControllerException::TYPE_ASYNC_PAGE_EXCEPTION
			);
		} elseif ($app != '/') {	// Handle app-specific async page calls
			$accessObj->app = $app;
		}

		// Set exception type and check authorization
		$accessObj->except = ControllerException::TYPE_ASYNC_PAGE_EXCEPTION;
		Authorization::access($accessObj);
		$this->setApi($accessObj->app);
		return $accessObj->app;
	}

	/**
	 * Processes a command-line interface (CLI) request
	 *
	 * This method handles CLI requests by:
	 * 1. Checking if the app is a recognized CLI application
	 * 2. Verifying that access configuration exists for non-CLI apps
	 * 3. Handling authentication for protected applications
	 * 4. Checking authorization for the requested operation
	 *
	 * CLI-specific applications bypass authorization checks.
	 *
	 * @param string $app The application identifier
	 * @throws ControllerException If the request is invalid or unauthorized
	 * @return void
	 */
	protected function cliRequest(string $app): void
	{
		// Use match to handle different scenarios
		match (true) {
			// CLI-specific applications bypass authorization
			in_array($app, Event::TYPE_APPS['cli']) => null,

			// Handle authentication and authorization for protected apps
			default => (function () use ($app) {
				// Check if login is required
				if (Config::getConfig($app)->app->access > 0) {
					Login::login(Login::TYPE_LOGIN_CLI);
				}

				// Verify authorization
				Authorization::access(
					(object) [
						'app' => $app,
						'except' => ControllerException::TYPE_CLI_EXCEPTION
					]
				);
			})()
		};
	}

	/**
	 * Loads application class files
	 *
	 * This method scans the application's src directory for PHP class files
	 * and loads them using require_once. These classes form the core functionality
	 * of the application.
	 *
	 * @return void
	 */
	protected function loadAppClasses(): void
	{
		if (!empty(self::$index->classes)) {
			foreach (self::$index->classes as $file) {
				require_once $file;
			}
		}
	}

	/**
	 * Loads application library files
	 *
	 * This method scans the application's libs directory for PHP library files
	 * and loads them using require_once. These libraries provide additional
	 * functionality that may be used across the application.
	 *
	 * @return void
	 */
	protected function loadAppLibs(): void
	{
		if (!empty(self::$libs->app)) {
			foreach (self::$libs->app as $file) {
				require_once $file;
			}
		}
	}

	/**
	 * Retrieves the server's timezone
	 *
	 * This method returns the timezone that has been detected and set
	 * for the server. This timezone is used for all date and time operations
	 * throughout the application.
	 *
	 * @return string The server's timezone identifier
	 */
	public static function getServerTimezone(): string
	{
		return self::$serverTimezone;
	}

	/**
	 * Detects and sets the server's timezone
	 *
	 * This method attempts to determine the server's timezone by checking:
	 * 1. The TZ environment variable
	 * 2. The /etc/timezone file
	 * 3. The /etc/localtime file
	 *
	 * Once detected, it sets the timezone for all PHP date and time functions.
	 *
	 * @return void
	 */
	protected function setServerTimezone(): void
	{
		$timezone = $_SERVER['TZ']
			?? (is_readable('/etc/timezone') ? trim(file_get_contents('/etc/timezone')) : null)
			?? (is_link('/etc/localtime') ? preg_replace('#.*/zoneinfo/#', '', readlink('/etc/localtime')) : null)
			?? date_default_timezone_get();

		self::$serverTimezone = $timezone;
		date_default_timezone_set(self::$serverTimezone);
	}
}
