#!/usr/bin/env bash
# Restore a database backup produced by scripts/db-backup.sh.
#
# Refuses to overwrite the live database unless --yes is passed.
# Prefer --to for restore drills — prove the backup opens without
# touching production data.
#
# Usage:
#   ./scripts/db-restore.sh storage/backups/elitehub-….sqlite --to /tmp/verify.sqlite
#   ./scripts/db-restore.sh storage/backups/elitehub-….sqlite --yes
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

BACKUP=""
YES=0
TO=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --yes) YES=1; shift ;;
    --to) TO="${2:-}"; shift 2 ;;
    -*)
      echo "error: unknown flag $1" >&2
      exit 1
      ;;
    *)
      if [[ -z "$BACKUP" ]]; then BACKUP="$1"; else
        echo "error: unexpected argument $1" >&2
        exit 1
      fi
      shift
      ;;
  esac
done

if [[ -z "$BACKUP" ]]; then
  echo "usage: $0 <backup-file> (--to <path> | --yes)" >&2
  exit 1
fi

if [[ "$BACKUP" != /* ]]; then
  BACKUP="${ROOT}/${BACKUP}"
fi

if [[ ! -f "$BACKUP" ]]; then
  echo "error: backup not found: ${BACKUP}" >&2
  exit 1
fi

ENV_FILE="${ENV_FILE:-.env}"

if [[ -f "$ENV_FILE" ]]; then
  while IFS= read -r line; do
    case "$line" in
      DB_CONNECTION=*|DB_DATABASE=*|DB_HOST=*|DB_PORT=*|DB_USERNAME=*|DB_PASSWORD=*|DB_SSLMODE=*)
        export "$line"
        ;;
    esac
  done < <(grep -E '^(DB_CONNECTION|DB_DATABASE|DB_HOST|DB_PORT|DB_USERNAME|DB_PASSWORD|DB_SSLMODE)=' "$ENV_FILE" | sed 's/\r$//')
fi

CONNECTION="${DB_CONNECTION:-sqlite}"

case "$CONNECTION" in
  sqlite)
    LIVE="${DB_DATABASE:-database/database.sqlite}"
    if [[ "$LIVE" != /* && "$LIVE" != :memory:* ]]; then
      LIVE="${ROOT}/${LIVE}"
    fi

    TARGET="${TO:-$LIVE}"
    if [[ "$TARGET" != /* ]]; then
      TARGET="${ROOT}/${TARGET}"
    fi

    if [[ "$TARGET" == "$LIVE" && "$YES" -ne 1 ]]; then
      echo "error: refusing to overwrite live database ${LIVE}" >&2
      echo "       pass --yes to restore in place, or --to <path> for a drill." >&2
      exit 1
    fi

    mkdir -p "$(dirname "$TARGET")"

    if [[ "$TARGET" == "$LIVE" ]]; then
      SAFETY="${ROOT}/storage/backups/pre-restore-$(date +%Y%m%d-%H%M%S).sqlite"
      mkdir -p "$(dirname "$SAFETY")"
      if command -v sqlite3 >/dev/null 2>&1; then
        sqlite3 "$LIVE" ".backup '${SAFETY}'"
      else
        cp "$LIVE" "$SAFETY"
      fi
      echo "safety copy of live DB: ${SAFETY}"
    fi

    case "$BACKUP" in
      *.sql.gz)
        echo "error: this host is on sqlite; cannot restore a .sql.gz dump into it." >&2
        exit 1
        ;;
      *.sql)
        echo "error: this host is on sqlite; cannot restore a .sql dump into it." >&2
        exit 1
        ;;
    esac

    cp "$BACKUP" "$TARGET"
    # Confirm the restored file is a readable sqlite database.
    if command -v sqlite3 >/dev/null 2>&1; then
      TABLES="$(sqlite3 "$TARGET" "SELECT COUNT(*) FROM sqlite_master WHERE type='table';")"
      USERS="$(sqlite3 "$TARGET" "SELECT COUNT(*) FROM users;" 2>/dev/null || echo '?')"
      echo "restored ok: ${TARGET}"
      echo "tables=${TABLES} users=${USERS}"
    else
      echo "restored: ${TARGET} (sqlite3 not installed — skipped integrity query)"
    fi
    ;;
  mysql|mariadb)
    if [[ -z "$TO" && "$YES" -ne 1 ]]; then
      echo "error: refusing to overwrite live MySQL database without --yes" >&2
      exit 1
    fi
    : "${DB_DATABASE:?DB_DATABASE required}"
    : "${DB_USERNAME:?DB_USERNAME required}"
    HOST="${DB_HOST:-127.0.0.1}"
    PORT="${DB_PORT:-3306}"
    export MYSQL_PWD="${DB_PASSWORD:-}"
    if [[ "$BACKUP" == *.gz ]]; then
      gunzip -c "$BACKUP" | mysql -h "$HOST" -P "$PORT" -u "$DB_USERNAME" "$DB_DATABASE"
    else
      mysql -h "$HOST" -P "$PORT" -u "$DB_USERNAME" "$DB_DATABASE" < "$BACKUP"
    fi
    unset MYSQL_PWD
    echo "restored ok into mysql://${HOST}/${DB_DATABASE}"
    ;;
  pgsql|postgres|postgresql)
    if [[ -z "$TO" && "$YES" -ne 1 ]]; then
      echo "error: refusing to overwrite live Postgres database without --yes" >&2
      exit 1
    fi
    : "${DB_DATABASE:?DB_DATABASE required}"
    : "${DB_USERNAME:?DB_USERNAME required}"
    HOST="${DB_HOST:-127.0.0.1}"
    PORT="${DB_PORT:-5432}"
    export PGPASSWORD="${DB_PASSWORD:-}"
    export PGSSLMODE="${DB_SSLMODE:-prefer}"
    if [[ "$BACKUP" == *.gz ]]; then
      gunzip -c "$BACKUP" | psql -h "$HOST" -p "$PORT" -U "$DB_USERNAME" -d "$DB_DATABASE" -v ON_ERROR_STOP=1
    else
      psql -h "$HOST" -p "$PORT" -U "$DB_USERNAME" -d "$DB_DATABASE" -v ON_ERROR_STOP=1 -f "$BACKUP"
    fi
    unset PGPASSWORD PGSSLMODE
    echo "restored ok into postgres://${HOST}/${DB_DATABASE}"
    ;;
  *)
    echo "error: unsupported DB_CONNECTION=${CONNECTION}" >&2
    exit 1
    ;;
esac
