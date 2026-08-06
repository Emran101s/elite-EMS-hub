# Postgres cutover plan (environment only)

**Status:** proposal — Cursor owns the environment; Claude owns any schema
delta required for Postgres quirks. This file does **not** change
`phpunit.xml`, does **not** add migrations, and does **not** flip
`DB_CONNECTION` in committed defaults.

Tenancy slices 1–2 are already on `main` (`tenants` / `workspaces` /
`workspace_user`, plus `tenant_id` on customer tables). The app still runs
on SQLite in Herd today. Compose and CI already provide Postgres 16 — this
document is how we point the app at it without breaking Claude's remaining
slices (global scope, role move, auth rework).

---

## Goals

1. Staging and production run on **Postgres 16**, not SQLite.
2. Local Herd can stay on SQLite for day-to-day UI work, **or** optionally
   point at Compose Postgres — both must be documented and supported.
3. CI keeps a **reachable** Postgres service (already true) and, when Claude
   is ready, can run a second job (or matrix) against it — without removing
   the in-memory SQLite suite until that job is green.
4. One rehearsed **data copy** from the live SQLite file into Postgres before
   any production cutover.

Non-goals for this doc: writing the copy script as a migration, changing
`phpunit.xml`, or altering `BelongsToTenant` / policies.

---

## Current state (as of #16)

| Surface | Database today |
|---|---|
| Herd `.env` | `DB_CONNECTION=sqlite` → `database/database.sqlite` |
| `.env.example` | sqlite (local template) |
| `.env.staging.example` / `.env.production.example` | **pgsql** (already) |
| `phpunit.xml` | sqlite `:memory:` — **leave alone until Claude agrees** |
| CI `Test suite` | Postgres 16 + Redis 7 service containers; suite still hits sqlite |
| `docker-compose.yml` | `postgres:16-alpine` + healthcheck |

---

## Connection config (no schema)

### Staging / production

Already spelled out in `.env.staging.example` and `.env.production.example`:

```env
DB_CONNECTION=pgsql
DB_HOST=postgres          # Compose service name; or the managed host
DB_PORT=5432
DB_DATABASE=elitehub_staging   # / elitehub
DB_USERNAME=elitehub
DB_PASSWORD=…
```

PHP image already enables `pdo_pgsql` / `pgsql`. Herd PHP needs the
`pgsql` extension installed once (`herd` / pecl) if developers point local
PHP at Compose Postgres.

### Local Herd → Compose Postgres (optional)

```bash
docker compose up -d postgres redis
```

In `~/Herd/elitehub/.env` (never committed):

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=elitehub
DB_USERNAME=elitehub
DB_PASSWORD=elitehub
```

Then:

```bash
php artisan migrate --force          # Claude's migrations, already on main
php artisan db:seed --class=…        # only if agreed for that environment
```

Keep a copy of the sqlite file before flipping:

```bash
./scripts/db-backup.sh
```

### Redis (same cutover window)

Staging/production examples already use `SESSION_DRIVER=redis`,
`QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`. Turn those on in the same
change-window as Postgres so the database is not also carrying sessions /
jobs / cache.

---

## CI test database

**`phpunit.xml` is still sqlite `:memory:`.** That is intentional.

| Job | Name | Database |
|---|---|---|
| `tests` | `Test suite` | sqlite in-memory (required check today) |
| `tests_pgsql` | `Test suite (pgsql)` | Postgres 16 service `elitehub_test` |

The pgsql job overrides `DB_*` via process env (PHPUnit does not clobber
existing env unless `force="true"`), runs `php artisan migrate --force`, then
the full suite. No `phpunit.xml` edit.

**Required-check policy:** keep `Test suite` (+ `Static checks`) as the merge
gate until `Test suite (pgsql)` is green on `main` for a stretch. Then add it
to branch protection and only later discuss retiring sqlite from `phpunit.xml`.

SQLite vs Postgres quirks Claude should watch for when the pgsql job fails:

- boolean storage / casting,
- JSON vs JSONB columns (prefer JSONB in any new Postgres-only migration),
- `INSERT OR IGNORE` / upsert syntax,
- case sensitivity on `LIKE`,
- autoincrement / sequence behaviour after data copy.

Cursor will not invent migrations to "fix" those — flag them in
[14-cross-agent-notes.md](14-cross-agent-notes.md) if a pgsql job surfaces them.

---

## Data copy (SQLite → Postgres)

An untested copy is not a cutover. Outline:

### 1. Freeze writes (short window)

Put the app in maintenance mode on the source environment, or take the
copy from a known backup:

```bash
./scripts/db-backup.sh
# source = storage/backups/elitehub-….sqlite
```

### 2. Empty target schema

On a fresh Postgres database (staging first):

```bash
php artisan migrate --force --database=pgsql
```

Uses Claude's migrations already on `main`. No new migration for the copy.

### 3. Copy rows

Implemented: [`scripts/sqlite-to-pgsql.php`](../scripts/sqlite-to-pgsql.php)
(pure PDO, no migrations). Target = `.env` `DB_*` (must be pgsql). Source =
a SQLite file (live DB or a `db-backup.sh` snapshot).

```bash
# Point .env at Postgres first, then:
php artisan migrate --force
php scripts/sqlite-to-pgsql.php --source=storage/backups/elitehub-….sqlite --dry-run
php scripts/sqlite-to-pgsql.php --source=storage/backups/elitehub-….sqlite --truncate --verify
```

| Flag | Effect |
|---|---|
| `--dry-run` | Intersection tables + counts only |
| `--truncate` | `TRUNCATE … CASCADE` before insert (safe re-runs) |
| `--verify` | Per-table `COUNT(*)` must match; spot-checks `tenant_id` |
| `--skip=a,b` | Extra tables on top of framework defaults |

**Skipped by default:** `migrations`, `cache*`, `jobs*`, `failed_jobs`,
`sessions`, `password_reset_tokens`.

**FK / tenancy:** copies under `SET session_replication_role = replica`, then
resets sequences to `MAX(id)`. Preferred load order starts with `tenants` →
`users` → `workspaces` → `workspace_user` → `company_profiles`.

**Verification queries (minimum):**

```sql
SELECT COUNT(*) FROM users;
SELECT COUNT(*) FROM events;
SELECT COUNT(*) FROM tenants;
SELECT COUNT(*) FROM events WHERE tenant_id IS NULL;  -- expect 0 after slice 2
```

Compare counts to SQLite. Spot-check one flagship event's budget lines and
approvals.

### 4. Restore drill on staging

1. Copy staging Postgres → dump (`pg_dump`).
2. Load into a throwaway database.
3. Boot the app against it; hit `/up` and sign in.
4. Only then schedule production.

`scripts/db-restore.sh` already understands `pgsql` dumps (`.sql` / `.sql.gz`).
Extend the backup script's default for `DB_CONNECTION=pgsql` hosts (already
does `pg_dump | gzip`).

### 5. Production cutover checklist

- [ ] Staging copy verified (counts + one real workflow).
- [ ] `APP_DEBUG=false`, `SESSION_ENCRYPT=true`, redis drivers on.
- [ ] Backup of SQLite retained off-box for 30 days.
- [ ] DNS / Herd / Compose `DB_*` pointed at Postgres.
- [ ] `php artisan migrate --force` (should be no-op if schema pre-applied).
- [ ] `php artisan up` / remove maintenance.
- [ ] Watch Sentry + `stderr_json` logs for driver errors for 24h.
- [ ] Cron `schedule:run` still firing; first nightly backup is a `.sql.gz`.

---

## Ownership split

| Work | Owner |
|---|---|
| Compose / CI Postgres service, env examples, this plan, copy **script** | Cursor |
| Migrations, model casts, JSONB column types, fixing pgsql test failures in domain code | Claude |
| Flipping `phpunit.xml` off sqlite | Joint — only after pgsql CI job is green |

Schema lock remains in force for tenancy slices 3–4. A Postgres cutover
script must not add columns or rewrite migrations.

---

## Suggested PR sequence

1. ~~`cursor/pgsql-ci-job`~~ — landed: `Test suite (pgsql)` in `ci.yml`.
2. `cursor/pgsql-green` (PR #22) — domain quirks so pgsql CI is green; then
   make **Test suite (pgsql)** a required check (admin).
3. `cursor/sqlite-to-pgsql-script` — `scripts/sqlite-to-pgsql.php` + staging
   dry-run on a Compose volume.
4. Staging cutover (ops, not a code PR).
5. Production cutover (ops).
6. Only then: discuss retiring sqlite from `phpunit.xml`.

Related: [16-infrastructure.md](16-infrastructure.md),
[15-database-backups.md](15-database-backups.md),
[17-postgres-cutover-plan.md](17-postgres-cutover-plan.md).
