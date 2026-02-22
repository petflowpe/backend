# 🚀 Guía de Despliegue en VPS Hostinger KVM 1

## 📊 Evaluación de Especificaciones

### Recursos del VPS
- **CPU**: 1 vCPU core
- **RAM**: 4 GB
- **Almacenamiento**: 50 GB NVMe
- **Ancho de banda**: 4 TB

### ✅ **SÍ, es viable** para tu proyecto Laravel de facturación electrónica

---

## 📋 Análisis de Recursos

### ✅ **RAM (4 GB) - SUFICIENTE**
```
Distribución estimada:
- Sistema Operativo (Ubuntu/Debian): ~500 MB
- PHP-FPM (2-3 workers): ~300-600 MB
- MySQL: ~1-1.5 GB (configurable)
- Laravel aplicación: ~200-400 MB
- Cache/Redis (opcional): ~100-200 MB
- Buffer para picos: ~500 MB
─────────────────────────────────
Total estimado: ~2.5-3.5 GB
```

**Recomendación**: Configurar MySQL con límites de memoria para no exceder 1.5 GB.

### ⚠️ **CPU (1 vCore) - LIMITADO pero FUNCIONAL**
```
Consideraciones:
✅ Suficiente para: 10-50 usuarios concurrentes
⚠️ Limitante para: Generación masiva de PDFs/XML
⚠️ Limitante para: Múltiples procesos simultáneos
```

**Recomendación**: 
- Optimizar generación de PDFs (usar colas)
- Limitar workers de PHP-FPM a 2-3
- Usar colas para tareas pesadas (envío a SUNAT, generación de PDFs)

### ✅ **Almacenamiento (50 GB NVMe) - SUFICIENTE**
```
Distribución estimada:
- Sistema Operativo: ~5 GB
- Aplicación Laravel: ~500 MB
- Vendor (Composer): ~200 MB
- Base de Datos: ~1-5 GB (inicial)
- Archivos XML/PDF: ~10-30 GB (según volumen)
- Logs: ~1-2 GB
- Backups: ~5-10 GB
─────────────────────────────────
Total estimado: ~22-52 GB
```

**Recomendación**: 
- Implementar limpieza automática de archivos antiguos
- Configurar rotación de logs
- Hacer backups externos periódicos

### ✅ **Ancho de Banda (4 TB) - MÁS QUE SUFICIENTE**
```
Estimación mensual:
- API requests: ~50-100 GB
- Descargas PDF/XML: ~100-500 GB
- Backups: ~50-200 GB
─────────────────────────────────
Total estimado: ~200-800 GB/mes
```

---

## 🛠️ Configuración Recomendada

### 1. Stack Tecnológico

```bash
# Sistema Operativo
Ubuntu 22.04 LTS o Debian 12

# Servidor Web
Nginx 1.24+ (más ligero que Apache)

# PHP
PHP 8.2 o 8.3 con extensiones:
- php8.2-fpm
- php8.2-mysql
- php8.2-xml
- php8.2-mbstring
- php8.2-curl
- php8.2-zip
- php8.2-gd
- php8.2-opcache

# Base de Datos
MySQL 8.0 o MariaDB 10.11

# Procesador de Colas
Supervisor (para queue:work)

# Cache (Opcional pero recomendado)
Redis 7.0+ (mejora rendimiento significativamente)
```

### 2. Optimizaciones de PHP-FPM

**Archivo**: `/etc/php/8.2/fpm/pool.d/www.conf`

```ini
[www]
pm = dynamic
pm.max_children = 8          # Máximo de procesos
pm.start_servers = 2         # Procesos iniciales
pm.min_spare_servers = 2     # Mínimo en espera
pm.max_spare_servers = 4     # Máximo en espera
pm.max_requests = 500        # Reiniciar después de N requests

# Límites de memoria
php_admin_value[memory_limit] = 256M
php_admin_value[max_execution_time] = 300
php_admin_value[upload_max_filesize] = 10M
php_admin_value[post_max_size] = 10M
```

### 3. Optimizaciones de MySQL

**Archivo**: `/etc/mysql/mysql.conf.d/mysqld.cnf`

```ini
[mysqld]
# Límites de memoria para VPS pequeño
innodb_buffer_pool_size = 1G
max_connections = 50
query_cache_size = 64M
tmp_table_size = 64M
max_heap_table_size = 64M
```

### 4. Configuración de Laravel

**.env** (producción)

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tudominio.com

# Optimizaciones
QUEUE_CONNECTION=database
CACHE_DRIVER=file
SESSION_DRIVER=file

# Si instalas Redis (recomendado)
# CACHE_DRIVER=redis
# SESSION_DRIVER=redis
# REDIS_HOST=127.0.0.1
# REDIS_PASSWORD=null
# REDIS_PORT=6379

# Base de datos
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=facturacion_sunat
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_password_seguro

# Logs
LOG_CHANNEL=daily
LOG_LEVEL=error
```

### 5. Supervisor para Colas

**Archivo**: `/etc/supervisor/conf.d/laravel-worker.conf`

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /ruta/a/tu/proyecto/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/ruta/a/tu/proyecto/storage/logs/worker.log
stopwaitsecs=3600
```

**Comandos**:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

---

## 📦 Pasos de Instalación

### 1. Preparar el Servidor

```bash
# Actualizar sistema
sudo apt update && sudo apt upgrade -y

# Instalar herramientas básicas
sudo apt install -y curl wget git unzip software-properties-common

# Instalar Nginx
sudo apt install -y nginx

# Instalar PHP 8.2
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.2-fpm php8.2-cli php8.2-mysql php8.2-xml \
    php8.2-mbstring php8.2-curl php8.2-zip php8.2-gd php8.2-opcache

# Instalar MySQL
sudo apt install -y mysql-server
sudo mysql_secure_installation

# Instalar Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Instalar Supervisor
sudo apt install -y supervisor

# (Opcional) Instalar Redis
sudo apt install -y redis-server
```

### 2. Configurar Base de Datos

```bash
sudo mysql -u root -p
```

```sql
CREATE DATABASE facturacion_sunat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'facturacion_user'@'localhost' IDENTIFIED BY 'password_seguro_aqui';
GRANT ALL PRIVILEGES ON facturacion_sunat.* TO 'facturacion_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 3. Desplegar Aplicación

```bash
# Crear directorio
sudo mkdir -p /var/www/facturacion
sudo chown -R $USER:$USER /var/www/facturacion

# Clonar o subir tu proyecto
cd /var/www/facturacion
# git clone tu-repositorio . (si usas Git)
# O subir archivos vía SFTP/SCP

# Instalar dependencias
composer install --optimize-autoloader --no-dev

# Configurar permisos
sudo chown -R www-data:www-data /var/www/facturacion
sudo chmod -R 755 /var/www/facturacion
sudo chmod -R 775 /var/www/facturacion/storage
sudo chmod -R 775 /var/www/facturacion/bootstrap/cache

# Configurar .env
cp .env.example .env
nano .env  # Editar con tus configuraciones

# Generar clave
php artisan key:generate

# Ejecutar migraciones
php artisan migrate --force

# Optimizar Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 4. Configurar Nginx

**Archivo**: `/etc/nginx/sites-available/facturacion`

```nginx
server {
    listen 80;
    server_name tudominio.com www.tudominio.com;
    root /var/www/facturacion/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Optimizaciones
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css text/xml text/javascript application/json application/javascript application/xml+rss;
}
```

**Activar sitio**:
```bash
sudo ln -s /etc/nginx/sites-available/facturacion /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 5. Configurar SSL (Let's Encrypt)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d tudominio.com -d www.tudominio.com
```

---

## ⚡ Optimizaciones Adicionales

### 1. OPcache (PHP)

**Archivo**: `/etc/php/8.2/fpm/conf.d/10-opcache.ini`

```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
opcache.fast_shutdown=1
```

### 2. Limpieza Automática de Archivos

Crear tarea cron para limpiar archivos antiguos:

```bash
sudo crontab -e
```

```cron
# Limpiar XML/PDF antiguos (más de 90 días)
0 2 * * * find /var/www/facturacion/storage/app/public/facturas -type f -mtime +90 -delete

# Limpiar logs antiguos
0 3 * * * find /var/www/facturacion/storage/logs -name "*.log" -mtime +30 -delete

# Optimizar Laravel diariamente
0 4 * * * cd /var/www/facturacion && php artisan config:cache && php artisan route:cache
```

### 3. Monitoreo de Recursos

```bash
# Instalar herramientas de monitoreo
sudo apt install -y htop iotop

# Ver uso de recursos
htop
df -h
free -h
```

---

## 📊 Monitoreo y Mantenimiento

### Comandos Útiles

```bash
# Ver logs de Laravel
tail -f /var/www/facturacion/storage/logs/laravel.log

# Ver logs de Nginx
sudo tail -f /var/log/nginx/error.log

# Ver estado de colas
php artisan queue:work --verbose

# Ver procesos PHP
ps aux | grep php-fpm

# Ver uso de memoria
free -h

# Ver espacio en disco
df -h
```

### Alertas Recomendadas

Configurar alertas para:
- Uso de RAM > 90%
- Uso de disco > 80%
- CPU > 90% por más de 5 minutos
- Errores en logs de Laravel

---

## ⚠️ Limitaciones y Consideraciones

### Limitaciones del VPS

1. **1 vCPU**: 
   - ⚠️ Puede ser lento con múltiples usuarios generando PDFs simultáneamente
   - ✅ Solución: Usar colas para procesamiento asíncrono

2. **4 GB RAM**:
   - ⚠️ MySQL puede consumir mucha memoria si no está optimizado
   - ✅ Solución: Configurar límites de memoria en MySQL

3. **50 GB almacenamiento**:
   - ⚠️ Se puede llenar con muchos XML/PDF acumulados
   - ✅ Solución: Implementar limpieza automática

### Escalabilidad

**Si necesitas más recursos en el futuro**:
- Hostinger permite hacer upgrade fácilmente
- Considera migrar a VPS con 2 vCPU y 8 GB RAM si el tráfico crece

---

## ✅ Checklist de Despliegue

- [ ] Servidor configurado (Nginx, PHP, MySQL)
- [ ] Aplicación desplegada y permisos configurados
- [ ] Base de datos creada y migraciones ejecutadas
- [ ] Archivo `.env` configurado correctamente
- [ ] SSL/HTTPS configurado (Let's Encrypt)
- [ ] Supervisor configurado para colas
- [ ] Optimizaciones de PHP-FPM aplicadas
- [ ] Optimizaciones de MySQL aplicadas
- [ ] OPcache habilitado
- [ ] Tareas cron configuradas (limpieza, optimización)
- [ ] Backups configurados
- [ ] Monitoreo básico configurado
- [ ] Pruebas de funcionalidad completadas

---

## 🎯 Conclusión

**✅ SÍ, tu VPS Hostinger KVM 1 es SUFICIENTE** para desplegar tu proyecto de facturación electrónica, siempre que:

1. ✅ Configures correctamente los límites de recursos
2. ✅ Uses colas para tareas pesadas (generación de PDFs, envío a SUNAT)
3. ✅ Implementes limpieza automática de archivos
4. ✅ Monitorees el uso de recursos regularmente
5. ✅ Optimices MySQL y PHP-FPM según las recomendaciones

**Capacidad estimada**:
- ✅ 10-50 usuarios concurrentes
- ✅ 100-500 facturas/día
- ✅ Tráfico moderado de API

**Si necesitas más capacidad**, considera hacer upgrade a un VPS con más recursos cuando el tráfico crezca.

---

## 📞 Soporte

Si encuentras problemas durante el despliegue, revisa:
- Logs de Laravel: `storage/logs/laravel.log`
- Logs de Nginx: `/var/log/nginx/error.log`
- Logs de PHP-FPM: `/var/log/php8.2-fpm.log`
- Estado de colas: `php artisan queue:work --verbose`

