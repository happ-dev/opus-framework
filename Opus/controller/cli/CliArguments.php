<?php

/**
 * @Project: Opus
 * @Version: 1.0
 * @Author: Tomasz Ułazowski
 * @Date:   2026-02-18 09:57:47
 * @Last Modified by:   Tomasz Ułazowski
 * @Last Modified time: 2026-08-27 19:57:21
 **/

namespace Opus\controller\cli;

use Opus\config\Config;
use Opus\controller\exception\ControllerException;

class CliArguments
{
	private static array $arguments = [];

	/**
	 * Validates and processes command line arguments
	 *
	 * This method checks if sufficient arguments are provided and processes them by:
	 * 1. Validating minimum argument count (app name and command required)
	 * 2. Removing script name and app name from arguments array
	 * 3. Checking for help requests in remaining arguments
	 *
	 * @param array $arg The raw command line arguments array
	 * @throws ControllerException When insufficient arguments are provided or help is requested
	 * @return void
	 */
	public static function check(array $arg): void
	{
		// Use match to handle different argument scenarios
		match (true) {
			// Case: Sufficient arguments provided
			count($arg) > 2 => (function () use ($arg) {
				// Store and process arguments
				self::$arguments = $arg;
				array_shift(self::$arguments); // Remove script name
				array_shift(self::$arguments); // Remove app name

				// Check for help requests in remaining arguments
				self::helpArgument(self::$arguments, $arg[1]);
			})(),

			// Case: Insufficient arguments - show general help
			default => throw new ControllerException(
				$arg[1] . '\help',
				['message' => $arg[1]],
				ControllerException::TYPE_CLI_EXCEPTION,
				self::errorFile($arg[1])
			)
		};
	}

	/**
	 * Retrieves the processed command line arguments
	 *
	 * @return array The processed command line arguments array
	 */
	public static function getArguments(): array
	{
		return self::$arguments;
	}

	/**
	 * Determines the path to an application's error file
	 *
	 * This method locates the optional error file for an application based on:
	 * 1. Whether it's a core Opus app or a custom app
	 * 2. The current language setting in the session
	 *
	 * Returns null if the file does not exist, causing ControllerException
	 * to fall back to the central {lang}_error.json.
	 *
	 * @param string|null $app The application identifier
	 * @return string|null Path to the error file in format {language}_error.json, or null
	 */
	private static function errorFile(?string $app): ?string
	{
		if (is_null($app)) {
			return null;
		}

		$isOpusApp = !empty(array_filter(
			Config::OPUS_APPS,
			fn($opusApp) => $opusApp['app'] === $app
		));

		$file = match ($isOpusApp) {
			true =>  __DIR__ . '/../../apps/' . $app . '/lang/' . $_SESSION['lang'] . '_error.json',
			false => __DIR__ . '/../../../apps/' . $app . '/lang/' . $_SESSION['lang'] . '_error.json'
		};

		return file_exists($file) ? $file : null;
	}

	/**
	 * Processes help-related command line arguments
	 *
	 * This method checks if any of the provided arguments is a help request.
	 * It handles two types of help requests:
	 * 1. General help for the application (--help as the first argument)
	 * 2. Specific help for a command (command --help)
	 *
	 * When a help request is detected, it throws a ControllerHelpException
	 * with the appropriate context.
	 *
	 * @param array $arg The command line arguments array
	 * @param string $app The application identifier
	 * @throws ControllerException When a help argument is detected
	 * @return void
	 */
	private static function helpArgument(array $arg, string $app): void
	{
		// Process each argument
		foreach ($arg as $key => $value) {
			// Skip if not a help argument
			if (strpos($value, 'help') === false) {
				continue;
			}

			// Determine help type
			match (true) {
				// General help (--help as first argument)
				$key === 0 => throw new ControllerException(
					$app . '\help',
					['message' => $app],
					ControllerException::TYPE_CLI_EXCEPTION,
				self::errorFile($app)
				),

				// Command-specific help (command --help)
				default => (function () use ($arg, $key, $app) {
					$helpArg = strip_tags(str_replace('-', '', $arg[$key - 1]));
					throw new ControllerException(
						$app . '\help\\' . $helpArg,
						['message' => $helpArg],
						ControllerException::TYPE_CLI_EXCEPTION,
						self::errorFile($app)
					);
				})()
			};
		}
	}
}
