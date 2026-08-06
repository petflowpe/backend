#!/usr/bin/env bash
set -euo pipefail

# Despliegue manual del backend Laravel al VPS Hostinger (sin Docker).
# Ejecutar desde la raíz del repo backend en tu PC:
#   cd backend
#   chmod +x ops/deploy_hostinger.sh
#   ./ops/deploy_hostinger.sh
#
# Requiere: ssh, rsync, acceso root (o usuario con permisos en DEPLOY_PATH).

SSH_HOST="${SSH_HOST:-root@srv1197160.hstgr.cloud}"
DEPLOY_PATH="${DEPLOY_PATH:-/var/www/petmovil/backend}"
RUN_SEEDERS="${RUN_SEEDERS:-false}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKEND_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

echo "==> Origen:  $BACKEND_DIR"
echo "==> Destino: $SSH_HOST:$DEPLOY_PATH"
read -r -p "¿Continuar? [y/N] " confirm
if [[ ! "$confirm" =~ ^[yY]$ ]]; then
  echo "Cancelado."
  exit 0
fi

echo "==> Sincronizando código (rsync)..."
rsync -az --delete \
  --exclude .git \
  --exclude .github \
  --exclude .env \
  --exclude storage/app \
  --exclude storage/framework \
  --exclude storage/logs \
  --exclude node_modules \
  --exclude tests \
  --exclude vendor \
  "$BACKEND_DIR/" "$SSH_HOST:$DEPLOY_PATH/"

echo "==> Post-deploy en el VPS..."
ssh "$SSH_HOST" "DEPLOY_PATH='$DEPLOY_PATH' RUN_SEEDERS='$RUN_SEEDERS' bash -s" << 'REMOTE'
set -euo pipefail
cd "$DEPLOY_PATH"

if [ ! -f .env ]; then
  echo "ERROR: falta .env en $DEPLOY_PATH"
  echo "Copia .env.example a .env, configura APP_URL y DB, luego: php artisan key:generate"
  exit 1
fi

composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force

if [ "$RUN_SEEDERS" = "true" ]; then
  php artisan db:seed --force
fi

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache 2>/dev/null || true
chown -R "$USER":www-data storage bootstrap/cache 2>/dev/null || true

php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan view:clear 2>/dev/null || true

php artisan config:cache
if [ -d resources/views ]; then
  php artisan view:cache 2>/dev/null || true
fi
php artisan event:cache 2>/dev/null || true

systemctl reload php8.2-fpm 2>/dev/null || true
systemctl reload nginx 2>/dev/null || true

echo "Deploy completado en $DEPLOY_PATH"
REMOTE

echo ""
echo "Verifica:"
echo "  curl -s https://srv1197160.hstgr.cloud/api/system/info"
echo "  curl -s https://srv1197160.hstgr.cloud/api/v1/clients -H 'Accept: application/json'"
echo "  (clients debe devolver 401 sin token, no ParseError)"
