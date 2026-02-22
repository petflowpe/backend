# Empresas, horarios laborales y disponibilidad de vehículos

## ¿Qué empresa no trabaja los domingos?

La que usa el sistema al crear citas es **tu empresa** (la asociada al usuario logueado, normalmente `company_id = 1`). Los horarios en los que “la empresa trabaja” (para validar citas) no vienen de la tabla `companies`, sino de **configuración por empresa** en la tabla `company_configurations`:

- **Config type:** `document_settings`
- **Dato:** `config_data.working_hours`

El seeder `CompanyConfigSeeder` deja por defecto:

- Lunes–viernes: 08:00–18:00  
- Sábado: 09:00–14:00  
- **Domingo: cerrado** (`open: false`)

Por eso, al agendar un domingo, el backend responde “La empresa no trabaja los domingos”.

---

## Dónde gestionar empresas

### Backend (API)

- **Listar / ver / crear / actualizar empresas:**  
  `GET/POST/GET/PUT /api/v1/companies` y `/api/v1/companies/{id}`
- **Configuración de una empresa (horarios, documentos, etc.):**  
  `GET/PUT /api/v1/companies/{company_id}/config/{section}`  
  - Para horarios laborales, la sección es: **`document_settings`**  
  - En el body del `PUT` puedes enviar `working_hours` con la misma estructura que usa el seeder (ver abajo).

### Frontend (PetSmart)

Hoy **no hay** un módulo “Empresas” en el menú lateral. Lo que sí existe:

- **Configuración** (menú): pantalla de ajustes generales; tiene “días y horarios” pero por ahora en estado local, no conectados a la API de `companies/{id}/config`.
- Para **gestionar empresas** (CRUD) desde la app habría que añadir una pantalla que use los endpoints de `/companies` y, si quieres, otra para “Horarios laborales” que use `PUT companies/{id}/config/document_settings` con `working_hours`.

### Cómo cambiar “no trabaja los domingos”

**Opción 1 – Por API (recomendado)**  
Hacer un `PUT` a:

`/api/v1/companies/1/config/document_settings`

con un body que incluya `working_hours` completo, por ejemplo abriendo domingo:

```json
{
  "working_hours": {
    "monday":    { "open": true, "start": "08:00", "end": "18:00" },
    "tuesday":   { "open": true, "start": "08:00", "end": "18:00" },
    "wednesday": { "open": true, "start": "08:00", "end": "18:00" },
    "thursday":  { "open": true, "start": "08:00", "end": "18:00" },
    "friday":    { "open": true, "start": "08:00", "end": "18:00" },
    "saturday":  { "open": true, "start": "09:00", "end": "14:00" },
    "sunday":    { "open": true, "start": "09:00", "end": "14:00" }
  }
}
```

(Reemplaza `1` por el `company_id` de tu empresa si es otro.)

**Opción 2 – En base de datos**  
Editar el registro en `company_configurations` donde `company_id = 1`, `config_type = 'document_settings'`, y en la columna JSON `config_data` ajustar `working_hours.sunday` (por ejemplo `"open": true` y `start`/`end` que quieras).

**Opción 3 – Desde la app**  
Cuando exista una pantalla “Horarios laborales” o “Configuración de empresa” que llame a `PUT companies/{id}/config/document_settings` con `working_hours`, se podrá gestionar desde ahí. El backend ya acepta `working_hours` en esa ruta.

---

## ¿Se puede gestionar la disponibilidad de los vehículos?

**Sí, a nivel de modelo y API.**

- En el **backend**, el modelo `Vehicle` tiene el campo **`horario_disponibilidad`** (array/JSON).  
- El **VehicleController** ya valida y acepta `horario_disponibilidad` al crear/actualizar vehículos (por ejemplo en el `store`/`update` del recurso).

En el **frontend** (PetSmart), el módulo **Vehículos** (`VehicleManagement`) **no** muestra ni edita todavía `horario_disponibilidad`. Para “gestionar la disponibilidad de los vehículos” desde la app habría que:

1. Definir el formato del array (por ejemplo por día y franjas hora inicio/fin).
2. Añadir en la pantalla de crear/editar vehículo un bloque (formulario o tabla) que envíe `horario_disponibilidad` en el payload al backend.

Resumen:

- **Empresa que no trabaja los domingos:** la tuya; se configura en `company_configurations` (document_settings.working_hours).
- **Dónde gestionar empresas:** por API en `/companies` y `/companies/{id}/config`; en la app aún no hay pantalla “Empresas”.
- **Disponibilidad de vehículos:** el backend ya lo soporta con `horario_disponibilidad`; en el frontend falta la UI en Vehículos.
