<?php

/**
 * @Project: opus-skeleton
 * @Version: v1.0.0
 * @Author: Tomasz Ułazowski
 * @Date:   2026-08-29 20:41:57
 * @Last Modified by:   Tomasz Ułazowski
 * @Last Modified time: 2026-08-29 21:17:48
 **/

namespace Opus\apps\setup\src;

use Throwable;
use Opus\controller\cli\CliColor;
use Opus\controller\lang\Lang;
use Opus\storage\db\Db;

class SetupCheck
{
	public const EXPECTED_DIRS = [
		'apps'                => ['perms' => '0755', 'group' => null],
		'config'              => ['perms' => '0775', 'group' => 'www-data'],
		'public'              => ['perms' => '0775', 'group' => 'www-data'],
		'public/vendor'       => ['perms' => '0775', 'group' => null],
		'public/vendor/opus'  => ['perms' => '0775', 'group' => 'www-data'],
		'public/img'          => ['perms' => '0755', 'group' => null],
		'public/var'          => ['perms' => '0775', 'group' => 'www-data'],
		'sessions'            => ['perms' => '0775', 'group' => 'www-data'],
	];

	public const EXPECTED_FILES = [
		'config/local.json'  => ['perms' => '0660', 'group' => 'www-data'],
		'config/secret.key'  => ['perms' => '0660', 'group' => 'www-data'],
	];

	private array $report = [];

	/**
	 * Runs full system check and returns CLI output
	 *
	 * @return string
	 */
	public static function doCheck(): string
	{
		$check = new self();
		$lang = Lang::getInstance();

		$output = CliColor::write(PHP_EOL . '  === ' . $lang->get('setup.check.header') . ' ===' . PHP_EOL, CliColor::COLOR_LIGHT_CYAN);

		$check->report['timestamp'] = date('Y-m-d H:i:s');

		$output .= $check->checkDirectories($lang);
		$output .= $check->checkFiles($lang);
		$output .= $check->checkDatabase($lang);

		$status = $check->resolveStatus();
		$check->report['status'] = $status;

		$output .= PHP_EOL . '  ' . match ($status) {
			'OK'   => CliColor::write('[OK] ' . $lang->get('setup.check.status.ok'), CliColor::COLOR_LIGHT_GREEN),
			'FAIL' => CliColor::write('[FAIL] ' . $lang->get('setup.check.status.fail'), CliColor::COLOR_LIGHT_RED),
		};

		$check->saveReport();

		return $output . PHP_EOL;
	}

	/**
	 * Checks directories existence, permissions and group ownership
	 *
	 * @param Lang $lang
	 * @return string CLI output
	 */
	private function checkDirectories(Lang $lang): string
	{
		$output = '  ' . CliColor::write($lang->get('setup.check.directories'), CliColor::COLOR_YELLOW);

		foreach (self::EXPECTED_DIRS as $dir => $expected) {
			$result = $this->checkPath($dir, $expected, true);
			$this->report['directories'][$dir] = $result;
			$output .= $this->formatResult($dir, $result);
		}

		return $output;
	}

	/**
	 * Checks files existence, permissions and group ownership
	 *
	 * @param Lang $lang
	 * @return string CLI output
	 */
	private function checkFiles(Lang $lang): string
	{
		$output = PHP_EOL . '  ' . CliColor::write($lang->get('setup.check.files'), CliColor::COLOR_YELLOW);

		foreach (self::EXPECTED_FILES as $file => $expected) {
			$result = $this->checkPath($file, $expected, false);
			$this->report['files'][$file] = $result;
			$output .= $this->formatResult($file, $result);
		}

		return $output;
	}

	/**
	 * Checks database connection, roles, schemas and application users
	 *
	 * @param Lang $lang
	 * @return string CLI output
	 */
	private function checkDatabase(Lang $lang): string
	{
		$output = PHP_EOL . '  ' . CliColor::write($lang->get('setup.check.database'), CliColor::COLOR_YELLOW);

		// Connection test
		try {
			Db::dbConnectTest();
			$this->report['database']['connection'] = true;
			$output .= '    ' . CliColor::write('[OK]', CliColor::COLOR_LIGHT_GREEN, null, false)
				. '   connection' . PHP_EOL;
		} catch (Throwable) {
			$this->report['database']['connection'] = false;
			$output .= '    ' . CliColor::write('[FAIL]', CliColor::COLOR_LIGHT_RED, null, false)
				. ' connection' . PHP_EOL;
			return $output;
		}

		// Roles
		$roles = ['opus_admin', 'opus_user'];
		foreach ($roles as $role) {
			try {
				$result = Db::dbArrayResult("SELECT 1 FROM pg_roles WHERE rolname = '{$role}'");
				$exists = !empty($result);
			} catch (Throwable) {
				$exists = false;
			}
			$this->report['database']['roles'][$role] = $exists;
			$output .= $this->formatBool("role: {$role}", $exists);
		}

		// Schemas
		$schemas = ['groups', 'logs', 'users', 'demo'];
		foreach ($schemas as $schema) {
			try {
				$result = Db::dbArrayResult("SELECT 1 FROM information_schema.schemata WHERE schema_name = '{$schema}'");
				$exists = !empty($result);
			} catch (Throwable) {
				$exists = false;
			}
			$this->report['database']['schemas'][$schema] = $exists;
			$output .= $this->formatBool("schema: {$schema}", $exists);
		}

		// Application users
		$appUsers = ['root', 'api'];
		foreach ($appUsers as $user) {
			try {
				$result = Db::dbArrayResult("SELECT 1 FROM users.users WHERE login = '{$user}'");
				$exists = !empty($result);
			} catch (Throwable) {
				$exists = false;
			}
			$this->report['database']['app_users'][$user] = $exists;
			$output .= $this->formatBool("user: {$user}", $exists);
		}

		return $output;
	}

	/**
	 * Formats a boolean check result as CLI line
	 *
	 * @param string $label
	 * @param bool $ok
	 * @return string
	 */
	private function formatBool(string $label, bool $ok): string
	{
		return $ok
			? '    ' . CliColor::write('[OK]', CliColor::COLOR_LIGHT_GREEN, null, false) . "   {$label}" . PHP_EOL
			: '    ' . CliColor::write('[FAIL]', CliColor::COLOR_LIGHT_RED, null, false) . " {$label}" . PHP_EOL;
	}

	/**
	 * Checks a single path for existence, permissions and group
	 *
	 * @param string $path Relative path from project root
	 * @param array $expected Expected permissions and group
	 * @param bool $isDir Whether the path is a directory
	 * @return array Check result
	 */
	private function checkPath(string $path, array $expected, bool $isDir): array
	{
		$exists = $isDir ? is_dir($path) : file_exists($path);

		if (!$exists) {
			return ['exists' => false, 'permissions' => null, 'group' => null, 'expected' => $expected['perms']];
		}

		$currentPerms = substr(sprintf('%o', fileperms($path)), -4);
		$permsOk = $currentPerms === $expected['perms'];

		$groupOk = true;
		$currentGroup = null;

		if ($expected['group'] !== null) {
			$gid = filegroup($path);
			$groupInfo = function_exists('posix_getgrgid') ? posix_getgrgid($gid) : null;
			$currentGroup = $groupInfo['name'] ?? (string) $gid;
			$groupOk = $currentGroup === $expected['group'];
		}

		return [
			'exists'      => true,
			'permissions' => $permsOk ? 'OK' : 'FAIL',
			'current'     => $currentPerms,
			'expected'    => $expected['perms'],
			'group'       => $groupOk ? 'OK' : 'FAIL',
			'currentGroup' => $currentGroup,
			'expectedGroup' => $expected['group'],
		];
	}

	/**
	 * Formats a single check result as CLI line
	 *
	 * @param string $path
	 * @param array $result
	 * @return string
	 */
	private function formatResult(string $path, array $result): string
	{
		if (!$result['exists']) {
			return '    ' . CliColor::write('[FAIL]', CliColor::COLOR_LIGHT_RED, null, false)
				. " {$path} — missing" . PHP_EOL;
		}

		$issues = [];

		if ($result['permissions'] === 'FAIL') {
			$issues[] = "perms {$result['current']} (expected {$result['expected']})";
		}

		if ($result['group'] === 'FAIL') {
			$issues[] = "group {$result['currentGroup']} (expected {$result['expectedGroup']})";
		}

		if (empty($issues)) {
			return '    ' . CliColor::write('[OK]', CliColor::COLOR_LIGHT_GREEN, null, false)
				. "   {$path}" . PHP_EOL;
		}

		return '    ' . CliColor::write('[FAIL]', CliColor::COLOR_LIGHT_RED, null, false)
			. " {$path} — " . implode(', ', $issues) . PHP_EOL;
	}

	/**
	 * Resolves overall status from report
	 *
	 * @return string OK or FAIL
	 */
	private function resolveStatus(): string
	{
		foreach (['directories', 'files'] as $section) {
			foreach ($this->report[$section] ?? [] as $result) {
				if (!$result['exists'] || $result['permissions'] === 'FAIL' || $result['group'] === 'FAIL') {
					return 'FAIL';
				}
			}
		}

		$db = $this->report['database'] ?? [];

		if (!($db['connection'] ?? false)) {
			return 'FAIL';
		}

		foreach (['roles', 'schemas', 'app_users'] as $key) {
			if (in_array(false, $db[$key] ?? [], true)) {
				return 'FAIL';
			}
		}

		return 'OK';
	}

	/**
	 * Saves report to config/setup_report.json
	 *
	 * @return void
	 */
	private function saveReport(): void
	{
		file_put_contents(
			'config/setup_report.json',
			json_encode($this->report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
		);
	}
}
