/**
 * @Project: Opus
 * @Version: 1.0
 * @Author: Tomasz Ułazowski
 * @Date:   2026-06-28 00:13:57
 * @Last Modified by:   Tomasz Ułazowski
 * @Last Modified time: 2026-07-03 16:29:53
 **/

/**
 * Async page class for managing dynamically loaded page content
 *
 * Handles loading, displaying, and closing async pages triggered via sidebar links.
 * Uses event delegation to support dynamically created elements.
 * Communicates with the server via AJAX and expects raw HTML on success
 * or JSON with error details on failure.
 *
 * @class OpusAsyncPage
 * @description Handles asynchronous page loading within the main content area
 *
 * @example
 * // Initialize async page handler (once, in DOMContentLoaded)
 * new OpusAsyncPage();
 *
 * @template AsyncPage Structure
 * Each async page must contain the following HTML structure:
 *
 * - `.async-page-opus` (id="id__opus-async-page")  - Root container
 *   - `.async-page-header-opus`                    - Fixed header (always visible)
 *     - `[data-close="#id__opus-async-page"]`      - Close button
 *     - `.container > .row > .col > h5`            - Header title with icon
 *   - `.async-page-body-opus`                      - Scrollable body content
 *
 * @template Trigger Element
 * Links/buttons that trigger async page loading must have:
 *
 * - `data-apage="{app_name}"` - Application identifier
 * - `data-event="{event_name}"` - Async page event name (key in asyncPage config)
 *
 * @example
 * // Trigger element in sidebar
 * <a href="#" data-apage="demo" data-event="demoOffcanvas">
 *     <i class="bi bi-layout-sidebar-inset"></i> Offcanvas
 * </a>
 *
 * @template Server Response
 * - Success: Raw HTML string matching AsyncPage Structure
 * - Error: JSON object { success: false, message: string, details: string }
 *
 * @template Events
 * - `apage:loaded` - Dispatched on `document` after successful HTML insertion
 *   - `detail.app` {string} - Application identifier (data-apage)
 *   - `detail.event` {string} - Event name (data-event)
 *   - `detail.signal` {AbortSignal} - Signal aborted when async page is closed
 * - `apage:closed` - Dispatched on `document` after async page is closed
 *   - `detail.app` {string} - Application identifier
 *   - `detail.event` {string} - Event name
 *
 * @example
 * // Listen for apage:loaded, use signal inside callback for auto-cleanup
 * document.addEventListener("apage:loaded", (e) => {
 *     if (e.detail.event !== "demoOffcanvas") return;
 *     highlightCode(document.querySelector(".async-page-opus"));
 *     document.addEventListener("apage:closed", () => { ... }, { signal: e.detail.signal });
 * });
 *
 * @template CSS Classes
 * - `.async-page-opus`              - Flex column container, 100% height
 * - `.async-page-header-opus`       - Base header styles (flex-shrink: 0)
 * - `.async-page-header-opus-green` - Success header (green gradient)
 * - `.async-page-header-opus-red`   - Error header (red gradient)
 * - `.async-page-header-opus-black` - Black header variant
 * - `.async-page-body-opus`         - Scrollable body (flex-grow: 1, overflow-y: auto)
 * - `.async-page-overlay-opus`      - Full-screen overlay with spinner during loading
 */
class OpusAsyncPage {
	/**
	 * Creates a new OpusAsyncPage instance and initializes event listeners
	 */
	constructor() {
		this.container = document.querySelector("main");
		this.dashboard = document.querySelector(".dashboard-opus");
		this.activeLink = null;
		this.abortController = null;
		this.init();
	}

	/**
	 * Registers event delegation listeners for async page loading and closing
	 *
	 * @returns {void}
	 */
	init() {
		// Async page loading
		document.addEventListener("click", (event) => {
			const el = event.target.closest("[data-apage]");
			if (!el || el.classList.contains("active")) return;
			event.preventDefault();
			this.load(el);
		});

		// Async page close
		document.addEventListener("click", (event) => {
			const btn = event.target.closest("[data-close]");
			if (!btn) return;
			this.close(btn.dataset.close);
		});
	}

	/**
	 * Loads an async page via AJAX request
	 *
	 * @description Removes any existing async page, shows overlay spinner,
	 * sends AJAX request to server, and inserts returned HTML into main container.
	 * If server returns JSON (error response), displays error template instead.
	 * On success dispatches `apage:loaded` CustomEvent on document.
	 *
	 * @param {Element} el - The trigger element with data-apage and data-event attributes
	 * @returns {void}
	 *
	 * @fires document#apage:loaded
	 */
	load(el) {
		// remove previous
		this.container.querySelector(".async-page-opus")?.remove();

		// update active link
		document.querySelector("[data-apage].active")?.classList.remove("active");
		el.classList.add("active");
		this.activeLink = el;

		// hide dashboard and show overlay
		this.dashboard?.classList.add("dashboard-opus-hidden");
		const overlay = this.showOverlay();

		// build url
		const url = new URLSearchParams();
		url.append("app", el.dataset.apage);
		url.append("event", el.dataset.event);
		const link = window.location.pathname + "?apage=asyncpage&" + url.toString();

		$.ajax({ url: link, cache: false })
			.done((result) => {
				try {
					const response = JSON.parse(result);
					this.ajaxCatch(response);
				} catch {
					this.abortController = new AbortController();
					this.container.insertAdjacentHTML("beforeend", result);
					document.dispatchEvent(
						new CustomEvent("apage:loaded", {
							detail: {
								app: el.dataset.apage,
								event: el.dataset.event,
								signal: this.abortController.signal,
							},
						}),
					);
				}
			})
			.fail(() => {
				this.ajaxFail();
			})
			.always(() => {
				overlay.remove();
			});
	}

	/**
	 * Closes an async page and deactivates the trigger link
	 *
	 * @description Dispatches `apage:closed` CustomEvent on document before removal.
	 * @param {string} selector - CSS selector of the async page element to remove
	 * @returns {void}
	 *
	 * @fires document#apage:closed
	 */
	close(selector) {
		const app = this.activeLink?.dataset.apage ?? null;
		const event = this.activeLink?.dataset.event ?? null;
		this.abortController?.abort();
		this.abortController = null;
		document.dispatchEvent(new CustomEvent("apage:closed", { detail: { app, event } }));
		document.querySelector(selector)?.remove();
		this.dashboard?.classList.remove("dashboard-opus-hidden");
		this.activeLink?.classList.remove("active");
		this.activeLink = null;
	}

	/**
	 * Creates and displays a full-screen overlay with loading spinner
	 *
	 * @returns {HTMLElement} The overlay element (for removal after AJAX completes)
	 */
	showOverlay() {
		const overlay = document.createElement("div");
		overlay.className = "async-page-overlay-opus";
		overlay.innerHTML =
			'<div class="spinner-border text-success" style="width: 5rem; height: 5rem;" role="status"></div>';
		document.body.appendChild(overlay);
		return overlay;
	}

	/**
	 * Handles JSON error responses from the server
	 *
	 * @description Formats error message from server response and displays
	 * the error HTML template in the main container.
	 *
	 * @param {Object} obj - Parsed JSON error response
	 * @param {string} [obj.message] - Error message from server
	 * @param {string} [obj.details] - Additional error details
	 * @returns {void}
	 */
	ajaxCatch(obj) {
		const sanitize = (str) => str.replace(/<(?!\/?(?:em|strong|br|span|i|b)\b)[^>]*>/gi, "");
		const message = obj?.message || "";
		const details = obj?.details || "";
		const errorMessage = details ? `${sanitize(message)}<br>${sanitize(details)}` : sanitize(message);
		this.container.insertAdjacentHTML("beforeend", this.errorHtmlTemplate(errorMessage));
	}

	/**
	 * Handles AJAX request failures (network errors, 500, etc.)
	 *
	 * @description Displays the global http500 error message using error template.
	 * @returns {void}
	 */
	ajaxFail() {
		this.container.insertAdjacentHTML("beforeend", this.errorHtmlTemplate(http500));
	}

	/**
	 * Generates HTML template for error state display
	 *
	 * @description Builds a complete async page structure with red error header,
	 * close button, and alert message in the body.
	 *
	 * @param {string} message - HTML error message content to display
	 * @returns {string} Complete HTML string for error async page
	 */
	errorHtmlTemplate(message) {
		return `
		<div class="async-page-opus bs-opus-red-3d" id="id__opus-async-page">
			<div class="async-page-header-opus async-page-header-opus-red">
				<div class="position-absolute btn-close-x">
					<button type="button" class="btn btn-dark btn-sm bs-opus-black" data-close="#id__opus-async-page">
						<i class="bi bi-x-lg"></i>
					</button>
				</div>
				<div class="container">
					<div class="row">
						<div class="col">
							<h5 style="margin-bottom: 0;">
								<span class="me-1 ms-0 badge bg-opus-black bs-opus-black fs-5">
									<i class="bi bi-exclamation-triangle"></i>
								</span>
								<span class="me-2">Error</span>
							</h5>
						</div>
					</div>
				</div>
			</div>
			<div class="async-page-body-opus">
				<div class="container-fluid">
					<div class="row async-page-alerts">
						<div class="col">
							<div class="alert alert-danger bs-opus-red-3d" style="word-break: normal">${message}</div>
						</div>
					</div>
				</div>
			</div>
		</div>`;
	}
}
