# App Configuration

File location: `apps/{{app_name}}/config/{{app_name}}.config.json`

---

## Required sections

Every app config must contain these 4 sections: `app`, `route`, `view`, `js`.

---

## `app` — application metadata (required)

| Field | Type | Required | Validation | Description |
|-------|------|----------|------------|-------------|
| `type` | string | ✅ | `page`, `api`, or `cli` | Application type |
| `class` | string | ✅ | Pattern: `apps\\name\\src\\ClassName` | Main controller class (double backslash in JSON) |
| `access` | int | ✅ | `0`–`9` | Minimum access level required |
| `version` | string | ✅ | Non-empty string | App version |
| `description` | string | ✅ | Any string | App description |

```json
"app": {
	"type": "page",
	"class": "apps\\hello\\src\\HelloController",
	"access": 3,
	"version": "0.2-alpha.1",
	"description": "hello world app"
}
```

---

## `route` — URL routes (required)

| Type | Validation | Description |
|------|------------|-------------|
| array of strings | Non-empty array, all elements must be non-empty strings | URL path segments that map to this app |

```json
"route": ["hello"]
```

Multiple routes:
```json
"route": ["hello", "hello-world"]
```

---

## `view` — view templates (required)

| Field | Type | Required | Pattern | Description |
|-------|------|----------|---------|-------------|
| `index` | string | ✅ | `apps/name/view/name.phtml` | Main view template |
| `layout` | string | ❌ | `apps/name/view/layout.phtml` | Custom layout (overrides global) |
| `error` | string | ❌ | `apps/name/view/error.phtml` | Custom error page |
| `css` | string | ❌ | `apps/name/css/name.css` | App-level CSS file |

```json
"view": {
	"index": "apps/hello/view/hello.phtml",
	"layout": "apps/hello/view/layout.phtml",
	"error": "apps/hello/view/error.phtml",
	"css": "apps/hello/css/hello.css"
}
```

---

## `js` — JavaScript files (required)

| Field | Type | Required | Pattern | Description |
|-------|------|----------|---------|-------------|
| `index` | string | ✅ | `apps/name/js/name.js` | Main JS file |
| `layout` | string | ❌ | `apps/name/js/layout.js` | Layout-level JS |
| `error` | string | ❌ | `apps/name/js/error.js` | Error page JS |

```json
"js": {
	"index": "apps/hello/js/hello.js",
	"layout": "apps/hello/js/layout.js",
	"error": "apps/hello/js/error.js"
}
```

---

## Optional sections

---

## `nav` — navigation menu entry (optional)

| Field | Type | Required | Validation | Description |
|-------|------|----------|------------|-------------|
| `type` | string | ✅ | `menu` or `submenu` | Navigation type |
| `disabled` | bool | ✅ | Boolean value | Whether nav item is disabled |
| `id` | string | ✅ | Pattern: `###_nav`, `###_usr`, or `###_dropdown` | Unique nav identifier (3 digits + suffix) |
| `name` | string | ✅ | Non-empty string | Display name in navigation |
| `icon` | string | ✅ | Non-empty string | Bootstrap icon class |
| `route` | string | ❌ | Auto-set to last element of `route` array if omitted | Navigation link target |

```json
"nav": {
	"type": "menu",
	"disabled": false,
	"id": "001_nav",
	"name": "Hello-World",
	"icon": "bi-bootstrap"
}
```

Other `id` formats:
- `"010_usr"` — user menu item
- `"020_dropdown"` — dropdown menu item

---

## `vendor` — additional vendor libraries (optional)

| Type | Validation | Description |
|------|------------|-------------|
| array of strings | Pattern: `directory/filename.(css|js)`, file must exist in `public/vendor/` | Extra CSS/JS vendor files for this app |

```json
"vendor": [
	"highlight/hljs.opus.css",
	"highlight/highlight.min.js"
]
```

Defaults to empty array `[]` if omitted.

---

## `idTableEvent` — DataTable event identifier (optional)

| Type | Validation | Description |
|------|------------|-------------|
| string | Pattern: `id__[alphanumeric-]+-event-dt` | HTML id for the DataTable event container |

```json
"idTableEvent": "id__hello-event-dt"
```

---

## `injectEvent` — HTML injection events (optional)

Object where each key is an event name and value is an object with a `file` field.

| Field | Type | Required | Pattern | Description |
|-------|------|----------|---------|-------------|
| `file` | string | ✅ | `apps/name/view/inject/name.phtml` | Path to injectable view template |

```json
"injectEvent": {
	"vthead": {
		"file": "apps/hello/view/inject/vthead.phtml"
	},
	"editForm": {
		"file": "apps/hello/view/inject/editForm.phtml"
	}
}
```

---

## `asyncPage` — async page definitions (optional)

Object where each key is a page name.

| Field | Type | Required | Validation | Description |
|-------|------|----------|------------|-------------|
| `type` | string | ✅ | Must be `"apage"` | Always "apage" |
| `access` | int | ✅ | `0`–`9` | Minimum access level |
| `view` | string | ✅ | Pattern: `(vendor/Opus/)?apps/name/src/path/File.php` | View handler file |
| `class` | string | ✅ | Pattern: `(Opus\\)?apps\\name\\src\\(subdir\\)?ClassName` | View handler class |

```json
"asyncPage": {
	"world": {
		"type": "apage",
		"access": 3,
		"view": "apps/hello/src/world/WorldPage.php",
		"class": "apps\\hello\\src\\world\\World"
	}
}
```

Vendor (framework-level) async page:
```json
"asyncPage": {
	"settings": {
		"type": "apage",
		"access": 1,
		"view": "vendor/Opus/apps/settings/src/SettingsPage.php",
		"class": "Opus\\apps\\settings\\src\\SettingsPage"
	}
}
```

---

## `tableEvent` — DataTable event configurations (optional)

Object where each key is a table event name.

| Field | Type | Required | Validation | Description |
|-------|------|----------|------------|-------------|
| `primaryKey` | string | ✅ | Pattern: `id__[prefix][suffix]` (e.g. `id__hello`) | Primary key column name |
| `table` | string | ✅ | Pattern: `schema.table_name` (e.g. `public.hello`) | Database table (schema-qualified) |
| `columns` | array or bool | ✅ | Non-empty array or boolean | Column definitions (`true` = all columns) |
| `access` | object | ✅ | See access table below | Access levels for CRUD operations |
| `join` | string or false | ❌ | — | SQL JOIN clause or `false` |
| `select` | object | ❌ | — | Select/dropdown data sources |
| `disabled` | object | ❌ | — | Fields to disable (key = field, value = "true") |
| `buttons` | object | ❌ | — | Custom button definitions |

**`access` object:**

| Field | Type | Required | Validation | Description |
|-------|------|----------|------------|-------------|
| `show` | int | ✅ | `0`–`9` | Access level to view table |
| `add` | int | ❌ | `0`–`9` | Access level to add rows |
| `edit` | int | ❌ | `0`–`9` | Access level to edit rows |
| `delete` | int | ❌ | `0`–`9` | Access level to delete rows |

```json
"tableEvent": {
	"hello": {
		"primaryKey": "id__hello",
		"table": "public.hello",
		"columns": [
			{ "db": "id__hello" },
			{ "db": "name" },
			{ "db": "status" }
		],
		"join": "LEFT JOIN public.world ON (world.id_to_hello = hello.id__hello)",
		"select": {
			"status_db": "SELECT id__value, text FROM public.statuses",
			"status_static": {
				"value": ["1", "2"],
				"text": ["Active", "Inactive"]
			}
		},
		"disabled": {
			"name": "true"
		},
		"buttons": {
			"action": {
				"text": "hello.action",
				"icon": "bi-person-x",
				"attributes": {
					"data-bs-toggle": "modal",
					"data-bs-target": "#id__hello-dt"
				}
			}
		},
		"access": {
			"show": 0,
			"add": 3,
			"edit": 6,
			"delete": 9
		}
	}
}
```

---

## `asyncEvent` — async API event configurations (optional)

Object where each key is an event name.

| Field | Type | Required | Validation | Description |
|-------|------|----------|------------|-------------|
| `type` | string | ✅ | Must be `"api"` | Always "api" |
| `access` | int | ✅ | `0`–`9` | Minimum access level |
| `file` | string | ✅ | Pattern: `(vendor/Opus/)?apps/name/src/path/NameApi.php` | API handler file path |
| `class` | string | ✅ | Pattern: `(Opus\\)?apps\\name\\src\\(subdir\\)?NameApi` | API handler class (must end with `Api`) |

```json
"asyncEvent": {
	"world": {
		"type": "api",
		"access": 2,
		"file": "apps/hello/src/world/WorldApi.php",
		"class": "apps\\hello\\src\\world\\WorldApi"
	}
}
```

Vendor (framework-level) async event:
```json
"asyncEvent": {
	"settings": {
		"type": "api",
		"access": 1,
		"file": "vendor/Opus/apps/settings/src/SettingsApi.php",
		"class": "Opus\\apps\\settings\\src\\SettingsApi"
	}
}
```

---

## Full example (all options)

```json
{
	"app": {
		"type": "page",
		"class": "apps\\hello\\src\\HelloController",
		"access": 3,
		"version": "1.0.0",
		"description": "hello world app"
	},

	"route": ["hello"],

	"nav": {
		"type": "menu",
		"disabled": false,
		"id": "001_nav",
		"name": "Hello-World",
		"icon": "bi-bootstrap"
	},

	"view": {
		"index": "apps/hello/view/hello.phtml",
		"layout": "apps/hello/view/layout.phtml",
		"error": "apps/hello/view/error.phtml",
		"css": "apps/hello/css/hello.css"
	},

	"js": {
		"index": "apps/hello/js/hello.js",
		"layout": "apps/hello/js/layout.js",
		"error": "apps/hello/js/error.js"
	},

	"vendor": [
		"highlight/hljs.opus.css",
		"highlight/highlight.min.js"
	],

	"idTableEvent": "id__hello-event-dt",

	"injectEvent": {
		"vthead": {
			"file": "apps/hello/view/inject/vthead.phtml"
		}
	},

	"asyncPage": {
		"world": {
			"type": "apage",
			"access": 3,
			"view": "apps/hello/src/world/WorldPage.php",
			"class": "apps\\hello\\src\\world\\World"
		}
	},

	"tableEvent": {
		"hello": {
			"primaryKey": "id__hello",
			"table": "public.hello",
			"columns": [
				{ "db": "id__hello" },
				{ "db": "name" },
				{ "db": "status" }
			],
			"access": {
				"show": 0,
				"add": 3,
				"edit": 6,
				"delete": 9
			}
		}
	},

	"asyncEvent": {
		"world": {
			"type": "api",
			"access": 2,
			"file": "apps/hello/src/world/WorldApi.php",
			"class": "apps\\hello\\src\\world\\WorldApi"
		}
	}
}
```

---

## Minimal example (only required sections)

```json
{
	"app": {
		"type": "page",
		"class": "apps\\hello\\src\\HelloController",
		"access": 0,
		"version": "1.0",
		"description": ""
	},

	"route": ["hello"],

	"view": {
		"index": "apps/hello/view/hello.phtml"
	},

	"js": {
		"index": "apps/hello/js/hello.js"
	}
}
```
