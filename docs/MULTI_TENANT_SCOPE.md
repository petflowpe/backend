# Aislamiento Multi-Tenant (Fase A · Módulo 2)

Este documento describe cómo se garantiza que un usuario de la **Empresa A**
nunca pueda leer, modificar o exportar datos de la **Empresa B** a través de
ningún endpoint del API.

## Arquitectura: Defensa en 3 capas

```
┌────────────────────────────────────────────────────────────┐
│  Capa 1 · Middleware  →  EnsureUserCompanyScope            │
│  Capa 2 · Eloquent    →  CompanyScope (Global Scope)       │
│  Capa 3 · Controllers →  scopedCompanyId / DB::table guard │
└────────────────────────────────────────────────────────────┘
```

### Capa 1 · Middleware `EnsureUserCompanyScope`

Aplicado a todo el grupo `/api/v1/*` y `/api/v2/*`:

```php
Route::prefix('v1')
    ->middleware(['auth:sanctum', 'throttle:api', EnsureUserCompanyScope::class])
    ->group(...);
```

Reglas:
- Si un usuario **NO super_admin** envía `X-Company-Id`, `?company_id=` o
  `company_id` en el body que NO coincide con su `company_id` → **403**.
- Para usuarios no super_admin, el middleware **reescribe** `company_id` y
  `branch_id` del request al del usuario (no se puede inyectar otro).
- Para super_admin, respeta el `X-Company-Id` o `company_id` enviado para
  cambiar de tenant.
- Coloca en attributes `scope_company_id` y `scope_branch_id` para que los
  controllers los consulten.

### Capa 2 · Global Scope `CompanyScope` + trait `BelongsToCompany`

Ubicación:
- `app/Models/Scopes/CompanyScope.php`
- `app/Models/Concerns/BelongsToCompany.php`

Aplicado a **46 modelos** con columna `company_id`:

```
AccountingEntry, Appointment, Area, AuditLog, BillingDocument, Boleta,
Brand, CashMovement, CashSession, Category, ChatConversation, Client,
ClientReview, CompanyConfiguration, CompanyTaxProfile, CreditNote,
DailySummary, DebitNote, DispatchGuide, Invoice, MedicalRecord,
Notification, OptimizationRecord, Payment, Pet, PetConfiguration,
Product, ProductSale, PurchaseOrder, Retention, Route, Service,
StockMovement, Supplier, Unit, VaccineRecord, Vehicle,
VehicleConfiguration, VehicleCoverageRule, VehicleExpense,
VehicleInspection, VehicleInspectionTemplate, VehicleMaintenance,
VehicleService, VoidedDocument, Zone
```

Comportamiento del scope:
| Contexto del request                        | Filtro aplicado                       |
| ------------------------------------------- | ------------------------------------- |
| Sin auth (CLI, jobs, schedulers)            | No filtra                             |
| Auth como super_admin                       | No filtra                             |
| Auth con `company_id` definido              | `WHERE company_id = user.company_id`  |
| Auth sin `company_id` y NO super_admin      | `WHERE 1 = 0` (no devuelve nada)      |

Consecuencias clave:
- `Model::find($id)` retorna **null** si el id es de otra empresa.
- `Model::all()`, `Model::count()`, paginate, etc. **solo ven** la empresa
  del usuario, aunque el controller no filtre explícitamente.
- Para escapar del scope (super_admin cross-tenant, jobs):

  ```php
  Client::withoutGlobalScope(\App\Models\Scopes\CompanyScope::class)->get();
  ```

### Capa 3 · Controllers y queries raw

El global scope **solo afecta a Eloquent**. Si un controller usa `DB::table()`
o SQL raw, debe filtrar manualmente:

```php
$authUser = $request->user();
$companyId = $authUser && !$authUser->hasRole('super_admin')
    ? (int) ($authUser->company_id ?? 0)
    : null;

DB::table('mi_tabla')
    ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
    ->get();
```

Ejemplos refactorizados en este módulo:
- `PetController::duplicates()` → DB::table('pet_configurations') ahora filtra.
- `AuditLogController::log()` → `audit_logs` ahora persiste `company_id` del
  user autenticado.

## Helpers disponibles

Trait `HandlesStaffAuthorization` (en `app/Http/Controllers/Concerns/`):
- `$this->scopedCompanyId($request, $auth)` → devuelve el `company_id` efectivo
  (el del user, o el solicitado si es super_admin).
- `$this->denyStaff($msg)` → 403 estándar.
- Permisos: `canListUsers`, `canCreateUsers`, etc.

## Test maestro

Archivo: `tests/Feature/MultiTenantScopeTest.php`

Cubre:
1. **Aislamiento Eloquent**: siembra registros en 18 tablas para Empresa A
   y B; autenticado como user de A, verifica que `Model::query()->get()` nunca
   devuelve registros de B (Client, Pet, Appointment, MedicalRecord, Vehicle,
   VehicleMaintenance, VehicleExpense, Product, Category, Brand, Unit,
   Supplier, Invoice, Boleta, Area, Zone, Payment, Notification).
2. **`find()` por id ajeno**: pasar un id de empresa B retorna `null`.
3. **Forge de company_id**: bloqueado vía header `X-Company-Id`, query string
   y body JSON (403).
4. **Super admin**: ve datos de ambas empresas (sin scope).
5. **User huérfano**: sin company_id y sin role super_admin → 0 registros.

Correr:

```bash
php artisan test --filter=MultiTenantScopeTest
```

## Reglas para nuevos modelos

1. Si la tabla tiene `company_id`, el modelo **DEBE** usar el trait:

   ```php
   use App\Models\Concerns\BelongsToCompany;

   class MiModelo extends Model
   {
       use HasFactory, BelongsToCompany;
       // ...
   }
   ```

2. Si la migración crea una tabla nueva multi-tenant, la columna debe llamarse
   exactamente `company_id` y referenciar `companies.id`.

3. Para queries fuera de Eloquent, aplicar el filtro manual de la **Capa 3**.

4. Agregar el modelo al test `MultiTenantScopeTest.php` (siembra + assert).

## Excepciones legítimas

- `Company`, `User`, `Branch`, `Role`, `Permission` no aplican el trait
  (son entidades fundacionales, manejadas por sus propios controllers con
  validaciones explícitas).
- Tablas catálogo (`ubi_regiones`, `ubi_provincias`, `ubi_distritos`,
  exchange_rates globales) no requieren scope.
- Jobs y comandos artisan que deban procesar varios tenants deben envolver
  cada bloque con `Model::withoutGlobalScope(CompanyScope::class)` o usar
  `Auth::guard()->forgetUser()` para correr sin contexto.

## Auditoría inicial (snapshot)

- Controllers en `/api/v1`: **64**
- Controllers que ahora dependen del Global Scope (capa 2): **todos los que
  consultan modelos multi-tenant** (≥ 50).
- Controllers que ya usaban filtrado explícito robusto (ExportReportService,
  ClientController, AppointmentController, UserController, BranchController,
  RoleController, etc.): mantenidos sin cambios — defensa redundante.
- Controllers refactorizados puntualmente: `PetController::duplicates`,
  `AuditLogController::log`.
- Tests: 58 verdes (337 → 349 assertions, +5 tests del módulo multi-tenant).
