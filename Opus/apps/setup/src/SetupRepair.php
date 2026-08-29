<?php

/**
 * @Project: opus-skeleton
 * @Version: v1.0.0
 * @Author: Tomasz Ułazowski
 * @Date:   2026-08-29 20:42:38
 * @Last Modified by:   Tomasz Ułazowski
 * @Last Modified time: 2026-08-29 21:40:44
 **/

namespace Opus\apps\setup\src;

use stdClass;
use Throwable;
use Opus\controller\cli\CliColor;
use Opus\controller\lang\Lang;
use Opus\storage\json\Json;

class SetupRepair
{
	private const REPORT_FILE = 'config/setup_report.json';

	/**
	 * Runs filesystem repair based on setup_report.json
	 *
	 * @return string CLI output
	 */
	public static function doRepair(): string
	{
		$repair = new self();
		$lang = Lang::getInstance();

		$output = CliColor::write(PHP_EOL . '  === ' . $lang->get('setup.repair.header') . ' ===' . PHP_EOL, CliColor::COLOR_LIGHT_CYAN);

		// Load or generate report
		if (!file_exists(self::REPORT_FILE)) {
			$output .= '  ' . CliColor::write($lang->get('setup.repair.running_check'), CliColor::COLOR_YELLOW);
			$output .= SetupCheck::doCheck();
		}

		try {
			$report = Json::loadJsonFile(self::REPORT_FILE);
		} catch (Throwable) {
			return $output . '  ' . CliColor::write('[ERROR] ' . $lang->get('setup.repair.report_error'), CliColor::COLOR_LIGHT_RED) . PHP_EOL;
		}

		// Check sudo
		$isSudo = function_exists('posix_getuid') && posix_getuid() === 0;

		if (!$isSudo) {
			$output .= $repair->listIssues($report, $lang);
			$output .= PHP_EOL . '  ' . CliColor::write($lang->get('setup.repair.need_sudo'), CliColor::COLOR_YELLOW) . PHP_EOL;
			return $output;
		}

		$output .= $repair->repairDirectories($report, $lang);
		$output .= $repair->repairFiles($report, $lang);
		$output .= $repair->reportDatabase($report, $lang);

		return $output;
	}

	/**
	 * Lists issues without fixing (no sudo)
	 *
	 * @param object $report
	 * @param Lang $lang
	 * @return string
	 */
	private function listIssues(object $report, Lang $lang): string
	{
		$output = '';
		$hasIssues = false;

		foreach ($report->directories ?? new stdClass() as $dir => $result) {
			$issues = $this->getPathIssues($result);
			if ($issues) {
				$output .= '    ' . CliColor::write('[FAIL]', CliColor::COLOR_LIGHT_RED, null, false) . " {$dir} — {$issues}" . PHP_EOL;
				$hasIssues = true;
			}
		}

		foreach ($report->files ?? new stdClass() as $file => $result) {
			$issues = $this->getPathIssues($result);
			if ($issues) {
				$output .= '    ' . CliColor::write('[FAIL]', CliColor::COLOR_LIGHT_RED, null, false) . " {$file} — {$issues}" . PHP_EOL;
				$hasIssues = true;
			}
		}

		if (!$hasIssues) {
			$output .= '  ' . CliColor::write('[OK] ' . $lang->get('setup.repair.nothing'), CliColor::COLOR_LIGHT_GREEN) . PHP_EOL;
		}

		return $output . PHP_EOL;
	}

	/**
	 * Returns issue description for a path result, or null if OK
	 *
	 * @param object $result
	 * @return string|null
	 */
	private function getPathIssues(object $result): ?string
	{
		if (!$result->exists) {
			return 'missing';
		}

		$issues = [];

		if (($result->permissions ?? 'OK') === 'FAIL') {
			$issues[] = "perms {$result->current} (expected {$result->expected})";
		}

		if (($result->group ?? 'OK') === 'FAIL') {
			$issues[] = "group {$result->currentGroup} (expected {$result->expectedGroup})";
		}

		return empty($issues) ? null : implode(', ', $issues);
	}

	/**
	 * Repairs directories — creates missing, fixes permissions/group
	 *
	 * @param object $report
	 * @param Lang $lang
	 * @return string
	 */
	private function repairDirectories(object $report, Lang $lang): string
	{
		$output = '  ' . CliColor::write($lang->get('setup.check.directories'), CliColor::COLOR_YELLOW);
		$expected = SetupCheck::EXPECTED_DIRS;

		foreach ($report->directories ?? new \stdClass() as $dir => $result) {
			if (!$result->exists) {
				mkdir($dir, octdec($expected[$dir]['perms']), true);
				if ($expected[$dir]['group']) {
					chgrp($dir, $expected[$dir]['group']);
				}
				$output .= '    ' . CliColor::write('[FIXED]', CliColor::COLOR_LIGHT_GREEN, null, false) . " {$dir} — created" . PHP_EOL;
				continue;
			}

			$fixed = [];

			if (($result->permissions ?? 'OK') === 'FAIL') {
				chmod($dir, octdec($expected[$dir]['perms']));
				$fixed[] = 'perms';
			}

			if (($result->group ?? 'OK') === 'FAIL' && $expected[$dir]['group']) {
				chgrp($dir, $expected[$dir]['group']);
				$fixed[] = 'group';
			}

			if (!empty($fixed)) {
				$output .= '    ' . CliColor::write('[FIXED]', CliColor::COLOR_LIGHT_GREEN, null, false) . " {$dir} — " . implode(', ', $fixed) . PHP_EOL;
			} else {
				$output .= '    ' . CliColor::write('[OK]', CliColor::COLOR_LIGHT_GREEN, null, false) . "   {$dir}" . PHP_EOL;
			}
		}

		return $output;
	}

	/**
	 * Repairs file permissions/group. Cannot create missing local.json or secret.key
	 *
	 * @param object $report
	 * @param Lang $lang
	 * @return string
	 */
	private function repairFiles(object $report, Lang $lang): string
	{
		$output = PHP_EOL . '  ' . CliColor::write($lang->get('setup.check.files'), CliColor::COLOR_YELLOW);
		$expected = SetupCheck::EXPECTED_FILES;

		foreach ($report->files ?? new \stdClass() as $file => $result) {
			if (!$result->exists) {
				$msg = match ($file) {
					'config/local.json' => $lang->get('setup.repair.run_install'),
					'config/secret.key' => $lang->get('setup.repair.key_auto'),
					default => 'missing'
				};
				$output .= '    ' . CliColor::write('[SKIP]', CliColor::COLOR_YELLOW, null, false) . " {$file} — {$msg}" . PHP_EOL;
				continue;
			}

			$fixed = [];

			if (($result->permissions ?? 'OK') === 'FAIL') {
				chmod($file, octdec($expected[$file]['perms']));
				$fixed[] = 'perms';
			}

			if (($result->group ?? 'OK') === 'FAIL' && $expected[$file]['group']) {
				chgrp($file, $expected[$file]['group']);
				$fixed[] = 'group';
			}

			if (!empty($fixed)) {
				$output .= '    ' . CliColor::write('[FIXED]', CliColor::COLOR_LIGHT_GREEN, null, false) . " {$file} — " . implode(', ', $fixed) . PHP_EOL;
			} else {
				$output .= '    ' . CliColor::write('[OK]', CliColor::COLOR_LIGHT_GREEN, null, false) . "   {$file}" . PHP_EOL;
			}
		}

		return $output;
	}

	/**
	 * Reports database issues — cannot fix, only informs
	 *
	 * @param object $report
	 * @param Lang $lang
	 * @return string
	 */
	private function reportDatabase(object $report, Lang $lang): string
	{
		$db = $report->database ?? null;

		if (!$db || ($db->connection ?? false) === false) {
			return PHP_EOL . '  ' . CliColor::write('[FAIL] ' . $lang->get('setup.repair.db_issues'), CliColor::COLOR_LIGHT_RED) . PHP_EOL;
		}

		$hasDbIssues = false;

		foreach (['roles', 'schemas', 'app_users'] as $key) {
			foreach ($db->$key ?? new \stdClass() as $name => $ok) {
				if (!$ok) {
					$hasDbIssues = true;
				}
			}
		}

		if ($hasDbIssues) {
			return PHP_EOL . '  ' . CliColor::write('[FAIL] ' . $lang->get('setup.repair.db_issues'), CliColor::COLOR_LIGHT_RED) . PHP_EOL;
		}

		return '';
	}
}
