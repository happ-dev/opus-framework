<?php

/**
 * @Project: opus-skeleton
 * @Version: v1.0.0
 * @Author: Tomasz Ułazowski
 * @Date:   2026-09-21 12:22:32
 * @Last Modified by:   Tomasz Ułazowski
 * @Last Modified time: 2026-09-26 16:33:34
 **/

namespace Opus\apps\setup\src;

use stdClass;
use Opus\config\Config;
use Opus\controller\cli\CliColor;
use Opus\controller\cli\CliTable;
use Opus\controller\exception\ControllerException;
use Opus\controller\lang\Lang;
use Opus\storage\db\Db;
use Opus\storage\exception\StorageException;

/**
 * SQL command handler for setup CLI
 *
 * Manages database schema operations (create/reset) for registered applications.
 * Requires role=dev. Uses temporary DB config with admin credentials.
 */
class SetupSQL
{
	const SETUP_SQL_ARGUMENTS = ['create', 'reset'];

	private object $dbConfig;
	private bool $force = false;
	private object $migrationCredentials;
	private bool $migrationSqlFile = false;
	private array $sqlFiles = [];
	private string $sqlPath;

	/**
	 * Initializes SQL handler, removes command name from arguments,
	 * builds temporary DB config and prompts for admin credentials
	 *
	 * @param array $arguments CLI arguments ['create|reset', '{app-name}']
	 */
	public function __construct(public array $arguments)
	{
		array_shift($this->arguments);
		$this->temporaryDbConfig();
		$this->promptCredentials();
	}

	/**
	 * Entry point — validates arguments, role, app existence,
	 * then dispatches to create() or reset()
	 *
	 * @param array $arguments Raw CLI arguments ['sql', 'create|reset', '{app-name}']
	 * @return string CLI output
	 * @throws ControllerException On invalid arguments, unknown command, missing app, or wrong role
	 */
	public static function doSQL(array $arguments): string
	{
		$argCount = count($arguments);

		match (true) {
			$argCount < 3 || $argCount > 4 => throw new ControllerException(
				'setup\sql\arguments',
				['message' => implode(' ', $arguments)],
				ControllerException::TYPE_CLI_EXCEPTION,
				SETUP_ERROR_FILE
			),
			!in_array($arguments[1], self::SETUP_SQL_ARGUMENTS) => throw new ControllerException(
				'setup\sql\command',
				['message' => $arguments[1]],
				ControllerException::TYPE_CLI_EXCEPTION,
				SETUP_ERROR_FILE
			),
			!in_array($arguments[2], Config::getConfig('apps')) => throw new ControllerException(
				'setup\sql\app',
				['message' => $arguments[2]],
				ControllerException::TYPE_CLI_EXCEPTION,
				SETUP_ERROR_FILE
			),
			Config::getConfig('role') !== 'dev' => throw new ControllerException(
				'setup\sql\role',
				['message' => Config::getConfig('role')],
				ControllerException::TYPE_CLI_EXCEPTION,
				SETUP_ERROR_FILE
			),
			$argCount === 4 && $arguments[3] !== '--force' => throw new ControllerException(
				'setup\sql\arguments',
				['message' => implode(' ', $arguments)],
				ControllerException::TYPE_CLI_EXCEPTION,
				SETUP_ERROR_FILE
			),
			default => null
		};

		$setupSQL = new self($arguments);

		return match ($setupSQL->arguments[0]) {
			'create' => $setupSQL->create(),
			'reset' => $setupSQL->reset(),
		};
	}

	/**
	 * Executes all SQL files for the application
	 *
	 * @return string CLI output
	 */
	public function create(): string
	{
		array_shift($this->arguments);
		$this->force = in_array('--force', $this->arguments);
		$this->arguments = array_values(array_filter($this->arguments, fn($a) => $a !== '--force'));
		$this->sqlPath = 'apps/' . $this->arguments[0] . '/sql/';
		$this->loadSqlFiles();

		if (!$this->force) {
			$this->applyIdempotency();
		}

		if ($this->migrationSqlFile && $this->promptMigration()) {

			foreach ($this->sqlFiles as &$entry) {
				if (str_starts_with($entry['file'], '98-')) {
					$entry['execute'] = true;
				}
			}

			$this->parseMigrationCredentials();
			$this->promptMigrationCredentials();
		}

		echo PHP_EOL;
		CliTable::writeTable($this->buildTable(), CliTable::HEAD_POSITION_HORIZONTAL);

		$this->executeSqlFiles();

		echo PHP_EOL;
		CliTable::writeTable($this->buildTable(true), CliTable::HEAD_POSITION_HORIZONTAL);

		Config::removeTemporaryDbConfig();
		return '';
	}

	/**
	 * Drops application schema with CASCADE after user confirmation.
	 * Verifies schema exists before executing DROP.
	 * Removes sql.log if present. Cleans up temporary DB config.
	 *
	 * @return string CLI output
	 * @throws StorageException On DB connection or query failure
	 */
	public function reset(): string
	{
		array_shift($this->arguments);
		$reset = $this->parseSchemaName($this->arguments[0]);
		$lang = Lang::getInstance();

		echo PHP_EOL
			. CliColor::write('  ⚠ ' . $lang->get('setup.sql.reset.warning'), CliColor::COLOR_YELLOW)
			. CliColor::write('    ' . $lang->get('setup.sql.reset.schema') . ': ' . $reset->schema, CliColor::COLOR_LIGHT_RED)
			. CliColor::write('    ' . $lang->get('setup.sql.reset.cascade'), CliColor::COLOR_LIGHT_RED)
			. PHP_EOL;

		$confirm = readline(
			CliColor::write('  ' . $lang->get('setup.sql.reset.confirm') . ': ', CliColor::COLOR_YELLOW, null, false)
		);

		if ($confirm !== $reset->schema) {
			Config::removeTemporaryDbConfig();
			return PHP_EOL . CliColor::write('  ' . $lang->get('setup.sql.reset.aborted'), CliColor::COLOR_LIGHT_GREEN);
		}

		echo PHP_EOL;

		try {
			$result = Db::dbArrayResult(
				'SELECT 1 FROM information_schema.schemata WHERE schema_name = \'' . $reset->schema . '\'',
				'temporary',
				StorageException::TYPE_CLI_EXCEPTION
			);

			if (empty($result)) {
				Config::removeTemporaryDbConfig();
				return CliColor::write('  ' . $lang->get('setup.sql.reset.not_exists') . ': ' . $reset->schema, CliColor::COLOR_YELLOW);
			}

			Db::dbExec(
				'DROP SCHEMA IF EXISTS "' . $reset->schema . '" CASCADE;',
				'temporary',
				StorageException::TYPE_CLI_EXCEPTION
			);
			echo CliColor::write('  DROP SCHEMA ' . $reset->schema . ' CASCADE   ' . $lang->get('setup.sql.status.ok'), CliColor::COLOR_LIGHT_GREEN);
		} catch (StorageException $e) {
			Config::removeTemporaryDbConfig();
			return CliColor::write('  DROP SCHEMA ' . $reset->schema . ' CASCADE   ' . $lang->get('setup.sql.status.fail'), CliColor::COLOR_LIGHT_RED)
				. CliColor::write('  ' . $e->getMessage(), CliColor::COLOR_LIGHT_RED);
		}

		$logFile = 'public/var/' . $reset->app . '_sql.log';

		if (file_exists($logFile)) {
			unlink($logFile);
			echo CliColor::write('  ' . $lang->get('setup.sql.log.remove') . ' ' . $reset->app . '_sql.log        ' . $lang->get('setup.sql.status.ok'), CliColor::COLOR_LIGHT_GREEN);
		}

		Config::removeTemporaryDbConfig();

		return PHP_EOL . CliColor::write('  ' . $lang->get('setup.sql.reset.success'), CliColor::COLOR_LIGHT_GREEN);
	}

	/**
	 * Parses schema name from 00-schema.sql CREATE SCHEMA statement
	 *
	 * @param string $app Application name
	 * @return object {app: string, schema: string}
	 * @throws ControllerException If file missing or CREATE SCHEMA not found
	 */
	private function parseSchemaName(string $app): object
	{
		$schemaFile = 'apps/' . $app . '/sql/00-schema.sql';

		if (!file_exists($schemaFile)) {
			Config::removeTemporaryDbConfig();
			throw new ControllerException(
				'setup\sql\schema',
				['message' => $schemaFile],
				ControllerException::TYPE_CLI_EXCEPTION,
				SETUP_ERROR_FILE
			);
		}

		$content = file_get_contents($schemaFile);

		if (!preg_match('/CREATE\s+SCHEMA\s+(?:IF\s+NOT\s+EXISTS\s+)?"([^"]+)"/i', $content, $matches)) {
			Config::removeTemporaryDbConfig();
			throw new ControllerException(
				'setup\sql\schema_parse',
				['message' => $schemaFile],
				ControllerException::TYPE_CLI_EXCEPTION,
				SETUP_ERROR_FILE
			);
		}

		return (object) [
			'app' => $app,
			'schema' => $matches[1]
		];
	}

	/**
	 * Builds temporary DB config object from default storage config
	 * Sets type, name, encoding, host, port from default; user/pass as null
	 *
	 * @return void
	 */
	private function temporaryDbConfig(): void
	{
		$default = Config::getConfig('storage')->default;
		$storage = Config::getConfig('storage')->{$default};

		$this->dbConfig = (object) [
			'type' => $storage->type,
			'name' => $storage->name,
			'encoding' => $storage->encoding ?? 'UTF8',
			'host' => $storage->host,
			'port' => $storage->port,
			'user' => null,
			'pass' => null,
		];
	}

	/**
	 * Prompts user for DB admin credentials and database name.
	 * Defaults: user=postgres, dbname from default storage config.
	 * Password input is hidden. Registers config via Config::setTemporaryDbConfig()
	 *
	 * @return void
	 */
	private function promptCredentials(): void
	{
		$lang = Lang::getInstance();

		$this->dbConfig->user = readline(
			CliColor::write('  ' . $lang->get('setup.sql.prompt.user') . ' [postgres]: ', CliColor::COLOR_LIGHT_CYAN, null, false)
		) ?: 'postgres';

		echo CliColor::write('  ' . $lang->get('setup.sql.prompt.password') . ': ', CliColor::COLOR_LIGHT_CYAN, null, false);
		system('stty -echo');
		$this->dbConfig->pass = trim(fgets(STDIN));
		system('stty echo');
		echo PHP_EOL;

		$dbName = readline(
			CliColor::write('  ' . $lang->get('setup.sql.prompt.dbname') . ' [' . $this->dbConfig->name . ']: ', CliColor::COLOR_LIGHT_CYAN, null, false)
		);

		if (!empty($dbName)) {
			$this->dbConfig->name = $dbName;
		}

		Config::setTemporaryDbConfig($this->dbConfig);
	}

	/**
	 * Prompts user whether to execute migration file [y/N]
	 *
	 * @return bool
	 */
	private function promptMigration(): bool
	{
		$lang = Lang::getInstance();

		$answer = readline(
			CliColor::write('  ' . $lang->get('setup.sql.migration.prompt') . ' [y/N]: ', CliColor::COLOR_YELLOW, null, false)
		);

		return strtolower($answer) === 'y';
	}

	/**
	 * Prompts user for migration variable values from $this->migrationCredentials.
	 * Password fields are masked.
	 *
	 * @return void
	 */
	private function promptMigrationCredentials(): void
	{
		foreach ($this->migrationCredentials as $name => $var) {
			if (str_contains($name, 'password')) {
				echo CliColor::write('  ' . $var->label . ': ', CliColor::COLOR_LIGHT_CYAN, null, false);
				system('stty -echo');
				$var->value = trim(fgets(STDIN));
				system('stty echo');
				echo PHP_EOL;
			} elseif (str_contains($name, 'host')) {
				$this->promptHost($var);
			} elseif (str_contains($name, 'user')) {
				$var->value = readline(
					CliColor::write('  ' . $var->label . ' [postgres]: ', CliColor::COLOR_LIGHT_CYAN, null, false)
				) ?: 'postgres';
			} else {
				$var->value = readline(
					CliColor::write('  ' . $var->label . ': ', CliColor::COLOR_LIGHT_CYAN, null, false)
				);
			}
		}
	}

	/**
	 * Prompts for host address, validates IP/hostname and pings.
	 * Repeats until a reachable host is provided.
	 *
	 * @param object $var Variable object with label and value
	 * @return void
	 */
	private function promptHost(object $var): void
	{
		$lang = Lang::getInstance();

		while (true) {
			$host = readline(
				CliColor::write('  ' . $var->label . ': ', CliColor::COLOR_LIGHT_CYAN, null, false)
			);

			if (empty($host) || (!filter_var($host, FILTER_VALIDATE_IP) && !filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME))) {
				echo CliColor::write('  ' . $lang->get('setup.sql.migration.host_invalid'), CliColor::COLOR_LIGHT_RED);
				continue;
			}

			$conn = @fsockopen($host, 5432, $errno, $errstr, 2);

			if (!$conn) {
				echo CliColor::write('  ' . $lang->get('setup.sql.migration.host_unreachable') . ': ' . $host, CliColor::COLOR_LIGHT_RED);
				continue;
			}

			fclose($conn);
			echo CliColor::write('  ' . $lang->get('setup.sql.migration.host_ok') . ': ' . $host, CliColor::COLOR_LIGHT_GREEN);
			$var->value = $host;
			return;
		}
	}

	/**
	 * Parses Variables block from 00-schema.sql header.
	 * Each line: --   {Label}:$variable_name$
	 * Stores parsed variables in $this->migrationCredentials
	 *
	 * @return void
	 */
	private function parseMigrationCredentials(): void
	{
		$content = file_get_contents($this->sqlPath . '00-schema.sql');

		if (!preg_match('/^-- Variables:\s*\n((?:--\s+\{[^}]+\}:\$\w+\$\s*\n)+)/m', $content, $block)) {
			return;
		}

		preg_match_all('/^--\s+\{([^}]+)\}:\$(\w+)\$\s*$/m', $block[1], $matches, PREG_SET_ORDER);
		$this->migrationCredentials = new stdClass();

		foreach ($matches as $match) {
			$name = $match[2];
			$this->migrationCredentials->$name = (object) [
				'label' => $match[1],
				'value' => null
			];
		}
	}

	/**
	 * Scans app sql/ directory for files matching {dd}-{name}.sql pattern
	 *
	 * @return void
	 */
	private function loadSqlFiles(): void
	{
		$entries = scandir($this->sqlPath);

		foreach ($entries as $file) {

			if (preg_match('/^\d{2}-[a-z0-9_-]+\.sql$/', $file)) {

				if (str_starts_with($file, '98-')) {
					$this->migrationSqlFile = true;
				}

				$this->sqlFiles[] = [
					'file' => $file,
					'execute' => !str_starts_with($file, '98-'),
					'result' => null
				];
			}
		}
	}

	/**
	 * Marks already-executed files (from log) as SKIP.
	 * Parses log for [OK] entries and skips matching files.
	 *
	 * @return void
	 */
	private function applyIdempotency(): void
	{
		$logFile = 'public/var/' . $this->arguments[0] . '_sql.log';

		if (!file_exists($logFile)) {
			return;
		}

		$executed = [];
		$lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

		foreach ($lines as $line) {
			if (preg_match('/\[OK\]\s+(.+\.sql)$/', $line, $m)) {
				$executed[$m[1]] = true;
			}
		}

		if (empty($executed)) {
			return;
		}

		$lang = Lang::getInstance();
		$skipped = 0;

		foreach ($this->sqlFiles as &$entry) {
			if (isset($executed[$entry['file']])) {
				$entry['execute'] = false;
				$entry['result'] = 'SKIP';
				$skipped++;
			}
		}

		if ($skipped > 0) {
			echo CliColor::write('  ' . $lang->get('setup.sql.idempotency.skipped') . ': ' . $skipped, CliColor::COLOR_YELLOW);
		}
	}

	/**
	 * Executes SQL files in order. Reads file content, substitutes
	 * migration variables via str_replace, executes via Db::dbExec().
	 * Sets result to OK/SKIP/FAIL for each file.
	 *
	 * @return void
	 */
	private function executeSqlFiles(): void
	{
		$failed = false;
		$logFile = 'public/var/' . $this->arguments[0] . '_sql.log';

		foreach ($this->sqlFiles as &$entry) {
			if (!$entry['execute'] || $failed) {
				$entry['result'] = $entry['result'] ?? ($failed ? 'FAIL' : 'SKIP');
				continue;
			}

			$sql = file_get_contents($this->sqlPath . $entry['file']);

			if (isset($this->migrationCredentials)) {
				foreach ($this->migrationCredentials as $name => $var) {
					$sql = str_replace('$' . $name . '$', $var->value, $sql);
				}
			}

			try {
				Db::dbExec($sql, 'temporary', StorageException::TYPE_CLI_EXCEPTION);
				$entry['result'] = 'OK';
				file_put_contents($logFile, date('Y-m-d H:i:s') . ' [OK] ' . $entry['file'] . PHP_EOL, FILE_APPEND);
			} catch (StorageException $e) {
				$entry['result'] = 'FAIL';
				$failed = true;
				file_put_contents($logFile, date('Y-m-d H:i:s') . ' [FAIL] ' . $entry['file'] . PHP_EOL . $e->getMessage() . PHP_EOL, FILE_APPEND);
			}
		}
	}

	/**
	 * Builds CliTable-compatible array from sqlFiles.
	 * Preview: columns File + Execute, non-executed rows in yellow.
	 * Summary: columns File + Result, colored by result value.
	 *
	 * @param bool $summary false = preview, true = summary after execution
	 * @return array
	 */
	private function buildTable(bool $summary = false): array
	{
		$table = [
			'head' => [
				'text' => ['File', $summary ? 'Result' : 'Execute'],
				'color' => CliColor::COLOR_LIGHT_CYAN
			]
		];

		foreach ($this->sqlFiles as $entry) {
			if ($summary) {
				$row = ['text' => [$entry['file'], $entry['result']]];
				$row['color'] = match ($entry['result']) {
					'OK' => CliColor::COLOR_LIGHT_GREEN,
					'SKIP' => CliColor::COLOR_YELLOW,
					'FAIL' => CliColor::COLOR_LIGHT_RED,
					default => CliColor::COLOR_WHITE
				};
			} else {
				$row = ['text' => [$entry['file'], $entry['execute'] ? 'yes' : 'no']];

				if (!$entry['execute']) {
					$row['color'] = CliColor::COLOR_YELLOW;
				}
			}

			$table[] = $row;
		}

		return $table;
	}
}
