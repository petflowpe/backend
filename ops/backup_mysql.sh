#!/usr/bin/env bash
set -euo pipefail

# Backup simple de MySQL (gzip) con rotación.
# Requiere: mysqldump, gzip.
#
# Uso recomendado:
# - Configurar variables abajo
# - Ejecutar vía cron diario
#
# Importante:
# - Ideal usar un usuario MySQL dedicado a backup con permisos mínimos.

DB_HOST="127.0.0.1"
DB_NAME="smartpet"
DB_USER="smartpet"
DB_PASSWORD="CHANGE_ME"

BACKUP_DIR="/var/backups/smartpet"
RETENTION_DAYS=14

mkdir -p "$BACKUP_DIR"

STAMP="$(date +%F_%H%M%S)"
OUT="$BACKUP_DIR/${DB_NAME}_${STAMP}.sql.gz"

export MYSQL_PWD="$DB_PASSWORD"
mysqldump --host="$DB_HOST" --user="$DB_USER" \
  --single-transaction --quick --routines --triggers \
  "$DB_NAME" | gzip > "$OUT"
unset MYSQL_PWD

# Rotación
find "$BACKUP_DIR" -type f -name "${DB_NAME}_*.sql.gz" -mtime +"$RETENTION_DAYS" -delete

echo "Backup OK: $OUT"

