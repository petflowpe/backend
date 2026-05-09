# Especificación Técnica: Integración Frontend SmartPet (React) con Backend (Laravel API)

Este documento ordena el **contrato de integración** entre el frontend SmartPet (React) y el backend (Laravel API), tomando como base:
- La API real existente en `routes/api.php` (Laravel 12 + Sanctum + multi-empresa).
- La necesidad de reemplazar progresivamente **mocks** por **endpoints reales** sin romper la UX.

---

## 1) Visión general

- **Arquitectura**: desacoplada (SPA React ↔ API REST Laravel).
- **Frontend**: React 18 + Tailwind (SPA).
- **Backend actual**: Laravel **12** (compatible con lo esperado 10/11 a nivel de patrón API), PHP 8.2, Sanctum, colas/cache en DB, soporte MySQL/PostgreSQL.
- **Objetivo**: que React consuma endpoints reales con baja latencia y respuestas consistentes.

---

## 2) Convenciones de API (obligatorias)

### Base URL y versionado

- Base: `/api`
- Versión: `/api/v1`
- Endpoints públicos fuera de `v1` (ej. `/api/system/info`, `/api/setup/status`) se mantienen para health/setup.

### Autenticación

- Esquema: **Bearer Token** (Laravel Sanctum)
- Header:
  - `Authorization: Bearer <token>`

### Contexto multi-empresa (tenant)

El backend ya usa middleware `EnsureUserCompanyScope` en rutas protegidas.

- Para usuarios normales: el **company_id efectivo** debe provenir del usuario (y no confiar en el body/query).
- Para `super_admin`: se permite seleccionar empresa/sucursal con headers:
  - `X-Company-Id: <int>`
  - `X-Branch-Id: <int>`

> Recomendación de integración: el frontend guarde `companyId` / `branchId` “activos” y los envíe como headers solo cuando el usuario tenga modo multi-empresa (super_admin).

### Formato de respuesta estándar

Para listados paginados (trait `RespondsWithPagination`):

```json
{
  "success": true,
  "data": [],
  "meta": {
    "total": 0,
    "per_page": 15,
    "current_page": 1,
    "last_page": 1
  }
}
```

Para errores típicos:

```json
{
  "success": false,
  "message": "Mensaje de error",
  "errors": {
    "campo": ["detalle"]
  }
}
```

### Naming JSON (camelCase)

Estado actual:
- La mayoría de modelos/columnas están en **snake_case** (ej. `razon_social`, `numero_documento`).

Objetivo (recomendado para el frontend):
- Respuestas en **camelCase** con **API Resources** (Laravel) para no forzar mapeos ad-hoc en React.

Estrategia sugerida (sin romper compatibilidad):
- **Fase 1**: mantener snake_case y mapear en frontend donde ya exista (rápido).
- **Fase 2**: exponer endpoints `v2` o un flag `?format=camel` (o header) y migrar pantallas gradualmente.
- **Fase 3**: estandarizar todo a camelCase.

---

## 3) Módulos principales (pilares) y relaciones

### A) Clientes y Mascotas (núcleo)

- Relación: `Client 1:N Pet`
- Backend ya tiene modelos `Client` y `Pet` con `Client::pets()` y `Pet::client()`.

Campos frontend (referencia) vs backend actual (resumen):
- `fullName` → `razon_social`
- `documentType` (DNI/RUC/CE/Pasaporte) → `tipo_documento` (códigos SUNAT: `1,6,4,7,...`)
- `documentNumber` → `numero_documento`
- `phone` → `telefono`
- `address` → `direccion`
- `district` → `distrito`
- `status` → `activo` (boolean)

Mascota:
- `birthDate` → `birth_date`
- `medicalNotes` → `notes` (y/o tabla `medical_records`)
- `gender` → `gender`
- `species` → `species` (nota: hoy usa `Perro/Gato/Otro`; el frontend habla de `Exótico`)

**Estado calculado de mascota (Nuevo/Recuperado/Perdido/Recurrente)**:
- Recomendado: devolver un campo calculado `statusFlag` (o `petStatus`) en la respuesta, calculado con `appointments` recientes y/o `fecha_ultima_visita`.

### B) Facturación (SUNAT)

Relaciones:
- `Invoice belongsTo Client`
- `Invoice 1:N InvoiceItems`

El backend ya cuenta con un módulo grande de documentos SUNAT (facturas, boletas, NC/ND, resúmenes, bajas, guías, retenciones) y endpoints para:
- emitir
- enviar a SUNAT
- descargar XML/CDR/PDF

### C) Logística (Zonas y Vehículos)

El backend ya expone:
- `zones` (API Resource)
- `vehicles` y configuraciones/mantenimientos/gastos/servicios
- `route-plans` (planes de ruta)

Asignación zona por distrito:
- Recomendación: al crear/editar `Client`, asignar `zone_id` automáticamente a partir de `district` (requiere tabla/relación y una regla única).

---

## 4) Endpoints (mapa por módulo) — API actual

> Base protegida: `/api/v1` con `auth:sanctum`, `throttle:api`, `EnsureUserCompanyScope`.

### Autenticación / Setup
- `GET /api/system/info`
- `GET /api/setup/status`
- `POST /api/auth/initialize`
- `POST /api/auth/login`
- `POST /api/auth/forgot-password`
- `POST /api/auth/reset-password`
- `POST /api/auth/request-access`
- `POST /api/v1/auth/logout`
- `GET /api/v1/auth/me`
- `POST /api/v1/auth/create-user`

### Core / Config
- `GET /api/v1/core/currencies`
- `GET /api/v1/core/modules`
- `GET /api/v1/config/defaults`
- `GET /api/v1/config/summary`
- `GET|PUT /api/v1/companies/{company_id}/config/{section}`

### Empresas / Sucursales
- `apiResource /api/v1/companies`
- `POST /api/v1/companies/{company}/activate`
- `POST /api/v1/companies/{company}/toggle-production`
- `apiResource /api/v1/branches`
- `POST /api/v1/branches/{branch}/activate`
- `GET /api/v1/companies/{company}/branches`

### Ubigeos
- `GET /api/v1/ubigeos/regiones`
- `GET /api/v1/ubigeos/provincias`
- `GET /api/v1/ubigeos/distritos`
- `GET /api/v1/ubigeos/search`
- `GET /api/v1/ubigeos/{id}`

### Clientes / Mascotas / Citas (núcleo SmartPet)
- `apiResource /api/v1/clients`
- `POST /api/v1/clients/search-by-document`
- `GET /api/v1/clients/{clientId}/pets`
- `apiResource /api/v1/pets`
- `POST /api/v1/pets/{id}/photos`
- `GET /api/v1/pets/{id}/timeline`
- `GET /api/v1/pets/{id}/audit-history`
- `apiResource /api/v1/appointments`
- `POST /api/v1/appointments/{appointment}/reschedule`
- `POST /api/v1/appointments/{appointment}/change-status`
- `POST /api/v1/appointments/{appointment}/send-reminder`
- `POST /api/v1/appointments/{appointment}/confirm`

### Registros médicos
- `apiResource /api/v1/medical-records`
- `GET /api/v1/pets/{petId}/medical-records`
- `GET /api/v1/clients/{clientId}/medical-records`

### Inventario / Productos
- `apiResource /api/v1/products` (+ `DELETE /products/{product}` custom)
- `POST /api/v1/products/{product}/activate`
- `GET /api/v1/companies/{company}/products`
- `GET /api/v1/companies/{company}/products/kpis`
- `GET /api/v1/products/low-stock`
- `POST /api/v1/products/{product}/adjust-stock`
- `GET /api/v1/products/{product}/kardex`
- `apiResource /api/v1/categories`
- `apiResource /api/v1/units`
- `apiResource /api/v1/areas`
- `apiResource /api/v1/brands`
- `apiResource /api/v1/suppliers`

### Caja / Pagos / Reportes
- `POST /api/v1/cash-sessions/open`
- `POST /api/v1/cash-sessions/{cashSession}/close`
- `apiResource /api/v1/cash-movements`
- `GET|POST /api/v1/payments`
- `GET /api/v1/reports/sales`
- `GET /api/v1/reports/stats`
- `GET /api/v1/reports/products`
- `GET /api/v1/reports/clients`

### Vehículos / Operaciones / Rutas
- `apiResource /api/v1/zones`
- `GET|POST /api/v1/route-plans` (+ show/update/destroy)
- `apiResource /api/v1/vehicles`
- Endpoints de mantenimientos/gastos/servicios de vehículos (ver `routes/api.php`).

### Documentos electrónicos SUNAT (resumen)
- `invoices/*`
- `boletas/*`
- `daily-summaries/*`
- `credit-notes/*`
- `debit-notes/*`
- `retentions/*`
- `voided-documents/*`
- `dispatch-guides/*`
- `consulta-cpe/*`
- `consulta-cpe-v2/*` (archivo `routes/api_consulta_mejorada.php`)

### Seguridad / Auditoría / Notificaciones
- `GET /api/v1/audit-logs`
- `GET /api/v1/roles`
- `GET /api/v1/permissions`
- `GET|POST /api/v1/users` (CRUD)
- `GET|PUT /api/v1/profile`
- `GET|PUT /api/v1/settings`
- `GET|POST /api/v1/notifications` (incluye marcar leído / borrar)

---

## 5) Roadmap recomendado (alineado al frontend)

### 5.1 Clientes (listado split-view rápido)
Requisitos frontend:
- paginación
- `?search=`
- filtros por `zone`, `status`
- ordenamiento rápido

Acciones backend recomendadas:
- Soportar `order_by` / `order_dir`.
- Expandir búsqueda global para incluir mascota relacionada (por ejemplo `orWhereHas('pets', ...)`).
- Evitar `company_id` por body/query para usuarios normales (usar scope efectivo del middleware).

### 5.2 Detalle de cliente “completo”
Requisito frontend:
- `GET /clients/{id}` con pets + últimas 5 invoices + próxima appointment

Acción:
- Incluir relaciones y secciones en un endpoint o en endpoints específicos (`/clients/{id}/summary`).

### 5.3 Catálogos (masters)
Requisito:
- `GET /api/config/masters` (distritos, razas, tipos documento, etc.)

Estado actual:
- Ubigeo existe (`/ubigeos/*`)
- Defaults/config existen (`/config/*`)

Propuesta:
- En **API v2** ya existe: `GET /api/v2/config/masters` (catálogos + ubigeo + monedas + módulos).
- En legacy v1, mantener hardcode/mapeos solo si una pantalla aún no migró a v2.

### 5.4 Billing (multi-país, contrato estable)
En **API v2** existe el módulo base:
- `POST /api/v2/billing/documents`
- `POST /api/v2/billing/documents/{id}/submit`
- `GET /api/v2/billing/documents/{id}/status`

Y el CRUD de perfil fiscal por empresa/país:
- `apiResource /api/v2/company-tax-profiles`

---

## 6) Preparación para multi-país, moneda, idioma y módulos futuros

Base ya existente:
- `core/currencies` y `core/modules`
- `company_configurations` por empresa (horarios, settings, etc.)
- `locale` configurable por usuario (middleware ya lo contempla)

Recomendaciones para escalar SaaS:
- **Feature flags por empresa** (módulos habilitados).
- **Moneda por empresa** (y tipo de cambio si aplica).
- **i18n**: `APP_LOCALE` + locale por usuario y catálogos traducibles.
- **Integraciones**: Google Maps/Geocoding en un módulo separado (service layer + jobs).
- **IA**: colas + eventos (jobs) y almacenamiento de prompts/outputs con auditoría.

---

## 7) Checklist de integración (React)

- Auth: `POST /api/auth/login` → guardar `access_token` → usar `Authorization: Bearer`.
- Manejo de 401: limpiar sesión y redirigir a login (sin caer en mocks).
- Listados: usar `per_page`, `search`, y consumir `meta`.
- Multi-tenant: si aplica, enviar `X-Company-Id`/`X-Branch-Id` (solo cuando corresponda).

