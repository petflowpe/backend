#!/bin/bash

# Script para solucionar el problema de rutas no encontradas
# Ejecutar en el servidor: bash fix-routes-cache.sh

echo "🔧 Solucionando problema de cache de rutas..."
echo ""

# Navegar al directorio del proyecto (ajustar según tu configuración)
cd /var/www/facturacion/backend-grooming || exit 1

echo "📋 Directorio actual: $(pwd)"
echo ""

echo "🧹 Limpiando todos los caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

echo ""
echo "🔄 Regenerando autoloader de Composer..."
composer dump-autoload --optimize

echo ""
echo "⚙️ Reoptimizando Laravel..."
php artisan optimize

echo ""
echo "✅ Proceso completado!"
echo ""
echo "📝 Verificando rutas disponibles..."
php artisan route:list --path=api/v1/suppliers

echo ""
echo "✨ Si el problema persiste, verifica:"
echo "   1. Que el archivo routes/api.php existe y tiene las rutas definidas"
echo "   2. Que los permisos de storage y bootstrap/cache son correctos (775)"
echo "   3. Que el servidor web tiene acceso de lectura al proyecto"

