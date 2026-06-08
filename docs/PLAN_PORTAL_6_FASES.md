# Plan Portal de Reservas — 6 Fases

Última actualización: 2026-03-07

## Decisiones de negocio acordadas

| Tema | Decisión |
|------|----------|
| Acceso | Portal **solo clientes registrados** (invitado deshabilitado por defecto) |
| Pago al reservar | Cita **Confirmada** automáticamente |
| Sin pago | Staff valida en **Confirmaciones** |
| Clientes recurrentes | Toggle `portal_booking_enabled` en ficha cliente |
| Clientes nuevos | `portal_approval_status = pending` hasta validación staff |
| Adelanto MVP | **30%** (configurable: porcentaje o monto fijo) |
| Pasarela MVP | **Simulada** (`payment_mode: simulated`) |
| Horarios | Capas: `working_hours` + horario móvil + reglas cobertura + zonas |

---

## Fase 1 — BD + Backend ✅ (en progreso)

| Entregable | Estado |
|------------|--------|
| Migración: `portal_booking_enabled`, `portal_approval_status` en `clients` | ✅ |
| Migración: `booking_source`, `advance_*` en `appointments` | ✅ |
| `PortalBookingService` (settings, adelanto, permisos, notificaciones) | ✅ |
| `portal_settings` en `company_configurations` | ✅ |
| `POST /appointments/{id}/pay-advance` (simulado) | ✅ |
| Notificación staff al reservar por portal | ✅ |
| Invitado bloqueado si `guest_booking_enabled = false` | ✅ |

**Deploy:** `php artisan migrate` en VPS.

---

## Fase 2 — Disponibilidad unificada

| Entregable | Estado |
|------------|--------|
| Servicio único `AvailabilityService` (empresa + móvil + cobertura + zonas) | Pendiente |
| Misma lógica en portal auth, API pública y staff | Pendiente |
| Slots con `reason` detallado (`fuera_horario`, `sin_cobertura`, `ocupado`) | Pendiente |

---

## Fase 3 — Portal autenticado ✅

| Entregable | Estado |
|------------|--------|
| Paso de pago en `BookingFlow` (adelanto simulado) | ✅ |
| Ocultar reserva invitado en `VetClinicPublic` | ✅ |
| Registro cliente → `portal_approval_status: pending` | ✅ |
| Código seguimiento `SPT-XXXXXX` en confirmación | ✅ |

---

## Fase 4 — Ficha cliente (staff) ✅

| Entregable | Estado |
|------------|--------|
| Toggle **Permitir reserva portal** en `Clients.tsx` | ✅ |
| Badge estado: `pending` / `approved` / `rejected` | ✅ |
| Acción rápida: aprobar cliente portal desde Confirmaciones | ✅ (Fase 5) |

---

## Fase 5 — Tres módulos staff ✅

| Módulo | Rol portal | Estado |
|--------|------------|--------|
| **Agenda Visual** | Badge portal + filtro origen + detalle adelanto | ✅ |
| **Confirmaciones** | Inbox portal + aprobar cliente + KPI pendientes | ✅ |
| **Lista de Citas** | Badge origen + filtro Staff/Portal/Invitado | ✅ |

---

## Fase 6 — Configuración UI

| Entregable | Estado |
|------------|--------|
| Sección **Portal de Reservas** en Settings | Pendiente |
| Campos: adelanto %, monto fijo, invitado on/off, auto-confirmar | Pendiente |
| API: `GET/PUT /companies/{id}/config/portal_settings` | ✅ (backend listo) |

---

## Campos clave

### `clients`
- `portal_booking_enabled` (bool, default `false`)
- `portal_approval_status` (`pending` | `approved` | `rejected`)
- `portal_registered_at` (timestamp)

### `appointments`
- `booking_source` (`staff` | `portal_auth` | `public_guest`)
- `advance_amount`, `advance_paid_at`, `advance_payment_method`, `advance_payment_reference`

### `portal_settings` (JSON en `company_configurations`)
```json
{
  "guest_booking_enabled": false,
  "registered_only": true,
  "require_advance": true,
  "advance_type": "percent",
  "advance_value": 30,
  "payment_mode": "simulated",
  "auto_confirm_on_advance": true,
  "new_clients_require_approval": true
}
```
