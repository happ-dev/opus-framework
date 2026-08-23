<?php

/**
 * @Project: Opus
 * @Version: 1.0
 * @Author: Tomasz Ulazowski
 * @Date:   2026-03-28 20:27:19
 * @Last Modified by:   Tomasz Ułazowski
 * @Last Modified time: 2026-08-22 22:07:35
 **/

namespace Opus\html\form;

use Opus\controller\exception\ControllerException;

abstract class AbstractForm
{
	use TraitValidHtmlTags;

	/**
	 * Validates a form element configuration
	 *
	 * @param array &$element The element configuration to validate (passed by reference)
	 * @throws ControllerException When the element configuration is invalid
	 * @return void
	 *
	 * Element structure:
	 * [
	 *     'name' => '',            // string not required, if not provided, will be generated automatically
	 *     'id' => '',              // string required, must start with "id_", lowercase only, allows hyphens, regex /^id_[a-z0-9\-]+$/
	 *     'tag' => '',             // required value like input, select, button
	 *     'closing-tag' => '',     // will be generated automatically from self::HTML_TAGS_REQUIRING_CLOSING or self::HTML_TAGS_NOT_REQUIRING_CLOSING
	 *     'attributes' => [        // required array
	 *         'type' => '',                    // required value if tag is input
	 *         'class' => '',                   // required value string
	 *         self::HTML_BOOLEAN_ATTRIBUTES    // Boolean attributes, do not require values,
	 *                                          // Other attributes, not in self::HTML_BOOLEAN_ATTRIBUTES require values
	 *     ],
	 *
	 *     'option' => [            // array required if tag is 'select', validated if tag is 'select'
	 *         'empty' => '',       // boolean, if true skips text/value validation and renders empty select
	 *         'all' => '',         // boolean, if not provided it defaults to false
	 *         'ftext' => '',       // string|null, if not provided it defaults to null
	 *         'value' => [],       // array with value select HTML tag, optional, if exists must have the same number of elements as 'text'
	 *         'text' => [],        // array with option select HTML tag, required (unless empty=true or optgroups present)
	 *         'selected' => '',    // integer not required, text/value array element number
	 *         'disabled' => []     // array of integers not required, text/value array element number
	 *     ],
	 *
	 *     'optgroups' => [         // array not required, validated if tag is select and optgroup exists
	 *         [
	 *             'label' => '',           // string required
	 *             'option' => [            // array required
	 *                 'value' => [],       // array with value select HTML tag, optional, if exists must have the same number of elements as 'text'
	 *                 'text' => [],        // array with option select HTML tag, required
	 *             ]
	 *         ],
	 *         [
	 *             // Additional optgroups...
	 *         ]
	 *     ]
	 * ]
	 */
	abstract public function addElement(array $element): object;

	/**
	 * Sets a form attribute
	 *
	 * @param string $key The attribute name
	 * @param string|null $value The attribute value (null for boolean attributes)
	 * @return object Returns $this for method chaining
	 */
	abstract public function setAttribute(string $key, ?string $value = null): object;

	/**
	 * Generates the opening HTML form tag with attributes
	 *
	 * @return string HTML form opening tag with attributes
	 */
	abstract public function beginForm(): string;

	/**
	 * Generates the closing HTML form tag
	 *
	 * @return string HTML form closing tag
	 */
	abstract public function endForm(): string;

	/**
	 * Generates HTML for a form element by its ID or name
	 *
	 * This method finds the element by ID or name and generates the appropriate HTML
	 * based on the element's tag type. Different element types (like select) have
	 * specialized rendering logic.
	 *
	 * @param string $value The ID or name of the element to render
	 * @return string The generated HTML for the element
	 * @throws ControllerException If no element with the given ID or name is found
	 */
	abstract public function getElement(string $value): string;

	/**
	 * Retrieves a list of all form element names
	 *
	 * This method extracts the name attribute from each form element
	 * and returns them as a simple indexed array.
	 *
	 * @return array List of all form element names
	 */
	abstract public function getListElements(): array;

	protected ?array $attributes = [];
	protected ?array $elements = [];
	protected ?array $keys = [];

	/**
	 * Finds the id_key for an element by its ID or name
	 *
	 * This method determines whether the provided value is an ID or name,
	 * then searches for the corresponding element in the keys array.
	 *
	 * @param string $value The ID or name to search for
	 * @return string The id_key of the found element
	 * @throws ControllerException If no element with the given ID or name is found
	 */
	protected function findIdKey(string $value): string
	{
		// Determine if the value is an ID or name
		$fieldType = match (preg_match('/^id_[a-z0-9\-]+$/', $value)) {
			1 => 'id',
			default => 'name'
		};

		// Find the element with the matching ID or name
		foreach ($this->keys as $item) {

			if ($item[$fieldType] === $value) {
				return $item['id_key'];
			}
		}

		// Throw an exception if no matching element is found
		throw new ControllerException(
			'html\form\getElement',
			['message' => $value]
		);
	}

	/**
	 * Formats HTML attributes
	 *
	 * @param array $attributes The attributes to format
	 * @return string The formatted attributes string
	 */
	private function formatAttributes(array $attributes): string
	{
		$attrHtml = '';

		foreach ($attributes as $attrKey => $attrValue) {

			$attrHtml .= is_numeric($attrKey)
				? $attrValue . ' '							// Boolean attribute
				: $attrKey . '="' . $attrValue . '" ';		// Regular attribute
		}

		return $attrHtml;
	}

	/**
	 * Generates HTML for default element types
	 *
	 * This method creates HTML markup for standard form elements by processing
	 * the element configuration and its attributes.
	 *
	 * @param array $element The element configuration
	 * @return string The generated HTML markup
	 */
	protected function defaultType(array $element): string
	{
		// Start the opening tag
		$html = '<' . $element['tag'] . ' ';

		// Get text content if available
		$text = $element['text'] ??= null;

		// Determine closing tag format
		$closingTag = $element['closing-tag'] === false
			? '>'
			: '>' . $text . $element['closing-tag'];

		foreach ($element as $key => $value) {
			$html .= match ($key) {
				// Skip these properties as they're handled separately
				'tag', 'closing-tag', 'text', 'option', 'optgroups' => null,

				// Process attributes array
				'attributes' => $this->formatAttributes($value),

				// Handle other properties as attributes
				default => $key . '="' . $value . '" '
			};
		}

		// Complete the element
		$html .= $closingTag;

		// Clean up any extra spaces and add line break
		return preg_replace('/\s+>/', '>', $html) . PHP_EOL;
	}

	/**
	 * Generates HTML for select elements
	 *
	 * This method creates HTML markup for select elements, including options
	 * and option groups if specified.
	 *
	 * @param array $element The select element configuration containing:
	 *                      - 'tag': The HTML tag name (should be 'select')
	 *                      - 'attributes': HTML attributes for the select element
	 *                      - 'option': Configuration for options including:
	 *                          - 'all': Boolean to include an "All" option
	 *                          - 'ftext': Text for an empty value option
	 *                          - Other option properties for selectOptionType
	 *                      - 'optgroups': Optional array of option groups
	 *                      - 'closing-tag': The closing tag string
	 * @return string The generated HTML markup for the select element
	 */
	protected function selectType(array $element): string
	{
		// Start the opening tag
		$html = '<' . $element['tag'] . ' ';

		// Add attributes
		foreach ($element as $key => $value) {
			$html .= match ($key) {
				// Skip these properties as they're handled separately
				'tag', 'closing-tag', 'text', 'option', 'optgroups' => null,

				// Process attributes array
				'attributes' => $this->formatAttributes($value),

				// Handle other properties as attributes
				default => $key . '="' . $value . '" '
			};
		}

		// Complete opening tag
		$html = preg_replace('/\s+>/', '>', $html . '>') . PHP_EOL;

		// Add special options if configured
		$html .= $element['option']['all'] === false ? null : '<option value="All">All</option>' . PHP_EOL;
		$html .= !is_null($element['option']['ftext']) ? '<option value="" data-ftext>'
			. htmlspecialchars($element['option']['ftext'], ENT_QUOTES)
			. '</option>' . PHP_EOL : null;

		// Add regular options or option groups
		$html .= match (isset($element['optgroups'])) {
			true => $this->selectOptGroupType($element['optgroups'], $element['option']['selected'] ?? null),
			default => $this->selectOptionType($element['option'])
		};

		// Add closing tag
		return $html . $element['closing-tag'];
	}

	/**
	 * Generates HTML for options in a select element
	 *
	 * This method creates HTML markup for options based on the provided configuration,
	 * handling selected and disabled states.
	 *
	 * @param array $option The options configuration containing:
	 *                     - 'empty': if true, returns empty string (no options rendered)
	 *                     - 'text': array of option text values
	 *                     - 'value': array of option values (uses text as fallback)
	 *                     - 'selected': index of the selected option (optional)
	 *                     - 'disabled': array of indices for disabled options (optional)
	 * @return string The generated HTML markup for options
	 */
	private function selectOptionType(array $option): string
	{
		if (($option['empty'] ?? false) === true) {
			return '';
		}

		$html = '';

		// Process each option
		foreach ($option['text'] as $key => $text) {
			// Handle selected and disabled attributes
			$selected = match (true) {
				is_array($option['selected'] ?? null) => in_array($key, $option['selected']) ? ' selected' : null,
				isset($option['selected']) && $option['selected'] === $key => ' selected',
				default => null
			};
			$disabled = isset($option['disabled']) && in_array($key, $option['disabled']) ? ' disabled' : null;

			// Get value (use text as fallback)
			$value = $option['value'][$key] ?? $text;

			// Generate option HTML with proper escaping
			$html .= '<option value="' . htmlspecialchars($value, ENT_QUOTES) . '"'
				. $selected . $disabled . '>'
				. htmlspecialchars($text, ENT_QUOTES) . '</option>' . PHP_EOL;
		}

		return $html;
	}

	/**
	 * Generates HTML for option groups in a select element
	 *
	 * This method creates HTML markup for option groups and their options
	 * based on the provided configuration.
	 *
	 * @param array $optgroups The option groups configuration
	 * @param int|array|null $selected Selected value(s) to mark as selected
	 * @return string The generated HTML markup for option groups
	 */
	private function selectOptGroupType(array $optgroups, int|array|null $selected = null): string
	{
		$selectedValues = match (true) {
			is_array($selected) => $selected,
			is_int($selected) => [$selected],
			default => []
		};

		// Define the function to handle option in optgroup
		$processOption = function ($option) use ($selectedValues) {
			$html = '';

			foreach ($option['text'] as $key => $text) {
				$value = $option['value'][$key] ?? $text;
				$sel = in_array($value, $selectedValues, true) ? ' selected' : '';
				$html .= '<option value="' . htmlspecialchars($value, ENT_QUOTES) . '"' . $sel . '>'
					. htmlspecialchars($text, ENT_QUOTES) . '</option>' . PHP_EOL;
			}

			return $html;
		};

		$html = '';

		foreach ($optgroups as $optgroup) {
			// Create optgroup opening tag with label
			$html .= '<optgroup label="' . htmlspecialchars($optgroup['label'], ENT_QUOTES) . '">' . PHP_EOL;

			// Generate options for this group
			$html .= $processOption($optgroup['option']);

			// Close the optgroup
			$html .= '</optgroup>' . PHP_EOL;
		}

		return $html;
	}
}
