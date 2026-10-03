<?php

/**
 * @Project: Opus
 * @Version: 1.0
 * @Author: Tomasz Ułazowski
 * @Date:   2026-01-27 13:29:20
 * @Last Modified by:   Tomasz Ułazowski
 * @Last Modified time: 2026-10-02 20:09:02
 **/

namespace Opus\config;

use Exception;
use stdClass;
use Opus\storage\json\Json;

class Config extends ValidateGlobalConfig
{

	/**
	 * Function returns an object with configuration
	 *
	 * @param ?string $app
	 * @return mixed
	 */
	public static function getConfig(?string $app = null): mixed
	{
		return !is_null($app) ? self::$config->{$app} ?? null : self::$config ?? null;
	}

	/**
	 * Function loads configurations from a json files into
	 * protected static object $config variable
	 */
	public static function loadConfiguration(): void
	{
		$config = new self();
		$config::$config = new stdClass();
		$config::$config->apps = [];

		// validate JSON and get error location
		$objGlobalConfig = Json::loadJsonFile(self::OPUS_GLOBAL_CONFIG);

		// validate JSON and get error location
		$objGlobalConfig = Json::loadJsonFile(self::OPUS_GLOBAL_CONFIG);

		// load internal application configuration
		$config->loadInternalAppsConfig();

		// load external application configuration from global.config.json
		$config->loadAppsConfig($objGlobalConfig->apps);

		// load storage configuration
		$config->loadStorageConfig($objGlobalConfig->storage);

		// load navbar configuration
		$config->loadNavbarConfig($objGlobalConfig->navbar);

		// load languages configuration
		$config->loadLanguagesConfig($objGlobalConfig->languages);

		// load role configuration
		$config->loadRoleConfig($objGlobalConfig->role);

		// load shortcut icon configuration
		$config->loadIconConfig($objGlobalConfig->icon ?? null);

		// load title configuration
		$config->loadTitleConfig($objGlobalConfig->title);

		// load vendor library configuration
		$config->loadVendorConfig($objGlobalConfig->vendor ?? []);

		// load e-mail configuration
		$config->loadEmailConfig($objGlobalConfig->email);

		// load trusted hosts configuration
		$config->loadTrustedHostsConfig($objGlobalConfig->trusted_hosts);

		// load auth configuration
		$config->loadAuthConfig($objGlobalConfig->auth ?? null);
	}

	/**
	 * Adds a temporary runtime storage config entry for CLI operations
	 * Encrypts user and pass, registers entry under 'temporary' key
	 *
	 * @param object $config {type, name, encoding, host, port, user, pass}
	 * @return bool
	 * @throws Exception if user or pass is null
	 */
	public static function setTemporaryDbConfig(object $config): bool
	{
		if (is_null($config->user) || is_null($config->pass)) {
			throw new Exception('Temporary DB config requires user and pass');
		}

		$config->user = EncryptStorageConfig::encryptValue($config->user);
		$config->pass = EncryptStorageConfig::encryptValue($config->pass);

		self::$config->storage->temporary = $config;

		return true;
	}

	/**
	 * Removes the temporary runtime storage config entry
	 *
	 * @return bool
	 */
	public static function removeTemporaryDbConfig(): bool
	{
		unset(self::$config->storage->temporary);
		return true;
	}

	/**
	 * Loads internal application configurations
	 *
	 * @return void
	 * @throws Exception if there are no files or no access to them
	 */
	private function loadInternalAppsConfig(): void
	{
		foreach (self::OPUS_APPS as $app) {
			array_push(self::$config->apps, $app['app']);

			// add app config to global config
			// validate JSON and get error location
			// exception if file not found
			self::$config->{$app['app']} = Json::loadJsonFile($app['config']);

			// checks the configuration for required parameters
			// full validation takes place at a higher level
			ValidateAppConfig::validate(self::$config->{$app['app']}, $app['config']);
		}
	}

	/**
	 * Loads application configurations from global.config
	 *
	 * @return void
	 * @throws Exception if there are no files or no access to them
	 */
	private function loadAppsConfig(array $apps): void
	{
		$result = $this->validateAppsNames($apps);

		if ($result === false) {
			return;
		}

		foreach ($apps as $app) {
			array_push(self::$config->apps, $app);

			// add app config to global config
			// validate JSON and get error location
			// exception if file not found
			$file = 'apps/' . $app . '/config/' . $app . '.config.json';
			self::$config->{$app} = Json::loadJsonFile($file);

			// checks the configuration for required parameters
			// full validation takes place at a higher level
			ValidateAppConfig::validate(self::$config->{$app}, $file);
		}
	}

	/**
	 * Merge storage config from global and local config file
	 *
	 * @return void
	 * @throws Exception if storage parameter does not meet the requirements
	 */
	private function loadStorageConfig(array $storage): void
	{
		// test input $storage parameter
		$storage ?: throw new Exception('Requaired parameter $storage not found');
		self::$config->storage = new stdClass();

		foreach ($storage as $value) {
			$key = key((array) $value);
			self::$config->storage->{$key} = $value->$key;
		}

		// check if local.json file is encrypted
		EncryptStorageConfig::encryptStorageConfig();

		// load local storage configuration
		$objLocalConfig = Json::loadJsonFile(self::OPUS_LOCAL_CONFIG);

		foreach ($objLocalConfig->storage as $value) {
			$key = key((array) $value);
			self::$config->storage->{$key} = (object) array_merge_recursive(
				(array) self::$config->storage->{$key},
				(array) $value->$key
			);

			// validate storage configuration
			$this->validateStorageConfig(self::$config->storage->{$key});
		}

		// default storage configuration
		self::$config->storage->default = array_key_first((array) self::$config->storage);
	}

	/**
	 * Loads navbar configurations from global.config
	 *
	 * @param object $navbar
	 * @return void
	 * @throws Exception if there are no files or no access to them
	 */
	private function loadNavbarConfig(object $navbar): void
	{
		// Initialize navbar object
		self::$config->navbar = $navbar
			?: throw new Exception('Requaired parameter $navbar not found');

		// validate navbar configuration
		$this->validateNavbarConfig(self::$config->navbar);
	}

	/**
	 * Loads languages configurations from global.config
	 *
	 * @param array $languages
	 * @return void
	 * @throws Exception if there are no files or no access to them
	 */
	private function loadLanguagesConfig(array $languages): void
	{
		self::$config->langs = $languages
			?: throw new Exception('Languages configuration array cannot be empty');

		// validate languages configuration
		$this->validateLanguagesConfig(self::$config->langs);
	}

	/**
	 * Loads and validates the role configuration
	 *
	 * @param string|null $role The role value from configuration (prod|dev), defaults to 'prod'
	 * @return void
	 * @throws Exception If role value is invalid
	 */
	private function loadRoleConfig(?string $role = null): void
	{
		self::$config->role = match ($role ?? 'prod') {
			'prod', 'dev' => $role ?? 'prod',
			default => throw new Exception("Invalid role: '{$role}'. Must be 'prod' or 'dev'")
		};
	}

	/**
	 * Loads and validates the shortcut icon configuration
	 *
	 * @param string|null $icon The icon file path relative to public directory, defaults to ''vendor/opus/opus.svg'
	 * @return void
	 * @throws Exception If icon file doesn't exist or has unsupported format
	 */
	private function loadIconConfig(?string $icon = null): void
	{
		$icon ??= 'vendor/opus/opus.svg';
		$fullPath = 'public/' . $icon;

		if (!file_exists($fullPath)) {
			throw new Exception("Icon file not found: '{$fullPath}'");
		}

		$extension = strtolower(pathinfo($icon, PATHINFO_EXTENSION));

		if (!in_array($extension, self::ALLOWED_ICON_EXTENSIONS)) {
			throw new Exception("Unsupported icon format: '{$extension}'. Allowed: " . implode(', ', self::ALLOWED_ICON_EXTENSIONS));
		}

		self::$config->icon = $icon;
	}

	/**
	 * Loads and validates the title configuration
	 *
	 * @param string|null $title The title value from configuration, defaults to 'Opus PHP Framework'
	 * @return void
	 */
	private function loadTitleConfig(?string $title = null): void
	{
		self::$config->title = $title ?: 'Opus PHP Framework';
	}

	/**
	 * Loads and validates vendor library configurations from global.config
	 *
	 * @param array $vendor Array of vendor library paths (optional)
	 * @return void
	 * @throws Exception If vendor paths are invalid
	 */
	private function loadVendorConfig(array $vendor = []): void
	{

		if (empty($vendor)) {
			self::$config->vendor = [];
			return;
		}

		// validate vendor configuration
		$this->validateVendor($vendor);
		self::$config->vendor = $vendor;
	}

	/**
	 * Loads email configurations from global.config
	 *
	 * @return void
	 * @throws Exception if there are no files or no access to them
	 */
	private function loadEmailConfig(string $email): void
	{
		self::$config->email = filter_var($email, FILTER_VALIDATE_EMAIL, FILTER_NULL_ON_FAILURE)
			?: throw new Exception(
				sprintf("Invalid email: '%s'", $email)
			);
	}

	/**
	 * Loads trusted hosts configurations from global.config
	 *
	 * @return void
	 * @throws Exception if there are no files or no access to them
	 */
	private function loadTrustedHostsConfig(array $trustedHosts): void
	{
		self::$config->trustedHosts = $trustedHosts
			?: throw new Exception('Trusted hosts configuration array cannot be empty');

		// validate trusted hosts configuration
		$this->validateTrustedHostsConfig(self::$config->trustedHosts);
	}

	/**
	 * Loads auth configuration from global.config and local.config
	 * Defaults to local driver when auth section is not defined
	 *
	 * @param ?object $auth Auth config from global.json (null = local driver)
	 * @return void
	 * @throws Exception if auth configuration is invalid
	 */
	private function loadAuthConfig(?object $auth = null): void
	{
		if ($auth === null || ($auth->driver ?? 'local') === 'local') {
			self::$config->auth = (object) ['driver' => 'local'];
			return;
		}

		self::$config->auth = $auth;

		// load bind_password from local.json
		$objLocalConfig = Json::loadJsonFile(self::OPUS_LOCAL_CONFIG);

		if (isset($objLocalConfig->auth->bind_password)) {
			self::$config->auth->bind_password = $objLocalConfig->auth->bind_password;
		}

		// encrypt bind_password if not already encrypted
		EncryptStorageConfig::encryptAuthConfig();

		// reload after encryption
		$objLocalConfig = Json::loadJsonFile(self::OPUS_LOCAL_CONFIG);

		if (isset($objLocalConfig->auth->bind_password)) {
			self::$config->auth->bind_password = $objLocalConfig->auth->bind_password;
		}

		// validate auth configuration
		$this->validateAuthConfig(self::$config->auth);
	}
}
