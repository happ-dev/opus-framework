# Global & Local Configuration

File locations:
- `config/global.json` — main application configuration
- `config/local.json` — database credentials (auto-encrypted on first run)

---

## `config/global.json`

---

### `apps` — registered applications (required)

| Type | Validation | Description |
|------|------------|-------------|
| array of strings | Lowercase letters, numbers, underscores only. Length: 2–50 chars. | List of user app names to load |

```json
"apps": ["hello", "demo"]
```

> Framework apps (`skeleton`, `settings`, `profile`, `logs`, `demo`) are loaded automatically from `vendor/Opus/apps/`.

---

### `storage` — database connections (required)

Array of named database connection objects. Each object key is the connection name.

**In `global.json`** (public settings):

| Field | Type | Required | Validation | Description |
|-------|------|----------|------------|-------------|
| `type` | string | ✅ | `pgsql`, `ibmodbc`, or `mysql` | Database driver |
| `name` | string | ✅ | — | Database name |
| `encoding` | string | ❌ | Pattern: `UTF8`, `LATIN1`–`LATIN9`, `ASCII`, `UNICODE`, `WIN1250`–`WIN1252`, `ISO-8859-*`, `CP***` | Character encoding |

```json
"storage": [
	{
		"happ": {
			"type": "pgsql",
			"name": "happ",
			"encoding": "UTF8"
		}
	}
]
```

**In `local.json`** (credentials — auto-encrypted on first run):

| Field | Type | Required | Validation | Description |
|-------|------|----------|------------|-------------|
| `user` | string | ✅ | Non-empty (encrypted after first run) | Database username |
| `pass` | string | ✅ | Non-empty (encrypted after first run) | Database password |
| `host` | string | ✅ | `localhost` or valid IP address | Database host |
| `port` | string | ✅ | Integer `1`–`65535` | Database port |

```json
"storage": [
	{
		"happ": {
			"user": "happ_user",
			"pass": "happ_password",
			"host": "localhost",
			"port": "5432"
		}
	}
]
```

> On first run, `user`, `pass`, `host`, and `port` are encrypted and a `config/secret.key` file is created.

---

### `navbar` — navigation bar settings (required)

| Field | Type | Required | Validation | Description |
|-------|------|----------|------------|-------------|
| `brand_icon` | string | ✅ | Pattern: `dir/filename.(ico|png|svg|jpg|jpeg|gif)` | Brand icon path (relative to `public/`) |
| `brand_text` | string | ✅ | 2–50 chars, letters/numbers/underscores, spaces allowed between words | Brand display text |
| `login_form` | string | ✅ | `yes`, `true`, `no`, or `false` (converted to boolean internally) | Show login form in navbar |

```json
"navbar": {
	"brand_icon": "img/app-indicator-icon-happ.svg",
	"brand_text": "Opus",
	"login_form": "yes"
}
```

---

### `languages` — supported languages (required)

| Type | Validation | Description |
|------|------------|-------------|
| array of strings | Supported codes with optional region | Languages available in the application |

Supported languages and regions:

| Code | Language | Regions |
|------|----------|---------|
| `en` | English | `US`, `GB`, `AU` |
| `pl` | Polski | `PL` |

```json
"languages": ["pl", "en"]
```

With region:
```json
"languages": ["pl-PL", "en-GB"]
```

---

### `role` — application environment (required)

| Type | Validation | Description |
|------|------------|-------------|
| string | `prod` or `dev` | Application role/environment |

```json
"role": "dev"
```

- `dev` — CSS/JS files regenerated every request
- `prod` — CSS/JS files cached, regenerated only if missing or older than one week

---

### `icon` — application icon (required)

| Type | Validation | Description |
|------|------------|-------------|
| string | Pattern: `dir/filename.(ico|png|svg|jpg|jpeg|gif)` | Favicon / app icon path |

```json
"icon": "img/app-indicator-icon-happ.svg"
```

---

### `title` — application title (required)

| Type | Validation | Description |
|------|------------|-------------|
| string | Non-empty | HTML page title |

```json
"title": "hApp"
```

---

### `vendor` — global vendor libraries (required)

| Type | Validation | Description |
|------|------------|-------------|
| array of strings | Pattern: `directory/filename.(css|js)`, file must exist in `public/vendor/` | CSS/JS vendor files loaded on every page |

```json
"vendor": [
	"bootstrap/bootstrap.min.css",
	"bootstrap/bootstrap-happ.css",
	"bootstrap/bootstrap-icons.min.css",
	"datatables/datatables.min.css",
	"jquery/jquery.min.js",
	"jquery/jquery.mask.min.js",
	"popperjs/popper.min.js",
	"bootstrap/bootstrap.min.js",
	"datatables/datatables.min.js",
	"moment/moment.min.js",
	"moment/locale/pl.js",
	"tempusdominus/tempus-dominus.min.js"
]
```

---

### `email` — admin email (required)

| Type | Validation | Description |
|------|------------|-------------|
| string | Valid email address | Administrator contact email |

```json
"email": "admin@opus.dev"
```

---

### `trusted_hosts` — allowed hostnames (required)

| Type | Validation | Description |
|------|------------|-------------|
| array of strings | Valid domain names or `localhost`, max 253 chars, no duplicates | Hosts allowed to access the application |

```json
"trusted_hosts": ["localhost", "opus.dev"]
```

---

## `config/local.json`

Contains only the `storage` section with database credentials. See [storage](#storage--database-connections-required) above.

```json
{
	"storage": [
		{
			"happ": {
				"user": "happ_user",
				"pass": "happ_password",
				"host": "localhost",
				"port": "5432"
			}
		}
	]
}
```

> After first run, credentials are encrypted automatically. Do not edit encrypted values manually.

---

## Full `global.json` example

```json
{
	"apps": [
		"hello", "demo"
	],

	"storage": [
		{
			"happ": {
				"type": "pgsql",
				"name": "happ",
				"encoding": "UTF8"
			}
		}
	],

	"navbar": {
		"brand_icon": "img/app-indicator-icon-happ.svg",
		"brand_text": "Opus",
		"login_form": "yes"
	},

	"languages": ["pl", "en"],

	"role": "dev",

	"icon": "img/app-indicator-icon-happ.svg",

	"title": "hApp",

	"vendor": [
		"bootstrap/bootstrap.min.css",
		"bootstrap/bootstrap-happ.css",
		"bootstrap/bootstrap-icons.min.css",
		"datatables/datatables.min.css",
		"jquery/jquery.min.js",
		"jquery/jquery.mask.min.js",
		"popperjs/popper.min.js",
		"bootstrap/bootstrap.min.js",
		"datatables/datatables.min.js",
		"moment/moment.min.js",
		"moment/locale/pl.js",
		"tempusdominus/tempus-dominus.min.js"
	],

	"email": "admin@opus.dev",

	"trusted_hosts": ["localhost"]
}
```
