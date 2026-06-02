# Arquitectura Core + Módulos Enchufables

Documento de diseño para escalar el sistema: **core** (datos y reglas clave), **módulos** que se pueden integrar o desactivar sin romper el núcleo, **multiidioma** (es, en, pt-BR) y **multimoneda**.

---

## 1. ¿Se puede? Sí

- **Core:** Contiene solo lo esencial: tenant (empresa/sucursal), usuarios, permisos, configuración global, idioma y moneda. No depende de módulos.
- **Módulos:** Cada módulo (facturación, mascotas, inventario, caja, etc.) se registra, expone rutas y menús, y puede activarse/desactivarse por empresa o globalmente.
- **Idiomas:** Backend con `lang/{locale}`; frontend con archivos de traducción; preferencia por usuario o por empresa.
- **Monedas:** Tabla de monedas y tipo de cambio; documentos y productos referencian moneda; el core ofrece el catálogo y la conversión base.

Todo lo anterior es compatible con tu stack actual (Laravel + React) y se puede introducir de forma gradual.

---

## 2. Visión: Core vs Módulos

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           CAPA DE MÓDULOS                                 │
│  [Facturación SUNAT] [Mascotas/Citas] [Inventario] [Caja] [Rutas] ...   │
│  Cada uno: rutas, modelos, permisos, menú, hooks frontend                │
└─────────────────────────────────────────────────────────────────────────┘
                    │ registra │ eventos │ permisos
                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                              CORE                                         │
│  • Tenant (Company, Branch)   • Users, Roles, Permissions                 │
│  • Locales (idiomas)          • Currencies (monedas + tipo cambio)        │
│  • Settings globales         • AuditLog   • Notificaciones base           │
│  • API auth y /me            • Registro de módulos activos               │
└─────────────────────────────────────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                         INFRAESTRUCTURA                                   │
│  Laravel, DB, Sanctum, colas, storage, logs                             │
└─────────────────────────────────────────────────────────────────────────┘
```

- **Core:** estable; cambios en core son pocos y controlados.
- **Módulos:** se “enchufan” vía Service Provider, rutas y menú; si un módulo se desactiva, no se cargan sus rutas ni sus ítems de menú.

---

## 3. Contenido del Core (datos y reglas clave)

### 3.1 Backend (Laravel)

| Componente | Responsabilidad |
|------------|------------------|
| **Tenant** | Company, Branch; middleware que resuelve empresa/sucursal del usuario. |
| **Auth** | Login, logout, /me, refresh; Sanctum; recuperación de contraseña. |
| **Users & RBAC** | User, Role, Permission; asignación rol/permisos; políticas base. |
| **Locales** | Catálogo de idiomas (es, en, pt_BR); preferencia por usuario y/o empresa; `App::setLocale()`. |
| **Currencies** | Tabla `currencies` (code, name, symbol, decimal_places); tabla `exchange_rates` (opcional) para conversión. |
| **Settings** | Configuración global y por empresa (tabla `settings` o `company_configurations` para lo que sea transversal). |
| **AuditLog** | Registro de acciones sensibles (quién, cuándo, recurso). |
| **Notifications** | Modelo base y canal mínimo (DB); los módulos pueden añadir canales. |
| **ModuleRegistry** | Tabla `modules` (name, slug, active, config json) y/o registro en código; lista de módulos “enchufados”. |

Todo lo que no sea estrictamente de un dominio (facturación, mascotas, inventario, caja, rutas, etc.) debería vivir en el core o en una capa compartida.

### 3.2 Frontend (React)

| Componente | Responsabilidad |
|------------|------------------|
| **Auth** | Login, token, /me, logout; contexto de usuario (y companyId/branchId). |
| **TenantContext** | Empresa y sucursal activas; disponible para todos los módulos. |
| **i18n** | Librería (ej. react-i18next); archivos `locales/{es,en,pt-BR}.json`; selector de idioma; idioma por usuario. |
| **Currencies** | Catálogo de monedas (desde API core); selector y formato de montos (símbolo, decimales). |
| **Layout y menú** | Menú principal construido a partir de “ítems” que cada módulo registra (ruta, nombre traducido, permiso, orden). |
| **API client** | Cliente HTTP, token, manejo 401; compartido por todos los módulos. |

Los módulos solo añaden: rutas (tabs o rutas reales), componentes de pantalla y entradas al menú; no duplican auth ni tenant.

---

## 4. Cómo “enchufar” un módulo

### 4.1 Backend

- **Carpeta por módulo (opcional pero recomendado):**
  - `app/Modules/Invoicing/` (o `app/Modules/Facturacion/`)
  - Contiene: Models, Http/Controllers, Requests, Services, Routes (archivo).
- **ServiceProvider del módulo:**
  - Registra rutas: `Route::prefix('api/v1')->middleware('auth:sanctum')->group(base_path('app/Modules/Invoicing/routes.php'))`.
  - Registra permisos en una tabla o en un seeder (ej. `invoicing.create`, `invoicing.view`).
  - (Opcional) Suscribe eventos del core (ej. “al crear cliente” → el módulo de facturación no hace nada, pero podría).
- **Registro en el core:**
  - En `config/modules.php` o en la tabla `modules`: nombre, slug, activo, rutas de API que expone.
  - O en `AppServiceProvider`: cargar solo los módulos que estén activos (según DB o config).

Contrato mínimo del módulo:

- No asumir que es el único que usa la DB; usar migraciones en su carpeta y no tocar tablas del core salvo las definidas (ej. `company`, `users`).
- Usar siempre el `company_id` (y si aplica `branch_id`) que provee el core (middleware o helper), no confiar solo en el request.
- Registrar sus permisos para que el core los asocie a roles.

### 4.2 Frontend

- **Carpeta por módulo (recomendado):**
  - `src/modules/invoicing/`: páginas, componentes, hooks que usen la API de facturación.
- **Registro del módulo:**
  - En un `registry` (objeto o array): `{ id: 'invoicing', nameKey: 'modules.invoicing', tab: 'invoicing', permission: 'invoicing.view', order: 40 }`.
  - El layout lee ese registry y genera el menú; si el usuario no tiene permiso o el módulo está desactivado, no se muestra.
- **Rutas/tabs:**
  - Cada módulo declara su tab (o ruta) y el componente; el router o el switch de tabs del core los usa.

Así, “agregar un módulo” = implementar backend + frontend del módulo + registrar en core; el core no tiene que tocar código del módulo.

---

## 5. Multiidioma (es, en, pt-BR)

### 5.1 Backend

- **Laravel ya tiene `lang/`:**
  - Mantener `lang/es/` (ya existe).
  - Añadir `lang/en/` y `lang/pt_BR/` (o `pt-BR`) con los mismos archivos: `validation.php`, `auth.php`, `pagination.php`, `messages.php`, etc.
- **Locale por usuario:**
  - Campo `locale` en `users` (ej. `es`, `en`, `pt_BR`). En login o en `/me` devolver `locale`; el middleware o un helper puede hacer `App::setLocale($request->user()->locale ?? config('app.locale'))`.
- **Locale por empresa (opcional):**
  - En `company_configurations` o `settings`: `default_locale` por empresa; si el usuario no tiene preferencia, usar la de la empresa.
- **Respuestas API:**
  - Los mensajes de validación y los textos que devuelve la API ya saldrán en el idioma del usuario si usas `__()` y `App::setLocale()`.
- **Paquete:** Ya usas `laravel-lang/*`; puedes añadir traducciones para `en` y `pt_BR` (o las que ofrezca el paquete).

### 5.2 Frontend

- **Librería:** `react-i18next` + `i18next` (recomendado).
- **Archivos:** `src/locales/es.json`, `en.json`, `pt-BR.json` (claves iguales, valores traducidos).
- **Idioma actual:** Guardado en usuario (desde `/me`) o en `localStorage`; al cargar la app, `i18n.changeLanguage(locale)`.
- **Selector:** En el menú o en ajustes; al cambiar, llamar a API `PATCH /profile` con `{ locale: 'en' }` y actualizar i18n.
- **Módulos:** Cada módulo puede tener su propio namespace (ej. `invoicing.*`) o compartir un `common.*`; las claves se referencian en los componentes con `t('common.save')`, `t('invoicing.title')`, etc.

Con esto puedes escalar a más idiomas añadiendo solo archivos de idioma y opciones en el selector.

---

## 6. Multimoneda

### 6.1 Modelo de datos (core)

- **Tabla `currencies`:**
  - `id`, `code` (PEN, USD, BRL), `name`, `symbol`, `decimal_places`, `active`, `is_default` (una sola por tenant o global).
- **Tabla `exchange_rates` (opcional):**
  - `id`, `company_id` (nullable para global), `from_currency`, `to_currency`, `rate`, `effective_at`; así puedes tener tipo de cambio por empresa y por fecha.
- **En documentos y productos:**
  - Ya tienes `currency` en productos; en facturas/boletas suele ir `moneda`. Mantener un campo `currency` o `currency_code` que sea FK a `currencies` o al menos código ISO; el core expone el catálogo y el tipo de cambio si aplica.

### 6.2 API core

- **GET /currencies:** Listado de monedas activas (para el tenant o global).
- **GET /exchange-rates?from=PEN&to=USD** (opcional): Tipo de cambio actual (o por fecha) para mostrar equivalentes.

Los módulos (facturación, productos, caja) usan ese catálogo y, si aplica, convierten a moneda de presentación o a la moneda legal de la empresa.

### 6.3 Frontend

- Selector de moneda en formularios (factura, producto, caja).
- Formatear montos con `Intl.NumberFormat` o una utilidad que use `symbol` y `decimal_places` del catálogo.
- Si hay tipo de cambio, mostrar “equivalente en PEN” (o la moneda base) en detalle de documentos.

---

## 7. Plan de implementación sugerido (por fases)

### Fase 1 – Core estable (sin mover aún la lógica de negocio)

1. **Middleware de tenant:** Resolver `company_id` (y `branch_id`) desde el usuario y dejarlo en `request()`; usarlo en todos los controladores que hoy usan `$request->company_id`.
2. **Tabla `currencies` y seeder:** PEN, USD, BRL; endpoint GET /currencies.
3. **Tabla `exchange_rates` (opcional)** y endpoint si quieres conversión desde ya.
4. **Campo `locale` en users** y en `/me`; en backend `App::setLocale()` según usuario.
5. **Frontend i18n:** Instalar react-i18next; `es.json` con las cadenas actuales; selector de idioma que guarde en perfil y recargue traducciones.

### Fase 2 – Idiomas completos y moneda en UI

1. Añadir `lang/en` y `lang/pt_BR` en backend (traducciones de validación, auth, etc.).
2. Añadir `en.json` y `pt-BR.json` en frontend; traducir pantallas clave (login, menú, dashboard, clientes, productos).
3. Reemplazar textos fijos en componentes por `t('key')`.
4. Usar en todos los formularios de documentos/productos el catálogo de monedas (dropdown) y formatear montos con la moneda seleccionada.

### Fase 3 – Módulos como “enchufables”

1. **Definir contrato del módulo:** lista de permisos, rutas API, tab/ruta en frontend, ítem de menú.
2. **Tabla `modules`:** name, slug, active, config (json); o solo config en `config/modules.php` por ahora.
3. **Mover un módulo piloto (ej. “Productos” o “Categorías”) a `app/Modules/Catalog`:** sus controladores, modelos, rutas; registrar rutas solo si el módulo está activo.
4. **Frontend:** Registry de módulos; menú generado desde el registry; rutas/tabs que carguen el componente del módulo.
5. Repetir con otro módulo (ej. Facturación, Mascotas) hasta tener el patrón claro.

### Fase 4 – Escalar

- Nuevos módulos (ej. CRM, Nómina, Otro país) siguen el mismo contrato: carpeta, rutas, permisos, menú; se “enchufan” sin tocar el core.
- Nuevos idiomas: nuevo locale en backend + nuevo json en frontend + opción en selector.
- Nuevas monedas: fila en `currencies` y, si aplica, en `exchange_rates`.

---

## 8. Resumen de decisiones clave

| Tema | Decisión |
|------|----------|
| **Core** | Auth, tenant, users/roles/permissions, locales, currencies, settings, audit, notificaciones base, registro de módulos. |
| **Módulos** | Carpetas propias (backend y frontend); se registran (rutas, permisos, menú); se activan/desactivan sin tocar core. |
| **Idiomas** | es, en, pt_BR; locale en usuario (y opcional en empresa); backend `lang/` + frontend i18next; mensajes API y UI traducidos. |
| **Monedas** | Catálogo en core (currencies; exchange_rates opcional); documentos y productos referencian moneda; UI con selector y formato. |
| **Integración** | Contrato claro: módulo no toca tablas del core salvo las permitidas; usa company_id/branch_id del core; registra permisos y menú. |

Con esta base puedes crecer hacia más módulos, más idiomas y más monedas sin reescribir el sistema, y cada nuevo módulo se “enchufa” de forma controlada.
