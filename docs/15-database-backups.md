# Database backups

An untested backup is not a backup. This document is the ops contract for
taking snapshots and proving they restore.

## Scripts

| Script | Purpose |
|---|---|
| [`scripts/db-backup.sh`](../scripts/db-backup.sh) | Snapshot the live DB into `storage/backups/` |
| [`scripts/db-restore.sh`](../scripts/db-restore.sh) | Restore a snapshot (drill via `--to`, live via `--yes`) |

Both read `DB_*` from `.env`. SQLite (current Herd default), MySQL, and
Postgres are supported. `storage/backups/` is gitignored.

### Take a backup

```bash
./scripts/db-backup.sh
# KEEP=30 ./scripts/db-backup.sh          # retain 30 newest (default 14)
```

### Restore drill (preferred — does not touch live data)

```bash
./scripts/db-restore.sh storage/backups/elitehub-YYYYMMDD-HHMMSS.sqlite \
  --to /tmp/elitehub-restore-drill.sqlite
```

### Restore in place (destructive)

```bash
./scripts/db-restore.sh storage/backups/elitehub-YYYYMMDD-HHMMSS.sqlite --yes
```

In-place restore first writes a `pre-restore-*.sqlite` safety copy into
`storage/backups/`.

## Automation

A daily Artisan schedule entry runs the backup script at 02:30 (see
`routes/console.php`). Cron must call the scheduler every minute so due
events fire:

```cron
PATH=/Users/emranalitan/Library/Application Support/Herd/bin:/usr/bin:/bin
* * * * * cd /Users/emranalitan/Herd/elitehub && php artisan schedule:run >> /dev/null 2>&1
```

**Installed on the Herd host (2026-08-06).** Verified with
`crontab -l | grep schedule:run`. A temporary `everyMinute()` schedule
produced `storage/backups/elitehub-20260806-094801.sqlite` via cron alone
(nobody ran `scripts/db-backup.sh` by hand); the schedule was then restored
to `dailyAt('02:30')`. Output also landed in `storage/logs/backup.log`.

Or call the script directly:

```cron
30 2 * * * cd /path/to/elitehub && ./scripts/db-backup.sh >> storage/logs/backup.log 2>&1
```

Offsite copies are not handled here yet — Phase 1 (S3 / remote Postgres) is
where that lands. Until then, copy `storage/backups/elitehub-*` off the box
on a cadence you trust.

## Rehearsed restore (2026-08-06)

Proven once on the Herd sqlite database before this doc shipped:

1. `./scripts/db-backup.sh` wrote `storage/backups/elitehub-<stamp>.sqlite`
   with a non-zero size and a sha256.
2. `./scripts/db-restore.sh … --to /tmp/elitehub-restore-drill.sqlite`
   produced a readable database (`sqlite_master` table count > 0, `users`
   row count matched the live DB).
3. Live `database/database.sqlite` was not modified.

Re-run the drill after any change to the scripts, and after the Phase 1
Postgres cutover.
