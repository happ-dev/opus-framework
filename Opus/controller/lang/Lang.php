<?php

/**
 * @Project: Opus
 * @Version: 1.0
 * @Author: Tomasz Ułazowski
 * @Date:   2026-02-13 09:45:59
 * @Last Modified by:   Tomasz Ułazowski
 * @Last Modified time: 2026-08-22 21:31:46
 **/

namespace Opus\controller\lang;

use Opus\config\Config;
use Opus\storage\json\Json;

class Lang
{
	private static ?Lang $instance = null;
	private static array $translations = [];

	private function __construct()
	{
		$this->load();
	}

	/**
	 * Prevents cloning of the singleton instance
	 */
	private function __clone() {}

	/**
	 * Returns the singleton instance of Lang class
	 *
	 * @return Lang The singleton instance
	 */
	public static function getInstance(): Lang
	{
		return self::$instance ??= new Lang();
	}

	/**
	 * Retrieves a translation for the given key
	 *
	 * Supports DB comment format: 'English description|lang.key'
	 * If the input contains '|' and the part after '|' matches a valid Lang key pattern,
	 * it attempts translation using the Lang key. Falls back to the English description.
	 *
	 * @param string|null $key The translation key or DB comment string
	 * @return string The translated string, fallback description, or the key itself
	 */
	public function get(?string $key): string
	{
		return match (true) {
			$key && preg_match('/^(.+)\|([a-z][a-z0-9_.]+)$/s', $key, $m) === 1 => self::$translations[$m[2]] ?? $m[1],
			default => self::$translations[$key] ?? $key
		};
	}

	/**
	 * Loads translation files from Opus framework and all configured applications
	 *
	 * Merges translations from main Opus lang file and all app-specific lang files
	 * based on the current session language.
	 *
	 * @return void
	 */
	private function load(): void
	{
		// main Opus lang file
		$opus = __DIR__ . '/' . $_SESSION['lang'] . '_opus.json';
		self::$translations = Json::loadJsonFile($opus, true);

		// Search through all configured applications
		foreach (Config::getConfig()->apps as $app) {
			// Opus app
			$file = __DIR__ . '/../../apps/' . $app . '/lang/' . $_SESSION['lang'] . '_' . $app . '.json';

			if (file_exists($file) === false) {
				// User app
				$file = __DIR__ . '/../../../../apps/' . $app . '/lang/' . $_SESSION['lang'] . '_' . $app . '.json';
			}

			$appTranslations = Json::loadJsonFile($file, true);

			if (is_array($appTranslations)) {
				self::$translations = array_merge(self::$translations, $appTranslations);
			}
		}
	}
}
