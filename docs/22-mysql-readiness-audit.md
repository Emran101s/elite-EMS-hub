# MySQL 8+ Readiness Audit — Elite Business Hub

**Date:** 2026-08-08  
**Scope:** Factual audit + safe cutover plan. No destructive migration performed.  
**Strategic target:** MySQL 8+ (utf8mb4) for primary development, staging, and production.  
**SQLite remains:** optional local lightweight use + PHPUnit (`phpunit.xml` → `DB_CONNECTION=sqlite`).

---

## 1. Current database state

| Item | Value |
|---|---|
| Default connection (`config/database.php`) | `env('DB_CONNECTION', 'sqlite')` |
| `.env.example` | `DB_CONNECTION=sqlite` |
| PHPUnit | Forced `sqlite` (correct for CI speed) |
| Tables observed (local) | **75** (includes cache/jobs/sessions) |
| Domain / app tables (approx.) | **~65** with business data |
| Migrations on disk | **120** |
| Eloquent models | **59** |
| Tenant column coverage | Most domain tables have `tenant_id` (`T` in inventory) |
| Soft deletes | Sparse (`event_budget_items`, `invoices`, `tenants`) |

Prior Postgres plan (`docs/17-postgres-cutover-plan.md`) is **superseded for strategy** by this MySQL direction. Keep that file for historical techniques; do not treat Postgres as the production target unless leadership revisits.

---

## 2. MySQL compatibility report

### Likely OK
- Standard Laravel migrations (increments, foreignIds, json columns, timestamps).
- Eloquent relationships used across hub modules.
- `utf8mb4` via Laravel MySQL defaults.

### Watch / test explicitly
| Risk | Why |
|---|---|
| Boolean storage | SQLite loosely typed; MySQL expects 0/1 |
| JSON columns | Valid on MySQL 8; avoid SQLite-only JSON quirks in raw SQL |
| Date / datetime | Timezone + `datetime` vs `timestamp` differences |
| Case sensitivity | Table/column names: MySQL on Linux is case-sensitive for table names depending on `lower_case_table_names` |
| Full-text / LIKE | Collation differences (`utf8mb4_unicode_ci`) |
| Foreign keys | MySQL enforces FKs strictly; SQLite often does not unless PRAGMA |
| `INSERT OR IGNORE` / upsert | Driver-specific SQL in any raw queries |
| AUTO_INCREMENT vs SQLite rowid | Import tools must preserve IDs if FKs matter |
| Long indexes | MySQL index prefix length on utf8mb4 varchar(255) |

### Naming / structure notes
- Pivot tables present: `event_supplier`, `event_team_members`, `event_favorites`, `agenda_session_speaker`, `attendee_session`, `plan_item_user`, `workspace_user`.
- Morph columns on contracts (`party_type` / `party_id`) — fine on MySQL.
- Wide tables: `events` (~46 cols), `event_transport` (~32), `event_accommodations` (~28) — acceptable; watch row size if adding more TEXT/BLOB.

---

## 3. Migration risks

1. **Silent SQLite leniency** — missing FKs or invalid IDs may exist in older local DBs.
2. **Data import ID remapping** — if dump/reload renumbers PKs, every FK breaks.
3. **Concurrent use** — never point production at a half-migrated schema.
4. **Seed vs real data** — DemoDataSeeder is not a production import path.
5. **docs/17 Postgres assumptions** — ignore Postgres-specific types if following this plan.

---

## 4. Required `.env` direction (MySQL)

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=elitehub
DB_USERNAME=elitehub
DB_PASSWORD=<strong-secret>
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
```

Do **not** commit real passwords. Keep local SQLite in a personal override if desired; CI stays on SQLite via `phpunit.xml`.

---

## 5. Required database setup

```sql
CREATE DATABASE elitehub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'elitehub'@'%' IDENTIFIED BY '<strong-secret>';
GRANT ALL PRIVILEGES ON elitehub.* TO 'elitehub'@'%';
FLUSH PRIVILEGES;
```

MySQL 8+ with `sql_mode` including `STRICT_TRANS_TABLES` (default) — app should remain compatible; fix any loose inserts discovered in testing.

---

## 6. Migration testing plan

1. Create empty MySQL database (above).
2. Point a **copy** of `.env` at MySQL (never overwrite production blindly).
3. `php artisan migrate:fresh --seed` on MySQL.
4. Run `php artisan test` against MySQL once (override `DB_CONNECTION=mysql` for a local suite run — do not change CI default yet).
5. Smoke-test login, CRM win, proposal accept, Event Hub tabs, invoice PDF, registration, check-in.
6. Only then plan data import from any valued SQLite file.

---

## 7. Seed / data import plan

| Path | Use when |
|---|---|
| `migrate:fresh --seed` | Greenfield / staging reset |
| `sqlite3 .dump` → transform → MySQL | Preserve real local data (manual, careful) |
| Laravel Excel / custom artisan command | Selective table import (future) |

**Rule:** Prefer greenfield MySQL for team dev. Import only if the SQLite file holds irreplaceable production-like data.

---

## 8. Backup plan

Before any cutover:
- Dump MySQL: `mysqldump --single-transaction --routines --triggers elitehub > backup-YYYYMMDD.sql`
- Keep SQLite file copy: `cp database/database.sqlite backups/…`
- Store dumps off the app server.

See also `docs/15-database-backups.md` for operational habits.

---

## 9. Rollback plan

1. Stop app / maintenance mode.
2. Restore previous DB from dump.
3. Point `.env` back to previous connection.
4. `php artisan config:clear`.
5. Verify login + one Event Hub open.

Never run `migrate:fresh` on production.

---

## 10. Production readiness checklist

- [ ] MySQL 8+ provisioned (staging then production)
- [ ] utf8mb4 + unicode_ci confirmed
- [ ] Fresh migrations green on MySQL
- [ ] Feature test suite green on MySQL (local gate)
- [ ] Backups automated + restore drilled
- [ ] FK integrity verified (`information_schema`)
- [ ] Slow-query log reviewed after demo load
- [ ] SQLite retained only for tests / optional local
- [ ] No SaaS billing schema required in Phase 1

---

## 11. Index / FK follow-ups (recommended, non-blocking)

Audit next (do not rush without EXPLAIN):
- `events(tenant_id, stage, starts_at, archived_at)`
- `deals(tenant_id, stage, client_id)`
- `tasks(event_id, status, due_on)`
- `event_approvals(status)`
- `invoices(status, event_id)`
- `event_contract_payments(due_on, contract_id)`

Confirm every `*_id` FK has an index (Laravel `foreignId` usually adds one).

---

## 12. Status

**MySQL readiness:** Audited and planned — **not cut over**.  
**Safe to proceed with app IA/workflow work on current SQLite/local DB.**  
**Next engineering step:** provision local MySQL, run fresh migrate+seed, fix any driver-specific failures, then adopt MySQL as primary local connection.
