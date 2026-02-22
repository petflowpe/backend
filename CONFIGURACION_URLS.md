# 🔧 Configuración de URLs y Conexiones

## 📍 Estado Actual

### Frontend (PetSmart)
- **URL de API por defecto**: `http://localhost:8000/api`
- **Archivo de configuración**: `PetSmart/src/utils/api/config.ts`
- **Variable de entorno**: `VITE_API_URL`

### Backend (Laravel)
- **URL de aplicación por defecto**: `http://localhost:8000`
- **Base de datos por defecto**: `127.0.0.1` (localhost)
- **Archivo de configuración**: `backend-grooming/.env`

---

## 🔍 Verificar Configuración Actual

### 1. Verificar Frontend

**Archivo**: `PetSmart/.env` (si existe) o `PetSmart/.env.local`

```bash
# Verificar si existe
cat PetSmart/.env

# Si no existe, crear uno
echo "VITE_API_URL=http://localhost:8000/api" > PetSmart/.env
```

**Código actual** (`PetSmart/src/utils/api/config.ts`):
```typescript
export const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api';
```

### 2. Verificar Backend

**Archivo**: `backend-grooming/.env`

```bash
# Verificar configuración actual
cd backend-grooming
php artisan config:show database.connections.mysql.host
php artisan config:show app.url
```

O revisar manualmente el archivo `.env`:
```env
APP_URL=http://localhost:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nombre_base_datos
DB_USERNAME=usuario
DB_PASSWORD=contraseña
```

---

## 🌐 Cambiar a Producción (VPS Hostinger)

### Opción 1: Backend en VPS, Base de Datos Local

**Backend `.env`**:
```env
APP_URL=https://tu-dominio.com
# O si usas IP:
APP_URL=http://tu-ip-vps:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1  # Base de datos en el mismo VPS
DB_PORT=3306
DB_DATABASE=nombre_base_datos
DB_USERNAME=usuario
DB_PASSWORD=contraseña
```

**Frontend `.env`**:
```env
VITE_API_URL=https://tu-dominio.com/api
# O si usas IP:
VITE_API_URL=http://tu-ip-vps:8000/api
```

### Opción 2: Backend en VPS, Base de Datos Remota

**Backend `.env`**:
```env
APP_URL=https://tu-dominio.com

DB_CONNECTION=mysql
DB_HOST=ip-base-datos-remota  # IP de tu base de datos remota
DB_PORT=3306
DB_DATABASE=nombre_base_datos
DB_USERNAME=usuario_remoto
DB_PASSWORD=contraseña_segura
```

**Frontend `.env`**:
```env
VITE_API_URL=https://tu-dominio.com/api
```

### Opción 3: Desarrollo Local con Base de Datos Remota

**Backend `.env`**:
```env
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=ip-vps-hostinger  # IP de tu VPS donde está la BD
DB_PORT=3306
DB_DATABASE=nombre_base_datos
DB_USERNAME=usuario_remoto
DB_PASSWORD=contraseña_segura
```

**Frontend `.env`**:
```env
VITE_API_URL=http://localhost:8000/api
```

---

## 📝 Pasos para Cambiar la Configuración

### Paso 1: Actualizar Backend

1. Editar `backend-grooming/.env`:
```bash
cd backend-grooming
nano .env
# O en Windows:
notepad .env
```

2. Cambiar las variables necesarias:
```env
APP_URL=https://tu-dominio.com
DB_HOST=ip-o-hostname
```

3. Limpiar caché de configuración:
```bash
php artisan config:clear
php artisan config:cache
```

### Paso 2: Actualizar Frontend

1. Crear o editar `PetSmart/.env`:
```bash
cd PetSmart
echo "VITE_API_URL=https://tu-dominio.com/api" > .env
```

2. Reiniciar el servidor de desarrollo:
```bash
npm run dev
# O si usas Vite directamente:
vite
```

### Paso 3: Verificar CORS (si es necesario)

Si el frontend y backend están en dominios diferentes, verificar CORS:

**Backend**: `backend-grooming/config/cors.php`
```php
'allowed_origins' => [
    'http://localhost:3000',
    'http://localhost:5173',
    'https://tu-dominio-frontend.com',
],
```

---

## 🔒 Configuración Segura para Producción

### Backend `.env` (Producción)
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.tu-dominio.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1  # O IP de BD remota
DB_PORT=3306
DB_DATABASE=nombre_bd
DB_USERNAME=usuario_seguro
DB_PASSWORD=contraseña_muy_segura

# SSL para MySQL (si está disponible)
MYSQL_ATTR_SSL_CA=/ruta/al/ca-cert.pem
```

### Frontend `.env.production`
```env
VITE_API_URL=https://api.tu-dominio.com/api
```

---

## 🧪 Verificar que Funciona

### 1. Verificar Backend
```bash
cd backend-grooming
php artisan config:show app.url
php artisan tinker
>>> config('database.connections.mysql.host')
```

### 2. Verificar Frontend
En la consola del navegador (F12):
```javascript
console.log(import.meta.env.VITE_API_URL)
```

O revisar las peticiones en la pestaña Network:
- Debe apuntar a la URL configurada en `VITE_API_URL`

### 3. Probar Conexión
```bash
# Desde el frontend, hacer una petición de prueba
curl https://tu-dominio.com/api/v1/auth/me
```

---

## ⚠️ Problemas Comunes

### Error: "Connection refused"
- **Causa**: El backend no está escuchando en esa URL
- **Solución**: Verificar que el servidor esté corriendo y que `APP_URL` sea correcta

### Error: "CORS policy"
- **Causa**: El frontend y backend están en dominios diferentes
- **Solución**: Configurar CORS en `backend-grooming/config/cors.php`

### Error: "Database connection failed"
- **Causa**: `DB_HOST` incorrecto o firewall bloqueando
- **Solución**: 
  - Verificar IP/hostname de la base de datos
  - Verificar firewall (ver `CONEXION_REMOTA_BD.md`)
  - Verificar credenciales

### Frontend sigue usando localhost
- **Causa**: Caché de Vite o `.env` no cargado
- **Solución**:
  ```bash
  # Limpiar caché de Vite
  rm -rf PetSmart/node_modules/.vite
  # Reiniciar servidor
  npm run dev
  ```

---

## 📋 Checklist de Configuración

- [ ] Backend `.env` configurado con `APP_URL` correcta
- [ ] Backend `.env` configurado con `DB_HOST` correcto
- [ ] Frontend `.env` configurado con `VITE_API_URL` correcta
- [ ] CORS configurado si frontend y backend en dominios diferentes
- [ ] Caché de configuración limpiada (`php artisan config:clear`)
- [ ] Servidor de desarrollo reiniciado
- [ ] Conexión a base de datos probada
- [ ] Peticiones API funcionando desde el frontend

---

## 🔗 Archivos Relacionados

- `backend-grooming/CONEXION_REMOTA_BD.md` - Guía para conectar BD remota
- `backend-grooming/GUIA_DESPLIEGUE_VPS.md` - Guía de despliegue en VPS
- `PetSmart/src/utils/api/config.ts` - Configuración de API en frontend
