# Changelog

## TODO

### Bugs
- [x] `ValidateGlobalConfig::validateStorageConfig()` — `host` validation only checks IP (`FILTER_VALIDATE_IP`), should also accept hostnames (e.g. Docker container names)

### Plans
- [ ] Setup CLI — `php index.php setup` ([docs/PLAN_SetupCli.md](PLAN_SetupCli.md))
- [x] CSP Nonce — ([docs/PLAN_CspNonce.md](PLAN_CspNonce.md))
- [x] InjectHtml — ([docs/PLAN_InjectHtml.md](PLAN_InjectHtml.md))
- [x] OpusSelect — ([docs/PLAN_OpusSelect.md](PLAN_OpusSelect.md))
- [x] StandardFormElements — ([docs/PLAN_StandardFormElements.md](PLAN_StandardFormElements.md))

### Config class
- [ ] `Config::setTemporaryDbConfig()` — add a temporary runtime storage config entry (e.g. `_setup_temp` for `setup sql create`)
- [ ] `Config::removeTemporaryDbConfig()` — remove a temporary runtime storage config entry (cleanup after execution)

### JS
- [ ] Chart library (chart.js + optional API for data fetching)
- [ ] Copy to clipboard — click icon to copy text (e.g. table cell, code block)

## Completed (v1.0)

- [x] Adjust directory/namespace to current changes
- [x] Required _public_ directory structure
- [x] CSS
	- [x] _opus.css_ file copied to _public/vendor/opus_
	- [x] Design a light/dark view
	- [x] App-level CSS file support
- [x] Config class
	- [x] Adapting the class to the new version of the configuration file
	- [x] `"role": "prod|dev"` default is prod
	- [x] `"vendor:" ...` file path validation
- [x] Opus\view\login\Login class
	- [x] Rewriting Login class using StandardFormElements
- [x] Lang class
	- [x] Handle messages depending on the selected language
- [x] Query class
	- [x] SELECT
- [x] Subpages loaded asynchronously
	- [x] AsyncPage class
	- [x] Asynchronous functions for loading subpage content in _global.js_
	- [x] Renamed _sApp_ to _asyncPage_ in config
	- [x] Event class handles asyncpage loading
	- [x] asyncAction(): mixed in InterfaceController
	- [x] Request class, TYPE_ASYNC_PAGE = 'apage'
- [x] New internal app: _demo_
	- [x] Sidebar, Modal, Offcanvas, Collapse, Buttons
	- [x] SQL for table event
	- [x] Table with asyncTable event
	- [x] OpusSelect — replacing Select2
	- [x] OpusDatePicker — replacing TempusDominus
- [x] JS
	- [x] Generating _opus.js_ from _Opus/js_ files
	- [x] _opus.js_ copied to _public/vendor/opus_
	- [x] _{{app_name}}.lib.js_ compiled and copied
	- [x] JS files from config added at end of `<body>`
	- [x] Adjust scanFiles function
	- [x] OpusSelect (ajax, search, selectEvent)
	- [x] OpusDatePicker
	- [x] Light/dark view toggle from navbar
- [x] Layout class — offcanvas html (not applicable — navbar offcanvas has different structure)
- [x] View class — finished while writing the _Demo_ application
- [x] Internal apps
	- [x] Settings - user panel settings with the ability to change for root
	- [x] Logs - search engine for all saved events
	- [x] Profile - a place where the user can change their data and reset their password
- [x] Known issues
	- [x] Navbar incorrectly displays icons in resolutions other than the default browser resolution
	- [x] Offcanvas-header - after changing the theme to dark, the text color remains black
	- [x] Opening the second page in the browser changes csrf token, which causes an error in the first page opened
	- [x] CSP blocks inline style attributes (`style-src 'self'`) — added `'unsafe-inline'` to `style-src`
	- [x] CSP blocks Bootstrap's inline SVG data URIs in CSS variables (`img-src 'self'`) — added `data:` to `img-src`
	- [x] `AbstractController::setServerTimezone()` — `/etc/timezone` missing on Debian 13 (Trixie) — added `readlink('/etc/localtime')` fallback
	- [x] `CliArguments::langFile()` — CLI help read from `{lang}_{app}.json` incompatible with `Lang` class — renamed to `errorFile()`, reads from optional `{lang}_error.json` per app, falls back to central `{lang}_error.json`
