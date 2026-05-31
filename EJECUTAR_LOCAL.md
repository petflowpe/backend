# Ejecutar ClonSmarpet en local

Para ver la app en el navegador y probar cambios, necesitas **backend** (Laravel) y **frontend** (React + Vite) corriendo a la vez.

---

## Requisitos previos

- **PHP 8.2+** ([php.net](https://www.php.net/downloads))
- **Composer** ([getcomposer.org](https://getcomposer.org))
- **Node.js 18+** y **npm** ([nodejs.org](https://nodejs.org))
- **MySQL 8** (o XAMPP/WAMP/Laragon con MySQL)

---

## 1. Base de datos

Crea una base de datos en MySQL para el backend, por ejemplo:

- Nombre: `db_api_sunat` (o el que quieras)
- Usuario y contraseña: los que uses en tu MySQL

---

## 2. Backend (Laravel)

Abre una terminal en la carpeta **`backend`**:

```powershell
cd "c:\Users\Usuario\Desktop\Proyecto Sistema LB\ClonSmarpet\backend"
```

### Instalar dependencias

```powershell
composer install
```

### Configurar entorno

```powershell
copy .env.example .env
php artisan key:generate
```

Abre el archivo **`.env`** y ajusta la base de datos:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=db_api_sunat
DB_USERNAME=root
DB_PASSWORD=tu_password_mysql
```

### Migraciones

```powershell
php artisan migrate
```

(Opcional) Si hay seeders para datos iniciales:

```powershell
php artisan db:seed
```

### Levantar el servidor

```powershell
php artisan serve
```

El backend quedará en **http://localhost:8000**. No cierres esta terminal.

---

## 3. Frontend (React + Vite)

Abre **otra terminal** en la carpeta **`frontend`**:

```powershell
cd "c:\Users\Usuario\Desktop\Proyecto Sistema LB\ClonSmarpet\frontend"
```

### Instalar dependencias

```powershell
npm install
```

### Levantar el servidor de desarrollo

```powershell
npm run dev
```

Verás algo como: **Local: http://localhost:5173/** (el puerto puede ser otro si 5173 está ocupado).

---

## 4. Ver la aplicación

1. Deja **las dos terminales abiertas** (backend y frontend).
2. En el navegador abre: **http://localhost:5173** (o la URL que muestre Vite).
3. La app del frontend ya está configurada para usar la API en **http://localhost:8000** por defecto.

Si cambias el puerto del backend, en `frontend` crea un archivo **`.env`** con:

```env
VITE_API_URL=http://localhost:8000/api
```

y reinicia `npm run dev`.

---

## Resumen rápido

| Paso | Dónde | Comando |
|------|--------|---------|
| 1 | backend | `composer install` |
| 2 | backend | `copy .env.example .env` → editar DB en `.env` |
| 3 | backend | `php artisan key:generate` |
| 4 | backend | `php artisan migrate` |
| 5 | backend | `php artisan serve` (puerto 8000) |
| 6 | frontend | `npm install` |
| 7 | frontend | `npm run dev` (puerto 5173) |
| 8 | Navegador | Abrir **http://localhost:5173** |

---

## Si algo falla

- **Backend no arranca:** Revisa que PHP y Composer estén en el PATH y que MySQL esté corriendo.
- **Error de base de datos:** Comprueba `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD` en `.env`.
- **Frontend no conecta al backend:** Comprueba que `php artisan serve` siga activo en el puerto 8000 y que no tengas un `.env` en frontend con otra `VITE_API_URL`.
