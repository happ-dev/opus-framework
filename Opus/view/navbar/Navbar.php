<?php

/**
 * @Project: Opus
 * @Version: 1.0
 * @Author: Tomasz Ulazowski
 * @Date:   2026-04-19 17:56:36
 * @Last Modified by:   Tomasz Ułazowski
 * @Last Modified time: 2026-10-05 19:28:18
 **/

namespace Opus\view\navbar;

use Opus\config\Config;
use Opus\controller\request\Request;
use Opus\controller\lang\Lang;

class Navbar
{
	const NAV_REGEX_ICON_SVG = ['options' => ['regexp' => '/^[a-z0-9\-\_]+.svg$/']];
	const NAV_REGEX_ICON_BI = ['options' => ['regexp' => '/^(bi-)+[a-z0-9]+$/']];

	private array $li = [];
	private array $user = [];
	private static ?Navbar $instance = null;

	private function __construct() {}
	private function __clone() {}

	/**
	 * Returns the singleton instance of the Navbar class
	 *
	 * @return self The singleton instance
	 */
	public static function getInstance(): self
	{
		return self::$instance ??= new self();
	}

	/**
	 * Initializes and caches the navigation bar configuration
	 *
	 * Uses session caching to improve performance:
	 * - Loads navigation from session if available
	 * - Otherwise builds navigation configuration and caches it in session
	 *
	 * @return self Returns this instance for method chaining
	 */
	public function appsNavbar(): self
	{
		if (Config::getConfig()->role === 'dev' || !isset($_SESSION['li'])) {
			$this->getAppsNavConfig();
			$_SESSION['li'] = $this->li;
		} else {
			$this->li = $_SESSION['li'];
		}

		return $this;
	}

	/**
	 * Builds user navigation dropdown menu for logged-in users
	 *
	 * Creates a user dropdown menu containing:
	 * - User profile with username
	 * - Settings, logs, profile, and logout options
	 * - Merges with any existing app-specific dropdown items
	 *
	 * @return self Returns this instance for method chaining
	 */
	public function userNavbar(): self
	{
		if ($_SESSION['logged'] !== true) {
			return $this;
		}

		$appSubmenu = $this->getAppsDropdownConfig('999_usr');

		$defaultMenuItems = [
			'995_dropdown' => [
				'route' => Request::url('index.php?page=demo'),
				'name' => 'Demo',
				'icon' => 'bi-info-circle'
			],
			'996_dropdown' => [
				'divider' => true,
				'route' => Request::url('index.php?page=settings'),
				'name' => Lang::getInstance()->get('navbar.default.item.settings'),
				'icon' => 'bi-tools'
			],
			'997_dropdown' => [
				'route' => Request::url('index.php?page=logs'),
				'name' => Lang::getInstance()->get('navbar.default.item.logs'),
				'icon' => 'bi-card-text'
			],
			'998_dropdown' => [
				'divider' => true,
				'route' => Request::url('index.php?page=profile'),
				'name' => Lang::getInstance()->get('navbar.default.item.profile'),
				'icon' => 'bi-person-badge'
			],
			'999_dropdown' => [
				'divider' => true,
				'route' => Request::url('index.php?page=logout'),
				'name' => Lang::getInstance()->get('navbar.default.item.logout'),
				'icon' => 'bi-box-arrow-right'
			]
		];

		$this->user['999_usr'] = [
			'route' => '#',
			'name' => $_SESSION['login'],
			'icon' => 'bi-person',
			'submenu' => match ($appSubmenu) {
				false => $defaultMenuItems,
				default => array_merge_recursive($appSubmenu, $defaultMenuItems)
			}
		];

		return $this;
	}

	/**
	 * Retrieves dropdown configuration for navigation submenu items
	 *
	 * Filters applications based on:
	 * - Type must be 'page'
	 * - User access level must be sufficient
	 * - Navigation type must be 'smenu'
	 * - Navigation ID must match the provided ID
	 *
	 * @param string $id The navigation ID to filter by
	 * @return array|false Array of dropdown configuration or false if no matches found
	 */
	private function getAppsDropdownConfig(string $id): false|array
	{
		$dropdownConfig = [];

		foreach (Config::getConfig()->apps as $app) {
			$appConfig = Config::getConfig($app);

			// Check all conditions using match for cleaner logic
			$isValidApp = match (true) {
				$appConfig->app->type !== 'page' => false,
				(int) $appConfig->app->access > (int) $_SESSION['level'] => false,
				!isset($appConfig->app->nav) || (bool) $appConfig->app->nav->disabled !== false => false,
				!isset($appConfig->nav) => false,
				$appConfig->nav->type !== 'smenu' => false,
				$appConfig->nav->id !== $id => false,
				default => true
			};

			if ($isValidApp) {
				$dropdownConfig[$appConfig->nav->id] = [
					'route' => Request::url('index.php?page=' . $appConfig->nav->route),
					'name' => $appConfig->nav->name,
					'icon' => $appConfig->nav->icon
				];
			}
		}

		return empty($dropdownConfig) ? false : $dropdownConfig;
	}

	/**
	 * Builds navigation configuration for menu items
	 *
	 * Processes applications to create navigation menu structure:
	 * - Filters valid applications based on type, access level, and navigation settings
	 * - Sorts navigation items by ID
	 * - Builds menu items with routes, names, icons, and submenus
	 *
	 * @return self Returns this instance for method chaining
	 */
	private function getAppsNavConfig(): self
	{
		$validApps = [];

		// First pass: collect valid applications with their configs
		foreach (Config::getConfig('apps') as $app) {
			$appConfig = Config::getConfig($app);

			$isValidApp = match (true) {
				$appConfig->app->type !== 'page' => false,
				(int) $appConfig->app->access > (int) $_SESSION['level'] => false,
				!isset($appConfig->nav) => false,
				filter_var($appConfig->nav->disabled, FILTER_VALIDATE_BOOL) !== false => false,
				$appConfig->nav->type !== 'menu' => false,
				default => true
			};

			if ($isValidApp) {
				$validApps[$appConfig->nav->id] = [
					'app' => $app,
					'config' => $appConfig
				];
			}
		}

		// Sort by navigation ID
		ksort($validApps);

		// Build navigation items
		foreach ($validApps as $navId => $appData) {
			$config = $appData['config'];
			$this->li[$navId] = [
				'route' => Request::url('index.php?page=' . $config->nav->route),
				'name' => $config->nav->name,
				'icon' => $config->nav->icon,
				'submenu' => $this->getAppsDropdownConfig($navId),
				'app' => $appData['app']
			];
		}

		return $this;
	}

	/**
	 * Generates HTML for navigation icons based on icon type
	 *
	 * This method determines whether an icon is a Bootstrap icon class or an SVG file
	 * and returns the appropriate HTML markup. It uses regex validation to distinguish
	 * between the two icon types.
	 *
	 * @param string|null $icon The icon identifier (Bootstrap class or SVG filename)
	 * @param string $alt Alternative text for accessibility (used in SVG alt attribute)
	 * @return string|null HTML markup for the icon or null if no icon provided
	 */
	private function matchIcon(?string $icon, string $alt): ?string
	{

		if (is_null($icon)) {
			return null;
		}

		return filter_var($icon, FILTER_VALIDATE_REGEXP, self::NAV_REGEX_ICON_SVG) === false
			? '<i class="me-1 bi ' . $icon . '"></i>'
			: '<img class="me-1 d-inline-block align-text-top" alt="' . $alt . '-icon" width="19.2" height="26" src="/img/' . $icon . '" >';
	}

	/**
	 * Generates HTML for dropdown submenu items
	 *
	 * This method creates Bootstrap dropdown menu HTML structure for navigation submenus.
	 * It handles menu items with optional dividers and generates proper Bootstrap markup
	 * with icons and links for each menu item.
	 *
	 * @param array|false $submenu Array of submenu items or false if no submenu exists
	 * @param string|null $index Optional aria-labelledby identifier for accessibility
	 * @return string|null Complete HTML for dropdown menu or null if no submenu provided
	 */
	private function isSubMenuHtml(array|false $submenu, ?string $index = null): ?string
	{
		if ($submenu === false) {
			return null;
		}

		$html = '<ul class="dropdown-menu bs-opus-black-3d" data-bs-popper="static" aria-labelledby="' . $index . '">';

		foreach ($submenu as $value) {

			if (isset($value['divider']) && $value['divider'] === true) {
				$html .= '<li><hr class="dropdown-divider"></li>';
			}

			$html .= <<<HTML
			<li>
				<a class="dropdown-item" href="{$value['route']}">
					<i class="me-1 bi {$value['icon']}"></i>{$value['name']}
				</a>
			</li>
			HTML;
		}

		return $html . '</ul>';
	}

	/**
	 * Generates HTML for the main application navigation bar
	 *
	 * This method creates Bootstrap-compatible navigation HTML with:
	 * - Responsive column classes for mobile and desktop layouts
	 * - Dropdown functionality for menu items with submenus
	 * - Icons and proper accessibility attributes
	 * - Bootstrap data attributes for dropdown behavior
	 *
	 * @return string|null Complete HTML for the navigation bar or null if no menu items
	 */
	public function appsNavbarHtml(): ?string
	{
		$html = '<ul class="navbar-nav flex-row flex-wrap bd-navbar-nav">';

		foreach ($this->li as $index => $menu) {
			list($dropdown, $dropdownToggle, $dataBsToggle) = ['', '', ''];

			match ($menu['submenu'] !== false) {
				true  => (function () use (&$dropdown, &$dropdownToggle, &$dataBsToggle) {
					$dropdown = ' dropdown';
					$dropdownToggle = ' dropdown-toggle';
					$dataBsToggle = ' data-bs-toggle="dropdown" aria-expanded="false"';
				})(),

				default => null
			};

			$html .= <<<HTML
				<li class="nav-item col-6 col-xxl-auto{$dropdown}">
					<a class="nav-link nav-link-opus py-2 px-0 px-xxl-2{$dropdownToggle}" href="{$menu['route']}" id="{$index}" role="button"{$dataBsToggle}>
						{$this->matchIcon($menu['icon'],$menu['app'])}{$menu['name']}
					</a>
					{$this->isSubMenuHtml($menu['submenu'],$index)}
				</li>
			HTML;
		}

		return $html . '</ul>';
	}

	/**
	 * Generates HTML for user navigation section
	 *
	 * This method creates the user navigation area which varies based on login status
	 * and configuration settings. It handles three scenarios:
	 * - User not logged in and login form disabled: returns empty string
	 * - User not logged in and login form enabled: returns login link with modal trigger
	 * - User logged in: returns user dropdown menu with profile options
	 *
	 * The method generates Bootstrap-compatible HTML with proper responsive classes
	 * and dropdown functionality for logged-in users.
	 *
	 * @return string|null HTML markup for user navigation or empty string if no navigation needed
	 */
	public function userNavbarHtml(): ?string
	{
		if ($_SESSION['logged'] === false && Config::getConfig('navbar')->login_form === false) {
			return <<<HTML
			<ul class="navbar-nav flex-row flex-wrap ms-xxl-auto">
				{$this->themeNavbarHtml()}
			</ul>
			HTML;
		}

		if ($_SESSION['logged'] === false && Config::getConfig('navbar')->login_form === true) {
			$loginText = Lang::getInstance()->get('html.buttons.login');
			return <<<HTML
			<ul class="navbar-nav flex-row flex-wrap ms-xxl-auto">
				<li class="nav-item col-6 col-xxl-auto">
					<a class="nav-link nav-link-opus pb-1 px-0 m-auto px-xxl-2" href="#" data-bs-toggle="modal" data-bs-target="#id__nav-opus-login">
						<i class="bi bi-person-up me-1"></i>{$loginText}
					</a>
				</li>
				{$this->themeNavbarHtml()}
			</ul>
			HTML;
		}

		$html = '<ul class="navbar-nav flex-row flex-wrap ms-xxl-auto">';

		foreach ($this->user as $index => $menu) {
			$dropdown = match ($menu['submenu']) {
				false => null,
				default => 'dropdown'
			};
			$dropdownToggle = match ($menu['submenu']) {
				false => null,
				default => 'dropdown-toggle'
			};
			$html .= <<<HTML
			<li class="nav-item col-6 col-xxl-auto {$dropdown}">
				<a class="nav-link nav-link-opus pb-1 px-0 m-auto px-xxl-2 {$dropdownToggle}" href="{$menu['route']}" id="{$index}" role="button" data-bs-toggle="dropdown" aria-expanded="false">
					<i class="me-1 bi {$menu['icon']}"></i>{$menu['name']}
				</a>
				{$this->isSubMenuHtml($menu['submenu'],$index)}
			</li>
			HTML;
		}

		$html .= $this->themeNavbarHtml();
		return $html . '</ul>';
	}

	private function themeNavbarHtml(): ?string
	{
		$autoText = Lang::getInstance()->get('navbar.theme.item.auto');
		$lightText = Lang::getInstance()->get('navbar.theme.item.light');
		$darkText = Lang::getInstance()->get('navbar.theme.item.dark');
		return <<<HTML
		<li class="nav-item py-1 py-xxl-0 col-12 col-xxl-auto">
			<div class="vr d-none d-xxl-flex h-100 mx-xxl-2 text-white"></div>
			<hr class="d-xxl-none my-2 text-white-50">
		</li>
		<li class="nav-item col-6 col-xxl-auto dropdown">
			<button class="btn btn-default nav-link nav-link-opus pb-1 px-0 m-auto px-xxl-2 dropdown-toggle"
				type="button"
				data-bs-toggle="dropdown"
				aria-expanded="false"
			>
				<i class="bi bi-display"></i>
			</button>
			<ul class="dropdown-menu dropdown-menu-end bs-opus-black-3d mode-switch">
				<li>
					<button title="{$lightText}"
						class="dropdown-item d-flex align-items-center btn btn-default"
						data-bs-theme-value="light"
						aria-pressed="false"
					>
						<i class="me-1 bi bi-sun"></i>{$lightText}
					</button>
				</li>
				<li>
					<button title="{$darkText}"
						class="dropdown-item d-flex align-items-center btn btn-default"
						data-bs-theme-value="dark"
						aria-pressed="false"
					>
						<i class="me-1 bi bi-moon"></i>{$darkText}
					</button>
				</li>
				<li><hr class="dropdown-divider"></li>
				<li>
					<button title="{$autoText}"
						class="dropdown-item d-flex align-items-center btn btn-default"
						data-bs-theme-value="auto"
						aria-pressed="true"
					>
						<i class="me-1 bi bi-circle-half"></i>{$autoText}
					</button>
				</li>
			</ul>
		</li>
		HTML;
	}
}
