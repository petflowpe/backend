# SmartPet / ClonSmarpet — Roadmap de producto

Última actualización: 2026-06-02

## Contexto operativo (decisiones del negocio)

| Tema | Decisión |
|------|----------|
| Vehículos y rutas | Operación actual manual; el sistema debe diseñarse **como si ya usaran van + rutas** |
| Portal público | Prioridad alta: reserva invitado y seguimiento sin login |
| SUNAT | Requerido al salir a producción real; preparar módulo, no bloquear citas ni portal |

## Orden de ejecución

```
Fase 0 (base)     → Fase 1 (citas)     → Fase 2 (vehículo/rutas)
        ↘ Fase 3 (portal) en paralelo tras citas mínimas
        ↘ Fase 4 (SUNAT operativo) antes de go-live comercial
        ↘ Fase 5 (analytics, loyalty) cuando A–C estén estables
```

---

## Fase 0 — Base multi-empresa y permisos

| Sprint | Entregable | Estado |
|--------|------------|--------|
| 0.1 | Scope `company_id` forzado desde usuario (no confiar solo en query del cliente) | Hecho (middleware + controladores clave) |
| 0.2 | Auditoría de permisos por módulo vs menú | Hecho (mapa MODULE_ACCESS + rol conductor) |

---

## Fase 1 — Citas (operación diaria staff)

| Sprint | Entregable | Estado |
|--------|------------|--------|
| 1.1 | Citas E2E: crear, reprogramar, estados, cliente+mascota+servicio | Hecho (validar en local/VPS) |
| 1.2 | Agenda/calendario alineado a API (sin datos ficticios) | Hecho (validar en local/VPS) |
| 1.3 | Confirmaciones y recordatorios ligados a estado real | Hecho (validar en local/VPS) |

---

## Fase 2 — Modelo móvil (vehículo + rutas)

| Sprint | Entregable | Estado |
|--------|------------|--------|
| 2.1 | Cobertura por distrito/día/hora al agendar (staff + portal + auto vehículo) | Hecho (validar en VPS) |
| 2.2 | Rutas del día + sesión chofer con citas reales | Hecho (validar en local/VPS) |
| 2.3 | Centro de control sin mocks o fuera de menú | Hecho (validar en local/VPS) |

---

## Fase 3 — Portal público

| Sprint | Entregable | Estado |
|--------|------------|--------|
| 3.1 | API pública: catálogo, disponibilidad, reserva invitado, código seguimiento | Hecho (pendiente deploy + migrate) |
| 3.2 | `AppointmentBooking` conectado a API (sin mock) | Hecho |
| 3.3 | `BookingTracking` por código `?tab=public-tracking&code=SPT-…` | Hecho |
| 3.4 | Portal autenticado (`BookingFlow`) con API real y vehículo sugerido | Hecho (validar en VPS) |

---

## Fase 4 — Facturación SUNAT (go-live)

| Sprint | Entregable | Estado |
|--------|------------|--------|
| 4.1 | Flujo mínimo boleta/factura desde cita completada | Hecho |
| 4.2 | Config SUNAT por empresa + ambiente beta/prod | Hecho (API `/companies/{id}/sunat-config`) |
| 4.3 | Caja y correlativos alineados a visita móvil | Hecho |

---

## Fase 5 — Crecimiento

| Sprint | Entregable | Estado |
|--------|------------|--------|
| 5.1 | Fidelización con puntos/niveles reales + ajuste API | Hecho (validar en VPS) |
| 5.2 | Reseñas CRUD + panel staff | Hecho (tabla `client_reviews`, migrate) |
| 5.3 | Analytics: tendencias citas, geo por distrito, segmentación | Hecho (validar en VPS) |
| 5.4 | Live chat / patrones avanzados por móvil | Hecho |

---

## Cómo marcar avance

- **Hecho**: flujo probado en local o VPS, sin mock en pantalla crítica.
- **En progreso**: código en rama `main` / `barco`, deploy pendiente de validación.
- Cada sprint cierra con checklist: crear → listar → cambiar estado → (portal) reservar sin login.

---

## Sprint actual (agente)

**5.1–5.3** — Fidelización, reseñas y analytics conectados a API (`/reports/growth/*`, `/reviews`).

Fase 4 lista para go-live operativo; validar envío SUNAT en ambiente beta cuando corresponda.

---

## Post-roadmap — Sprint A (pagos + analytics)

| Entregable | Estado |
|------------|--------|
| Analytics IA → `GrowthAnalyticsPanel` (API growth) | Hecho |
| Módulo Pagos conectado a `/payments` | Hecho |
| Config Mercado Pago + Niubiz por empresa | Hecho (`/companies/{id}/payment-gateways`) |
| Checkout pasarela (`POST /payments/checkout`) | Hecho |
| Webhooks públicos MP / Niubiz | Hecho (requiere `php artisan migrate` en VPS) |

**Configurar en VPS:** `APP_URL` correcto para URLs de webhook y retorno; en Mercado Pago registrar  
`POST {APP_URL}/api/public/webhooks/mercadopago?company_id={ID}`.

## Post-roadmap — Sprint B (facturar desde caja)

| Entregable | Estado |
|------------|--------|
| Botón Facturar en Cierre de Caja (mismo flujo que Lista de Citas) | Hecho |
| API `day-summary` con `pending_invoicing` y flags `invoiced` | Hecho |

## Post-roadmap — Sprint D (informes y backup)

| Entregable | Estado |
|------------|--------|
| API `/reports/export/dataset/{tabla}` para Exportar Datos | Hecho |
| API `/reports/export/report/{id}` para Informes | Hecho (16+ informes con datos reales) |
| `ExportsReports` sin mocks en informes conectados | Hecho |
| `DataExport` lee tablas desde API | Hecho |
