# Opus Setup & Management

## Prerequisites

- PHP 8.2+
- PostgreSQL 15+
- Composer
- System packages: `psql`, `jq`, `sha256sum`
- Apache with `www-data` group

---

## 1. Clone project skeleton

```bash
git clone https://github.com/happ-dev/opus-skeleton.git my-project
cd my-project
```

Provides the project structure: `index.php`, `config/`, `public/`, `apps/`, `sessions/`.

---

## 2. Clone Opus framework

```bash
git clone https://github.com/happ-dev/Opus.git vendor/Opus
```

---

## 3. Install dependencies

```bash
php composer.phar update
```

Generates `vendor/autoload.php` with PSR-4 autoloading for the Opus framework.

---

## 4. Configure

Create `config/global.json` — see [CONFIGURATION_GLOBAL_CONFIG.md](CONFIGURATION_GLOBAL_CONFIG.md) for full reference.

> `config/local.json` will be created automatically by the install script.

---

## 5. Install

```bash
sudo OPUS_LANG=pl ./vendor/Opus/bin/install.sh
```

| Option | Description |
|--------|-------------|
| `OPUS_LANG=en` | English (default) |
| `OPUS_LANG=pl` | Polish |

The script will:

1. Check dependencies (`psql`, `jq`, `sha256sum`)
2. Ask for PostgreSQL connection parameters (host, port, password)
3. Create required directories with correct permissions
4. Generate 4 passwords (or accept custom ones)
5. Create `config/local.json` with database credentials
6. Execute SQL files (roles, database, schemas, tables)
7. Save plaintext passwords to `~/opus_secret.txt`
8. Encrypt `config/local.json` and create `config/secret.key`

> [!IMPORTANT]
> Delete `~/opus_secret.txt` after memorizing the passwords.

### Generated passwords

| Account | Used for |
|---------|----------|
| `opus_admin` | PostgreSQL superuser role |
| `opus_user` | PostgreSQL application role (stored in `local.json`) |
| `root` | Application root user (SHA-256 in `users.users` table) |
| `api` | Application API user (SHA-256 in `users.users` table) |

### Idempotency

The script tracks executed SQL files in `config/install.log`. If interrupted, re-running the script will continue from where it stopped.

---

## 6. Reset

```bash
sudo OPUS_LANG=pl ./vendor/Opus/bin/reset.sh
```

Destructive operation — drops the database, roles, and removes config files.

The script will:

1. Read database name from `config/global.json`
2. Ask to confirm database name and role names (editable)
3. Display what will be deleted and require typing `RESET` to confirm
4. Ask for PostgreSQL connection parameters
5. Terminate active connections and drop database
6. Drop roles (`opus_admin`, `opus_user`)
7. Remove files: `local.json`, `secret.key`, `setup_report.json`, `install.log`

---

## 7. Check

```bash
php index.php setup check
```

Runs system diagnostics and saves report to `config/setup_report.json`.

**Filesystem checks:**
- Required directories exist with correct permissions and group ownership
- `config/local.json` and `config/secret.key` exist with correct permissions

**Database checks:**
- PostgreSQL connection
- Roles: `opus_admin`, `opus_user`
- Schemas: `groups`, `logs`, `users`, `demo`
- Application users: `root`, `api`

Output example:

```
  === Setup Check ===

  Directories:
    [OK]   apps
    [OK]   config
    [FAIL] public/var — perms 0750 (expected 0775)

  Files:
    [OK]   config/local.json
    [OK]   config/secret.key

  Database:
    [OK]   connection
    [OK]   role: opus_admin
    [OK]   role: opus_user
    [OK]   schema: groups
    [OK]   schema: logs
    [OK]   schema: users
    [FAIL] schema: demo

  [FAIL] Issues detected. Run 'php index.php setup repair' to fix.
```

---

## 8. Repair

```bash
sudo php index.php setup repair
```

Fixes filesystem issues based on the report from `check`. If no report exists, runs `check` automatically.

**What it fixes:**
- Missing directories — creates with correct permissions and group
- Wrong permissions — `chmod`
- Wrong group — `chgrp`

**What it cannot fix (informs only):**
- Missing `config/local.json` — "run install.sh"
- Missing `config/secret.key` — "will be generated on next request"
- Database issues — "run reset.sh + install.sh"

Without `sudo`, the script lists issues and displays:

```
  Run 'sudo php index.php setup repair' to fix.
```

---

## Typical workflows

### Fresh installation

```bash
git clone https://github.com/happ-dev/opus-skeleton.git my-project
cd my-project
git clone https://github.com/happ-dev/Opus.git vendor/Opus
php composer.phar update
# edit config/global.json
sudo OPUS_LANG=pl ./vendor/Opus/bin/install.sh
```

### Full reset and reinstall

```bash
sudo OPUS_LANG=pl ./vendor/Opus/bin/reset.sh
sudo OPUS_LANG=pl ./vendor/Opus/bin/install.sh
```

### Diagnose and fix permissions

```bash
php index.php setup check
sudo php index.php setup repair
```

---

## File permissions reference

### Directories

| Directory | Permissions | Group | Description |
|-----------|-------------|-------|-------------|
| `apps/` | 0755 | — | User applications |
| `config/` | 0775 | www-data | Configuration files |
| `public/` | 0775 | www-data | Document root |
| `public/vendor/` | 0775 | — | Vendor libraries |
| `public/vendor/opus/` | 0775 | www-data | Framework files |
| `public/img/` | 0755 | — | Images |
| `public/var/` | 0775 | www-data | Temporary files / uploads |
| `sessions/` | 0775 | www-data | PHP sessions |

### Files

| File | Permissions | Group | Description |
|------|-------------|-------|-------------|
| `config/local.json` | 0660 | www-data | Database credentials (encrypted) |
| `config/secret.key` | 0660 | www-data | Encryption key |
