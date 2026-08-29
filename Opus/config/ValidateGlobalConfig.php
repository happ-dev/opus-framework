<?php

/**
 * @Project: Opus
 * @Version: 1.0
 * @Author: Tomasz Ułazowski
 * @Date:   2026-01-27 11:06:49
 * @Last Modified by:   Tomasz Ułazowski
 * @Last Modified time: 2026-08-26 20:58:15
 **/

namespace Opus\config;

use Exception;

abstract class ValidateGlobalConfig
{
	protected static object $config;

	const OPUS_APPS = [
		[
			'app' => 'skeleton',
			'config' => 'vendor/Opus/apps/skeleton/config/skeleton.config.json'
		],
		[
			'app' => 'settings',
			'config' => 'vendor/Opus/apps/settings/config/settings.config.json'
		],
		[
			'app' => 'profile',
			'config' => 'vendor/Opus/apps/profile/config/profile.config.json'
		],
		[
			'app' => 'logs',
			'config' => 'vendor/Opus/apps/logs/config/logs.config.json'
		],
		[
			'app' => 'demo',
			'config' => 'vendor/Opus/apps/demo/config/demo.config.json'
		],
		[
			'app' => 'setup',
			'config' => 'vendor/Opus/apps/setup/config/setup.config.json'
		]
	];

	const OPUS_MAIN_APP = self::OPUS_APPS[0]['app'];
	const OPUS_GLOBAL_CONFIG = 'config/global.json';
	const OPUS_LOCAL_CONFIG = 'config/local.json';
	const OPUS_SECRET_KEY = 'config/secret.key';

	private const STORAGE_REQUIRED_TYPES = ['pgsql', 'ibmodbc', 'mysql'];
	private const REGEX_DB_ENCODING = ['options' => ['regexp' => '/^(UTF-?8|LATIN[0-9]|ASCII|UNICODE|WIN(1250|1251|1252)|ISO-?8859-[0-9]|CP[0-9]{3,4})$/i']];
	public const ALLOWED_LANGUAGES = [
		'en' => ['US', 'GB', 'AU'],		// English variants
		'pl' => ['PL']					// Polish variants
	];
	public const ALLOWED_LANGUAGES_TEXT = ['English', 'Polski'];
	public const ALLOWED_ICON_EXTENSIONS = ['ico', 'png', 'svg', 'jpg', 'jpeg', 'gif'];

	/**
	 * Validates app names array
	 *
	 * @param array $apps Array of app names to validate
	 * @return bool True if all app names are valid, false if array is empty
	 * @throws Exception If validation fails
	 */
	final protected function validateAppsNames(array $apps): bool
	{
		if (empty($apps)) {
			return false;
		}

		foreach ($apps as $app) {
			match (true) {
				!is_string($app) => throw new Exception('App name must be a string'),
				!preg_match('/^[a-z0-9_]+$/', $app) => throw new Exception("Invalid app name: '{$app}'. Only lowercase letters, numbers, hyphens and underscores are allowed"),
				strlen($app) < 2 || strlen($app) > 50 => throw new Exception("App name '{$app}' must be between 2 and 50 characters"),
				default => true
			};
		}

		return true;
	}

	/**
	 * Function checks the correctness of storage configuration
	 *
	 * @param object $config
	 * @return void
	 * @throws Exception if the configuration is incorrect
	 */
	final protected function validateStorageConfig(object $config): void
	{
		$errors = [];
		$required = [
			'type' => fn($v) => in_array($v, self::STORAGE_REQUIRED_TYPES),
			'host' => fn($v) => EncryptStorageConfig::decrypt($v) === 'localhost'
				|| filter_var(EncryptStorageConfig::decrypt($v), FILTER_VALIDATE_IP)
				|| filter_var(EncryptStorageConfig::decrypt($v), FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME),
			'port' => fn($v) => filter_var(EncryptStorageConfig::decrypt($v), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]),
			'user' => fn($v) => EncryptStorageConfig::isEncrypted($v),
			'pass' => fn($v) => EncryptStorageConfig::isEncrypted($v)
		];

		foreach ($required as $field => $validator) {

			if (!property_exists($config, $field)) {
				$errors[] = "Missing required field: {$field}";
				continue;
			}

			if ($validator($config->$field) === false) {
				$errors[] = match ($field) {
					'type' => 'Invalid storage type',
					'host' => 'Invalid storage host',
					'port' => 'Storage port must be between 1 and 65535',
					'user' => 'Empty username',
					'pass' => 'Empty password',
					default => "Invalid value for {$field}"
				};
			}
		}

		// Optional encoding validation
		if ((property_exists($config, 'encoding')) && (!filter_var($config->encoding, FILTER_VALIDATE_REGEXP, self::REGEX_DB_ENCODING))) {
			$errors[] = "Invalid encoding format: {$config->encoding}";
		}

		if (!empty($errors)) {
			throw new Exception("Configuration validation failed:\n" . implode(PHP_EOL, $errors));
		}
	}

	final protected function validateNavbarConfig(object &$config): void
	{
		$errors = [];
		$required = [
			'brand_icon' => fn($v) => preg_match('/^[a-z0-9_-]+\/[a-z0-9_.\/-]+\.(' . implode('|', self::ALLOWED_ICON_EXTENSIONS) . ')$/i', $v),
			'brand_text' => fn($v) => is_string($v) && preg_match('/^(?=.{2,50}$)[\p{L}0-9_]+( [\p{L}0-9_]+)*$/u', $v),
			'login_form' => fn($v) => (function ($v) use (&$config) {
				$bool = filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
				$config->login_form = $bool ?? false;
				return $bool !== null;
			})($v)
		];

		foreach ($required as $field => $validator) {

			if (!property_exists($config, $field)) {
				$errors[] = "Missing required field: {$field}.";
				continue;
			}

			if ($validator($config->$field) === false) {
				$errors[] = match ($field) {
					'brand_icon' => "Unsupported icon format: '{$field}'. Allowed: " . implode('|', self::ALLOWED_ICON_EXTENSIONS),
					'brand_text' => 'Unsupported brand text',
					'login_form' => "Invalid value for {$field}, allowed values: yes|true|no|false",
					default => "Invalid value for {$field}."
				};
			}
		}

		if (!empty($errors)) {
			throw new Exception("Configuration validation failed:\n" . implode(PHP_EOL, $errors));
		}
	}

	/**
	 * Validates vendor file paths
	 *
	 * @param array $vendor Array of vendor file paths to validate
	 * @return void
	 * @throws Exception If validation fails
	 */
	final protected function validateVendor(array $vendor): void
	{
		if (empty($vendor)) {
			throw new Exception('Vendor array cannot be empty');
		}

		foreach ($vendor as $path) {
			match (true) {
				!is_string($path) => throw new Exception('Vendor path must be a string'),
				!preg_match('/^[a-z0-9_-]+\/[a-z0-9_.\/-]+\.(css|js)$/i', $path) => throw new Exception("Invalid vendor path format: '{$path}'"),
				!file_exists('public/vendor/' . $path) => throw new Exception("Vendor file not found: 'public/vendor/{$path}'"),
				default => true
			};
		}
	}

	/**
	 * @param array $languages The languages to validate
	 * @return void
	 * @throws Exception If validation fails
	 */
	final protected function validateLanguagesConfig(array &$languages): void
	{
		foreach ($languages as $lang) {
			// Handle both simple codes and language-region formats
			$parts = explode('-', str_replace('_', '-', strtolower($lang)));
			$langCode = $parts[0];
			$regionCode = $parts[1] ?? null;

			// Validate language code
			if (!isset(self::ALLOWED_LANGUAGES[$langCode])) {
				throw new Exception(
					sprintf(
						"Unsupported language code: '%s'. Allowed languages are: %s",
						$langCode,
						implode(', ', array_keys(self::ALLOWED_LANGUAGES))
					)
				);
			}

			// Validate region code if provided
			if ($regionCode !== null) {
				$regionCode = strtoupper($regionCode);

				if (!in_array($regionCode, self::ALLOWED_LANGUAGES[$langCode], true)) {
					throw new Exception(
						sprintf(
							"Unsupported region '%s' for language '%s'. Allowed regions are: %s",
							$regionCode,
							$langCode,
							implode(', ', self::ALLOWED_LANGUAGES[$langCode])
						)
					);
				}
			}
		}
	}

	/**
	 * Validates trusted hosts configuration
	 *
	 * @param array $hosts Array of domain names to validate
	 * @return void
	 * @throws Exception if domains are invalid or empty
	 */
	final protected function validateTrustedHostsConfig(array &$hosts): void
	{
		$trustedHosts = [];
		$options = ['flags' => FILTER_FLAG_HOSTNAME];
		$validators = [
			fn($host) => is_string($host)
				?: throw new Exception(sprintf("Invalid host type: expected string, got %s", gettype($host))),
			fn($host) => strtolower(trim($host)),
			fn($host) => $host === 'localhost' ? null : $host,
			fn($host) => $host && strlen($host) <= 253
				? $host
				: throw new Exception(sprintf("Domain name too long: '%s'", $host)),
			fn($host) => $host && preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*$/i', $host)
				? $host
				: throw new Exception(sprintf("Invalid domain name format: '%s'", $host)),
			fn($host) => $host && filter_var($host, FILTER_VALIDATE_DOMAIN, $options)
				? $host
				: throw new Exception(sprintf("Invalid domain name: '%s'", $host)),
			fn($host) => $host && !in_array($host, $trustedHosts, true)
				? $host
				: throw new Exception(sprintf("Duplicate domain name: '%s'", $host))
		];

		foreach ($hosts as $host) {
			$validHost = array_reduce(
				$validators,
				fn($carry, $validator) => $validator($carry),
				$host
			);

			if ($validHost) {
				$trustedHosts[] = $validHost;
			} elseif ($host === 'localhost') {
				$trustedHosts[] = 'localhost';
			}
		}

		$hosts = sort($trustedHosts);
	}
}
