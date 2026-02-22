@echo off
echo 🚀 Iniciando optimización de Laravel...

echo 📦 Optimizando autoloader...
composer install --optimize-autoloader --no-dev

echo 🧹 Limpiando cachés...
php artisan optimize:clear

echo ⚡ Generando cachés optimizados...
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo 🗄️ Optimizando base de datos...
php artisan optimize

echo ✅ Optimización completada!
pause
