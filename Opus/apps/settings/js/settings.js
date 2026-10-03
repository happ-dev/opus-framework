/**
 * @Project: Opus
 * @Version: 1.0
 * @Author: Tomasz Ułazowski
 * @Date:   2026-08-15 15:54:38
 * @Last Modified by:   Tomasz Ułazowski
 * @Last Modified time: 2026-10-03 12:29:18
 **/

/**
 * Settings page — users DataTable, password reset modal, groups modal.
 */

/** @type {OpusDataTable} Users DataTable instance */
const dtSettings = new OpusDataTable({
	app: "settings",
	event: "users_dt",
	target: "<?= Opus\config\Config::getConfig('settings')->idTableEvent ?>",
});
OpusSelect.bindSelect(document.querySelector(dtSettings.table));

dtSettings.bindTable({
	lengthMenu: [
		[10, 25, 100],
		[10, 25, 100],
	],
	drawByInputText: [1, 7, 8, 9],
	drawBySelectMenu: [2, 3, 5, 12],
	hideColumns: [0, 4, 6, 11],
	order: [0, "asc"],
	columnDefs: [
		{
			targets: [
				0, //  0 id__users
				4, //  4 ulevel
				6, //  6 password
				11, // 11 cellphone
			],
		},
		{
			target: 1, //  1 login
			type: "html",
			orderable: false,
			className: "dt-body-right pe-2 align-middle",
			render: function (data, type, row) {
				return dtSettings.actionLink(row[0], data);
			},
		},
		{
			target: 2, //  2 active
			type: "html",
			orderable: false,
			width: "130px",
			className: "dt-body-center align-middle",
			render: function (data, type, row) {
				return data === true
					? "<?= Opus\controller\lang\Lang::getInstance()->get('event.message.true') ?>"
					: "<?= Opus\controller\lang\Lang::getInstance()->get('event.message.false') ?>";
			},
		},
		{
			targets: [
				3, //  3 auth_type
				5, //  5 gname
			],
			type: "html",
			width: "130px",
			className: "ps-2 align-middle",
		},
		{
			targets: [
				7, //  7 lastname
				8, //  8 firstname
				9, //  9 email
			],
			type: "html",
			width: "16%",
			className: "ps-2 align-middle",
		},
		{
			target: 10, // 10 homephone + cellphone
			type: "html",
			width: "130px",
			orderable: false,
			className: "align-middle",
			render: function (data, type, row) {
				const home = row[10] || "";
				const textHome = "<?= Opus\controller\lang\Lang::getInstance()->get('opus.db.users.homephone') ?>"
					.split(" ")[1]
					.replace(/^./, (c) => c.toUpperCase());
				const cell = row[11] || "";
				const textCell = "<?= Opus\controller\lang\Lang::getInstance()->get('opus.db.users.cellphone') ?>"
					.split(" ")[1]
					.replace(/^./, (c) => c.toUpperCase());
				return `
				<small class="text-start text-secondary font-monospace" style="font-size:.75rem">${textCell}:</small>
				<div class="text-end">${cell}</div>
				<small class="text-start text-secondary font-monospace" style="font-size:.75rem">${textHome}:</small>
				<div class="text-end">${home}</div>`;
			},
		},
		{
			target: 12, // 12 lang
			width: "120px",
			className: "dt-body-center align-middle",
		},
	],
	initComplete: function (settings, json) {
		InjectHtml.inject({
			html: `<?php
				$form = new Opus\html\form\Form();
				$button = Opus\html\buttons\Buttons::standardButton(
					'settings-groups',
					(object) [
						'text' => 'settings.modal.groups.button',
						'icon' => 'bi-people',
						'variant' => 'dark',
						'attributes' => [
							'data-bs-toggle' => 'modal',
							'data-bs-target' => '#id__opus-settings-groups'
						]
					]
				);
				$form->addElement($button);
				echo $form->getElement('standard-btn-settings-groups');
				unset($form, $button);
			?>`,
			onPrepare: () => {
				const slot = document.createElement("div");
				slot.className = "col-md-2";
				$(slot).insertAfter($(dtSettings.tableWrapper).find(".row:first-child div.col-md-auto:first-child"));
				return slot;
			},
		});
	},
});

/** @type {OpusModal} Reset password by admin modal */
const objSettingsResetEvent = new OpusModal({
	name: "opus-settings-reset-password-by-admin",
	data: { app: "settings", event: "resetPasswordByAdmin" },
});

objSettingsResetEvent.bindAjaxModal({
	footerStrategy: "edit",
	relatedTarget: (event) => {
		const relatedData = event.relatedTarget.dataset;
		objSettingsResetEvent.setData({
			id: relatedData.id,
			request: "reset-password-by-admin-form",
		});
	},
	onRender: () => {
		OpusSelect.bindSelect(objSettingsResetEvent.el);
		OpusDatePicker.bindDate(objSettingsResetEvent.el);

		const pass = document.getElementById("id_input_password");
		const confirm = document.getElementById("id_input_confirm_password");

		if (!pass || !confirm) {
			objSettingsResetEvent.setFooterButtons("show");
			return;
		}

		pass.pattern = "(?=.*[A-Z])(?=.*\\d).{8,}";

		const feedback = document.createElement("div");
		feedback.className = "invalid-feedback";
		confirm.closest(".input-group").after(feedback);

		const validate = () => {
			const weakPass = pass.value !== "" && !pass.value.match(/(?=.*[A-Z])(?=.*\d).{8,}/);
			const mismatch = confirm.value !== "" && confirm.value !== pass.value;

			let msg = "";
			if (weakPass)
				msg = "<?= Opus\controller\lang\Lang::getInstance()->get('settings.alert.password.requirements') ?>";
			else if (mismatch)
				msg = "<?= Opus\controller\lang\Lang::getInstance()->get('settings.alert.password.not.equal') ?>";

			pass.classList.toggle("is-invalid", weakPass);
			confirm.classList.toggle("is-invalid", mismatch);
			feedback.textContent = msg;
			feedback.style.display = msg ? "block" : "";
		};

		pass.addEventListener("input", validate);
		confirm.addEventListener("input", validate);
	},
	onHide: () => {
		dtSettings.dt.ajax.reload();
		OpusSelect.destroy(objSettingsResetEvent.el);
	},
	onSave: () => {
		objSettingsResetEvent.bindPostModal({
			json: true,
			buildUrl: () => {
				const url = new URLSearchParams();
				url.append("app", objSettingsResetEvent.data.app);
				url.append("event", objSettingsResetEvent.data.event);
				url.append("request", "reset-password-by-admin-save");
				return url;
			},
			buildData: (form) => {
				// Build data object for POST request
				const postData = {};
				const $formContainer = $(objSettingsResetEvent.formId);

				// Add form field values to the data object
				$formContainer.find('[id^="id_input_"], input[type=checkbox]:checked').each(function () {
					const name = $(this).attr("name");
					const value = $(this).val();

					if (value) {
						postData[name] = value;
					}
				});

				return postData;
			},
		});
	},
});

/** @type {OpusModal} Groups modal */
const objSettingsGroupsEvent = new OpusModal({
	name: "opus-settings-groups",
	data: { app: "settings", event: "groups" },
});

objSettingsGroupsEvent.bindAjaxModal();
