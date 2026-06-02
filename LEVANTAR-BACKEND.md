# El mensaje "No se pudo conectar al servidor" significa que el backend no está en marcha

El **frontend** (la pantalla de login) ya está corriendo. Para poder **iniciar sesión**, el **backend** debe estar ejecutándose en **http://localhost:8000**.

---

## Antes de empezar (Laragon): activar extensiones PHP

Para que `composer install` funcione bien, en Laragon activa **zip** y **soap**:

1. Abre **Laragon** → **Menu** → **PHP** → **php.ini** (se abre el archivo de configuración).
2. Busca con Ctrl+F la línea `;extension=zip` y quítale el `;` del inicio (queda `extension=zip`).
3. Busca `;extension=soap` y quítale el `;` (queda `extension=soap`).
4. Guarda el archivo y cierra. Reinicia Laragon si hace falta.

---

## Opción 1: Tienes Laragon instalado

1. Abre **Laragon**.
2. Clic derecho en el icono de Laragon → **Terminal** (o **Open Terminal Here**).
3. En la terminal escribe **dos comandos** (uno en cada línea, Enter después de cada uno):

```bash
cd "c:\Users\Usuario\Desktop\Proyecto Sistema LB\ClonSmarpet"
INICIAR-BACKEND.bat
```

O todo en una sola línea con `&&`:

```bash
cd "c:\Users\Usuario\Desktop\Proyecto Sistema LB\ClonSmarpet" && INICIAR-BACKEND.bat
```

4. **Antes:** en Laragon inicia **MySQL** (Start / Start All). Sin MySQL las migraciones fallan.
5. No cierres esa ventana. Cuando veas *"Laravel development server started"*, abre **http://localhost:3000** e inicia sesión con:
   - **Email:** `admin@sunatapi.com`
   - **Contraseña:** `admin123456`

---

## Opción 2: No tienes PHP/Laragon – Instalar Laragon (recomendado)

1. Descarga **Laragon** (incluye PHP, MySQL y Composer):  
   **https://laragon.org/download/**
2. Instala y abre Laragon.
3. Inicia **MySQL** y **Apache** (o "Start All") si quieres usar MySQL desde Laragon.
4. Clic derecho en Laragon → **Terminal**.
5. Ejecuta (dos líneas, Enter después de cada una; o una línea con `&&`):

```bash
cd "c:\Users\Usuario\Desktop\Proyecto Sistema LB\ClonSmarpet"
INICIAR-BACKEND.bat
```

```bash
cd "c:\Users\Usuario\Desktop\Proyecto Sistema LB\ClonSmarpet" && INICIAR-BACKEND.bat
```

6. Cuando el backend esté en marcha, en el navegador ve a **http://localhost:3000** y usa:
   - **Email:** `admin@sunatapi.com`
   - **Contraseña:** `admin123456`

---

## Opción 3: Ya tienes PHP y Composer en el PATH

Abre **PowerShell** o **CMD** y ejecuta:

```powershell
cd "c:\Users\Usuario\Desktop\Proyecto Sistema LB\ClonSmarpet\backend"
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
```

Deja esa ventana abierta. Luego abre **http://localhost:3000** e inicia sesión con `admin@sunatapi.com` / `admin123456`.

---

## Comprobar que el backend está corriendo

- Abre en el navegador: **http://localhost:8000/api/system/info**  
- Si ves un JSON con datos del sistema, el backend está bien.  
- Vuelve a **http://localhost:3000** e intenta iniciar sesión de nuevo.

---

## Ejecutar tests en tu máquina (recomendado antes de producción)

### Opción 1 (simple): ejecutar el script

Desde el explorador de Windows (doble clic):
- `EJECUTAR-TESTS.bat`

Este script intenta usar:
- `php` del PATH, o
- `php.exe` de Laragon (`C:\laragon\bin\php\php-*\php.exe`)

### Opción 2: desde Terminal de Laragon

```bash
cd "c:\Users\Usuario\Desktop\Proyecto Sistema LB\ClonSmarpet\backend"
php artisan test
```
