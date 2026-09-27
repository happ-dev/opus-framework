<?php

/**
 * @Project: Opus
 * @Version: 1.0
 * @Author: Tomasz Ulazowski
 * @Date:   2026-03-28 17:31:50
 * @Last Modified by:   Tomasz Ułazowski
 * @Last Modified time: 2026-09-25 21:12:30
 **/

namespace Opus\controller\cli;

use Opus\controller\cli\CliColor;
use Opus\controller\exception\ControllerException;

class CliTable
{
	const HEAD_POSITION_VERTICAL = 'vertical';
	const HEAD_POSITION_HORIZONTAL = 'horizontal';

	/**
	 * Writes a formatted table to the CLI with specified head position
	 *
	 * @param array $array Array containing table data with structure:
	 *                     - head: [
	 *                         'text' => string[],
	 *                         'color' => string|string[] (optional)
	 *                     ]
	 *                     - columns: [
	 *                         'text' => string[],
	 *                         'color' => string|string[] (optional)
	 *                     ]
	 * @param string $position Head position, one of:
	 *                        - CliTable::HEAD_POSITION_HORIZONTAL (default)
	 *                        - CliTable::HEAD_POSITION_VERTICAL
	 *
	 * @throws ControllerException When head validation fails
	 * @return void
	 */
	public static function writeTable(
		array $array,
		string $position = self::HEAD_POSITION_HORIZONTAL
	): void {
		// Validate head structure
		self::headValidate($array);

		// Write table based on head position
		match ($position) {
			self::HEAD_POSITION_HORIZONTAL => self::writeTableHorizontal($array, $position),
			self::HEAD_POSITION_VERTICAL => self::writeTableVertical($array, $position),
			default => throw new ControllerException(
				'controller\cli\table\position',
				['message' => $position],
				ControllerException::TYPE_CLI_EXCEPTION
			)
		};
	}

	/**
	 * Validates the presence and content of the 'head' element in the array
	 *
	 * @param array $array Array containing table configuration with 'head' element
	 * @throws ControllerException When:
	 *                            - 'head' key is not present in array
	 *                            - 'head' value is empty
	 * @return void
	 */
	private static function headValidate(array $array): void
	{
		// Check if 'head' key exists in array
		if (!array_key_exists('head', $array)) {
			throw new ControllerException(
				'controller\cli\table\head\lack',
				null,
				ControllerException::TYPE_CLI_EXCEPTION
			);
		}

		// Check if 'head' value is not empty
		if (empty($array['head'])) {
			throw new ControllerException(
				'controller\cli\table\head\empty',
				null,
				ControllerException::TYPE_CLI_EXCEPTION
			);
		}
	}

	/**
	 * Calculates the maximum string length from an array of strings
	 * using multibyte string length after trimming whitespace
	 *
	 * @param array $array Array of strings to measure
	 * @param string $encoding Character encoding to use (default: UTF-8)
	 * @return int Length of the longest string in the array
	 * @throws ControllerException If array elements are not string-compatible
	 */
	private static function maxStrLenFromArray(array $array, string $encoding = 'UTF-8'): int
	{
		return max(
			array_map(
				function (mixed $str) use ($encoding): int {

					if (!is_scalar($str) && !is_null($str) && !method_exists($str, '__toString')) {
						throw new ControllerException(
							'controller\cli\table\str',
							['message' => $str],
							ControllerException::TYPE_CLI_EXCEPTION
						);
					}

					return mb_strlen(trim((string) $str), $encoding);
				},
				$array
			)
		);
	}

	/**
	 * Calculates maximum string lengths for table columns based on head position
	 *
	 * @param array $array Array containing table data with 'head' and column text
	 * @param string $position Head position ('vertical' or 'horizontal')
	 * @return array Array of maximum lengths for each column
	 * @throws ControllerException When:
	 *                                 - Invalid position is provided
	 */
	private static function maxStrlenFromColumns(array $array, $position): array
	{
		return match ($position) {
			self::HEAD_POSITION_HORIZONTAL => (function () use ($array): array {
				$longest = [];
				$countCol = count($array) - 1;
				$countRow = count($array['head']['text']);

				for ($row = 0; $row < $countRow; $row++) {
					$strlen = [$array['head']['text'][$row]];

					for ($col = 0; $col < $countCol; $col++) {
						$strlen[] = $array[$col]['text'][$row];
					}

					$longest[] = self::maxStrLenFromArray($strlen);
				}

				return $longest;
			})(),
			self::HEAD_POSITION_VERTICAL => (function () use ($array): array {
				$longest = [];

				foreach ($array as $key => $value) {

					if (is_int($key)) {
						$longest[] = self::maxStrLenFromArray($value['text']);
					}
				}

				return $longest;
			})(),
			default => throw new ControllerException(
				'controller\cli\table\position',
				['message' => $position],
				ControllerException::TYPE_CLI_EXCEPTION
			)
		};
	}

	/**
	 * Writes a horizontal table to the CLI with optional color formatting
	 *
	 * @param array $array Array containing table data with structure:
	 *                     - head: [
	 * 							'text' => string[],
	 * 							'color' => string|string[] (optional)
	 * 						]
	 *                     - columns: [
	 * 							'text' => string[],
	 * 							'color' => string|string[] (optional)
	 * 						]
	 * @param string $position Table head position
	 * @return void
	 */
	private static function writeTableHorizontal(array $array, string $position): void
	{
		// Calculate column widths and initialize variables
		$gapColumn = self::maxStrlenFromColumns($array, $position);
		$countCol = count($array['head']['text']);

		$writeRow = function (string $text, int $row, int $gap, ?string $color = null) use ($countCol): void {
			$newLine = ($row === $countCol - 1) ? "s |\n" : "s |";

			if ($color !== null) {
				$ansiLen = mb_strlen(CliColor::write('', $color, null, false));
				$text = CliColor::write($text, $color, null, false);
				$gap += $ansiLen;
			}

			printf(" %" . $gap . $newLine, $text);
		};

		foreach ($array as $column) {

			if (!isset($column['text'])) {
				continue;
			}

			for ($row = 0; $row < $countCol; $row++) {
				$color = null;

				if (isset($column['color'])) {
					$color = is_array($column['color']) ? $column['color'][$row] : $column['color'];
				}

				$writeRow((string) $column['text'][$row], $row, $gapColumn[$row], $color);
			}
		}

		echo PHP_EOL;
	}

	/**
	 * Writes a vertical table to the CLI with optional color formatting
	 *
	 * @param array $array Array containing table data with structure:
	 *                     - head: [
	 *                         'text' => string[],
	 *                         'color' => string|string[] (optional)
	 *                     ]
	 *                     - columns: [
	 *                         'text' => string[],
	 *                         'color' => string|string[] (optional)
	 *                     ]
	 * @param string $position Table head position
	 * @throws ControllerException When:
	 *                                 - Invalid color format is provided
	 * @return void
	 */
	private static function writeTableVertical(array $array, string $position): void
	{
		// Calculate dimensions
		$countCol = count($array) - 1;
		$countRow = count($array['head']['text']);

		// Calculate formatting
		$headerWidth = self::maxStrlenFromArray($array['head']['text']);
		$addGapHeader = isset($array['head']['color']) ? 12 : 1;
		$gapHeader = "%-" . ($headerWidth + $addGapHeader) . "s|";
		$gapColumn = self::maxStrlenFromColumns($array, $position);

		// Helper function for text formatting
		$formatCell = function (string $text, $color = null): string {

			if ($color !== null && !is_string($color)) {
				throw new ControllerException(
					'controller\cli\table\color',
					['message' => $color],
					ControllerException::TYPE_CLI_EXCEPTION
				);
			}

			return $color
				? CliColor::write($text, $color, null, false)
				: $text;
		};

		// Write table rows
		for ($row = 0; $row < $countRow; $row++) {
			// Format and write header cell
			$headerColor = null;

			if (isset($array['head']['color'])) {
				$headerColor = is_array($array['head']['color'])
					? $array['head']['color'][$row]
					: $array['head']['color'];
			}

			printf($gapHeader, $formatCell($array['head']['text'][$row], $headerColor));

			// Format and write data cells
			for ($col = 0; $col < $countCol; $col++) {
				$newLine = ($col === $countCol - 1) ? "s |\n" : "s |";
				$addGapColumn = isset($array[$col]['color']) ? 12 : 1;
				$gap = "%" . ($gapColumn[$col] + $addGapColumn) . $newLine;
				$cellColor = null;

				if (isset($array[$col]['color'])) {
					$cellColor = is_array($array[$col]['color'])
						? $array[$col]['color'][$row]
						: $array[$col]['color'];
				}

				printf($gap, $formatCell($array[$col]['text'][$row], $cellColor));
			}
		}

		echo PHP_EOL;
	}
}
