# 🚀 Guía de Optimización de Performance para Laravel Backend

Esta guía contiene comandos y configuraciones para optimizar el rendimiento de tu backend Laravel.

## 📋 Comandos de Optimización

### 1. Optimizar Autoloader de Composer
```bash
composer install --optimize-autoloader --no-dev
```

### 2. Optimizar Configuración de Laravel
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

### 3. Optimizar Base de Datos
```bash
# Limpiar consultas lentas y optimizar índices
php artisan optimize:clear
```

### 4. Optimización Completa (Producción)
```bash
# Ejecutar todos los comandos de optimización
php artisan optimize
```

## ⚙️ Configuraciones Recomendadas

### 1. Configuración de OPcache (php.ini)
```ini
; Habilitar OPcache
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0  ; Solo en producción
opcache.revalidate_freq=0     ; Solo en producción
opcache.fast_shutdown=1
```

### 2. Variables de Entorno (.env)
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio.com

# Cache
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Base de Datos
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tu_base_de_datos
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_contraseña

# Redis (si está disponible)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### 3. Configuración de Caché (config/cache.php)
```php
// Usar Redis para caché en producción
'default' => env('CACHE_DRIVER', 'redis'),
```

## 🔧 Script de Optimización Automática

Crea un archivo `optimize.sh` en la raíz del proyecto:

```bash
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
```

Hacer ejecutable:
```bash
chmod +x optimize.sh
./optimize.sh
```

## 📊 Monitoreo de Performance

### 1. Habilitar Query Log (Solo desarrollo)
```php
// En AppServiceProvider
DB::enableQueryLog();
```

### 2. Usar Laravel Debugbar (Solo desarrollo)
```bash
composer require barryvdh/laravel-debugbar --dev
```

### 3. Monitorear con Laravel Telescope (Opcional)
```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

## 🎯 Optimizaciones Específicas

### 1. Eager Loading en Consultas
```php
// ❌ Mal: N+1 Problem
$clients = Client::all();
foreach ($clients as $client) {
    echo $client->pets->count();
}

// ✅ Bien: Eager Loading
$clients = Client::with('pets')->get();
foreach ($clients as $client) {
    echo $client->pets->count();
}
```

### 2. Usar Índices en Base de Datos
```php
// En migraciones
$table->index(['client_id', 'status']);
$table->index(['date', 'time']);
```

### 3. Paginación en Consultas Grandes
```php
// En lugar de Client::all()
Client::paginate(50);
```

### 4. Caché de Consultas Costosas
```php
$clients = Cache::remember('clients_list', 3600, function () {
    return Client::with('pets')->get();
});
```

## 🔄 Comandos para Despliegue

### Antes de Desplegar:
```bash
# 1. Optimizar autoloader
composer install --optimize-autoloader --no-dev

# 2. Limpiar y regenerar cachés
php artisan optimize:clear
php artisan optimize

# 3. Ejecutar migraciones
php artisan migrate --force

# 4. Limpiar logs antiguos (opcional)
php artisan log:clear
```

## 📝 Checklist de Optimización

- [ ] Composer autoloader optimizado
- [ ] Configuración en caché
- [ ] Rutas en caché
- [ ] Vistas en caché
- [ ] Eventos en caché
- [ ] OPcache habilitado
- [ ] Redis configurado (si está disponible)
- [ ] Índices en base de datos
- [ ] Eager loading en consultas
- [ ] Paginación implementada
- [ ] Caché de consultas costosas
- [ ] APP_DEBUG=false en producción
- [ ] Logs optimizados

## 🚨 Notas Importantes

1. **No ejecutar `php artisan optimize` en desarrollo** - Usa `php artisan optimize:clear` para limpiar
2. **OPcache validate_timestamps=0** solo en producción
3. **Redis** mejora significativamente el rendimiento si está disponible
4. **Monitorear** el uso de memoria y CPU después de optimizar

## 📚 Recursos Adicionales

- [Laravel Performance Optimization](https://laravel.com/docs/optimization)
- [OPcache Configuration](https://www.php.net/manual/en/opcache.configuration.php)
- [Redis Configuration](https://redis.io/docs/manual/config/)
