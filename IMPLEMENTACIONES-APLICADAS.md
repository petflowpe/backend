# Implementaciones aplicadas (7 sugeridas + 4 fases)

Resumen de lo implementado según ANALISIS-SISTEMA.md y ARQUITECTURA-CORE-Y-MODULOS.md.

---

## 7 Implementaciones sugeridas

### 1. Middleware de company scope ✅
- **EnsureUserCompanyScope** (`app/Http/Middleware/EnsureUserCompanyScope.php`): establece `scope_company_id` y `scope_branch_id` desde el usuario; super_admin puede usar header `X-Company-Id` (y `X-Branch-Id` si la sucursal pertenece a la empresa). Aplicado al grupo de rutas `v1`.

### 2. Reemplazo de fallbacks `company_id ?? 1` ✅
- **ScopeHelper** (`app/Helpers/ScopeHelper.php`): `companyId()`, `branchId()`, `branchBelongsToCompany()`.
- Sustituido en: PurchaseOrderController, ZoneController, RoutePlanController, AccountingEntryController, AppointmentController, ClientController (listado).

### 3. Validación branch pertenece a company ✅
- Validación con `ScopeHelper::branchBelongsToCompany()` en: VoidedDocumentController (`getDocumentsForVoiding`), CashSessionController (`open`).

### 4. Form Requests
- No implementado en esta tanda; se puede añadir después para Client, Product, Invoice, CashSession, Appointment.

### 5. Policies y authorize ✅
- **CompanyPolicy**: view, viewAny, create, update, delete (super_admin o misma empresa).
- **ClientPolicy**: view, viewAny, create, update, delete (super_admin o misma empresa).
- Uso de `$this->authorize()` en CompanyController (show, update, destroy) y ClientController (show, update, destroy).

### 6. Contexto tenant en frontend
- Parcial: backend devuelve `company_id` (y scope vía headers para super_admin). El frontend ya usa `currentUser.companyId`; se puede centralizar en un TenantContext si se desea.

### 7. Documentación tabs → endpoints
- Pendiente; se puede generar a partir de ANALISIS-SISTEMA.md y rutas actuales.

---

## 4 Fases (ARQUITECTURA-CORE-Y-MODULOS.md)

### Fase 1 – Core + i18n + multimoneda ✅
- **Migraciones:** `locale` en users, tablas `currencies`, `exchange_rates`, `modules`.
- **Modelos:** Currency, Module (con scope `active()`).
- **Seeders:** CurrenciesSeeder (PEN, USD, BRL, EUR), ModulesSeeder (catalog, invoicing, pets, inventory, cash, routes, reports).
- **CoreController:** `GET /v1/core/currencies`, `GET /v1/core/modules`.
- **AuthController::me:** incluye `locale`, `company_id`.
- **ProfileController:** acepta y devuelve `locale` en show/update.
- **Middleware:** establece `App::setLocale($user->locale)` cuando existe.
- **Frontend:** i18n con react-i18next; locales `es`, `en`, `pt-BR`; selector de idioma en Header que guarda `locale` en perfil; sincronización de idioma al cargar sesión (`/auth/me`).
- **Endpoints frontend:** `API.core.currencies`, `API.core.modules`.

### Fase 2 – Uso de monedas y tipos de cambio ✅ (backend)
- Tablas y modelos listos; endpoints de tipos de cambio se pueden añadir cuando se definan reglas de negocio.

### Fase 3 – Módulos enchufables ✅ (base)
- Tabla `modules` y seeder; `GET /v1/core/modules` para que el frontend construya menú/permisos por módulo. Módulo piloto (mover Catálogo a `app/Modules/Catalog` y registrar rutas condicionales) queda como refactor opcional posterior.

### Fase 4 – Escalado y despliegue
- Sin cambios en esta implementación; depende de entorno y estrategia de despliegue.

---

## Pasos que debes ejecutar

1. **Backend – migraciones y seeders**
   ```bash
   cd backend
   php artisan migrate
   php artisan db:seed --class=CurrenciesSeeder --force
   php artisan db:seed --class=ModulesSeeder --force
   ```
   (O ejecutar `php artisan db:seed` si quieres correr todos los seeders; CurrenciesSeeder y ModulesSeeder ya están en DatabaseSeeder.)

2. **Frontend – dependencias i18n**
   ```bash
   cd frontend
   npm install i18next react-i18next
   npm run dev
   ```

3. **Probar**
   - Login con `admin@sunatapi.com` / `admin123456`.
   - Cambiar idioma con el icono de globo en el header; debe guardarse en perfil y persistir al recargar.
   - Llamadas a `GET /v1/core/currencies` y `GET /v1/core/modules` (con token) para selectores o menú por módulos.

---

## Archivos tocados (resumen)

**Backend:**  
Middleware EnsureUserCompanyScope, ScopeHelper, migraciones (locale, currencies, exchange_rates, modules), modelos Currency/Module, seeders Currencies/Modules, CoreController, AuthController (me), ProfileController (locale), DatabaseSeeder, CompanyPolicy, ClientPolicy, CompanyController/ClientController (authorize), PurchaseOrderController, ZoneController, RoutePlanController, AccountingEntryController, AppointmentController, VoidedDocumentController, CashSessionController.

**Frontend:**  
endpoints.ts (core), i18n.ts, locales (es, en, pt-BR), main.tsx (import i18n + setI18nLanguage tras /me), LanguageSelector, Header (selector), useProfile (locale), App.tsx (setI18nLanguage al cargar usuario).

**Documentación:**  
IMPLEMENTACIONES-APLICADAS.md (este archivo).
