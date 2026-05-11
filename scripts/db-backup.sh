#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"
ENV_FILE="$ROOT_DIR/.env"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "ERROR: .env not found at $ENV_FILE" >&2
  exit 1
fi

# Load .env (skip comments and blank lines)
set -a
# shellcheck disable=SC1090
source <(grep -E '^[A-Z_]+=.' "$ENV_FILE")
set +a

DB_ROOT_PASSWORD="${DB_ROOT_PASSWORD:-root}"
DB_DATABASE="${DB_DATABASE:-laravel}"
CONTAINER="${1:-port-tracker-db}"
BACKUP_DIR="$ROOT_DIR/backups"
TIMESTAMP="$(date +%Y-%m-%d_%H%M%S)"
OUT="$BACKUP_DIR/${DB_DATABASE}-${TIMESTAMP}.sql"

mkdir -p "$BACKUP_DIR"

echo "Backing up '$DB_DATABASE' from container '$CONTAINER' → $OUT"

docker exec "$CONTAINER" \
  mariadb-dump \
    -u root -p"${DB_ROOT_PASSWORD}" \
    --single-transaction \
    --routines \
    --triggers \
    "$DB_DATABASE" \
  > "$OUT"

SIZE="$(du -sh "$OUT" | cut -f1)"
echo "Done. $OUT ($SIZE)"

# Sanity check — make sure the dump isn't suspiciously small
LINES="$(wc -l < "$OUT")"
if [[ "$LINES" -lt 20 ]]; then
  echo "WARNING: dump is only $LINES lines — verify it looks correct before relying on it." >&2
fi
