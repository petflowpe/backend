#!/bin/bash

echo "🚀 Iniciando optimización de Laravel..."

# Optimizar autoloader
echo "📦 Optimizando autoloader..."
composer install --optimize-autoloader --no-dev

# Limpiar cachés antiguos
echo "🧹 Limpiando cachés..."
php artisan optimize:clear

# Generar cachés optimizados
echo "⚡ Generando cachés optimizados..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Optimizar base de datos
echo "🗄️ Optimizando base de datos..."
php artisan optimize

echo "✅ Optimización completada!"
