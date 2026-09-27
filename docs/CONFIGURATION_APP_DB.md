# App Database Setup

CLI commands for managing application SQL schemas. Requires `role = dev` in `global.json`.

---

## SQL file structure

Place SQL files in `apps/{app-name}/sql/`:

```
apps/homefinances/sql/
├── 00-schema.sql        # schema + grants
├── 01-currency.sql      # tables
├── 02-accounts.sql
├── ...
├── 97-functions.sql     # stored functions
├── 98-migration.sql     # data migration (optional)
└── 99-triggers.sql      # triggers
```

### Naming convention

Files must match the pattern `{dd}-{name}.sql` where `{dd}` is a two-digit number:

| Range | Purpose |
|-------|---------|
| `00`–`89` | Tables, views, data |
| `97` | Functions |
| `98` | Migration (special — prompted separately) |
| `99` | Triggers |

Files are executed in alphabetical order (00, 01, ..., 99).

---

## SQLDoc header

The first SQL file (`00-schema.sql`) contains a header block that declares the connection mode and variables:

```sql
-- =============================================================================
-- Schema: homefinances
-- Description: Home-Finances application schema.
--
-- Connection: temporary
--   {Database admin user}:$db_user$
--   {Database admin password}:$db_password$
--
-- Variables:
--   {Old server hostname or IP}:$host$
--   {Old server database name}:$dbname$
--   {Old server database user}:$user$
--   {Old server database password}:$password$
-- =============================================================================
```

### Connection mode

Declared via `-- Connection:` line:

| Value | Description |
|-------|-------------|
| `temporary` | Prompts for admin credentials (e.g. `postgres`). Creates a temporary config entry, uses it for all SQL execution, removes it after. |
| `default` | Uses existing storage config from `global.json`/`local.json`. No additional prompts. |

Typical use: `temporary` for schema creation (requires `opus_admin` with CREATE/GRANT privileges), `default` for data-only operations.

### Variables

Declared in the `-- Variables:` block. Each line defines a prompt label and a variable name:

```
--   {Display text for user prompt}:$variable_name$
```

Variables are substituted in SQL content via `str_replace` before execution. They are embedded values in queries (e.g. dblink connection parameters), not connection credentials.

If a variable name contains `password` — input is masked. If it contains `host` — input is validated (IP/hostname + `fsockopen` on port 5432). If it contains `user` — defaults to `postgres`.

> [!NOTE]
> Variables are only prompted when a migration file (`98-*.sql`) is present and the user confirms migration execution.

---

## sql create

Executes all SQL files for an application.

```bash
php index.php setup sql create {app-name}
```

### What it does

1. Prompts for database admin credentials (user, password, database name)
2. Scans `apps/{app-name}/sql/` for matching files
3. If `98-*.sql` exists — asks whether to execute migration `[y/N]`
4. If migration confirmed — parses `-- Variables:` block and prompts for values
5. Displays preview table (File + Execute columns)
6. Executes files in order via `Db::dbExec()` with variable substitution
7. Stops on first failure — remaining files are marked FAIL
8. Displays summary table (File + Result columns)
9. Logs results to `public/var/{app-name}_sql.log`

### Output example

```
  Database admin user [postgres]:
  Database admin password:
  Database name [happ]:

  Migration file found. Execute migration? [y/N]: y

  Old server hostname or IP    : 192.168.1.100
  Host reachable: 192.168.1.100
  Old server database name     : happ
  Old server database user     [postgres]:
  Old server database password :

  ┌────────────────────┬─────────┐
  │ File               │ Execute │
  ├────────────────────┼─────────┤
  │ 00-schema.sql      │ yes     │
  │ 01-currency.sql    │ yes     │
  │ ...                │ ...     │
  │ 98-migration.sql   │ yes     │
  │ 99-triggers.sql    │ yes     │
  └────────────────────┴─────────┘

  ┌────────────────────┬────────┐
  │ File               │ Result │
  ├────────────────────┼────────┤
  │ 00-schema.sql      │ OK     │
  │ 01-currency.sql    │ OK     │
  │ ...                │ ...    │
  │ 98-migration.sql   │ OK     │
  │ 99-triggers.sql    │ OK     │
  └────────────────────┴────────┘
```

With migration declined, `98-*.sql` shows `SKIP` in yellow.

### Idempotency

The command tracks executed files in `public/var/{app-name}_sql.log`. On re-run, files with `[OK]` status in the log are automatically skipped:

```
  Files already executed (skipping): 9

  ┌────────────────────┬─────────┐
  │ File               │ Execute │
  ├────────────────────┼─────────┤
  │ 00-schema.sql      │ no      │
  │ ...                │ ...     │
  │ 98-migration.sql   │ no      │
  │ 99-triggers.sql    │ yes     │
  └────────────────────┴─────────┘
```

Use `--force` to ignore the log and re-execute all files:

```bash
php index.php setup sql create {app-name} --force
```

> [!TIP]
> If execution fails mid-way, fix the SQL error and re-run the command. Already-executed files will be skipped automatically. Use `--force` only when you need to re-execute everything from scratch (after `sql reset`).

---

## sql reset

Drops the entire application schema with CASCADE.

```bash
php index.php setup sql reset {app-name}
```

### What it does

1. Prompts for database admin credentials
2. Parses schema name from `00-schema.sql` (`CREATE SCHEMA` statement)
3. Verifies schema exists in database
4. Displays warning and requires typing the exact schema name to confirm
5. Executes `DROP SCHEMA IF EXISTS "{schema}" CASCADE`
6. Removes `public/var/{app-name}_sql.log` if present
7. Cleans up temporary DB config

### Output example

```
  Database admin user [postgres]:
  Database admin password:
  Database name [happ]:

  ⚠ WARNING: This will permanently destroy:
    Schema: homefinances
    Tables, views, functions, triggers — ALL will be dropped (CASCADE)

  Type schema name to confirm: homefinances

  DROP SCHEMA homefinances CASCADE   [OK]
  Remove homefinances_sql.log        [OK]

  Schema dropped successfully.
```

> [!CAUTION]
> This is irreversible. All tables, views, functions, triggers, and data in the schema will be permanently deleted.

---

## Typical workflows

### First-time setup

```bash
php index.php setup sql create homefinances
```

### Reset and recreate

```bash
php index.php setup sql reset homefinances
php index.php setup sql create homefinances
```

### Resume after failure

```bash
# fix the SQL file that caused the error, then:
php index.php setup sql create homefinances
# already-executed files are skipped automatically
```

### Force full re-execution

```bash
php index.php setup sql reset homefinances
php index.php setup sql create homefinances --force
```

---

## Log format

File: `public/var/{app-name}_sql.log`

```
2026-09-26 14:30:01 [OK] 00-schema.sql
2026-09-26 14:30:01 [OK] 01-currency.sql
2026-09-26 14:30:02 [FAIL] 02-accounts.sql
ERROR:  relation "homefinances.accounts" already exists
```

The log is append-only. Deleted automatically by `sql reset`.
