# Arquitectura del Módulo de Productos

## 📋 Índice
1. [Visión General](#visión-general)
2. [Arquitectura Backend](#arquitectura-backend)
3. [Arquitectura Frontend](#arquitectura-frontend)
4. [Modelo de Datos](#modelo-de-datos)
5. [Endpoints API](#endpoints-api)
6. [Flujos de Trabajo](#flujos-de-trabajo)
7. [Seguridad y Validaciones](#seguridad-y-validaciones)

---

## 🎯 Visión General

El módulo de Productos es un sistema completo de gestión de inventario que permite:

- **Gestión de Productos**: CRUD completo con categorización y unidades
- **Control de Stock**: Stock por área/ubicación con alertas
- **Análisis y KPIs**: Métricas de inventario, ventas y rentabilidad
- **Multiempresa**: Soporte completo para múltiples empresas
- **Auditoría**: Trazabilidad de movimientos de stock

---

## 🏗️ Arquitectura Backend

### Estructura de Capas

```
app/
├── Http/
│   ├── Controllers/Api/
│   │   ├── ProductController.php      # CRUD productos + KPIs
│   │   ├── CategoryController.php     # Gestión categorías
│   │   ├── UnitController.php          # Gestión unidades
│   │   └── AreaController.php          # Gestión áreas
│   └── Requests/
│       ├── Product/
│       │   ├── StoreProductRequest.php
│       │   ├── UpdateProductRequest.php
│       │   └── AdjustStockRequest.php
│       ├── Category/
│       ├── Unit/
│       └── Area/
├── Models/
│   ├── Product.php
│   ├── Category.php
│   ├── Unit.php
│   ├── Area.php
│   ├── ProductStock.php
│   ├── ProductSale.php
│   └── StockMovement.php
├── Services/
│   ├── ProductService.php    # Lógica de negocio productos
│   ├── CategoryService.php   # Lógica de negocio categorías
│   ├── UnitService.php        # Lógica de negocio unidades
│   └── AreaService.php        # Lógica de negocio áreas
└── Repositories/
    └── ProductRepository.php  # Acceso a datos productos
```

### Principios de Diseño

1. **Separación de Responsabilidades**
   - **Controllers**: Manejan HTTP requests/responses
   - **Services**: Contienen lógica de negocio
   - **Repositories**: Abstraen acceso a datos
   - **Models**: Representan entidades del dominio

2. **Inyección de Dependencias**
   - Todos los servicios se inyectan en los controladores
   - Facilita testing y mantenibilidad

3. **Transacciones**
   - Operaciones críticas (crear producto, ajustar stock) usan transacciones DB
   - Rollback automático en caso de error

---

## 🎨 Arquitectura Frontend

### Estructura de Componentes

```
frontend/src/
├── components/
│   └── Products.tsx           # Componente principal
├── services/
│   └── sunatApi.ts           # Cliente API
└── types/
    └── product.ts             # Tipos TypeScript
```

### Flujo de Datos

1. **Componente** → Llama a `productsApi`
2. **API Client** → Realiza petición HTTP
3. **Backend** → Procesa y retorna datos
4. **Componente** → Actualiza estado y UI

### Estado

- **Local State**: `useState` para estado del componente
- **Server State**: Datos obtenidos del API
- **Futuro**: Considerar React Query para cache y sincronización

---

## 📊 Modelo de Datos

### Tablas Principales

#### `products`
```sql
- id (PK)
- company_id (FK)
- category_id (FK, nullable)
- unit_id (FK, nullable)
- code (unique per company)
- name
- brand
- barcode
- description
- supplier
- item_type (PRODUCTO|SERVICIO)
- unit (código SUNAT)
- currency
- unit_price
- cost_price
- tax_affection
- igv_rate
- stock (total)
- min_stock
- max_stock
- rating
- sold_count
- last_restocked_at
- active
- metadata (JSON)
- timestamps
```

#### `categories`
```sql
- id (PK)
- company_id (FK)
- name (unique per company)
- description
- color (blue|purple|green|orange|red)
- icon
- active
- sort_order
- timestamps
- deleted_at (soft delete)
```

#### `units`
```sql
- id (PK)
- company_id (FK)
- name
- abbreviation (unique per company)
- sunat_code
- active
- sort_order
- timestamps
- deleted_at (soft delete)
```

#### `areas`
```sql
- id (PK)
- company_id (FK)
- branch_id (FK, nullable)
- name (unique per company)
- description
- location
- active
- sort_order
- timestamps
- deleted_at (soft delete)
```

#### `product_stocks`
```sql
- id (PK)
- product_id (FK)
- area_id (FK)
- quantity
- reserved_quantity
- min_stock
- max_stock
- timestamps
- deleted_at (soft delete)
- UNIQUE(product_id, area_id)
```

#### `product_sales`
```sql
- id (PK)
- product_id (FK, unique)
- company_id (FK)
- quantity_sold
- total_revenue
- total_cost
- last_sale_date
- sale_count
- timestamps
```

#### `stock_movements`
```sql
- id (PK)
- company_id (FK)
- branch_id (FK, nullable)
- product_id (FK)
- movement_date
- type (IN|OUT|ADJUST)
- quantity
- unit_cost
- total_cost
- source_type
- source_id
- notes
- created_by (FK, nullable)
- timestamps
```

### Relaciones

```
Company
  ├── Products (1:N)
  ├── Categories (1:N)
  ├── Units (1:N)
  └── Areas (1:N)

Product
  ├── Category (N:1)
  ├── Unit (N:1)
  ├── ProductStocks (1:N)
  ├── ProductSale (1:1)
  └── StockMovements (1:N)

ProductStock
  ├── Product (N:1)
  └── Area (N:1)
```

---

## 🔌 Endpoints API

### Productos

#### `GET /v1/products`
Listar productos con filtros.

**Query Params:**
- `company_id` (required)
- `category_id` (optional)
- `area_id` (optional)
- `only_active` (boolean)
- `low_stock` (boolean)
- `search` (string)
- `order_by` (string)
- `order_dir` (asc|desc)
- `per_page` (number)

**Response:**
```json
{
  "success": true,
  "data": [...],
  "pagination": {...}
}
```

#### `POST /v1/products`
Crear producto.

**Body:**
```json
{
  "company_id": 1,
  "category_id": 1,
  "unit_id": 1,
  "name": "Royal Canin Adult",
  "brand": "Royal Canin",
  "unit_price": 150.00,
  "cost_price": 100.00,
  "stock": 50,
  "min_stock": 10,
  "max_stock": 100,
  "area_id": 1
}
```

#### `PUT /v1/products/{id}`
Actualizar producto.

#### `DELETE /v1/products/{id}`
Desactivar producto (soft delete).

#### `POST /v1/products/{id}/activate`
Activar producto.

#### `GET /v1/companies/{company}/products/kpis`
Obtener KPIs de productos.

**Response:**
```json
{
  "success": true,
  "data": {
    "total_products": 150,
    "active_products": 140,
    "low_stock_products": 12,
    "total_inventory_value": 50000.00,
    "total_potential_revenue": 75000.00,
    "total_profit_potential": 25000.00,
    "average_margin": 33.33,
    "total_sold": 5000
  }
}
```

#### `GET /v1/products/low-stock`
Obtener productos con stock bajo.

**Query Params:**
- `company_id` (required)

#### `POST /v1/products/{id}/adjust-stock`
Ajustar stock de producto.

**Body:**
```json
{
  "area_id": 1,
  "quantity": 10,
  "type": "IN",
  "notes": "Reabastecimiento"
}
```

### Categorías

#### `GET /v1/categories`
Listar categorías.

**Query Params:**
- `company_id` (required)
- `only_active` (boolean)

#### `POST /v1/categories`
Crear categoría.

#### `PUT /v1/categories/{id}`
Actualizar categoría.

#### `DELETE /v1/categories/{id}`
Eliminar categoría (soft delete).

#### `POST /v1/categories/{id}/toggle-active`
Cambiar estado activo/inactivo.

### Unidades

Endpoints similares a categorías:
- `GET /v1/units`
- `POST /v1/units`
- `PUT /v1/units/{id}`
- `DELETE /v1/units/{id}`
- `POST /v1/units/{id}/toggle-active`

### Áreas

Endpoints similares a categorías:
- `GET /v1/areas`
- `POST /v1/areas`
- `PUT /v1/areas/{id}`
- `DELETE /v1/areas/{id}`
- `POST /v1/areas/{id}/toggle-active`

---

## 🔄 Flujos de Trabajo

### Crear Producto

1. Usuario completa formulario
2. Frontend valida datos
3. `POST /v1/products` con datos
4. Backend valida con `StoreProductRequest`
5. `ProductService::create()`:
   - Genera código si no existe
   - Crea producto
   - Si hay `area_id` y `stock`, crea `ProductStock`
   - Registra movimiento inicial en `StockMovement`
6. Retorna producto creado

### Ajustar Stock

1. Usuario selecciona producto y área
2. Ingresa cantidad y tipo (IN/OUT/ADJUST)
3. `POST /v1/products/{id}/adjust-stock`
4. `ProductService::adjustStock()`:
   - Busca o crea `ProductStock`
   - Actualiza cantidad según tipo
   - Actualiza stock total del producto
   - Registra movimiento en `StockMovement`
5. Retorna stock actualizado

### Obtener KPIs

1. Usuario accede a dashboard
2. `GET /v1/companies/{company}/products/kpis`
3. `ProductService::getKPIs()`:
   - Calcula métricas agregadas
   - Retorna objeto con KPIs
4. Frontend muestra cards con métricas

---

## 🔐 Seguridad y Validaciones

### Validaciones Backend

1. **Request Validation**: FormRequests validan datos de entrada
2. **Business Rules**: Services validan reglas de negocio
3. **Database Constraints**: Foreign keys y unique constraints

### Validaciones Frontend

1. **TypeScript**: Tipos estáticos
2. **Form Validation**: Validación antes de enviar
3. **Error Handling**: Manejo de errores del API

### Permisos (Futuro)

- Middleware de roles
- Permisos por acción (crear, editar, eliminar)
- Auditoría de cambios críticos

### Auditoría

- `StockMovement` registra todos los cambios
- `created_by` identifica usuario
- `movement_date` timestamp del cambio

---

## 📈 Mejoras Futuras

1. **Cache**: Redis para KPIs y listados frecuentes
2. **Exportación**: Excel/CSV de productos
3. **Búsqueda Avanzada**: Full-text search con Elasticsearch
4. **Notificaciones**: Alertas de stock bajo
5. **Historial**: Vista de movimientos de stock
6. **Reportes**: Reportes de inventario y ventas
7. **Imágenes**: Upload de imágenes de productos
8. **Códigos de Barras**: Escaneo y generación

---

## 🧪 Testing

### Backend

- **Unit Tests**: Services y Repositories
- **Feature Tests**: Endpoints API
- **Integration Tests**: Flujos completos

### Frontend

- **Component Tests**: React Testing Library
- **E2E Tests**: Playwright/Cypress

---

## 📚 Documentación Adicional

- [API Documentation](./API_PRODUCTOS.md) - Documentación detallada de endpoints
- [Frontend Guide](./FRONTEND_PRODUCTOS.md) - Guía de uso del frontend
- [Deployment Guide](../GUIA_DESPLIEGUE_VPS.md) - Guía de despliegue

