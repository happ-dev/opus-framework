<?php

/**
 * @Project: Opus
 * @Version: 1.0
 * @Author: Tomasz Ułazowski
 * @Date:   2026-02-07 13:27:13
 * @Last Modified by:   Tomasz Ułazowski
 * @Last Modified time: 2026-02-07 13:38:04
 **/

namespace Opus\storage\db;

use PDO;
use PDOException;
use Opus\config\Config;
use Opus\config\EncryptStorageConfig;
use Opus\storage\exception\StorageException;

class Postgre extends AbstractDb
{
	/**
	 * Initializes PostgreSQL database connection parameters
	 *
	 * @param string $conf Configuration name from storage config
	 * @param string $storageException Exception path for error handling
	 */
	public function __construct($conf, string $storageException)
	{
		$this->host = EncryptStorageConfig::decrypt(Config::getConfig('storage')->{$conf}->host);
		$this->port = EncryptStorageConfig::decrypt(Config::getConfig('storage')->{$conf}->port);
		$this->user = EncryptStorageConfig::decrypt(Config::getConfig('storage')->{$conf}->user);
		$this->pass = EncryptStorageConfig::decrypt(Config::getConfig('storage')->{$conf}->pass);
		$this->name = Config::getConfig('storage')->{$conf}->name;
		$this->encoding = isset(Config::getConfig('storage')->{$conf}->encoding)
			? '\'--client_encoding=' . Config::getConfig('storage')->{$conf}->encoding . '\''
			: '\'--client_encoding=UTF8\'';
		$this->storageException = $storageException;
	}

	protected function dbConnect(bool $test = false): bool
	{
		$connectString = 'pgsql:host=' . $this->host
			. ';port=' . $this->port
			. ';dbname=' . $this->name
			. ';user=' . $this->user
			. ';password=' . $this->pass
			. ';options=' . $this->encoding;

		try {
			$this->handle = new PDO($connectString);
			$this->handle->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			return true;
		} catch (PDOException $event) {
			return match ($test) {
				true => false,
				false => throw new StorageException(
					'storage\db\dbConnect',
					[
						'message' => $event->getMessage(),
						'details' => (isset($this->handle))
							? print_r($this->handle->errorInfo(), true)
							: null
					],
					$this->storageException
				)
			};
		}
	}

	public function dbGetTableDetails(
		string $scheme,
		string $table,
		?array $columns,
		?string $conf = null,
		string $storageException
	): array {
		$attname = (is_array($columns) && !empty($columns) && !is_null($columns))
			? ' AND a.attname IN (\'' . str_replace(' , ', ' ', implode('\', \'', $columns)) . '\') '
			: ' ';

		$query = 'SELECT a.attname, a.attnum, pg_catalog.format_type(a.atttypid, a.atttypmod)
		AS type, a.atttypmod, a.attnotnull, a.atthasdef, pg_catalog.pg_get_expr(adef.adbin, adef.adrelid, true)
		AS adsrc, a.attstattarget, a.attstorage, t.typstorage, (
			SELECT 1 FROM pg_catalog.pg_depend pd, pg_catalog.pg_class pc
			WHERE pd.objid = pc.oid AND pd.classid = pc.tableoid AND pd.refclassid = pc.tableoid
			AND pd.refobjid = a.attrelid AND pd.refobjsubid=a.attnum AND pd.deptype = \'i\' AND pc.relkind = \'S\')
			IS NOT NULL AS attisserial, pg_catalog.col_description(a.attrelid, a.attnum)
			AS comment FROM pg_catalog.pg_attribute a
				LEFT JOIN pg_catalog.pg_attrdef adef ON a.attrelid = adef.adrelid AND a.attnum = adef.adnum
				LEFT JOIN pg_catalog.pg_type t ON a.atttypid = t.oid
			WHERE a.attrelid = (SELECT oid FROM pg_catalog.pg_class WHERE relname = \'' . $table . '\'
				AND relnamespace = (SELECT oid FROM pg_catalog.pg_namespace WHERE nspname = \'' . $scheme . '\'))
				AND a.attnum > 0' . $attname . 'AND NOT a.attisdropped	ORDER BY a.attnum';

		return Db::dbArrayResult($query, $conf, $storageException);
	}
}
