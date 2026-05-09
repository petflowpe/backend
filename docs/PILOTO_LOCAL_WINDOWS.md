## Piloto (local en Windows + Laragon)

### Objetivo

Dejar el backend listo para operar como en producción (API + colas + scheduler) con un setup reproducible.

### 1) Setup inicial (migraciones + seed + usuario piloto)

- Ejecuta `PILOTO-SETUP.bat` (en la raíz del repo).

Esto corre:
- `composer install`
- `php artisan pilot:setup --fresh --demo`

Al final imprimirá:
- Empresa / sucursal creadas
- Credenciales del admin
- Token Bearer (Sanctum)

### 2) Iniciar servicios (modo “producción local”)

- Ejecuta `PILOTO-INICIAR-SERVICIOS.bat` (en la raíz del repo).

Levanta 3 ventanas:
- API: `php artisan serve`
- Queue worker: `php artisan queue:work`
- Scheduler: `php artisan schedule:work`

### 3) Smoke rápido

- `GET /api/system/info` (público)
- Login: `POST /api/auth/login`
- Masters v2: `GET /api/v2/config/masters` (requiere Bearer token)
- Clientes v2: `GET /api/v2/clients` (requiere Bearer token)

Tip:
- Ejecuta `PILOTO-SMOKE.bat` (en la raíz) para validar automáticamente los endpoints base.

### Reset limpio (borrar datos de prueba)

Si quieres **eliminar todos los datos demo/prueba** y volver a un estado limpio:

- Ejecuta `PILOTO-RESET.bat` (en la raíz del repo).

Esto corre:
- `php artisan pilot:setup --fresh` (sin `--demo`)

Resultado:
- BD vacía de datos demo (clientes/mascotas/etc.)
- Catálogos base + empresa + admin recreados

