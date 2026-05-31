# UAT (Piloto) — Checklist de flujos críticos para PetFlow

Checklist para validar el sistema “end-to-end” con la empresa piloto antes de producción.

---

## 1) Acceso y sesión

- Login válido (usuario activo) → entra al sistema.
- Login inválido → error claro (401/422).
- Token expirado/invalidado → frontend fuerza logout.

API:
- `POST /api/auth/login`
- `GET /api/v1/auth/me`

---

## 2) Contexto de empresa/sucursal (multi-tenant)

- Usuario de empresa A:
  - No puede leer/crear/editar datos de empresa B aunque envíe `company_id` en request.
- Si aplica super admin:
  - Puede cambiar empresa activa vía `X-Company-Id` y ver datos de esa empresa.

Validar en módulos:
- Clientes, Mascotas, Citas, Productos, Caja, Facturación, Rutas.

---

## 3) Clientes

### 3.1 Listado (Split view / búsqueda rápida)
- Listado carga rápido (paginado) y soporta `search`.
- Búsqueda encuentra por:
  - nombre del cliente
  - documento
  - nombre de mascota

API v2:
- `GET /api/v2/clients?perPage=15&search=...`

### 3.2 Crear/editar/desactivar
- Crear cliente con DNI/RUC/CE/Pasaporte.
- Editar datos de contacto.
- Desactivar (no borrar físico).

API v2:
- `POST /api/v2/clients`
- `PUT /api/v2/clients/{id}`
- `DELETE /api/v2/clients/{id}`

---

## 4) Mascotas

- Agregar mascota desde cliente (flujo principal).
- Editar datos médicos básicos.
- Ver timeline / historial (si aplica).

API:
- `POST /api/v2/clients/{id}/pets`
- `GET /api/v1/clients/{clientId}/pets` (legacy si pantalla no migró)
- `GET /api/v1/pets/{id}/timeline` (legacy)

---

## 5) Citas (Agenda)

- Crear cita con cliente + mascota + fecha/hora.
- Cambiar estado (Pendiente → Confirmada → En Proceso → Completada).
- Reprogramar.
- Validar horarios de empresa (si está activo).

API:
- `POST /api/v1/appointments`
- `POST /api/v1/appointments/{id}/change-status`
- `POST /api/v1/appointments/{id}/reschedule`

---

## 6) Inventario / Productos

- Crear producto
- Ajustar stock (entrada/salida) y revisar kardex
- “Bajo stock” correcto

API:
- `GET/POST/PUT/DELETE /api/v1/products`
- `POST /api/v1/products/{id}/adjust-stock`
- `GET /api/v1/products/{id}/kardex`
- `GET /api/v1/products/low-stock`

---

## 7) Caja y pagos

- Abrir caja
- Registrar movimientos/pagos
- Cerrar caja y validar totales

API:
- `POST /api/v1/cash-sessions/open`
- `POST /api/v1/payments`
- `POST /api/v1/cash-sessions/{id}/close`

---

## 8) Facturación (piloto)

### 8.1 Perú (SUNAT) — si aplica en tu operación actual
- Emitir documento y enviar a SUNAT.
- Descargar PDF/XML/CDR.

### 8.2 Colombia (DIAN) — contrato v2 (base lista)
- Configurar perfil fiscal CO.
- Crear documento billing v2.
- Enviar a “timbrado/validación” (en staging con stub).
- Revisar estado fiscal.

API v2:
- `POST /api/v2/company-tax-profiles`
- `POST /api/v2/billing/documents`
- `POST /api/v2/billing/documents/{id}/submit`
- `GET /api/v2/billing/documents/{id}/status`

---

## 9) Operación (servidor)

- Worker de colas corriendo (Supervisor).
- Scheduler activo (cron).
- Backups diarios de MySQL y prueba de restore.
- Monitoreo de errores (Sentry recomendado).

Ver runbook:
- `ops/PRODUCTION_RUNBOOK_UBUNTU_MYSQL.md`

