#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"
ENV_FILE="$ROOT_DIR/.env"

DUMP_FILE="${1:-}"

if [[ -z "$DUMP_FILE" ]]; then
  echo "Usage: $0 <path-to-dump.sql>" >&2
  echo ""
  echo "Available backups:"
  ls -lht "$ROOT_DIR/backups/"*.sql 2>/dev/null | head -10 || echo "  (none found in $ROOT_DIR/backups/)"
  exit 1
fi

if [[ ! -f "$DUMP_FILE" ]]; then
  echo "ERROR: dump file not found: $DUMP_FILE" >&2
  exit 1
fi

if [[ ! -f "$ENV_FILE" ]]; then
  echo "ERROR: .env not found at $ENV_FILE" >&2
  exit 1
fi

set -a
# shellcheck disable=SC1090
source <(grep -E '^[A-Z_]+=.' "$ENV_FILE")
set +a

DB_ROOT_PASSWORD="${DB_ROOT_PASSWORD:-root}"
DB_DATABASE="${DB_DATABASE:-laravel}"
CONTAINER="${2:-port-tracker-db}"

echo "WARNING: This will DROP and recreate '$DB_DATABASE' in container '$CONTAINER'."
echo "Restoring from: $DUMP_FILE"
read -r -p "Type YES to continue: " CONFIRM

if [[ "$CONFIRM" != "YES" ]]; then
  echo "Aborted."
  exit 1
fi

echo "Restoring..."
docker exec -i "$CONTAINER" \
  mariadb \
    -u root -p"${DB_ROOT_PASSWORD}" \
    -e "DROP DATABASE IF EXISTS \`${DB_DATABASE}\`; CREATE DATABASE \`${DB_DATABASE}\`;"

docker exec -i "$CONTAINER" \
  mariadb \
    -u root -p"${DB_ROOT_PASSWORD}" \
    "$DB_DATABASE" \
  < "$DUMP_FILE"

echo "Done. '$DB_DATABASE' restored from $DUMP_FILE"
