// @ts-nocheck
/**
 * @Project: Opus
 * @Version: 1.0
 * @Author: Tomasz Ułazowski
 * @Date:   2026-07-27 17:17:16
 * @Last Modified by:   Tomasz Ułazowski
 * @Last Modified time: 2026-10-20 11:10:23
 **/

/**
 * Custom select replacement supporting single, multi, and visible (inline panel) modes.
 * Operates as a singleton. Supports both static (DOM-based) and AJAX data sources.
 *
 * @class OpusSelect
 */
class OpusSelect {
	/** @type {OpusSelect|null} */
	static #instance = null;

	/** @type {Map<HTMLSelectElement, SelectData>} */
	#selects = new Map();

	/** @type {string} PHP-rendered "Load more" button HTML */
	#loadMoreButton = `<?php
	$button = Opus\html\buttons\Buttons::standardButton(
		'load-more',
		(object) [
			'text' => Opus\controller\lang\Lang::getInstance()->get('opus.select.load.button'),
			'icon' => 'bi-cloud-download',
			'variant' => 'outline-success'
		]
	);

	$form = new Opus\html\form\Form();
	echo $form->addElement($button)->getElement('standard-btn-load-more');
	?>`;

	/**
	 * Returns the singleton instance, creating it if necessary.
	 *
	 * @returns {OpusSelect}
	 */
	static getInstance() {
		return (this.#instance ??= new this());
	}

	/** @type {{visible: boolean, search: boolean, limit: number}} */
	// @ts-ignore
	static #defaults = {
		visible: false,
		search: true,
		limit: 20,
	};

	/**
	 * Reads configuration from element data-attributes and passed options.
	 *
	 * @param {HTMLSelectElement} select - The native select element.
	 * @param {Object} options - Options passed via bindSelect.
	 * @returns {{multiple: boolean, visible: boolean, search: boolean, ajax: boolean, app: string|null, event: string|null, limit: number}}
	 */
	#readConfig(select, options) {
		const multiple = select.hasAttribute("multiple");
		const data = select.dataset;

		return {
			multiple,
			visible: options.visible ?? data.visible === "true",
			search: options.search ?? data.search !== "false",
			ajax: "event" in data,
			app: options.app ?? data.app ?? null,
			event: options.event ?? data.event ?? null,
			limit: options.limit ?? (parseInt(data.limit) || this.constructor.#defaults.limit),
		};
	}

	/**
	 * Determines the rendering mode and data source from config.
	 *
	 * @param {Object} config - Config object from #readConfig.
	 * @returns {{mode: "single"|"multi"|"visible", source: "ajax"|"static"}}
	 */
	#resolveMode(config) {
		const mode = !config.multiple ? "single" : config.visible ? "visible" : "multi";
		const source = config.ajax ? "ajax" : "static";
		return { mode, source };
	}

	/**
	 * Creates the wrapper element around the native select, hides the select,
	 * moves validation feedback elements, and binds validation/change listeners.
	 *
	 * @param {HTMLSelectElement} select - The native select element.
	 * @returns {HTMLDivElement} The created wrapper.
	 */
	#createWrapper(select) {
		const wrapper = document.createElement("div");
		wrapper.className = "opus-select-wrapper";

		if (select.classList.contains("search-filter")) wrapper.classList.add("search-filter");
		if (select.style.width) wrapper.style.width = select.style.width;
		if (select.classList.contains("form-select-sm")) wrapper.classList.add("opus-select-sm");
		if (select.classList.contains("form-select-lg")) wrapper.classList.add("opus-select-lg");

		const hasInputGroup = select.parentNode.classList.contains("input-group");
		if (hasInputGroup) wrapper.classList.add("opus-select-input-group");
		if (select.parentNode.classList.contains("form-floating")) wrapper.classList.add("form-select");

		select.parentNode.insertBefore(wrapper, select);
		select.style.display = "none";

		// move .invalid-feedback / .valid-feedback after wrapper
		let sibling = select.nextElementSibling;
		while (
			sibling &&
			(sibling.classList.contains("invalid-feedback") || sibling.classList.contains("valid-feedback"))
		) {
			const next = sibling.nextElementSibling;
			wrapper.after(sibling);
			sibling = next;
		}

		// validation sync + state sync
		select.addEventListener("invalid", () => this.#updateValidation(select, wrapper));
		select.addEventListener("change", () => {
			this.#updateValidation(select, wrapper);
			this.#syncState(select);
		});

		return wrapper;
	}

	/**
	 * Syncs Bootstrap validation classes on the wrapper based on select validity.
	 *
	 * @param {HTMLSelectElement} select - The native select element.
	 * @param {HTMLDivElement} wrapper - The opus-select-wrapper element.
	 */
	#updateValidation(select, wrapper) {
		const form = select.closest("form");
		if (!form || !form.classList.contains("was-validated")) return;

		const valid = select.checkValidity();
		wrapper.classList.toggle("opus-select-wrapper--invalid", !valid);
		wrapper.classList.toggle("opus-select-wrapper--valid", valid);
	}

	/**
	 * Synchronizes the visual state after a programmatic value change.
	 * For visible+static: re-renders options. For dropdown: updates trigger text.
	 * Skips visible+ajax (options come from API).
	 *
	 * @param {HTMLSelectElement} select - The native select element.
	 */
	#syncState(select) {
		const data = this.#selects.get(select);
		if (!data) return;

		if (data.mode === "visible") {
			if (data.source === "ajax") return;
			const list = data.wrapper.querySelector(".opus-select-list");
			if (list) this.#renderOptions(select, list, data.config);
		} else {
			// For ajax multi: remove orphaned <option> elements that are no longer selected
			if (data.source === "ajax" && data.config.multiple) {
				Array.from(select.options).forEach((o) => {
					if (!o.selected) o.remove();
				});
			}
			this.#updateTrigger(select, data.config, data.wrapper);
		}
	}

	/** @type {{el: HTMLDivElement, search: HTMLInputElement, list: HTMLDivElement, footer: HTMLDivElement}|null} */
	#popup = null;

	/** @type {HTMLSelectElement|null} */
	#activeSelect = null;

	/** @type {number|null} */
	#debounceTimer = null;

	/**
	 * Returns the shared popup element (singleton), creating it on first call.
	 * Registers global event listeners for close-on-scroll, close-on-outside-click,
	 * Escape key, apage change, and search input (static filtering / AJAX debounce).
	 *
	 * @returns {{el: HTMLDivElement, search: HTMLInputElement, list: HTMLDivElement, footer: HTMLDivElement}}
	 */
	#getPopup() {
		if (this.#popup) return this.#popup;

		const popup = document.createElement("div");
		popup.className = "opus-select-popup";
		popup.style.zIndex = "1070";

		// Prevent Bootstrap modal from stealing focus
		popup.addEventListener("focusin", (e) => e.stopPropagation());

		const searchWrap = document.createElement("div");
		searchWrap.className = "opus-select-search-wrap";

		const search = document.createElement("input");
		search.type = "text";
		search.className = "opus-select-search";
		search.autocomplete = "off";
		search.placeholder = "<?= Opus\controller\lang\Lang::getInstance()->get('opus.select.search.input') ?>";

		const list = document.createElement("div");
		list.className = "opus-select-list";

		const footer = document.createElement("div");
		footer.className = "opus-select-footer";
		footer.style.display = "none";
		footer.innerHTML = this.#loadMoreButton;

		searchWrap.appendChild(search);
		popup.appendChild(searchWrap);
		popup.appendChild(list);
		popup.appendChild(footer);
		document.body.appendChild(popup);

		// load more click
		footer.addEventListener("click", () => {
			if (!this.#activeSelect) return;
			const data = this.#selects.get(this.#activeSelect);
			if (!data || data.source !== "ajax") return;
			this.#showFooterSpinner(footer);
			this.#fetch(this.#activeSelect, this.#popup, data.config, search.value.trim(), data.ajaxOffset, true);
		});

		// close on scroll (but not inside popup list)
		window.addEventListener(
			"scroll",
			(e) => {
				if (!this.#activeSelect) return;
				if (list.contains(e.target)) return;
				this.#close();
			},
			true,
		);

		// close on click outside
		document.addEventListener("mousedown", (e) => {
			if (!this.#activeSelect) return;
			const data = this.#selects.get(this.#activeSelect);
			if (!popup.contains(e.target) && !data?.wrapper.contains(e.target)) {
				this.#close();
			}
		});

		// close on Escape
		document.addEventListener("keydown", (e) => {
			if (e.key === "Escape" && this.#activeSelect) this.#close();
		});

		// close on apage change
		document.addEventListener("apage:closed", () => this.#close());

		this.#popup = { el: popup, search, list, footer };

		// search filtering (static or ajax with debounce)
		search.addEventListener("input", () => {
			if (!this.#activeSelect) return;
			const data = this.#selects.get(this.#activeSelect);

			if (data?.source === "ajax") {
				clearTimeout(this.#debounceTimer);
				this.#debounceTimer = setTimeout(() => {
					this.#showSearchSpinner(search, true);
					this.#fetch(this.#activeSelect, this.#popup, data.config, search.value.trim(), 0, false);
				}, 2000);
				return;
			}

			const term = search.value.toLowerCase().trim();
			const groups = new Set();

			list.querySelectorAll(".opus-select-option").forEach((item) => {
				const match = term === "" || item.textContent.toLowerCase().includes(term);
				item.style.display = match ? "" : "none";
				if (match && item.previousElementSibling?.classList.contains("opus-select-optgroup")) {
					groups.add(item.previousElementSibling);
				}
			});

			list.querySelectorAll(".opus-select-optgroup").forEach((g) => {
				g.style.display = term === "" || groups.has(g) ? "" : "none";
			});
		});

		return this.#popup;
	}

	/**
	 * Fetches options from the server via AJAX (asyncselect API).
	 *
	 * @param {HTMLSelectElement} select - The native select element.
	 * @param {{list: HTMLDivElement, footer: HTMLDivElement, search: HTMLInputElement}} ctx - Context with DOM references.
	 * @param {Object} config - Select configuration.
	 * @param {string} search - Current search term.
	 * @param {number} offset - Pagination offset.
	 * @param {boolean} append - Whether to append results or replace.
	 */
	#fetch(select, ctx, config, search, offset, append) {
		const url = new URLSearchParams({
			api: "asyncselect",
			app: config.app,
			event: config.event,
			limit: config.limit,
			offset: offset,
		});
		if (search) url.set("search", search);

		$.ajax({ url: window.location.pathname + "?" + url.toString(), cache: false })
			.done((result) => {
				try {
					const json = typeof result === "string" ? JSON.parse(result) : result;

					if (!append) ctx.list.innerHTML = "";

					this.#renderAjaxOptions(json, ctx.list, config, select);

					// update offset
					const data = this.#selects.get(select);
					if (data) data.ajaxOffset = offset + config.limit;

					// footer visibility
					const hasMore = json.filtered > offset + config.limit;
					this.#resetFooter(ctx.footer, hasMore);
				} catch {
					this.#showError(ctx.list, append);
					this.#resetFooter(ctx.footer, false);
				}
			})
			.fail(() => {
				this.#showError(ctx.list, append);
				this.#resetFooter(ctx.footer, false);
			})
			.always(() => {
				this.#showSearchSpinner(ctx.search, false);
			});
	}

	/**
	 * Renders AJAX response data into the option list.
	 *
	 * @param {Object} json - Parsed API response with scenario and data.
	 * @param {HTMLDivElement} list - The .opus-select-list container.
	 * @param {Object} config - Select configuration.
	 * @param {HTMLSelectElement} select - The native select element.
	 */
	#renderAjaxOptions(json, list, config, select) {
		const isOptgroup = json.scenario[2] === "1";

		if (isOptgroup) {
			json.data.forEach((group) => {
				const label = document.createElement("div");
				label.className = "opus-select-optgroup";
				label.textContent = group.label;
				list.appendChild(label);

				group.options.forEach((opt) => {
					list.appendChild(this.#createAjaxOptionItem(opt, config, select));
				});
			});
		} else {
			json.data.forEach((opt) => {
				list.appendChild(this.#createAjaxOptionItem(opt, config, select));
			});
		}
	}

	/**
	 * Creates a single option DOM element from AJAX data.
	 * Checks the hidden select for pre-existing selections.
	 *
	 * @param {{value: string, text: string}} opt - Option data from API.
	 * @param {Object} config - Select configuration.
	 * @param {HTMLSelectElement} select - The native select element.
	 * @returns {HTMLDivElement} The option item element.
	 */
	#createAjaxOptionItem(opt, config, select) {
		const item = document.createElement("div");
		item.className = "opus-select-option";
		item.dataset.value = opt.value;

		const isSelected = select ? !!select.querySelector(`option[value="${opt.value}"]`) : false;

		if (config.multiple) {
			const checkbox = document.createElement("input");
			checkbox.type = "checkbox";
			checkbox.className = "form-check-input opus-select-checkbox";
			checkbox.checked = isSelected;
			item.appendChild(checkbox);
		}

		const text = document.createElement("span");
		text.textContent = opt.text;
		item.appendChild(text);

		if (!config.multiple && isSelected) item.classList.add("opus-select-option--selected");

		item.addEventListener("click", () => {
			this.#selectAjaxOption(select, opt, item, config);
		});

		return item;
	}

	/**
	 * Handles selection/deselection of an AJAX-loaded option.
	 * Updates the hidden select, trigger text, and dispatches change event.
	 *
	 * @param {HTMLSelectElement} select - The native select element.
	 * @param {{value: string, text: string}} opt - Option data.
	 * @param {HTMLDivElement} item - The clicked option item element.
	 * @param {Object} config - Select configuration.
	 */
	#selectAjaxOption(select, opt, item, config) {
		if (!select) return;
		const data = this.#selects.get(select);

		if (config.multiple) {
			const checkbox = item.querySelector(".opus-select-checkbox");
			checkbox.checked = !checkbox.checked;

			if (checkbox.checked) {
				const option = new Option(opt.text, opt.value, false, true);
				select.appendChild(option);
			} else {
				const existing = select.querySelector(`option[value="${opt.value}"]`);
				if (existing) existing.remove();
			}

			this.#updateTrigger(select, config, data.wrapper);
		} else {
			select.innerHTML = "";
			const option = new Option(opt.text, opt.value, false, true);
			select.appendChild(option);

			const list = item.closest(".opus-select-list");
			list.querySelector(".opus-select-option--selected")?.classList.remove("opus-select-option--selected");
			item.classList.add("opus-select-option--selected");

			data.wrapper.querySelector(".opus-select-trigger").textContent = opt.text;
			this.#close();
		}

		select.dispatchEvent(new Event("change"));
	}

	/**
	 * Shows or hides the spinner inside the search input wrapper.
	 *
	 * @param {HTMLInputElement} search - The search input element.
	 * @param {boolean} show - Whether to show or hide the spinner.
	 */
	#showSearchSpinner(search, show) {
		let spinner = search.parentElement.querySelector(".opus-select-search-spinner");

		if (show && !spinner) {
			spinner = document.createElement("span");
			spinner.className = "spinner-border spinner-border-sm text-secondary opus-select-search-spinner";
			search.parentElement.appendChild(spinner);
		} else if (!show && spinner) {
			spinner.remove();
		}
	}

	/**
	 * Replaces footer content with a loading spinner.
	 *
	 * @param {HTMLDivElement} footer - The .opus-select-footer element.
	 */
	#showFooterSpinner(footer) {
		footer.innerHTML = '<span class="spinner-border spinner-border-sm text-success"></span>';
	}

	/**
	 * Resets footer to the "Load more" button or hides it.
	 *
	 * @param {HTMLDivElement} footer - The .opus-select-footer element.
	 * @param {boolean} show - Whether to display the footer.
	 */
	#resetFooter(footer, show) {
		footer.style.display = show ? "" : "none";
		if (show) footer.innerHTML = this.#loadMoreButton;
	}

	/**
	 * Displays an error alert inside the option list.
	 *
	 * @param {HTMLDivElement} list - The .opus-select-list container.
	 * @param {boolean} append - If false, clears the list before showing error.
	 */
	#showError(list, append) {
		if (!append) list.innerHTML = "";
		const alert = document.createElement("div");
		alert.className = "alert alert-danger m-2 py-1 px-2";
		alert.style.fontSize = "0.875rem";
		alert.textContent = "<?= Opus\controller\lang\Lang::getInstance()->get('opus.select.error') ?>";
		list.appendChild(alert);
	}

	/**
	 * Creates the dropdown (trigger-based) UI for single/multi mode.
	 * Appends a clickable trigger span to the wrapper.
	 *
	 * @param {HTMLSelectElement} select - The native select element.
	 * @param {Object} config - Select configuration.
	 */
	#createDropdown(select, config) {
		const wrapper = select.previousElementSibling;
		const trigger = document.createElement("span");
		trigger.className = "opus-select-trigger";

		if (config.multiple) {
			wrapper.appendChild(trigger);
			this.#updateTrigger(select, config, wrapper);
		} else {
			trigger.textContent = select.options[select.selectedIndex]?.text || "";
			wrapper.appendChild(trigger);
		}

		wrapper.addEventListener("click", () => {
			if (wrapper.classList.contains("disabled")) return;
			this.#open(select, config);
		});
	}

	/**
	 * Creates the inline visible panel UI with search, list, and footer.
	 * Supports both static rendering and AJAX loading with debounced search.
	 *
	 * @param {HTMLSelectElement} select - The native select element.
	 * @param {Object} config - Select configuration.
	 */
	#createPanel(select, config) {
		const wrapper = select.previousElementSibling;
		wrapper.classList.add("opus-select-wrapper--visible");

		const searchWrap = document.createElement("div");
		searchWrap.className = "opus-select-search-wrap";

		const search = document.createElement("input");
		search.type = "text";
		search.className = "opus-select-search";
		search.autocomplete = "off";
		search.placeholder = "<?= Opus\controller\lang\Lang::getInstance()->get('opus.select.search.input') ?>";
		search.style.display = config.search ? "" : "none";

		const list = document.createElement("div");
		list.className = "opus-select-list";

		const size = select.size || 6;
		list.style.maxHeight = `${size * 32}px`;

		const footer = document.createElement("div");
		footer.className = "opus-select-footer";
		footer.style.display = "none";
		footer.innerHTML = this.#loadMoreButton;

		const ctx = { list, footer, search };

		searchWrap.appendChild(search);
		wrapper.appendChild(searchWrap);
		wrapper.appendChild(list);
		wrapper.appendChild(footer);

		if (config.ajax) {
			this.#fetch(select, ctx, config, "", 0, false);

			footer.addEventListener("click", () => {
				const data = this.#selects.get(select);
				if (!data) return;
				this.#showFooterSpinner(footer);
				this.#fetch(select, ctx, config, search.value.trim(), data.ajaxOffset, true);
			});

			search.addEventListener("input", () => {
				clearTimeout(this.#debounceTimer);
				this.#debounceTimer = setTimeout(() => {
					this.#showSearchSpinner(search, true);
					this.#fetch(select, ctx, config, search.value.trim(), 0, false);
				}, 2000);
			});
		} else {
			this.#renderOptions(select, list, config);

			search.addEventListener("input", () => {
				const term = search.value.toLowerCase().trim();
				const groups = new Set();

				list.querySelectorAll(".opus-select-option").forEach((item) => {
					const match = term === "" || item.textContent.toLowerCase().includes(term);
					item.style.display = match ? "" : "none";
					if (match && item.previousElementSibling?.classList.contains("opus-select-optgroup")) {
						groups.add(item.previousElementSibling);
					}
				});

				list.querySelectorAll(".opus-select-optgroup").forEach((g) => {
					g.style.display = term === "" || groups.has(g) ? "" : "none";
				});
			});
		}
	}

	/**
	 * Opens the shared popup positioned below (or above) the wrapper.
	 * Loads options from DOM (static) or fetches from API (ajax).
	 *
	 * @param {HTMLSelectElement} select - The native select element.
	 * @param {Object} config - Select configuration.
	 */
	#open(select, config) {
		const popup = this.#getPopup();
		const data = this.#selects.get(select);
		const wrapper = data.wrapper;

		if (this.#activeSelect === select) {
			this.#close();
			return;
		}

		this.#activeSelect = select;
		wrapper.classList.add("opus-select-wrapper--open");

		// position with flip-above
		const rect = wrapper.getBoundingClientRect();
		popup.el.style.left = `${rect.left + window.scrollX}px`;
		popup.el.style.minWidth = `${rect.width}px`;
		popup.el.style.width = "auto";

		const estimatedH = 330;
		const spaceBelow = window.innerHeight - rect.bottom;
		const flipAbove = spaceBelow < estimatedH && rect.top > estimatedH;

		if (flipAbove) {
			popup.el.style.top = "auto";
			const docH = document.documentElement.scrollHeight;
			popup.el.style.bottom = `${docH - (rect.top + window.scrollY) + 4}px`;
		} else {
			popup.el.style.bottom = "auto";
			popup.el.style.top = `${rect.bottom + window.scrollY}px`;
		}

		// search visibility
		popup.search.style.display = config.search ? "" : "none";
		popup.search.value = "";

		// populate options
		if (data.source === "ajax") {
			data.ajaxOffset = 0;
			popup.list.innerHTML = "";
			popup.footer.style.display = "none";
			this.#showFooterSpinner(popup.footer);
			popup.footer.style.display = "";
			this.#fetch(select, popup, config, "", 0, false);
		} else {
			popup.footer.style.display = "none";
			this.#renderOptions(select, popup.list, config);
		}

		popup.el.classList.add("opus-select-popup--open");
		if (config.search) popup.search.focus();
	}

	/**
	 * Closes the shared popup and removes the open state from the active wrapper.
	 */
	#close() {
		if (!this.#popup) return;
		this.#popup.el.classList.remove("opus-select-popup--open");

		if (this.#activeSelect) {
			const data = this.#selects.get(this.#activeSelect);
			if (data) data.wrapper.classList.remove("opus-select-wrapper--open");
		}

		this.#activeSelect = null;
	}

	/**
	 * Renders static options from the native select into the given list container.
	 * Handles optgroups and skips data-ftext options in visible mode.
	 *
	 * @param {HTMLSelectElement} select - The native select element.
	 * @param {HTMLDivElement} list - The .opus-select-list container.
	 * @param {Object} config - Select configuration.
	 */
	#renderOptions(select, list, config) {
		list.innerHTML = "";

		Array.from(select.children).forEach((child) => {
			if (child.tagName === "OPTGROUP") {
				const group = document.createElement("div");
				group.className = "opus-select-optgroup";
				group.textContent = child.label;
				list.appendChild(group);

				Array.from(child.children).forEach((option) => {
					if (option.dataset.ftext !== undefined && config.visible) return;
					list.appendChild(this.#createOptionItem(option, config));
				});
			} else if (child.tagName === "OPTION") {
				if (!child.value && child.disabled) return;
				if (child.dataset.ftext !== undefined && config.visible) return;
				list.appendChild(this.#createOptionItem(child, config));
			}
		});
	}

	/**
	 * Creates a single option DOM element from a native <option>.
	 *
	 * @param {HTMLOptionElement} option - The native option element.
	 * @param {Object} config - Select configuration.
	 * @returns {HTMLDivElement} The option item element.
	 */
	#createOptionItem(option, config) {
		const item = document.createElement("div");
		item.className = "opus-select-option";
		item.dataset.value = option.value;
		const isFtext = option.dataset.ftext !== undefined;

		if (config.multiple && !isFtext) {
			const checkbox = document.createElement("input");
			checkbox.type = "checkbox";
			checkbox.className = "form-check-input opus-select-checkbox";
			checkbox.checked = option.selected;
			item.appendChild(checkbox);
		}

		const text = document.createElement("span");
		text.textContent = option.text;
		item.appendChild(text);

		if (!config.multiple && option.selected) item.classList.add("opus-select-option--selected");

		item.addEventListener("click", () => {
			this.#selectOption(option.closest("select"), option, item, config);
		});

		return item;
	}

	/**
	 * Updates the trigger span text based on current selection.
	 * Shows count label when text overflows.
	 *
	 * @param {HTMLSelectElement} select - The native select element.
	 * @param {Object} config - Select configuration.
	 * @param {HTMLDivElement} wrapper - The opus-select-wrapper element.
	 */
	#updateTrigger(select, config, wrapper) {
		const trigger = wrapper.querySelector(".opus-select-trigger");
		if (!trigger) return;
		const selected = Array.from(select.options).filter((o) => o.selected && o.dataset.ftext === undefined);

		if (selected.length === 0) {
			const ftext = Array.from(select.options).find((o) => o.dataset.ftext !== undefined);
			trigger.textContent = ftext?.text || "";
			return;
		}

		trigger.textContent = selected.map((o) => o.text).join(", ");

		if (trigger.scrollWidth > trigger.clientWidth) {
			trigger.textContent =
				selected.length === 1
					? `${selected.length} <?= Opus\controller\lang\Lang::getInstance()->get('opus.select.multi.trigger.singular') ?>`
					: `${selected.length} <?= Opus\controller\lang\Lang::getInstance()->get('opus.select.multi.trigger.plural') ?>`;
		}
	}

	/**
	 * Handles selection/deselection of a static option.
	 * Updates native select state, visual indicators, and dispatches change event.
	 *
	 * @param {HTMLSelectElement} select - The native select element.
	 * @param {HTMLOptionElement} option - The native option element.
	 * @param {HTMLDivElement} item - The clicked option item element.
	 * @param {Object} config - Select configuration.
	 */
	#selectOption(select, option, item, config) {
		if (config.multiple) {
			const checkbox = item.querySelector(".opus-select-checkbox");
			if (checkbox) {
				option.selected = !option.selected;
				checkbox.checked = option.selected;
			}
			this.#updateTrigger(select, config, this.#selects.get(select).wrapper);
		} else {
			Array.from(select.options).forEach((o) => (o.selected = false));
			option.selected = true;

			const list = this.#popup.list;
			list.querySelector(".opus-select-option--selected")?.classList.remove("opus-select-option--selected");
			item.classList.add("opus-select-option--selected");

			const data = this.#selects.get(select);
			data.wrapper.querySelector(".opus-select-trigger").textContent = option.text;

			this.#close();
		}

		select.dispatchEvent(new Event("change"));
	}

	/**
	 * Initializes OpusSelect on all select.form-select-opus elements within the container.
	 * Skips elements that are already bound.
	 *
	 * @param {HTMLElement} container - Parent element to scan for selects.
	 * @param {Object} [options={}] - Override options (visible, search, app, event, limit).
	 */
	static bindSelect(container, options = {}) {
		if (!container) return;
		const instance = this.getInstance();
		const selects = container.querySelectorAll("select.form-select-opus");

		selects.forEach((select) => {
			if (instance.#selects.has(select)) return;

			const config = instance.#readConfig(select, options);
			const { mode, source } = instance.#resolveMode(config);
			const wrapper = instance.#createWrapper(select);

			switch (mode) {
				case "single":
				case "multi":
					instance.#createDropdown(select, config);
					break;
				case "visible":
					instance.#createPanel(select, config);
					break;
			}

			instance.#selects.set(select, { config, mode, source, wrapper, ajaxOffset: 0 });
		});
	}

	/**
	 * Destroys OpusSelect instances within the container, restoring native selects.
	 *
	 * @param {HTMLElement} container - Parent element to scan for bound selects.
	 */
	static destroy(container) {
		const instance = this.getInstance();
		const selects = container.querySelectorAll("select.form-select-opus");

		selects.forEach((select) => {
			const data = instance.#selects.get(select);
			if (!data) return;

			data.wrapper.replaceWith(select);
			instance.#selects.delete(select);
		});
	}
}
