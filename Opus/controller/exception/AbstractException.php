<?php

/**
 * @Project: Opus
 * @Version: 1.0
 * @Author: Tomasz Ułazowski
 * @Date:   2026-02-02 11:48:21
 * @Last Modified by:   Tomasz Ulazowski
 * @Last Modified time: 2026-03-24 11:32:32
 **/

namespace Opus\controller\exception;

use Exception;
use Opus\controller\InterfaceController;
use Opus\controller\cli\CliColor;
use Opus\controller\request\Request;
use Opus\storage\json\Json;

define('EXCEPTION_LANG_FILE', 'vendor/Opus/controller/lang/' . $_SESSION['lang'] . '_error.json');

/**
 * Abstract exception handler for Opus framework
 *
 * Handles different types of exceptions (page, API, CLI, async) and formats error messages
 * according to the request type.
 */
abstract class AbstractException extends Exception implements InterfaceController
{
	const TYPE_PAGE_EXCEPTION = Request::TYPE_PAGE;
	const TYPE_ASYNC_PAGE_EXCEPTION = Request::TYPE_ASYNC_PAGE;
	const TYPE_API_EXCEPTION = Request::TYPE_API;
	const TYPE_API_STRONG_EXCEPTION = 'sapi';
	const TYPE_CLI_EXCEPTION = Request::TYPE_CLI;

	const TYPE_HTML_SPAN_MESSAGE = '<span class="fst-normal text-danger">$value$</span>';
	const TYPE_HTML_STRONG_MESSAGE = '<br><span class="badge text-bg-warning font-monospace">$value$</span>';

	protected ?string $exMessage = null;
	protected ?string $exDetails = null;
	protected ?object $errorFile = null;
	protected ?object $errorModulFile = null;
	protected ?object $logError;

	/**
	 * Loads error messages from JSON files
	 *
	 * @param string|null $file Path to module-specific error file
	 * @return void
	 */
	protected function loadErrorsMessage(?string $file): void
	{
		$this->errorModulFile = (!is_null($file)) ? Json::loadJsonFile($file) : null;
		$this->errorFile = Json::loadJsonFile(EXCEPTION_LANG_FILE);
	}

	/**
	 * Creates error object from path string
	 *
	 * @param string $path Dot-separated path to error message
	 * @param string|null $file Source file (module or global)
	 * @return object Error object
	 * @throws Exception When path does not exist
	 */
	protected function createObjectError(string $path, ?string $file): object
	{
		return array_reduce(
			explode('\\', $path),
			function ($obj, $path) {
				return isset($obj->$path)
					? (object) $obj->$path
					: throw new Exception('No path: ' . $path);
			},
			(is_null($file)) ? $this->errorFile : $this->errorModulFile
		);
	}

	/**
	 * Sets formatted error string based on type and value
	 *
	 * @param string|null $exError Reference to error string to be set
	 * @param object $error Error object containing message templates
	 * @param mixed $value Value(s) to replace in message template
	 * @param string $type Exception type (page, API, CLI, async)
	 * @param string $messageType Message property name in error object
	 * @return void
	 */
	protected function setStrError(?string &$exError, object $error, mixed $value, string $type, string $messageType): void
	{
		// Define message transformations for different types
		$transformations = [
			self::TYPE_ASYNC_PAGE_EXCEPTION,
			self::TYPE_PAGE_EXCEPTION => fn($msg) => str_replace('$value$', self::TYPE_HTML_SPAN_MESSAGE, $msg),
			self::TYPE_API_EXCEPTION => fn($msg) => strip_tags(str_replace('<br>', ' ', $msg)),
			self::TYPE_API_STRONG_EXCEPTION => fn($msg) => str_replace('$value$', self::TYPE_HTML_STRONG_MESSAGE, $msg),
			self::TYPE_CLI_EXCEPTION => fn($msg) => str_replace(
				'$value$',
				CliColor::write(
					'$value$',
					match ($exError === $this->exMessage) {
						true => CliColor::COLOR_LIGHT_RED,
						false => CliColor::COLOR_LIGHT_CYAN
					},
					null,
					false
				),
				strip_tags(str_replace('<br>', PHP_EOL, $msg))
			)
		];

		// Apply type-specific transformation if type exists and value is not empty
		if (!empty($value) && isset($transformations[$type])) {
			$error->message = $transformations[$type]($error->message);
		}

		// Handle variable replacement
		if (empty($value)) {
			$exError = isset($error->{$messageType})
				? str_replace('$value$', '', $error->{$messageType})
				: null;
			return;
		}

		if (is_string($value) && property_exists($error, $messageType)) {
			$exError = str_replace('$value$', $value, $error->{$messageType});
			return;
		}

		if (is_array($value)) {
			$message = $error->{$messageType};

			foreach ($value as $replacement) {
				$message = preg_replace('/\$value\$/', $replacement, $message, 1);
			}

			$exError = $message;
		}
	}
}
