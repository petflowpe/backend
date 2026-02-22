# API de Productos - Documentación Técnica

## Base URL
```
/api/v1
```

## Autenticación
Todas las rutas requieren autenticación mediante Sanctum:
```
Authorization: Bearer {token}
```

---

## 📦 Productos

### Listar Productos

**GET** `/products`

**Query Parameters:**
| Parámetro | Tipo | Requerido | Descripción |
|-----------|------|-----------|-------------|
| `company_id` | integer | ✅ | ID de la empresa |
| `category_id` | integer | ❌ | Filtrar por categoría |
| `area_id` | integer | ❌ | Filtrar por área |
| `only_active` | boolean | ❌ | Solo productos activos |
| `low_stock` | boolean | ❌ | Solo productos con stock bajo |
| `search` | string | ❌ | Búsqueda en nombre, código, marca |
| `order_by` | string | ❌ | Campo de ordenamiento (default: name) |
| `order_dir` | string | ❌ | Dirección (asc|desc, default: asc) |
| `per_page` | integer | ❌ | Items por página (default: 15, max: 200) |

**Response 200:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "company_id": 1,
      "category_id": 1,
      "unit_id": 1,
      "code": "AL-ROY-001",
      "name": "Royal Canin Adult",
      "brand": "Royal Canin",
      "unit_price": 150.00,
      "cost_price": 100.00,
      "stock": 50,
      "min_stock": 10,
      "max_stock": 100,
      "active": true,
      "category": {...},
      "unitRelation": {...}
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 15,
    "total": 150,
    "last_page": 10
  }
}
```

---

### Crear Producto

**POST** `/products`

**Body:**
```json
{
  "company_id": 1,
  "category_id": 1,
  "unit_id": 1,
  "name": "Royal Canin Adult",
  "brand": "Royal Canin",
  "barcode": "1234567890123",
  "description": "Alimento para perros adultos",
  "supplier": "Distribuidora Pet SA",
  "item_type": "PRODUCTO",
  "unit": "NIU",
  "currency": "PEN",
  "unit_price": 150.00,
  "cost_price": 100.00,
  "tax_affection": "10",
  "igv_rate": 18.00,
  "stock": 50,
  "min_stock": 10,
  "max_stock": 100,
  "area_id": 1,
  "active": true
}
```

**Response 201:**
```json
{
  "success": true,
  "message": "Producto creado exitosamente",
  "data": {
    "id": 1,
    "code": "AL-ROY-001",
    ...
  }
}
```

---

### Actualizar Producto

**PUT** `/products/{id}`

**Body:** (campos opcionales)
```json
{
  "name": "Royal Canin Adult 15kg",
  "unit_price": 160.00,
  "stock": 60
}
```

---

### Eliminar Producto

**DELETE** `/products/{id}`

**Response 200:**
```json
{
  "success": true,
  "message": "Producto desactivado exitosamente"
}
```

---

### Activar Producto

**POST** `/products/{id}/activate`

---

### Obtener KPIs

**GET** `/companies/{company}/products/kpis`

**Response 200:**
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

---

### Productos con Stock Bajo

**GET** `/products/low-stock?company_id=1`

**Response 200:**
```json
{
  "success": true,
  "data": [...]
}
```

---

### Ajustar Stock

**POST** `/products/{id}/adjust-stock`

**Body:**
```json
{
  "area_id": 1,
  "quantity": 10,
  "type": "IN",
  "notes": "Reabastecimiento desde proveedor"
}
```

**Tipos:**
- `IN`: Entrada de stock
- `OUT`: Salida de stock
- `ADJUST`: Ajuste directo

**Response 200:**
```json
{
  "success": true,
  "message": "Stock ajustado exitosamente",
  "data": {
    "id": 1,
    "product_id": 1,
    "area_id": 1,
    "quantity": 60,
    "area": {...}
  }
}
```

---

## 🏷️ Categorías

### Listar Categorías

**GET** `/categories?company_id=1&only_active=true`

**Response 200:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "company_id": 1,
      "name": "Alimento",
      "description": "Alimentos para mascotas",
      "color": "blue",
      "icon": "Heart",
      "active": true,
      "sort_order": 0
    }
  ]
}
```

### Crear Categoría

**POST** `/categories`

**Body:**
```json
{
  "company_id": 1,
  "name": "Alimento",
  "description": "Alimentos para mascotas",
  "color": "blue",
  "icon": "Heart",
  "active": true,
  "sort_order": 0
}
```

### Actualizar Categoría

**PUT** `/categories/{id}`

### Eliminar Categoría

**DELETE** `/categories/{id}`

**Nota:** No se puede eliminar si tiene productos asociados.

### Toggle Activo

**POST** `/categories/{id}/toggle-active`

---

## 📏 Unidades

Endpoints similares a categorías:

- `GET /units`
- `POST /units`
- `PUT /units/{id}`
- `DELETE /units/{id}`
- `POST /units/{id}/toggle-active`

**Body para crear:**
```json
{
  "company_id": 1,
  "name": "Unidad",
  "abbreviation": "UND",
  "sunat_code": "NIU",
  "active": true,
  "sort_order": 0
}
```

---

## 📍 Áreas

Endpoints similares a categorías:

- `GET /areas`
- `POST /areas`
- `PUT /areas/{id}`
- `DELETE /areas/{id}`
- `POST /areas/{id}/toggle-active`

**Body para crear:**
```json
{
  "company_id": 1,
  "branch_id": 1,
  "name": "Tienda Principal",
  "description": "Área principal de ventas",
  "location": "Av. Principal 123",
  "active": true,
  "sort_order": 0
}
```

---

## ❌ Manejo de Errores

### Errores de Validación (422)

```json
{
  "success": false,
  "message": "Error de validación",
  "errors": {
    "name": ["El campo nombre es requerido"],
    "unit_price": ["El precio debe ser mayor a 0"]
  }
}
```

### Errores del Servidor (500)

```json
{
  "success": false,
  "message": "Error al crear producto",
  "error": "Database connection failed" // Solo en desarrollo
}
```

### Errores de Negocio (400)

```json
{
  "success": false,
  "message": "No se puede eliminar la categoría porque tiene productos asociados"
}
```

---

## 📝 Notas

1. Todos los timestamps están en formato ISO 8601
2. Los decimales se manejan con precisión de 2-3 decimales según el campo
3. Los soft deletes no eliminan físicamente los registros
4. Las relaciones se cargan con `with()` cuando es necesario
5. La paginación es opcional, si no se especifica `per_page`, retorna todos los resultados

