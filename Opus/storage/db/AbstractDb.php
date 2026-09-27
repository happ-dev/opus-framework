<?php

/**
 * @Project: Opus
 * @Version: 1.0
 * @Author: Tomasz Ułazowski
 * @Date:   2026-02-06 15:37:17
 * @Last Modified by:   Tomasz Ułazowski
 * @Last Modified time: 2026-05-24 12:27:24
 **/

namespace Opus\storage\db;

use PDO;
use PDOStatement;
use PDOException;
use Opus\storage\exception\StorageException;
use stdClass;

/**
 * Abstract base class for database operations
 *
 * Provides common database functionality and defines the contract for database-specific implementations.
 * Implements PDO-based operations including queries, prepared statements, transactions, and cursors.
 *
 * @package Opus\storage\db
 */
abstract class AbstractDb implements InterfaceDb
{
	const GIMA_SEARCH_CHAR = array('¬', '¡', '©', '|', '¾', 'Ê', '#', '£', 'Ñ', 'ç', 'µ', '§');
	const GIMA_REPLACE_CHAR = array('Ł', 'Ś', 'Ż', 'Ń', 'Ź', 'Ę', 'Ą', '#', 'Ć', '\\', '\'', '[');

	public object $handle;
	protected string $host;
	protected string $port;
	protected string $name;
	protected string $user;
	protected string $pass;
	protected string $encoding;
	protected string $storageException;

	/**
	 * Function connects to the database and returns the handle
	 *
	 * @param bool $test = false
	 * 		- true - test connection
	 * 		- false - connect to the database
	 * @return bool|PDO
	 * @throws StorageException
	 */
	abstract protected function dbConnect(bool $test = false): bool;

	public function dbConnectTest(): bool
	{
		return $this->dbConnect(true);
	}

	public function dbSetFetchMode(PDOStatement $objPDO, int $mode): bool
	{
		try {
			$result = $objPDO->setFetchMode($mode);
			return $result;
		} catch (PDOException $event) {
			throw new StorageException(
				'storage\db\dbSetFetchMode',
				[
					'message' => $mode,
					'details' => $event->getMessage()
				],
				$this->storageException
			);
		}
	}

	public function dbExec(string $query): int|false
	{
		// Connecting to database
		$this->dbConnect();

		try {
			$result = $this->handle->exec($query);
			return $result;
		} catch (PDOException $event) {
			throw new StorageException(
				'storage\db\dbExec',
				[
					'message' => $query,
					'details' => $event->getMessage()
				],
				$this->storageException
			);
		}
	}

	public function dbQuery(string $query): PDOStatement|false
	{
		// Connecting to database
		$this->dbConnect();

		try {
			$result = $this->handle->query($query);
			return $result;
		} catch (PDOException $event) {
			throw new StorageException(
				'storage\db\dbQuery',
				[
					'message' => $query,
					'details' => $event->getMessage()
				],
				$this->storageException
			);
		}
	}

	/**
	 * Validates and normalizes parameters for prepared statement execution
	 *
	 * @param array $params Parameters array to validate and normalize
	 * @return void
	 * @throws StorageException When validation fails
	 */
	private function validateExecuteParams(array &$params): void
	{
		$required = [
			'prepare' => fn($val) => !empty($val) && preg_match('/^\s*(SELECT|INSERT|UPDATE|DELETE|CREATE|ALTER|DROP|TRUNCATE|CALL)\s+/i', $val)
				?: throw new StorageException(
					'storage\db\dbExecute\validateParams',
					[
						'message' => 'SQL query',
						'details' => $val
					],
					$this->storageException
				)
		];

		$optional = [
			'bindType' => fn($val) => match ($val) {
				'bindParam', 'bindValue' => $val,
				default => throw new StorageException(
					'storage\db\dbExecute\validateParams',
					[
						'message' => 'bindType',
						'details' => $val
					],
					$this->storageException
				)
			},
			'fetchMode' => fn($val) => $val,
			'params' => fn($val) => $val,
			'pdoTypes' => fn($val) => $val
		];

		// Validate required
		foreach ($required as $key => $validator) {
			$validator($params[$key] ?? null);
		}

		// Process optional with defaults
		$params['bindType'] = isset($params['bindType'])
			? $optional['bindType']($params['bindType'])
			: 'bindParam';

		$params['fetchMode'] = isset($params['fetchMode'])
			? $optional['fetchMode']($params['fetchMode'])
			: PDO::FETCH_ASSOC;

		$params['params'] = isset($params['params'])
			? $optional['params']($params['params'])
			: (preg_match_all('/(:\w+)/', $params['prepare'], $matches)
				? array_unique($matches[1])
				: []);

		$params['pdoTypes'] = isset($params['pdoTypes'])
			? $optional['pdoTypes']($params['pdoTypes'])
			: array_fill(0, count($params['params']), PDO::PARAM_STR);

		// Validate parameter values exist
		array_walk(
			$params['params'],
			fn($param) => array_key_exists($param, $params)
				?: throw new StorageException(
					'storage\db\dbExecute\validateParams',
					[
						'message' => 'Missing parameter',
						'details' => $param
					],
					$this->storageException
				)
		);
	}

	public function dbExecute(array $params): array
	{
		$this->validateExecuteParams($params);

		// Connecting to database
		$this->dbConnect();

		try {
			$stmt = $this->handle->prepare($params['prepare']);
			$stmt->setFetchMode($params['fetchMode']);

			foreach ($params['params'] as $index => $binding) {
				$stmt->{$params['bindType']}(
					$binding,
					$params[$binding],
					$params['pdoTypes'][$index]
				);
			}

			$stmt->execute();
			return $stmt->fetchAll();
		} catch (PDOException $event) {
			throw new StorageException(
				'storage\db\dbExecute',
				[
					'message' => $params['prepare'],
					'details' => $event->getMessage()
				],
				$this->storageException
			);
		}
	}

	/**
	 * Determines if each query should be executed as direct query or prepared statement
	 *
	 * @param array $params Array of query parameters
	 * @return array Returns array of execution types ('query' or 'prepare')
	 * @throws StorageException If neither query nor prepare is present
	 */
	private function determineTransactionScenarios(array $params): array
	{
		return array_map(
			fn($paramSet) => match (true) {
				isset($paramSet['query']) => 'query',
				isset($paramSet['prepare']) => 'prepare',
				default => throw new StorageException(
					'storage\db\dbTransactions\determineTransactionScenarios',
					['details' =>  print_r($params, true)],
					$this->storageException
				)
			},
			$params
		);
	}

	/**
	 * Validates the structure of database execution parameter array
	 *
	 * @param array $params Array of database execution parameters
	 * @param array $scenarios Array of execution scenarios ('query' or 'prepare')
	 * @return bool Returns true if valid
	 * @throws StorageException If validation fails
	 */
	private function validateTransactionParams(array &$params, array $scenarios): bool
	{
		$validationRules = [
			'query' => [
				'query' => fn($val) => is_string($val) && !empty($val)
			],
			'prepare' => [
				'prepare' => fn($val) => is_string($val) && !empty($val),
				'params' => fn($val) => is_array($val) && !empty($val)
			]
		];

		foreach ($scenarios as $index => $scenario) {
			$requiredKeys = $validationRules[$scenario];

			// Validate required keys based on scenario
			foreach ($requiredKeys as $key => $validator) {

				if (!isset($params[$index][$key]) || !$validator($params[$index][$key])) {
					throw new StorageException(
						'storage\db\dbTransactions\validateTransactionParams',
						[
							'message' => [$index, $key, $scenario],
							'details' => print_r($params, true),
						],
						$this->storageException
					);
				}

				// Additional validation for prepare scenario
				if ($scenario === 'prepare') {
					// Auto-extract params if not provided
					if (!isset($params[$index]['params'])) {
						preg_match_all('/(:\w+)/', $params[$index]['prepare'], $matches);
						$params[$index]['params'] = array_unique($matches[1]);
					}

					// Set default pdoTypes if not provided
					if (!isset($params[$index]['pdoTypes'])) {
						$params[$index]['pdoTypes'] = array_fill(0, count($params[$index]['params']), PDO::PARAM_STR);
					}

					// Validate pdoTypes if present
					match (true) {
						!is_array($params[$index]['pdoTypes']) => throw new StorageException(
							'storage\db\dbTransactions\validateTransactionParams',
							[
								'message' => [$index, 'pdoTypes', $scenario],
								'details' => print_r($params, true),
							],
							$this->storageException
						),
						count($params[$index]['pdoTypes']) !== count($params[$index]['params']) => throw new StorageException(
							'storage\db\dbTransactions\validateTransactionParams',
							[
								'message' => [$index, 'pdoTypes', $scenario],
								'details' => print_r($params, true),
							],
							$this->storageException
						),
						default => null
					};

					// Validate PDO types
					array_walk(
						$params[$index]['pdoTypes'],
						fn($type, $typeIndex) => is_int($type)
							?: throw new StorageException(
								'storage\db\dbTransactions\validateTransactionParams\pdoTypes',
								[
									'message' => [$index, $typeIndex],
									'details' => print_r($params, true),
								],
								$this->storageException
							)
					);

					// Validate parameter values exist
					array_walk(
						$params[$index]['params'],
						fn($param) => array_key_exists($param, $params[$index])
							?: throw new StorageException(
								'storage\db\dbTransactions\validateTransactionParams',
								[
									'message' => [$index, $param, $scenario],
									'details' => 'Missing parameter value'
								],
								$this->storageException
							)
					);
				}
			}
		}

		return true;
	}

	/**
	 * Detects and returns the query type
	 *
	 * @param string $query The SQL query
	 * @return string The query type
	 * @throws StorageException If query type cannot be determined
	 */
	private function transactionGetQueryType(string $query): string
	{
		// Remove comments and normalize whitespace
		$sanitizedQuery = preg_replace(
			['/--.*$/m', '/\/\*.*?\*\//s'],
			'',
			trim($query)
		);

		// First, check for explicit WITH to handle CTEs
		if (preg_match('/^\s*WITH\s+/i', $sanitizedQuery)) {
			// Look for the main command after the CTE
			preg_match('/\)\s*(SELECT|UPDATE|DELETE|INSERT)\b/i', $sanitizedQuery, $matches);
			return strtoupper($matches[1] ?? '');
		}

		// For regular queries and queries with subqueries
		preg_match('/^\s*(UPDATE|INSERT|DELETE|SELECT)\b/i', $sanitizedQuery, $matches);
		return match (strtoupper($matches[1] ?? '')) {
			'SELECT' => 'SELECT',
			'INSERT' => 'INSERT',
			'UPDATE' => 'UPDATE',
			'DELETE' => 'DELETE',
			default => throw new StorageException(
				'storage\db\dbTransactions\transactionGetQueryType',
				['details' => strtoupper($matches[1] ?? '')],
				$this->storageException
			)
		};
	}

	/**
	 * Processes the query result and updates the result object
	 *
	 * @param array|PDOStatement $queryResult The query result
	 * @param stdClass $result The result object
	 * @param string $query The SQL query
	 * @return void
	 */
	private function transactionProcessQueryResult(array|PDOStatement $queryResult, stdClass &$result, string $query): void
	{
		$action = match ($this->transactionGetQueryType($query)) {
			'SELECT' => fn() => $result->data = array_merge($result->data, $queryResult),
			'INSERT' => (function () use ($queryResult, $result) {
				$result->rowCount += $queryResult->rowCount();
				$result->lastInsertIds[] = $this->handle->lastInsertId();
			}),
			'UPDATE', 'DELETE' => fn() => $result->rowCount += $queryResult->rowCount(),
			default => fn() => null
		};

		$action();
	}

	/**
	 * Executes the query and returns the result
	 *
	 * @param string $query The SQL query
	 * @return array|PDOStatement The query result
	 */
	private function transactionQueryScenario(string $query): array|PDOStatement
	{
		$objStatement = $this->handle->query($query);
		return $this->transactionGetQueryType($query) === 'SELECT'
			? $objStatement->fetchAll(PDO::FETCH_ASSOC)
			: $objStatement;
	}

	/**
	 * Binds values and executes prepared statement
	 *
	 * @param array $params The parameters array
	 * @return array|PDOStatement The query results
	 */
	private function transactionPrepareScenario(array $params): array|PDOStatement
	{
		$results = [];
		$objStatement = $this->handle->prepare($params['prepare']);

		// There is always some parameter in the query
		$maxCount = count($params[$params['params'][0]]);

		// Execute for each set of parameters
		for ($i = 0; $i < $maxCount; $i++) {
			array_walk(
				$params['params'],
				fn($param, $index) => $objStatement->bindValue(
					$param,
					$params[$param][$i],
					$params['pdoTypes'][$index] ??= PDO::PARAM_STR
				)
			);

			$objStatement->execute();

			if ($this->transactionGetQueryType($params['prepare']) === 'SELECT') {
				$results = array_merge($results, $objStatement->fetchAll(PDO::FETCH_ASSOC));
			}
		}

		return $this->transactionGetQueryType($params['prepare']) === 'SELECT'
			? $results
			: $objStatement;
	}

	public function dbTransactions(array $params): stdClass
	{
		// Initialize result object before any database operations
		$result = new stdClass();
		$result->success = false;
		$result->data = [];
		$result->rowCount = 0;
		$result->lastInsertIds = [];

		// Setting a scenario for a particular transaction
		$scenarios = $this->determineTransactionScenarios($params);

		// Input data validation, exception caught by
		// Opus\storage\exception\StorageException;
		$this->validateTransactionParams($params, $scenarios);

		// Connecting to database
		$this->dbConnect();

		try {
			$this->handle->beginTransaction();

			foreach ($scenarios as $index => $scenario) {
				$queryResult = match ($scenario) {
					'query' => $this->transactionQueryScenario($params[$index]['query']),
					'prepare' => $this->transactionPrepareScenario($params[$index])
				};

				$this->transactionProcessQueryResult($queryResult, $result, $params[$index][$scenario]);
			}

			$this->handle->commit();
			$result->success = true;
		} catch (PDOException $event) {
			$this->handle->inTransaction() && $this->handle->rollBack();
			throw new StorageException(
				'storage\db\dbTransactions',
				[
					'message' => $event->getMessage(),
					'details' => [
						(isset($this->handle)) ? print_r($this->handle->errorInfo(), true) : null,
						print_r($params, true)
					]
				],
				$this->storageException
			);
		}

		return $result;
	}

	public function dbCursor(string $query, string $cursor, int $batchSize): array
	{
		$results = [];

		// Connecting to database,
		// Validate PDO connection is in dbConnect function
		$this->dbConnect();

		try {
			$this->handle->beginTransaction();
			// Declare cursor
			$this->handle->prepare($query)->execute();

			// Fetch in batches
			while (true) {
				$stmt = $this->handle->query("FETCH $batchSize FROM $cursor");
				$batch = $stmt->fetchAll(PDO::FETCH_ASSOC);

				if (empty($batch)) {
					break;
				}

				$results = array_merge($results, $batch);
				$stmt->closeCursor();
			}

			// Clean up
			$this->handle->exec("CLOSE $cursor");
			$this->handle->commit();

			return $results;
		} catch (PDOException $event) {
			$this->handle->inTransaction() && $this->handle->rollBack();
			throw new StorageException(
				'storage\db\dbCursor',
				[
					'message' => $event->getMessage(),
					'details' => (isset($this->handle)) ? print_r($this->handle->errorInfo(), true) : null
				],
				$this->storageException
			);
		}
	}

	public function dbResult(PDOStatement $objPDO): array
	{
		return $objPDO->fetchAll();
	}

	public function dbArrayResult(string $query): array
	{
		$result = $this->dbQuery($query);
		// returns an array in the form $result[row]['col']
		return $result->fetchAll(PDO::FETCH_ASSOC);
	}

	public function dbArrayNumResult(string $query): array
	{
		$result = [];
		$dbResult = $this->dbQuery($query)->fetchAll(PDO::FETCH_NUM);

		foreach ($dbResult as $key => $value) {
			$result[$key] = $value[0];
		}

		return $result;
	}

	public function dbQuote(string $string): string
	{
		// Connect to database to ensure handle is available
		$this->dbConnect();

		try {
			return $this->handle->quote($string);
		} catch (PDOException $event) {
			throw new StorageException(
				'storage\db\dbQuote',
				[
					'message' => $string,
					'details' => $event->getMessage()
				],
				$this->storageException
			);
		}
	}
}
