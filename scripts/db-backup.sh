#!/usr/bin/env bash
# Backup the application database into storage/backups/.
#
# Supports sqlite (default here), mysql, and pgsql — driven by .env.
# Uses sqlite3's online .backup when available so a live write cannot
# corrupt the snapshot; falls back to cp for hosts without sqlite3.
#
# Usage:
#   ./scripts/db-backup.sh
#   KEEP=14 ./scripts/db-backup.sh          # retain N newest (default 14)
#   OUT=/tmp/manual.sqlite ./scripts/db-backup.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [[ -f .env ]]; then
  # shellcheck disable=SC1091
  set -a
  # Only pull the DB_* keys — never eval the whole file.
  while IFS= read -r line; do
    case "$line" in
      DB_CONNECTION=*|DB_DATABASE=*|DB_HOST=*|DB_PORT=*|DB_USERNAME=*|DB_PASSWORD=*)
        export "$line"
        ;;
    esac
  done < <(grep -E '^(DB_CONNECTION|DB_DATABASE|DB_HOST|DB_PORT|DB_USERNAME|DB_PASSWORD)=' .env | sed 's/\r$//')
  set +a
fi

CONNECTION="${DB_CONNECTION:-sqlite}"
STAMP="$(date +%Y%m%d-%H%M%S)"
KEEP="${KEEP:-14}"
DEST_DIR="${ROOT}/storage/backups"
mkdir -p "$DEST_DIR"

case "$CONNECTION" in
  sqlite)
    DB_PATH="${DB_DATABASE:-database/database.sqlite}"
    # Relative paths in .env are relative to the project root.
    if [[ "$DB_PATH" != /* && "$DB_PATH" != :memory:* ]]; then
      DB_PATH="${ROOT}/${DB_PATH}"
    fi
    if [[ ! -f "$DB_PATH" ]]; then
      echo "error: sqlite database not found at ${DB_PATH}" >&2
      exit 1
    fi
    OUT="${OUT:-${DEST_DIR}/elitehub-${STAMP}.sqlite}"
    if command -v sqlite3 >/dev/null 2>&1; then
      sqlite3 "$DB_PATH" ".backup '${OUT}'"
    else
      cp "$DB_PATH" "$OUT"
    fi
    ;;
  mysql|mariadb)
    OUT="${OUT:-${DEST_DIR}/elitehub-${STAMP}.sql.gz}"
    : "${DB_DATABASE:?DB_DATABASE required}"
    : "${DB_USERNAME:?DB_USERNAME required}"
    HOST="${DB_HOST:-127.0.0.1}"
    PORT="${DB_PORT:-3306}"
    export MYSQL_PWD="${DB_PASSWORD:-}"
    mysqldump --single-transaction --routines --triggers \
      -h "$HOST" -P "$PORT" -u "$DB_USERNAME" "$DB_DATABASE" \
      | gzip -c > "$OUT"
    unset MYSQL_PWD
    ;;
  pgsql|postgres|postgresql)
    OUT="${OUT:-${DEST_DIR}/elitehub-${STAMP}.sql.gz}"
    : "${DB_DATABASE:?DB_DATABASE required}"
    : "${DB_USERNAME:?DB_USERNAME required}"
    HOST="${DB_HOST:-127.0.0.1}"
    PORT="${DB_PORT:-5432}"
    export PGPASSWORD="${DB_PASSWORD:-}"
    pg_dump -h "$HOST" -p "$PORT" -U "$DB_USERNAME" -d "$DB_DATABASE" \
      --no-owner --format=plain \
      | gzip -c > "$OUT"
    unset PGPASSWORD
    ;;
  *)
    echo "error: unsupported DB_CONNECTION=${CONNECTION}" >&2
    exit 1
    ;;
esac

BYTES="$(wc -c < "$OUT" | tr -d ' ')"
SHA="$(shasum -a 256 "$OUT" | awk '{print $1}')"
echo "backup written: ${OUT}"
echo "bytes=${BYTES} sha256=${SHA}"

# Prune older snapshots in the default directory only — never delete a
# path the caller set via OUT= (that may be a drill or an offsite copy).
if [[ "$OUT" == "${DEST_DIR}/"* ]]; then
  # shellcheck disable=SC2012
  ls -1t "${DEST_DIR}"/elitehub-* 2>/dev/null | tail -n +$((KEEP + 1)) | while read -r old; do
    rm -f "$old"
    echo "pruned: ${old}"
  done
fi
