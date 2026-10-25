<?php

/**
 * @Project: Opus
 * @Version: 1.0
 * @Author: Tomasz Ulazowski
 * @Date:   2026-04-01 20:13:07
 * @Last Modified by:   Tomasz Ułazowski
 * @Last Modified time: 2026-06-28 18:34:57
 **/

namespace Opus\html\sidebar;

use Opus\controller\exception\ControllerException;

class ValidateSidebarItems
{
	const DEFAULT_ICON = 'bi-app-indicator';
	const VALID_HREF = '/^(#|page=\w+(&spage=\w+)?)$/';
	const VALID_LANG_KEY = '/^[a-zA-Z]+(\.[a-zA-Z]+)*$/';

	/**
	 * Validates sidebar items configuration
	 *
	 * @param array &$element Array of sidebar item configurations
	 * @return void
	 * @throws ControllerException When validation fails
	 */
	public static function validateItems(array &$element): void
	{
		if (empty($element)) {
			throw new ControllerException(
				'html\sidebar\validateItems\empty',
				['message' => null]
			);
		}

		foreach ($element as $index => &$item) {
			if ($item === 'hr') {
				continue;
			}
			self::validateItem($item, $index);
		}
	}

	/**
	 * Validates a single sidebar item
	 *
	 * @param array &$item Sidebar item configuration
	 * @param int $index Item index in the array
	 * @param bool $isDropdown Whether this item is inside a dropdown
	 * @return void
	 * @throws ControllerException When validation fails
	 */
	private static function validateItem(array &$item, int $index, bool $isDropdown = false): void
	{
		$rules = [
			'required' => ['href', 'text'],
			'validators' => [
				'href' => fn($value) => preg_match(self::VALID_HREF, $value) === 1,
				'text' => fn($value) => is_string($value) && preg_match(self::VALID_LANG_KEY, $value) === 1
			],
			'messages' => [
				'href' => fn($i) => "Invalid 'href' value at index {$i}: must match pattern 'page=name' or '#'",
				'text' => fn($i) => "Invalid 'text' value at index {$i}: must match pattern 'key.subkey'"
			]
		];

		// Check required fields
		foreach ($rules['required'] as $field) {
			if (!isset($item[$field]) || !is_string($item[$field]) || $item[$field] === '') {
				throw new ControllerException(
					'html\sidebar\validateItems\isset',
					['message' => [$field, $index]]
				);
			}
		}

		// Validate fields
		foreach ($rules['validators'] as $field => $validator) {
			if (!$validator($item[$field])) {
				throw new ControllerException(
					'html\sidebar\validateItems\parametr',
					['message' => $rules['messages'][$field]($index)]
				);
			}
		}

		// Set default icon
		if (!isset($item['icon']) || !is_string($item['icon']) || $item['icon'] === '') {
			$item['icon'] = self::DEFAULT_ICON;
		}

		// Validate dropdown
		if (
			$item['href'] === '#'
			&& !$isDropdown
			&& !isset($item['data-bs-toggle'])
			&& !isset($item['data-apage'])
		) {
			if (!isset($item['dropdown']) || !is_array($item['dropdown']) || empty($item['dropdown'])) {
				throw new ControllerException(
					'html\sidebar\validateItems\isset',
					['message' => ['dropdown', $index]]
				);
			}

			foreach ($item['dropdown'] as $dIndex => &$dropdownItem) {
				if ($dropdownItem === 'hr') {
					continue;
				}
				self::validateItem($dropdownItem, $dIndex, true);
			}
		}
	}
}
