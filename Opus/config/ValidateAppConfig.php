<?php

/**
 * @Project: Opus
 * @Version: 1.0
 * @Author: Tomasz Ułazowski
 * @Date:   2026-02-01 09:44:17
 * @Last Modified by:   Tomasz Ułazowski
 * @Last Modified time: 2026-08-26 21:10:24
 **/

namespace Opus\config;

use Exception;

class ValidateAppConfig
{
	private const REQUIRED_SECTIONS = [
		'page' => ['app', 'route', 'view', 'js'],
		'api' => ['app'],
		'cli' => ['app']
	];
	private const APP_TYPES = ['page', 'api', 'cli'];
	private const NAV_TYPES = ['menu', 'submenu'];
	private const APP_TABLE_EVENT_ACCESS = ['add', 'edit', 'delete'];
	private const REGEX_APP_CLASS = ['options' => ['regexp' => '/apps\\\\[a-z]+\\\\src\\\\[a-zA-Z]+$/']];
	private const REGEX_ACCESS = ['options' => ['min_range' => 0, 'max_range' => 9]];
	private const REGEX_NAV_ID = ['options' => ['regexp' => '/^\d{3}(_nav)$|^\d{3}(_usr)$|^\d{3}(_dropdown)$/']];
	private const REGEX_VIEW_INDEX = ['options' => ['regexp' => '/apps\/[a-z]+\/view\/[a-zA-Z0-9\/_-]+\.phtml$/']];
	private const REGEX_VIEW_LAYOUT = ['options' => ['regexp' => '/apps\/[a-z]+\/view\/layout.phtml$/']];
	private const REGEX_VIEW_ERROR = ['options' => ['regexp' => '/apps\/[a-z]+\/view\/error.phtml$/']];
	private const REGEX_VIEW_CSS = ['options' => ['regexp' => '/apps\/[a-z]+\/css\/[a-zA-Z0-9_\/-]+\.css$/']];
	private const REGEX_JS_INDEX = ['options' => ['regexp' => '/apps\/[a-z]+\/js\/[a-zA-Z0-9\/_-]+\.js$/']];
	private const REGEX_JS_LAYOUT = ['options' => ['regexp' => '/apps\/[a-z]+\/js\/layout.js$/']];
	private const REGEX_JS_ERROR = ['options' => ['regexp' => '/apps\/[a-z]+\/js\/error.js$/']];
	private const REGEX_ID_TABLE_EVENT = ['options' => ['regexp' => '/^id__[a-zA-Z0-9]+-event-dt$/']];
	private const REGEX_INJECT_VIEW = ['options' => ['regexp' => '/^apps\/[a-z]+\/view\/inject\/[a-zA-Z0-9\/_-]+\.phtml$/']];
	private const REGEX_PRIMARY_KEY = ['options' => ['regexp' => '/^(id__)+[^_]+[\w\D]+$/']];
	private const REGEX_TABLE_NAME = ['options' => ['regexp' => '/^[a-z_]+\.[a-z_]+$/']];
	private const REGEX_ASYNC_FILE = ['options' => ['regexp' => '/^(vendor\/Opus\/)?apps\/[a-z]+\/src\/[a-zA-Z0-9\/_-]+\.php$/']];
	private const REGEX_ASYNC_CLASS = ['options' => ['regexp' => '/^(Opus\\\\)?apps\\\\[a-z]+\\\\src\\\\([a-z]+\\\\)?[a-zA-Z]+Api$/']];
	private const REGEX_ASYNC_PAGE_VIEW = ['options' => ['regexp' => '/^(vendor\/Opus\/)?apps\/[a-z]+\/src\/[a-zA-Z0-9\/_-]+\.php$/']];
	private const REGEX_ASYNC_PAGE_CLASS = ['options' => ['regexp' => '/^(Opus\\\\)?apps\\\\[a-z]+\\\\src\\\\([a-z]+\\\\)?[a-zA-Z]+$/']];

	protected array $errors = [];

	/**
	 * Validates application configuration against defined rules
	 *
	 * This method performs comprehensive validation of the application configuration:
	 * 1. Creates a validator instance and runs all validation methods
	 * 2. Checks required sections, app settings, routes, navigation, views, JS files
	 * 3. Validates table events, inject events, and async events
	 * 4. Throws an exception with detailed error messages if validation fails
	 *
	 * @param object $config The configuration object to validate
	 * @param string|null $jsonFile Optional path to the JSON file being validated (for error reporting)
	 * @return void
	 * @throws Exception If configuration validation fails with detailed error messages
	 */
	public static function validate(object &$config, ?string $jsonFile): void
	{
		$validator = new self();
		$validator->validateRequiredSections($config)
			->validateAppSection($config)
			->validateRouteSection($config)
			->validateNavSection($config)
			->validateViewSection($config)
			->validateJsSection($config)
			->validateIdTableEventSection($config)
			->validateInjectEventSection($config)
			->validateAsyncPageSection($config)
			->validateTableEventSection($config)
			->validateAsyncEventSection($config)
			->validateVendor($config);

		if (!empty($validator->errors)) {
			throw new Exception(
				"Configuration validation failed:\n" . implode(PHP_EOL, $validator->errors)
					. PHP_EOL
					. match (!is_null($jsonFile)) {
						true => "In file: {$jsonFile}",
						default => ""
					}
			);
		}
	}

	/**
	 * Validates that all required configuration sections are present
	 *
	 * Checks for the presence of mandatory sections (app, route, view, js)
	 * and adds error messages for any missing sections.
	 *
	 * @param object $config The configuration object to validate
	 * @return self Returns this instance for method chaining
	 */
	private function validateRequiredSections(object $config): self
	{
		$type = $config->app->type ?? null;
		$sections = self::REQUIRED_SECTIONS[$type] ?? self::REQUIRED_SECTIONS['page'];

		foreach ($sections as $section) {

			if (!property_exists($config, $section)) {
				$this->errors[] = "Missing required section: {$section}";
			}
		}

		return $this;
	}

	/**
	 * Validates the app section of the configuration
	 *
	 * Checks required fields in the app section:
	 * - type: must be 'page', 'api', or 'cli'
	 * - class: must match the app class path pattern
	 * - access: must be an integer between 0 and 9
	 * - version: must be a non-empty string
	 * - description: must be a string
	 *
	 * @param object $config The configuration object to validate
	 * @return self Returns this instance for method chaining
	 */
	private function validateAppSection(object $config): self
	{
		$required = [
			'type' => fn($v) => in_array($v, self::APP_TYPES),
			'class' => fn($v) => filter_var($v, FILTER_VALIDATE_REGEXP, self::REGEX_APP_CLASS),
			'access' => fn($v) => filter_var($v, FILTER_VALIDATE_INT, self::REGEX_ACCESS),
			'version' => fn($v) => is_string($v) && strlen($v) > 0,
			'description' => fn($v) => is_string($v)
		];

		foreach ($required as $field => $validator) {

			if ($validator($config->app->$field) === false) {
				$this->errors[] = match ($field) {
					'type' => "App type must be one of: " . implode(', ', self::APP_TYPES),
					'class' => "Invalid class path format: {$config->app->$field}",
					'access' => "Access level must be an integer between 0 and 9",
					'version' => "Version must be a non-empty string",
					'description' => "Description must be a string",
					default => "Invalid value for {$field}"
				};
			}
		}

		return $this;
	}

	/**
	 * Validates the route section of the configuration
	 *
	 * Ensures that the route field exists and contains:
	 * - A non-empty array
	 * - All elements are strings
	 * - All strings are non-empty
	 *
	 * @param object $config The configuration object to validate
	 * @return self Returns this instance for method chaining
	 */
	private function validateRouteSection(object $config): self
	{
		if (!in_array('route', self::REQUIRED_SECTIONS[$config->app->type ?? 'page'])) {
			return $this;
		}

		$required = [
			'route' => fn($v) => is_array($v) && !empty($v)
				&& count(array_filter($v, 'is_string')) === count($v)
				&& count(array_filter($v, 'strlen')) === count($v)
		];

		foreach ($required as $field => $validator) {

			if ($validator($config->$field) === false) {
				$this->errors[] = "Route must be an array with at least one non-empty string";
			}
		}

		return $this;
	}

	/**
	 * Validates the navigation section of the configuration
	 *
	 * This method validates the optional nav section and sets default route if needed:
	 * - Sets nav route to last route element if not specified
	 * - type: must be 'menu' or 'submenu'
	 * - disabled: must be a boolean value
	 * - id: must match pattern ###_nav, ###_usr, or ###_dropdown
	 * - name: must be a non-empty string
	 * - icon: must be a non-empty string
	 *
	 * @param object $config The configuration object to validate (passed by reference)
	 * @return self Returns this instance for method chaining
	 */
	private function validateNavSection(object &$config): self
	{
		if (!property_exists($config, 'nav')) {
			return $this;
		}

		// Set default route to last element in route array
		$config->nav->route ??= $config->route[array_key_last($config->route)];

		$required = [
			'type' => fn($v) => in_array($v, self::NAV_TYPES),
			'disabled' => fn($v) => filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== null,
			'id' => fn($v) => filter_var($v, FILTER_VALIDATE_REGEXP, self::REGEX_NAV_ID),
			'name' => fn($v) => is_string($v) && strlen($v) > 0,
			'icon' => fn($v) => is_string($v) && strlen($v) > 0
		];

		foreach ($required as $field => $validator) {

			if (!property_exists($config->nav, $field)) {
				$this->errors[] = "Missing required field in nav section: {$field}";
				continue;
			}

			if ($validator($config->nav->$field) === false) {
				$this->errors[] = match ($field) {
					'type' => "Nav type must be one of: " . implode(', ', self::NAV_TYPES),
					'disabled' => "Disabled must be a boolean value",
					'id' => "Invalid nav id format: {$config->nav->$field}, must match pattern ###_nav",
					'name' => "Name must be a non-empty string",
					'icon' => "Icon must be a non-empty string",
					default => "Invalid value for {$field}"
				};
			}
		}

		return $this;
	}

	/**
	 * Validates the view section of the configuration
	 *
	 * Validates required and optional view file paths:
	 * - index: required, must match pattern apps/name/view/name.phtml
	 * - layout: optional, must match pattern apps/name/view/layout.phtml
	 * - error: optional, must match pattern apps/name/view/error.phtml
	 *
	 * @param object $config The configuration object to validate
	 * @return self Returns this instance for method chaining
	 */
	private function validateViewSection(object $config): self
	{
		if (!in_array('view', self::REQUIRED_SECTIONS[$config->app->type ?? 'page'])) {
			return $this;
		}

		// Required fields with their validation rules
		$required = [
			'index' => fn($v) => filter_var($v, FILTER_VALIDATE_REGEXP, self::REGEX_VIEW_INDEX)
		];

		// Optional fields with their validation rules
		$optional = [
			'layout' => fn($v) => filter_var($v, FILTER_VALIDATE_REGEXP, self::REGEX_VIEW_LAYOUT),
			'error' => fn($v) => filter_var($v, FILTER_VALIDATE_REGEXP, self::REGEX_VIEW_ERROR),
			'css' => fn($v) => filter_var($v, FILTER_VALIDATE_REGEXP, self::REGEX_VIEW_CSS)
		];

		// Validate required fields
		foreach ($required as $field => $validator) {

			if (!property_exists($config->view, $field)) {
				$this->errors[] = "Missing required field in view section: {$field}";
				continue;
			}

			if ($validator($config->view->$field) === false) {
				$this->errors[] = match ($field) {
					'index' => "Invalid index path format: {$config->view->$field}, must match pattern apps/name/view/name.phtml",
					default => "Invalid value for {$field}"
				};
			}
		}

		// Validate optional fields (only if they exist)
		foreach ($optional as $field => $validator) {

			if (property_exists($config->view, $field)) {

				if ($validator($config->view->$field) === false) {
					$this->errors[] = match ($field) {
						'layout' => "Invalid layout path format: {$config->view->$field}, must match pattern apps/name/view/layout.phtml",
						'error' => "Invalid error path format: {$config->view->$field}, must match pattern apps/name/view/error.phtml",
						'css' => "Invalid css path format: {$config->view->$field}, must match pattern apps/name/css/name.css",
						default => "Invalid value for {$field}"
					};
				}
			}
		}

		return $this;
	}

	/**
	 * Validates the JavaScript section of the configuration
	 *
	 * Validates required and optional JavaScript file paths:
	 * - index: required, must match pattern apps/name/js/name.js
	 * - layout: optional, must match pattern apps/name/js/layout.js
	 * - error: optional, must match pattern apps/name/js/error.js
	 *
	 * @param object $config The configuration object to validate
	 * @return self Returns this instance for method chaining
	 */
	private function validateJsSection(object $config): self
	{
		if (!in_array('js', self::REQUIRED_SECTIONS[$config->app->type ?? 'page'])) {
			return $this;
		}

		// Required fields with their validation rules
		$required = [
			'index' => fn($v) => filter_var($v, FILTER_VALIDATE_REGEXP, self::REGEX_JS_INDEX)
		];

		// Optional fields with their validation rules
		$optional = [
			'layout' => fn($v) => filter_var($v, FILTER_VALIDATE_REGEXP, self::REGEX_JS_LAYOUT),
			'error' => fn($v) => filter_var($v, FILTER_VALIDATE_REGEXP, self::REGEX_JS_ERROR)
		];

		// Validate required fields
		foreach ($required as $field => $validator) {

			if (!property_exists($config->js, $field)) {
				$this->errors[] = "Missing required field in js section: {$field}";
				continue;
			}

			if ($validator($config->js->$field) === false) {
				$this->errors[] = match ($field) {
					'index' => "Invalid index path format: {$config->view->$field}, must match pattern apps/name/js/name.js",
					default => "Invalid value for {$field}"
				};
			}
		}

		// Validate optional fields (only if they exist)
		foreach ($optional as $field => $validator) {

			if (property_exists($config->js, $field)) {

				if ($validator($config->js->$field) === false) {
					$this->errors[] = match ($field) {
						'layout' => "Invalid layout path format: {$config->js->$field}, must match pattern apps/name/js/layout.js",
						'error' => "Invalid error path format: {$config->js->$field}, must match pattern apps/name/js/error.js",
						default => "Invalid value for {$field}"
					};
				}
			}
		}

		return $this;
	}

	/**
	 * Validates the idTableEvent section of the configuration
	 *
	 * This optional section validates the DataTable event identifier:
	 * - idTableEvent: must match pattern id__[alphanumeric-]+-event-dt
	 *
	 * @param object $config The configuration object to validate
	 * @return self Returns this instance for method chaining
	 */
	private function validateIdTableEventSection(object $config): self
	{
		if (!property_exists($config, 'idTableEvent')) {
			return $this;
		}

		$required = [
			'idTableEvent' => fn($v) => filter_var($v, FILTER_VALIDATE_REGEXP, self::REGEX_ID_TABLE_EVENT)
		];

		foreach ($required as $field => $validator) {

			if (!property_exists($config, $field)) {
				$this->errors[] = "Missing required field: {$field}";
				continue;
			}

			if ($validator($config->$field) === false) {
				$this->errors[] = "Invalid idTableEvent format: {$config->$field}, must match pattern id__[alphanumeric-]+-event-dt";
			}
		}

		return $this;
	}

	/**
	 * Validates the injectEvent section of the configuration
	 *
	 * This optional section validates HTML injection events:
	 * - Each event must be an object with a 'file' property
	 * - file: must match pattern apps/name/view/inject/name.phtml
	 *
	 * @param object $config The configuration object to validate
	 * @return self Returns this instance for method chaining
	 */
	private function validateInjectEventSection(object $config): self
	{
		if (!property_exists($config, 'injectEvent')) {
			return $this;
		}

		foreach ($config->injectEvent as $key => $value) {

			if (!is_object($value) || !property_exists($value, 'file')) {
				$this->errors[] = "Missing required field 'file' in injectEvent.{$key}";
				continue;
			}

			if (!filter_var($value->file, FILTER_VALIDATE_REGEXP, self::REGEX_INJECT_VIEW)) {
				$this->errors[] = "Invalid inject view file path: {$value->file}, must match pattern apps/name/view/inject/name.phtml";
			}
		}

		return $this;
	}

	/**
	 * Validates the asyncPage section of the configuration
	 *
	 * Validates async page definitions:
	 * - type: must be 'apage'
	 * - access: must be an integer between 0 and 9
	 * - view: must match pattern apps/name/view/path/file.phtml
	 * - class: must match pattern apps\name\src\path\ClassName
	 *
	 * @param object $config The configuration object to validate
	 * @return self Returns this instance for method chaining
	 */
	private function validateAsyncPageSection(object $config): self
	{
		if (!property_exists($config, 'asyncPage')) {
			return $this;
		}

		$required = [
			'type' => fn($v) => $v === 'apage',
			'access' => fn($v) => filter_var($v, FILTER_VALIDATE_INT, self::REGEX_ACCESS),
			'view' => fn($v) => filter_var($v, FILTER_VALIDATE_REGEXP, self::REGEX_ASYNC_PAGE_VIEW),
			'class' => fn($v) => filter_var($v, FILTER_VALIDATE_REGEXP, self::REGEX_ASYNC_PAGE_CLASS)
		];

		foreach ($config->asyncPage as $name => $page) {

			foreach ($required as $field => $validator) {

				if (!property_exists($page, $field)) {
					$this->errors[] = "Missing required field in asyncPage '{$name}': {$field}";
					continue;
				}

				if ($validator($page->$field) === false) {
					$this->errors[] = match ($field) {
						'type' => "AsyncPage '{$name}' type must be 'apage'",
						'access' => "AsyncPage '{$name}' access must be an integer between 0 and 9",
						'view' => "Invalid view path format in asyncPage '{$name}': {$page->$field}",
						'class' => "Invalid class path format in asyncPage '{$name}': {$page->$field}",
						default => "Invalid value for {$field} in asyncPage '{$name}'"
					};
				}
			}
		}

		return $this;
	}

	/**
	 * Validates table access configuration object
	 *
	 * Validates access levels for table operations:
	 * - show: required, must be an integer between 0 and 9
	 * - add, edit, delete: optional, must be integers between 0 and 9 if present
	 *
	 * @param object $access The access configuration object to validate
	 * @return bool Returns true if valid, false otherwise
	 */
	private function validateTableAccess(object $access): bool
	{
		if (!property_exists($access, 'show')) {
			return false;
		}

		// Validate required 'show' field
		if (filter_var($access->show, FILTER_VALIDATE_INT, self::REGEX_ACCESS) === false) {
			return false;
		}

		// Validate optional fields if they exist
		foreach (self::APP_TABLE_EVENT_ACCESS as $field) {

			if (
				property_exists($access, $field) &&
				filter_var($access->$field, FILTER_VALIDATE_INT, self::REGEX_ACCESS) === false
			) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validates the tableEvent section of the configuration
	 *
	 * This optional section validates DataTable event configurations:
	 * - primaryKey: must match pattern id__[prefix]+[suffix]
	 * - table: must match pattern schema.table_name
	 * - columns: must be either boolean or non-empty array
	 * - access: must be an object with valid access levels (validated by validateTableAccess)
	 *
	 * @param object $config The configuration object to validate
	 * @return self Returns this instance for method chaining
	 */
	private function validateTableEventSection(object $config): self
	{
		if (!property_exists($config, 'tableEvent')) {
			return $this;
		}

		$required = [
			'primaryKey' => fn($v) => filter_var($v, FILTER_VALIDATE_REGEXP, self::REGEX_PRIMARY_KEY),
			'table' => fn($v) => filter_var($v, FILTER_VALIDATE_REGEXP, self::REGEX_TABLE_NAME),
			'columns' => fn($v) => is_bool($v) || (is_array($v) && !empty($v)),
			'access' => fn($v) => is_object($v) && $this->validateTableAccess($v)
		];

		foreach ($config->tableEvent as $key => $table) {

			if (!is_object($table)) {
				$this->errors[] = "Invalid tableEvent.{$key} configuration: must be an object";
				continue;
			}

			foreach ($required as $field => $validator) {

				if (!property_exists($table, $field)) {
					$this->errors[] = "Missing required field in tableEvent.{$key}: {$field}";
					continue;
				}

				if ($validator($table->$field) === false) {
					$this->errors[] = match ($field) {
						'primaryKey' => "Invalid primaryKey format in tableEvent.{$key}: {$table->$field}",
						'table' => "Invalid table name format in tableEvent.{$key}: {$table->$field}",
						'columns' => "Columns must be either boolean or non-empty array in tableEvent.{$key}",
						'access' => "Invalid access configuration in tableEvent.{$key}, access level must be an integer between 0 and 9",
						default => "Invalid value for tableEvent.{$key}.{$field}"
					};
				}
			}
		}

		return $this;
	}

	/**
	 * Validates the asyncEvent section of the configuration
	 *
	 * This optional section validates asynchronous API event configurations:
	 * - type: must be 'api'
	 * - access: must be an integer between 0 and 9
	 * - file: must match pattern apps/name/src/path/name.php
	 * - class: must match pattern apps\name\src\path\NameApi
	 *
	 * @param object $config The configuration object to validate
	 * @return self Returns this instance for method chaining
	 */
	private function validateAsyncEventSection(object $config): self
	{
		if (!property_exists($config, 'asyncEvent')) {
			return $this;
		}

		$required = [
			'type' => fn($v) => $v === 'api',
			'access' => fn($v) => filter_var($v, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 9]]),
			'file' => fn($v) => filter_var($v, FILTER_VALIDATE_REGEXP, self::REGEX_ASYNC_FILE),
			'class' => fn($v) => filter_var($v, FILTER_VALIDATE_REGEXP, self::REGEX_ASYNC_CLASS)
		];

		foreach ($config->asyncEvent as $key => $event) {

			if (!is_object($event)) {
				$this->errors[] = "Invalid asyncEvent.{$key} configuration: must be an object";
				continue;
			}

			foreach ($required as $field => $validator) {

				if (!property_exists($event, $field)) {
					$this->errors[] = "Missing required field in asyncEvent.{$key}: {$field}";
					continue;
				}

				if ($validator($event->$field) === false) {
					$this->errors[] = match ($field) {
						'type' => "asyncEvent.{$key}.type must be 'api'",
						'access' => "asyncEvent.{$key}.access must be an integer between 0 and 9",
						'file' => "Invalid file path format in asyncEvent.{$key}: {$event->$field}",
						'class' => "Invalid class name format in asyncEvent.{$key}: {$event->$field}",
						default => "Invalid value for asyncEvent.{$key}.{$field}"
					};
				}
			}
		}

		return $this;
	}

	/**
	 * Validates the vendor section of the configuration
	 *
	 * This optional section validates vendor library file paths:
	 * - Each path must be a string
	 * - Must match pattern directory/filename.(css|js)
	 * - File must exist in public/vendor/ directory
	 *
	 * @param object $config The configuration object to validate (passed by reference)
	 * @return self Returns this instance for method chaining
	 */
	private function validateVendor(object &$config): self
	{
		if (!property_exists($config, 'vendor')) {
			$config->vendor = [];
			return $this;
		}

		foreach ($config->vendor as $path) {
			match (true) {
				!is_string($path) => $this->errors[] = "Vendor path must be a string",
				!preg_match('/^[a-z0-9_-]+\/[a-z0-9_.\/-]+\.(css|js)$/i', $path) => $this->errors[] = "Invalid vendor path format: '{$path}'",
				!file_exists('public/vendor/' . $path) => $this->errors[] = "Vendor file not found: 'public/vendor/{$path}'",
				default => true
			};
		}

		return $this;
	}
}
