# Producción (Runbook): Ubuntu + MySQL + Laravel (SmartPet)

Este documento deja un “paso a paso” para desplegar y operar SmartPet en **1 VPS** con **Ubuntu + MySQL**.

> Recomendado: separar dominios:
> - Frontend (React SPA): `www.petflow.com`
> - Backend (Laravel API): `api.petflow.com`
> y TLS obligatorio.

---

## 0) Requisitos

- Ubuntu 22.04+ (recomendado)
- Dominio apuntando al VPS
- Acceso SSH con llave

---

## 1) Instalar stack base

### Paquetes

```bash
sudo apt update && sudo apt -y upgrade
sudo apt -y install nginx mysql-server redis-server supervisor unzip git curl ufw certbot python3-certbot-nginx
```

### PHP 8.2 + extensiones

```bash
sudo apt -y install php8.2-fpm php8.2-cli php8.2-mysql php8.2-xml php8.2-curl php8.2-mbstring php8.2-zip php8.2-bcmath php8.2-soap php8.2-intl
php -v
```

### Firewall

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
sudo ufw status
```

---

## 2) Base de datos MySQL

### Hardening

```bash
sudo mysql_secure_installation
```

### Crear DB y usuario

En MySQL:

```sql
CREATE DATABASE smartpet CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'smartpet'@'localhost' IDENTIFIED BY 'TU_PASSWORD_FUERTE';
GRANT ALL PRIVILEGES ON smartpet.* TO 'smartpet'@'localhost';
FLUSH PRIVILEGES;
```

---

## 3) Deploy (carpetas y permisos)

### Usuario de deploy (recomendado)

```bash
sudo adduser deploy
sudo usermod -aG sudo deploy
```

### Directorio

```bash
sudo mkdir -p /var/www/smartpet
sudo chown -R deploy:www-data /var/www/smartpet
```

Clona el repo en `/var/www/smartpet` (ajusta URL):

```bash
cd /var/www/smartpet
git clone <TU_REPO> .
```

---

## 4) Configurar `.env` producción

En `backend/`:

```bash
cd /var/www/smartpet/backend
cp .env.example .env
php artisan key:generate
```

Ajusta:
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://api.tu-dominio.com`
- DB: `DB_DATABASE=smartpet`, `DB_USERNAME=smartpet`, `DB_PASSWORD=...`
- Redis:
  - `QUEUE_CONNECTION=redis`
  - `CACHE_STORE=redis`

> Para DIAN stub: `DIAN_STUB_MODE=accepted|sent|rejected`

---

## 5) Instalar dependencias y optimizar

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Permisos:

```bash
sudo chown -R deploy:www-data /var/www/smartpet/backend
sudo chmod -R ug+rwx /var/www/smartpet/backend/storage /var/www/smartpet/backend/bootstrap/cache
```

---

## 6) Nginx (API + SPA)

Usa los templates:
- `backend/ops/nginx.petflow-api.conf` (API Laravel)
- `backend/ops/nginx.petflow-www.conf` (React SPA)

Luego:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

TLS:

```bash
sudo certbot --nginx -d api.petflow.com
sudo certbot --nginx -d www.petflow.com -d petflow.com
```

---

## 7) Workers + Scheduler

### Supervisor (colas)

Usa `backend/ops/supervisor.smartpet-worker.conf`.

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

### Cron scheduler

```bash
sudo crontab -e
```

Agregar:

```cron
* * * * * cd /var/www/smartpet/backend && php artisan schedule:run >> /dev/null 2>&1
```

---

## 8) Smoke tests (mínimos)

- `GET /api/system/info`
- Login `POST /api/auth/login` y `GET /api/v1/auth/me`
- `GET /api/v2/config/masters`
- `GET /api/v2/clients`
- Billing:
  - `POST /api/v2/company-tax-profiles` (crear perfil CO)
  - `POST /api/v2/billing/documents` (crear doc)
  - `POST /api/v2/billing/documents/{id}/submit` (cola)
  - `GET /api/v2/billing/documents/{id}/status`

---

## 9) Backups (obligatorio)

Usa `backend/ops/backup_mysql.sh` (ajusta credenciales/paths).

Recomendación:
- 1 backup diario DB (rotación 7–14 días)
- backup de `backend/storage/` si guardas PDFs/XML/fotos
- prueba de restore mensual

