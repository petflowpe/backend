#!/usr/bin/env bash
set -euo pipefail

# Deploy simple para 1 VPS (sin releases).
# Ajusta APP_DIR y usuario según tu entorno.
#
# Requiere: git, composer, php, npm (si compilas assets).

APP_DIR="/var/www/smartpet"
BACKEND_DIR="$APP_DIR/backend"

echo "==> Pull latest code"
cd "$APP_DIR"
git pull

echo "==> Install PHP deps"
cd "$BACKEND_DIR"
composer install --no-dev --optimize-autoloader

echo "==> Migrate DB"
php artisan migrate --force

echo "==> Cache config/routes/views"
php artisan config:cache
php artisan route:cache
php artisan view:cache

if [ -f "$BACKEND_DIR/package.json" ]; then
  echo "==> Build frontend assets (vite)"
  npm install
  npm run build
fi

echo "==> Reload services"
sudo systemctl reload php8.2-fpm || true
sudo systemctl reload nginx || true
sudo supervisorctl restart smartpet-worker:* || true

echo "Deploy OK"

