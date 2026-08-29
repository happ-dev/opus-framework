<?php

/**
 * @Project: Opus
 * @Version: 1.0
 * @Author: Tomasz Ułazowski
 * @Date:   2026-05-21 19:27:54
 * @Last Modified by:   Tomasz Ułazowski
 * @Last Modified time: 2026-08-15 20:57:48
 **/

namespace Opus\controller\event;

use stdClass;
use ArrayObject;
use Opus\html\modal\Modal;
use Opus\html\buttons\Buttons;
use Opus\html\form\Form;
use Opus\controller\query\TraitQuery;
use Opus\controller\security\Csp;

/**
 * Handles the view generation for table event operations
 *
 * This class extends ArrayObject to provide a flexible container for view variables
 * while generating HTML output for table event operations. It's used to render
 * the response for table event API calls.
 *
 * API Endpoint Format:
 * https://14.6.83.14/index.php
 * 		?api=tableevent
 * 		&app={app}
 * 		&event={event}
 * 		&process=editor
 * 		&strategy={strategy}
 *
 * @param string app Application name, must be defined in config.json
 * @param string event Table event name from the application's config file
 * @param string process Processing mode, typically "editor"
 * @param string strategy Operation type: "add", "edit", "show", or "delete"
 *
 * @example
 * https://14.6.83.14/index.php?api=tableevent&app=users&event=manage_users&process=editor&strategy=edit
 *
 * @extends ArrayObject Provides array-like access to view variables
 * @property string $id The ID property from variables array
 */
class TableEventView extends ArrayObject
{
	use TraitQuery;

	private ?string $indexAction = null;
	private string $page = self::QINPUT_EXEPTIONS_PAGE;
	private string $hashtag = self::QINPUT_EXEPTIONS_HASHTAG;
	private string $plus = self::QINPUT_EXEPTIONS_PLUS;

	public function __construct(array $variables = [])
	{
		parent::__construct($variables, ArrayObject::ARRAY_AS_PROPS);

		$form = new Form();
		$form->addElement(Buttons::cancelButton('table-event', 'modal'));							// id_cancel-btn-table-event
		$form->addElement(Buttons::closeButton('table-event', ['data-bs-dismiss' => 'modal']));		// id_close-btn-table-event
		$form->addElement(Buttons::saveButton('table-event'));										// id_save-btn-table-event

		$options = new stdClass();
		$options->id = $this->id;
		$options->size = 'xl';
		$options->scrollable = false;		// if form is true, value must be false because bootstrap issue
		$options->form = true;
		$options->body = <<<HTML
			<div class="table-responsive pt-2 pb-2"></div>
		HTML;
		$options->footer = $form->getElement('close-btn-table-event')
			. $form->getElement('save-btn-table-event')
			. $form->getElement('cancel-btn-table-event');

		$modal = new Modal();
		$modal->addModal('table-event', $options);

		$nonce = Csp::getNonce();
		ob_start();
		echo <<<HTML
		{$modal->getModalByName('table-event')}
		<script type="text/javascript" nonce="{$nonce}">
			$(document).ready(function () {
				const objTableEvent = new OpusModal({
					name: "table-event",
					id: "{$this->id}",
					api: "tableevent"
				});

				// Maps input values to their corresponding query exception constants
				// @param {string} input - The input value to map ('page', '#', '+', or any other string)
				// @returns {number|string} The corresponding exception constant or the original input
				function queryInputExceptions(input) {
					const exceptions = {
						'page': '{$this->page}',
						'#': '{$this->hashtag}',
						'+': '{$this->plus}'
					};

					return exceptions[input] ?? input;
				}

				objTableEvent.bindAjaxModal({
					relatedTarget: (event) => {
						objTableEvent.setData({
							app: $(event.relatedTarget).attr('data-app'),
							event: $(event.relatedTarget).attr('data-event'),
							process: $(event.relatedTarget).attr('data-process'),
							strategy: $(event.relatedTarget).attr('data-strategy'),
							id: $(event.relatedTarget).attr('data-id'),
							tableId: $(event.relatedTarget).attr('data-table-id')
						});
					},
					onShow: (response) => {
						// Body input table
						objTableEvent.tableContainer = $(objTableEvent.bodyRowColClass + " > div");
						objTableEvent.tableContainer.html(response.body.table);
						objTableEvent.fixTableCSS(objTableEvent.tableContainer);
					},
					onRender: () => {
						// Cache the table container and get all checkboxes once
						const table = $(objTableEvent.tableContainer).find("table");
						const inputs = table.find('[id^="id_input_"]');
						const checkboxes = table.find('input[type=checkbox]');

						// Select inputs
						OpusSelect.bindSelect(objTableEvent.el);

						// Handle edit strategy - disable inputs for checked checkboxes
						if (objTableEvent.data.strategy === 'edit') {
							checkboxes.filter(':checked').each(function () {
								const idChecked = this.id.split('_')[2];
								const input = inputs.filter('#id_input_' + idChecked);
								input.prop('disabled', true);
								input.prev('.opus-select-wrapper').addClass('disabled');
							});
						}

						// Set up click handlers for all checkboxes
						checkboxes.each(function () {
							const checkbox = this;
							const idCheckbox = checkbox.id.split('_')[2];
							const relatedInput = inputs.filter('#id_input_' + idCheckbox);
							const relatedWrapper = relatedInput.prev('.opus-select-wrapper');

							$(checkbox).on('click', function () {
								relatedInput.prop('disabled', this.checked);
								relatedWrapper.toggleClass('disabled', this.checked);
							});
						});

						// Input date
						OpusDatePicker.bindDate(objTableEvent.el);
					},
					onHide: () => {
						// Reload DataTable if not undefined
						const tableId = objTableEvent.data.tableId;

						if (tableId && $.fn.dataTable.isDataTable(tableId)) {
							$(tableId).DataTable().ajax.reload();
						}

						OpusSelect.destroy(objTableEvent.el);
					},
					onSave: () => {
						objTableEvent.bindPostModal({
							buildUrl: () => {
								const url = new URLSearchParams();
								url.append('app', objTableEvent.data.app);
								url.append('event', objTableEvent.data.event);
								url.append('process', 'query');
								return url;
							},
							buildData: (form) => {
								// Build data object for POST request
								const postData = {};

								// Add form field values to the data object
								objTableEvent.tableContainer.find('[id^="id_input_"], input[type=checkbox]:checked').each(function() {
									const name = $(this).attr('name');
									const value = this.type === 'checkbox' ? $(this).val() : queryInputExceptions($(this).val());

									if (value) {
										postData[name] = value;
									}
								});

								return postData;
							}
						});
					}
				});

			});
		</script>
		HTML;

		$this->indexAction = ob_get_clean();
	}

	public function __toString()
	{
		return $this->indexAction;
	}
}
