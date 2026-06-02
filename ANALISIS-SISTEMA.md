# Análisis exhaustivo del sistema ClonSmarpet

Documento de análisis por módulos, relaciones, casos de uso, debilidades y recomendaciones.

---

## 1. Visión general del sistema

- **Backend:** Laravel 12, API REST, autenticación Sanctum, multi-empresa (Company/Branch).
- **Frontend:** React 18 + Vite, navegación por tabs (`?tab=...`), capa API centralizada (`src/utils/api`).
- **Dominios:** Facturación electrónica SUNAT, gestión de clientes/mascotas, citas, inventario, compras, caja, reportes, vehículos/rutas, notificaciones, contabilidad.

---

## 2. Módulos del backend y relaciones

### 2.1 Núcleo multi-tenant

| Entidad    | Descripción breve | Relaciones principales |
|------------|-------------------|-------------------------|
| **Company** | Empresa (RUC, SUNAT, config) | HasMany: Branch, Client, Product, Invoice, Boleta, DailySummary, VoidedDocument, CompanyConfiguration. |
| **Branch**  | Sucursal          | BelongsTo Company; HasMany: Correlative, documentos por sucursal. |
| **User**    | Usuario del sistema | BelongsTo Role, Company. Sin Policies: solo auth:sanctum. |

**Punto crítico:** No existe carpeta `app/Policies`. La autorización se reduce a “usuario autenticado”. El alcance por empresa se hace en cada controlador con `company_id` (request o user), sin una capa única de “scope por empresa”.

### 2.2 Clientes y mascotas

| Entidad   | Descripción | Relaciones |
|-----------|-------------|------------|
| **Client** | Cliente (documento, contacto) | BelongsTo Company; HasMany: Pet (y BelongsToMany vía pet_owners), Appointment, MedicalRecord; documentos (Invoice, Boleta, etc.). |
| **Pet**    | Mascota     | BelongsTo Client, Company; HasMany: Appointment, MedicalRecord, VaccineRecord, PetPhoto; BelongsToMany Client (owners). |
| **PetConfiguration** | Razas, temperamentos por empresa | company_id nullable (global o por empresa). |

### 2.3 Citas y servicios

| Entidad        | Descripción | Relaciones |
|----------------|-------------|------------|
| **Appointment** | Cita (servicio, cliente, mascota, técnico) | BelongsTo: Service, Client, Pet, Company, Branch, Vehicle, User; HasOne MedicalRecord; HasMany AppointmentItem; recurrencia (parent/child, series). |
| **Service**     | Servicio ofrecido | Usado en citas. |

### 2.4 Catálogo e inventario

| Entidad         | Descripción | Relaciones |
|-----------------|-------------|------------|
| **Product**     | Producto/servicio (precio, IGV, stock) | BelongsTo Company, Category, Unit, Brand, Supplier; HasMany ProductStock, StockMovement; HasOne ProductSale. |
| **Category**    | Categoría de productos | Por empresa. |
| **Unit**        | Unidad de medida | Por empresa. |
| **Area**        | Área de almacén | Company, Branch. |
| **Brand**       | Marca        | Por empresa. |
| **Supplier**    | Proveedor    | Por empresa. |
| **ProductStock**| Stock por producto/almacén | Product, Area. |
| **StockMovement**| Movimiento de inventario | Product. |

### 2.5 Documentos electrónicos (SUNAT)

| Entidad         | Descripción | Relaciones |
|-----------------|-------------|------------|
| **Invoice**     | Factura electrónica | Company, Branch, Client. |
| **Boleta**      | Boleta de venta | Company, Branch, Client; BelongsTo DailySummary. |
| **DailySummary**| Resumen diario de boletas | Company, Branch. |
| **CreditNote**  | Nota de crédito | Company, Branch, Client. |
| **DebitNote**   | Nota de débito | Company, Branch, Client. |
| **DispatchGuide**| Guía de remisión | Company, Branch, Client. |
| **VoidedDocument**| Comunicación de baja | Company, Branch. |
| **Retention**   | Retención     | Company, Branch. |
| **Correlative**| Correlativo por serie/sucursal | Branch. |

### 2.6 Caja y pagos

| Entidad        | Descripción | Relaciones |
|----------------|-------------|------------|
| **CashSession**| Sesión de caja (apertura/cierre) | Branch/Company. |
| **CashMovement**| Movimiento de caja | Sesión. |
| **Payment**    | Pago registrado | Documento/cliente. |

### 2.7 Otros

- **Permission / Role:** RBAC; User → Role → Permission.
- **AuditLog:** Trazabilidad de acciones.
- **Notification:** Por usuario.
- **Vehicle, Zone, Route, RouteStop, OptimizationRecord:** Flota y rutas.
- **AccountingEntry / AccountingEntryLine:** Contabilidad.
- **PurchaseOrder / PurchaseOrderItem:** Compras.
- **MedicalRecord, VaccineRecord:** Historial médico/vacunas.
- **UbiRegion, UbiProvincia, UbiDistrito:** Ubigeo Perú.

---

## 3. Módulos del frontend (por tab / sección)

- **Login, perfil, ajustes de usuario:** Login, recuperación de contraseña, perfil, user-settings.
- **Dashboard:** Estadísticas y resumen (useDashboardStats, reports/stats).
- **Citas y agenda:** calendar, appointments, confirmation; useAppointments.
- **Clientes y mascotas:** clients, pets; useClients; segmentación.
- **Servicios y productos:** services, products, inventory; useProducts, useInventory, useLowStock, useKardex.
- **Compras:** purchases; usePurchases, useSuppliers.
- **Facturación y pagos:** invoicing, payments, cash-register; useInvoices, useCashRegister, hooks de boletas/NC/ND/resúmenes/bajas/guías.
- **SUNAT:** sunat-config, electronic-invoicing, electronic-books, sunat-reports; sunatService.
- **Veterinaria:** medical (MedicalCare), medical-records; useMedicalRecords; portal público (VetClinicPublic).
- **Flota y rutas:** vehicles, routes; useVehicles; driver-session, operations-center, public-tracking (BookingTracking).
- **Personal y permisos:** staff, users; useStaff, useUsers, useRoles, usePermissions.
- **Empresas:** companies; useCompanies, useBranches.
- **Reportes y análisis:** reports, analytics, segmentación, patrones, análisis geográfico, data-export.
- **Otros:** notifications, accounting, financial, loyalty, reviews, audit-logs.

La navegación es por **tab** en una SPA; no hay React Router con rutas por path (salvo lógica especial para login, reset password, public-tracking, conductor).

---

## 4. Recorrido por casos de uso: debilidades y errores

### Caso 1: Login → Dashboard → ver datos de “mi empresa”

- **Flujo:** Usuario inicia sesión → frontend guarda token y user (con companyId) → llama `/v1/auth/me` y luego endpoints de listados (clientes, productos, etc.).
- **Debilidad:** En muchos controladores el listado filtra por `$request->company_id` si viene en la petición. Si el frontend no envía `company_id` o lo envía mal, el comportamiento varía:
  - Donde se usa solo `$request->company_id`, sin fallback a `$request->user()->company_id`, un usuario de empresa A podría no ver datos (o en otros casos, si se usa solo request, podría enviar otro company_id y ver datos de otra empresa).
- **Ejemplo:** ClientController::index filtra por `company_id` solo “if ($request->has('company_id'))”; si no se envía, se listan clientes de todas las empresas (si la tabla no tiene scope global por defecto).
- **Recomendación:** Unificar criterio: para usuarios no super_admin, forzar siempre el scope por `$request->user()->company_id` (y opcionalmente permitir super_admin pasar company_id). No confiar solo en el `company_id` enviado por el cliente.

### Caso 2: Crear cliente → crear mascota → crear cita

- **Flujo:** Clientes → nuevo cliente (company_id, documento, razón social, etc.) → Mascotas → nueva mascota (client_id, company_id) → Citas → nueva cita (client, pet, branch, service, etc.).
- **Relaciones:** Client → Company; Pet → Client + Company; Appointment → Client, Pet, Company, Branch, User, Service.
- **Debilidades:**
  - En backend, creación de cliente/mascota/cita acepta `company_id` por request; si no se valida que pertenezca al usuario, hay riesgo de crear datos en otra empresa.
  - En frontend, si no se envía siempre el companyId del usuario logueado (desde auth/me o contexto), se pueden enviar company_id incorrectos o null.
- **Recomendación:** En backend, en store/update de Client, Pet, Appointment (y recursos análogos), para usuarios con company_id no nulo, asignar o validar que `company_id` sea el del usuario. En frontend, asegurar que todos los formularios que requieran empresa usen el companyId del usuario logueado.

### Caso 3: Emitir factura / boleta (SUNAT)

- **Flujo:** Facturación → elegir empresa/sucursal → cliente → detalles → enviar a SUNAT.
- **Debilidades:**
  - Controladores de Invoice/Boleta filtran por `$request->company_id`; sin validación de pertenencia al usuario, un atacante podría intentar emitir documentos por otra empresa si conoce IDs.
  - Correlativos y series dependen de Branch; si branch_id no se valida contra la empresa del usuario, podría usarse una sucursal ajena.
- **Recomendación:** Validar en backend que `company_id` y `branch_id` pertenezcan al usuario (o que el usuario sea super_admin). Usar siempre `user()->company_id` como fuente de verdad para usuarios de una sola empresa.

### Caso 4: Apertura/cierre de caja (CashSession)

- **Flujo:** Caja → abrir sesión (branch/company) → movimientos → cerrar sesión.
- **Debilidad:** CashSessionController filtra por `company_id` cuando viene en request; si no se fuerza el de la empresa del usuario, podría abrirse caja en otra empresa.
- **Recomendación:** Mismo patrón: scope por `user()->company_id` para usuarios no super_admin; super_admin puede elegir empresa.

### Caso 5: Usuario con rol “conductor”

- **Flujo:** Rol conductor → DriverSession (tab fija); operaciones sobre rutas/vehículos.
- **Debilidad:** Si las rutas y vehículos se filtran solo por `request->company_id` sin validar contra el usuario, un conductor podría ver o modificar datos de otra empresa.
- **Recomendación:** Aplicar scope por empresa según usuario y políticas por rol (conductor solo su empresa/branch si aplica).

### Caso 6: Bajo stock y kardex

- **Flujo:** Productos → bajo stock; Productos → kardex de un producto.
- **Debilidad:** KardexController y listados de productos usan `company_id` del request; mismo riesgo de filtrado incorrecto o acceso cruzado.
- **Recomendación:** Centralizar en backend el “company_id efectivo” (user o request solo para super_admin) y usarlo en todos los listados y reportes.

### Caso 7: Sesión expirada (401)

- **Flujo:** Token inválido o expirado → API devuelve 401 → frontend debe cerrar sesión y redirigir a login.
- **Estado actual:** ApiClient tiene `setOnUnauthorized`; AuthContext lo registra; App.tsx verifica sesión y solo limpia token en 401 (no en error de red). Comportamiento correcto en principio.
- **Recomendación:** Asegurar que en todas las llamadas que usan apiClient, el 401 dispare el callback y que la UI muestre un mensaje claro (“Sesión expirada”) antes de ir a login.

### Caso 8: Multi-empresa (varias empresas)

- **Flujo:** Usuario super_admin o usuario con acceso a varias empresas cambia de “empresa activa” en la UI.
- **Debilidad:** No hay un “tenant context” único en backend (middleware que inyecte company_id desde token o header). Cada endpoint resuelve company de forma distinta (request vs user).
- **Recomendación:** Si se soporta multi-empresa en la UI, definir en backend un criterio único (ej. header `X-Company-Id` o claim en token) y un middleware que lo valide y lo deje disponible; para usuarios de una sola empresa, ignorar el header y usar siempre user()->company_id.

---

## 5. Resumen de debilidades

1. **Autorización por empresa inconsistente:** Uso mixto de `$request->company_id` y `$request->user()->company_id`; en varios listados/creaciones no se fuerza el company del usuario, lo que puede permitir listar o crear recursos en otra empresa (IDOR).
2. **Sin Policies:** No hay Laravel Policies; la restricción por empresa y por rol está repartida y duplicada en controladores.
3. **Fallback a company_id = 1:** En algunos sitios (PurchaseOrder, Zone, AccountingEntry, etc.) se usa `?? 1` si no hay company_id; en entornos con varias empresas es frágil y puede asociar datos a la empresa equivocada.
4. **Validación de branch vs company:** No en todos los endpoints se valida que `branch_id` pertenezca a la empresa del usuario antes de usarlo.
5. **Frontend y companyId:** Riesgo de que no todos los formularios envíen el companyId correcto (o que lo tomen de un contexto desactualizado) si el usuario cambia de empresa.
6. **Navegación por tabs:** Muchos tabs y componentes; falta documentación clara de qué tab usa qué endpoint y con qué parámetros (company_id, branch_id). Dificulta auditar que no quede ninguna pantalla sin enviar empresa.

---

## 6. Recomendaciones prioritarias

### Seguridad y autorización

1. **Middleware “EnsureUserCompanyScope”:** Crear un middleware que, para usuarios con `company_id` no nulo, establezca el “company_id efectivo” (por ejemplo en `request()->attributes`) desde `user()->company_id`, y opcionalmente permita a super_admin sobreescribir con un header o query param validado. Usarlo en las rutas API que sean por empresa.
2. **Policies por recurso:** Introducir Policies (CompanyPolicy, ClientPolicy, InvoicePolicy, etc.) que comprueben que el usuario puede actuar sobre esa empresa/recurso, y usar `$this->authorize()` en controladores. Así se unifica la lógica y se evita confiar en el company_id del body/query.
3. **Eliminar fallback `company_id = 1`:** Sustituir por `$request->user()->company_id` o por el valor que devuelva el middleware; si el usuario no tiene empresa, devolver 403 o 400 en lugar de asumir 1.
4. **Validar branch pertenece a company:** En todos los endpoints que reciban `branch_id`, validar que esa sucursal sea de la empresa del usuario (o de la empresa en contexto).

### Backend

5. **Form Requests y validación:** Usar Form Requests en todos los store/update que reciban company_id o branch_id, con reglas que comprueben existencia y pertenencia (ej. `exists:branches,id,company_id,{company_id}`).
6. **Scope global en modelos:** Donde aplique, considerar un global scope en modelos (Client, Product, Invoice, etc.) que filtren por company_id cuando el usuario no sea super_admin, para no depender de que cada controlador lo ponga.
7. **Auditoría:** Asegurar que acciones sensibles (emitir documento SUNAT, cerrar caja, cambiar permisos) queden registradas en AuditLog con user_id, company_id y recurso afectado.

### Frontend

8. **Contexto de empresa/sucursal:** Tener un contexto (o store) “tenant” con companyId y branchId activos, derivados de `/auth/me` o de configuración post-login, y usarlos sistemáticamente en todas las llamadas que requieran empresa/sucursal.
9. **Manejo de errores y 401:** Revisar que ninguna llamada capture 401 sin propagar al callback de onUnauthorized; mostrar toast o mensaje “Sesión expirada” antes de redirigir a login.
10. **Documentación de tabs y API:** Mantener una tabla (o doc) que indique para cada tab qué endpoints usa y qué parámetros envía (company_id, branch_id, etc.) para facilitar revisión y pruebas.

### Operación y despliegue

11. **Variables de entorno:** Revisar que no queden URLs o claves de SUNAT/email en el código; usar .env y config cache en producción.
12. **Tests automatizados:** Añadir tests de integración que simulen usuario con company_id = 1 e intenten acceder a recursos de company_id = 2; deben recibir 403 o listas vacías según el diseño deseado.

---

## 7. Implementaciones sugeridas (orden sugerido)

1. Middleware de company scope y uso en rutas API.
2. Reemplazo de fallbacks `company_id ?? 1` por valor del usuario o middleware.
3. Validación explícita de branch pertenece a company en endpoints que usen branch_id.
4. Form Requests para recursos críticos (Client, Product, Invoice, Boleta, CashSession, Appointment).
5. Policies básicas (view/update por empresa) y uso en controladores.
6. Contexto “tenant” en frontend y uso consistente de companyId/branchId.
7. Documentación de tabs → endpoints y parámetros.
8. Tests de autorización por empresa.

Este documento puede usarse como base para planificar sprints o tareas de mejora y para revisar nuevos endpoints antes de desplegar.
