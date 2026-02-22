# Base de datos – Diagrama y configuración

## Ubicación de la configuración de conexión

La conexión a la base de datos se define en dos sitios:

| Archivo | Uso |
|--------|-----|
| **`config/database.php`** | Define las conexiones disponibles (mysql, sqlite, etc.) y lee las variables de entorno. La conexión por defecto es `env('DB_CONNECTION', 'sqlite')`. |
| **`.env`** (raíz del proyecto) | Variables reales: `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`. No se versiona; para ver estructura usa **`.env.example`**. |

**Rutas absolutas (backend-grooming):**
- `c:\laragon\www\Proyecto2026\backend-grooming\config\database.php`
- `c:\laragon\www\Proyecto2026\backend-grooming\.env`
- `c:\laragon\www\Proyecto2026\backend-grooming\.env.example`

En `config/database.php`, la conexión **mysql** usa: `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` del `.env`.

---

## Diagramas de tablas y relaciones

Los diagramas están en sintaxis Mermaid. Puedes verlos en:
- GitHub/GitLab (renderizan Mermaid)
- VS Code con extensión "Mermaid"
- [mermaid.live](https://mermaid.live)

---

### 1. Núcleo: Empresa, Sucursal, Usuarios y Roles

```mermaid
erDiagram
    companies ||--o{ branches : "tiene"
    companies ||--o{ company_configurations : "tiene"
    companies ||--o{ users : "tiene"
    roles ||--o{ users : "tiene"
    roles }o--o{ permissions : "role_permission"
    
    companies {
        bigint id PK
        string ruc
        string razon_social
        string nombre_comercial
        string direccion
        string ubigeo
    }
    
    branches {
        bigint id PK
        bigint company_id FK
        string codigo
        string nombre
        string direccion
    }
    
    users {
        bigint id PK
        bigint role_id FK
        bigint company_id FK
        string name
        string email
        string password
    }
    
    roles {
        bigint id PK
        string name
        string display_name
    }
    
    permissions {
        bigint id PK
        string name
        string display_name
        string category
    }
    
    company_configurations {
        bigint id PK
        bigint company_id FK
        string config_type
        json config_data
    }
    
    role_permission {
        bigint role_id FK
        bigint permission_id FK
    }
```

---

### 2. Clientes, Mascotas y Citas

```mermaid
erDiagram
    companies ||--o{ clients : "tiene"
    clients ||--o{ pets : "tiene"
    clients ||--o{ appointments : "tiene"
    pets ||--o{ appointments : "tiene"
    companies ||--o{ appointments : "tiene"
    branches ||--o{ appointments : "tiene"
    users ||--o{ appointments : "asignado"
    vehicles ||--o{ appointments : "asignado"
    services ||--o{ appointments : "servicio"
    appointments ||--o{ appointment_items : "tiene"
    products ||--o{ appointment_items : "item"
    appointments ||--o| medical_records : "tiene"
    pets ||--o{ medical_records : "tiene"
    clients ||--o{ medical_records : "tiene"
    medical_records ||--o{ vaccine_records : "tiene"
    pets ||--o{ vaccine_records : "tiene"
    companies ||--o{ pet_configurations : "config"
    
    clients {
        bigint id PK
        bigint company_id FK
        string tipo_documento
        string numero_documento
        string razon_social
        string email
    }
    
    pets {
        bigint id PK
        bigint client_id FK
        bigint company_id FK
        string name
        string species
        string breed
        string size
    }
    
    appointments {
        bigint id PK
        bigint client_id FK
        bigint pet_id FK
        bigint company_id FK
        bigint branch_id FK
        bigint user_id FK
        bigint vehicle_id FK
        bigint service_id FK
        date fecha
        string estado
    }
    
    appointment_items {
        bigint id PK
        bigint appointment_id FK
        bigint product_id FK
        decimal cantidad
    }
    
    medical_records {
        bigint id PK
        bigint pet_id FK
        bigint client_id FK
        bigint company_id FK
        bigint appointment_id FK
        bigint user_id FK
        text diagnostico
    }
    
    vaccine_records {
        bigint id PK
        bigint pet_id FK
        bigint client_id FK
        bigint company_id FK
        bigint medical_record_id FK
        bigint user_id FK
        string vacuna
        date fecha
    }
    
    services {
        bigint id PK
        bigint company_id FK
        string name
        string code
        json pricing
    }
    
    vehicles {
        bigint id PK
        bigint company_id FK
        bigint driver_id FK
        string name
        string type
        string placa
    }
    
    pet_configurations {
        bigint id PK
        bigint company_id FK
        string type
        string name
    }
```

---

### 3. Productos, Inventario y Compras

```mermaid
erDiagram
    companies ||--o{ categories : "tiene"
    companies ||--o{ units : "tiene"
    companies ||--o{ areas : "tiene"
    companies ||--o{ brands : "tiene"
    companies ||--o{ suppliers : "tiene"
    companies ||--o{ products : "tiene"
    categories ||--o{ products : "categoría"
    units ||--o{ products : "unidad"
    brands ||--o{ products : "marca"
    suppliers ||--o{ products : "proveedor"
    services ||--o| products : "producto servicio"
    products ||--o{ product_stocks : "tiene"
    areas ||--o{ product_stocks : "en área"
    products ||--o{ stock_movements : "movimiento"
    companies ||--o{ stock_movements : "tiene"
    branches ||--o{ stock_movements : "sucursal"
    companies ||--o{ purchase_orders : "tiene"
    suppliers ||--o{ purchase_orders : "proveedor"
    purchase_orders ||--o{ purchase_order_items : "tiene"
    products ||--o{ purchase_order_items : "producto"
    
    categories {
        bigint id PK
        bigint company_id FK
        string name
        string color
    }
    
    units {
        bigint id PK
        bigint company_id FK
        string name
        string abbreviation
        string sunat_code
    }
    
    areas {
        bigint id PK
        bigint company_id FK
        bigint branch_id FK
        string name
    }
    
    brands {
        bigint id PK
        bigint company_id FK
        string name
    }
    
    suppliers {
        bigint id PK
        bigint company_id FK
        string name
        string document_number
    }
    
    products {
        bigint id PK
        bigint company_id FK
        bigint category_id FK
        bigint unit_id FK
        bigint brand_id FK
        bigint supplier_id FK
        bigint service_id FK
        string name
        string code
        string item_type
        decimal unit_price
    }
    
    product_stocks {
        bigint id PK
        bigint product_id FK
        bigint area_id FK
        decimal cantidad
    }
    
    stock_movements {
        bigint id PK
        bigint company_id FK
        bigint branch_id FK
        bigint product_id FK
        bigint created_by FK
        string tipo
        decimal cantidad
    }
    
    purchase_orders {
        bigint id PK
        bigint company_id FK
        bigint supplier_id FK
        bigint created_by FK
        string estado
    }
    
    purchase_order_items {
        bigint id PK
        bigint purchase_order_id FK
        bigint product_id FK
        decimal cantidad
    }
```

---

### 4. Facturación y Documentos SUNAT

```mermaid
erDiagram
    companies ||--o{ invoices : "tiene"
    branches ||--o{ invoices : "emite"
    clients ||--o{ invoices : "cliente"
    companies ||--o{ boletas : "tiene"
    branches ||--o{ boletas : "emite"
    clients ||--o{ boletas : "cliente"
    daily_summaries ||--o{ boletas : "resumen"
    companies ||--o{ credit_notes : "tiene"
    branches ||--o{ credit_notes : "emite"
    clients ||--o{ credit_notes : "cliente"
    companies ||--o{ debit_notes : "tiene"
    branches ||--o{ debit_notes : "emite"
    clients ||--o{ debit_notes : "cliente"
    companies ||--o{ dispatch_guides : "tiene"
    branches ||--o{ dispatch_guides : "emite"
    clients ||--o{ dispatch_guides : "destinatario"
    companies ||--o{ daily_summaries : "tiene"
    branches ||--o{ daily_summaries : "tiene"
    companies ||--o{ voided_documents : "tiene"
    branches ||--o{ voided_documents : "tiene"
    branches ||--o{ correlatives : "tiene"
    companies ||--o{ retentions : "tiene"
    branches ||--o{ retentions : "tiene"
    clients ||--o{ retentions : "proveedor"
    
    invoices {
        bigint id PK
        bigint company_id FK
        bigint branch_id FK
        bigint client_id FK
        string serie
        string numero
        decimal total
    }
    
    boletas {
        bigint id PK
        bigint company_id FK
        bigint branch_id FK
        bigint client_id FK
        bigint daily_summary_id FK
        string serie
        string numero
        decimal total
    }
    
    credit_notes {
        bigint id PK
        bigint company_id FK
        bigint branch_id FK
        bigint client_id FK
        string serie
        string numero
    }
    
    debit_notes {
        bigint id PK
        bigint company_id FK
        bigint branch_id FK
        bigint client_id FK
        string serie
        string numero
    }
    
    dispatch_guides {
        bigint id PK
        bigint company_id FK
        bigint branch_id FK
        bigint client_id FK
        string numero
    }
    
    daily_summaries {
        bigint id PK
        bigint company_id FK
        bigint branch_id FK
        date fecha
    }
    
    voided_documents {
        bigint id PK
        bigint company_id FK
        bigint branch_id FK
        string tipo
        string identificador
    }
    
    correlatives {
        bigint id PK
        bigint branch_id FK
        string tipo_documento
        string serie
        int correlativo_actual
    }
    
    retentions {
        bigint id PK
        bigint company_id FK
        bigint branch_id FK
        bigint proveedor_id FK
    }
```

---

### 5. Caja, Pagos y Contabilidad

```mermaid
erDiagram
    companies ||--o{ cash_sessions : "tiene"
    branches ||--o{ cash_sessions : "tiene"
    users ||--o{ cash_sessions : "cajero"
    cash_sessions ||--o{ payments : "tiene"
    invoices ||--o{ payments : "pago"
    users ||--o{ payments : "registrado"
    cash_sessions ||--o{ cash_movements : "tiene"
    companies ||--o{ cash_movements : "tiene"
    users ||--o{ cash_movements : "usuario"
    companies ||--o{ accounting_entries : "tiene"
    users ||--o{ accounting_entries : "created_by"
    accounting_entries ||--o{ accounting_entry_lines : "tiene"
    
    cash_sessions {
        bigint id PK
        bigint company_id FK
        bigint branch_id FK
        bigint user_id FK
        datetime opened_at
        datetime closed_at
    }
    
    payments {
        bigint id PK
        bigint company_id FK
        bigint branch_id FK
        bigint invoice_id FK
        bigint user_id FK
        bigint cash_session_id FK
        decimal monto
    }
    
    cash_movements {
        bigint id PK
        bigint company_id FK
        bigint branch_id FK
        bigint user_id FK
        bigint cash_session_id FK
        string tipo
        decimal monto
    }
    
    accounting_entries {
        bigint id PK
        bigint company_id FK
        bigint created_by FK
        date fecha
    }
    
    accounting_entry_lines {
        bigint id PK
        bigint accounting_entry_id FK
        string cuenta
        decimal debe
        decimal haber
    }
```

---

### 6. Rutas, Zonas y Notificaciones

```mermaid
erDiagram
    companies ||--o{ zones : "tiene"
    zones ||--o{ routes : "zona"
    companies ||--o{ routes : "tiene"
    vehicles ||--o{ routes : "vehículo"
    routes ||--o{ route_stops : "tiene"
    clients ||--o{ route_stops : "cliente"
    companies ||--o{ vehicles : "tiene"
    users ||--o{ vehicles : "driver_id"
    companies ||--o{ optimization_records : "tiene"
    vehicles ||--o{ optimization_records : "vehículo"
    companies ||--o{ notifications : "tiene"
    users ||--o{ notifications : "destinatario"
    users ||--o{ audit_logs : "usuario"
    
    zones {
        bigint id PK
        bigint company_id FK
        string name
        string color
        json districts
    }
    
    routes {
        bigint id PK
        bigint company_id FK
        bigint zone_id FK
        bigint vehicle_id FK
        date fecha
    }
    
    route_stops {
        bigint id PK
        bigint route_id FK
        bigint client_id FK
        int order
    }
    
    notifications {
        bigint id PK
        bigint company_id FK
        bigint user_id FK
        string type
        string title
        text data
    }
    
    audit_logs {
        bigint id PK
        bigint user_id FK
        string action
        string model_type
        bigint model_id
    }
```

---

### 7. Ubigeo (Perú)

```mermaid
erDiagram
    ubi_regiones ||--o{ ubi_provincias : "tiene"
    ubi_provincias ||--o{ ubi_distritos : "tiene"
    
    ubi_regiones {
        string id PK
        string nombre
    }
    
    ubi_provincias {
        string id PK
        string region_id FK
        string nombre
    }
    
    ubi_distritos {
        string id PK
        string provincia_id FK
        string region_id FK
        string nombre
        string info_busqueda
    }
```

---

## Resumen de tablas por módulo

| Módulo | Tablas principales |
|--------|---------------------|
| **Núcleo** | companies, branches, users, roles, permissions, role_permission, company_configurations |
| **Clientes / Operativo** | clients, pets, appointments, appointment_items, services, vehicles, medical_records, vaccine_records, pet_configurations |
| **Productos / Inventario** | categories, units, areas, brands, suppliers, products, product_stocks, product_sales, stock_movements, purchase_orders, purchase_order_items |
| **Facturación** | invoices, boletas, credit_notes, debit_notes, dispatch_guides, daily_summaries, voided_documents, correlatives, retentions, payments |
| **Caja / Contabilidad** | cash_sessions, cash_movements, payments, accounting_entries, accounting_entry_lines |
| **Rutas / Logística** | zones, routes, route_stops, optimization_records |
| **Sistema** | notifications, audit_logs, personal_access_tokens |
| **Ubigeo** | ubi_regiones, ubi_provincias, ubi_distritos |
