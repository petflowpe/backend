# 🔧 Solución: Error "The route api/v1/suppliers could not be found"

## 📋 Descripción del Problema

Cuando intentas consumir una API REST, recibes el error:
```
"The route api/v1/suppliers could not be found."
```

Este error ocurre porque **el cache de rutas de Laravel está desactualizado** en el servidor.

## ✅ Solución Rápida (Ejecutar en el Servidor)

### Opción 1: Usar el Script Automático

1. Sube el archivo `fix-routes-cache.sh` al servidor
2. Ejecuta:
```bash
cd /var/www/facturacion/backend-grooming
bash fix-routes-cache.sh
```

### Opción 2: Comandos Manuales

Conecta por SSH a tu servidor y ejecuta:

```bash
cd /var/www/facturacion/backend-grooming

# Limpiar todos los caches
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Regenerar autoloader
composer dump-autoload --optimize

# Reoptimizar Laravel
php artisan optimize
```

### Opción 3: Si usas Nginx/Apache

Después de limpiar el cache, reinicia el servicio PHP-FPM:

```bash
# Para PHP 8.2
sudo systemctl restart php8.2-fpm

# O para la versión que uses
sudo systemctl restart php-fpm
```

## 🔍 Verificación

Para verificar que las rutas están correctamente cargadas:

```bash
php artisan route:list --path=api/v1/suppliers
```

Deberías ver algo como:
```
GET|HEAD  api/v1/suppliers ................ suppliers.index
POST      api/v1/suppliers ................ suppliers.store
GET|HEAD  api/v1/suppliers/{supplier} ..... suppliers.show
PUT|PATCH api/v1/suppliers/{supplier} ..... suppliers.update
DELETE    api/v1/suppliers/{supplier} ..... suppliers.destroy
```

## 🚀 Prevención Futura

El script de deploy (`.github/workflows/deploy.yml`) ha sido actualizado para limpiar el cache automáticamente antes de optimizar. Esto evitará que el problema vuelva a ocurrir en futuros despliegues.

## 📝 Notas Adicionales

### Si el problema persiste:

1. **Verifica permisos:**
   ```bash
   sudo chown -R www-data:www-data /var/www/facturacion/backend-grooming
   sudo chmod -R 775 storage bootstrap/cache
   ```

2. **Verifica que el archivo de rutas existe:**
   ```bash
   ls -la routes/api.php
   ```

3. **Verifica la configuración de Laravel:**
   ```bash
   php artisan config:show app.url
   ```

4. **Revisa los logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

### Estructura de Rutas Esperada

Las rutas están definidas en `routes/api.php` con la siguiente estructura:

- **Prefijo base:** `/api` (automático en Laravel)
- **Versión:** `/v1`
- **Recurso:** `/suppliers`

**URL completa:** `https://tudominio.com/api/v1/suppliers`

## 🔗 Rutas Relacionadas

Todas las rutas bajo el prefijo `v1` requieren autenticación con Sanctum:
- `api/v1/suppliers`
- `api/v1/products`
- `api/v1/brands`
- `api/v1/companies`
- etc.

## 📞 Soporte

Si después de seguir estos pasos el problema persiste, verifica:
- La versión de Laravel (`php artisan --version`)
- La configuración del servidor web (Nginx/Apache)
- Los logs del servidor web y de Laravel

