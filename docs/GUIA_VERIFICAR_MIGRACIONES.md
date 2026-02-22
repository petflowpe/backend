# 🔍 Guía: Cómo Verificar las Nuevas Tablas Creadas

## 📍 Ubicación de las Migraciones

Las migraciones están en:
```
apifacturacion/database/migrations/
```

### Nuevas Migraciones Creadas:

1. ✅ `2025_01_20_000001_create_categories_table.php` → Tabla `categories`
2. ✅ `2025_01_20_000002_create_units_table.php` → Tabla `units`
3. ✅ `2025_01_20_000003_create_areas_table.php` → Tabla `areas`
4. ✅ `2025_01_20_000004_create_product_stocks_table.php` → Tabla `product_stocks`
5. ✅ `2025_01_20_000005_create_product_sales_table.php` → Tabla `product_sales`
6. ✅ `2025_01_20_000006_add_product_relations_to_products_table.php` → Modifica tabla `products`

---

## 🔧 Comandos para Verificar

### 1. Ver Estado de Migraciones

```bash
php artisan migrate:status
```

**Salida esperada:**
```
+------+----------------------------------------------------+-------+
| Ran? | Migration                                          | Batch |
+------+----------------------------------------------------+-------+
| Yes  | 2025_01_20_000001_create_categories_table        | 1     |
| Yes  | 2025_01_20_000002_create_units_table              | 1     |
| Yes  | 2025_01_20_000003_create_areas_table              | 1     |
| Yes  | 2025_01_20_000004_create_product_stocks_table     | 1     |
| Yes  | 2025_01_20_000005_create_product_sales_table      | 1     |
| Yes  | 2025_01_20_000006_add_product_relations_to_products_table | 1 |
+------+----------------------------------------------------+-------+
```

### 2. Ver Todas las Tablas en la Base de Datos

#### Opción A: Usando Tinker (Laravel)

```bash
php artisan tinker
```

Luego ejecuta:
```php
DB::select("SHOW TABLES");
// O más específico:
Schema::getTableListing();
```

#### Opción B: Consulta SQL Directa

```bash
php artisan tinker
```

```php
use Illuminate\Support\Facades\DB;

// Ver todas las tablas
$tables = DB::select("SHOW TABLES");
print_r($tables);

// Ver solo las nuevas tablas
$newTables = ['categories', 'units', 'areas', 'product_stocks', 'product_sales'];
foreach ($newTables as $table) {
    if (Schema::hasTable($table)) {
        echo "✅ Tabla '{$table}' existe\n";
        echo "   Columnas: " . count(Schema::getColumnListing($table)) . "\n";
    } else {
        echo "❌ Tabla '{$table}' NO existe\n";
    }
}
```

### 3. Ver Estructura de una Tabla Específica

```bash
php artisan tinker
```

```php
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// Ver columnas de categories
$columns = Schema::getColumnListing('categories');
print_r($columns);

// Ver estructura completa
$columns = DB::select("DESCRIBE categories");
print_r($columns);
```

---

## 🗄️ Verificar en el Cliente de Base de Datos

### MySQL (phpMyAdmin / MySQL Workbench / HeidiSQL)

1. **Conecta a tu base de datos** (la que configuraste en `.env`)
2. **Navega a las tablas** y busca:

   - ✅ `categories`
   - ✅ `units`
   - ✅ `areas`
   - ✅ `product_stocks`
   - ✅ `product_sales`
   - ✅ `products` (debe tener nuevas columnas)

3. **Ver estructura de una tabla:**

```sql
DESCRIBE categories;
-- O
SHOW CREATE TABLE categories;
```

### PostgreSQL (pgAdmin / DBeaver)

```sql
-- Listar todas las tablas
SELECT table_name 
FROM information_schema.tables 
WHERE table_schema = 'public';

-- Ver estructura de categories
SELECT column_name, data_type, is_nullable
FROM information_schema.columns
WHERE table_name = 'categories';
```

---

## ✅ Verificación Rápida con Comando

Crea este comando personalizado para verificar:

```bash
php artisan tinker
```

```php
$tables = [
    'categories',
    'units', 
    'areas',
    'product_stocks',
    'product_sales'
];

echo "\n🔍 Verificando nuevas tablas...\n\n";

foreach ($tables as $table) {
    $exists = Schema::hasTable($table);
    $icon = $exists ? '✅' : '❌';
    $status = $exists ? 'EXISTE' : 'NO EXISTE';
    
    if ($exists) {
        $count = DB::table($table)->count();
        $columns = count(Schema::getColumnListing($table));
        echo "{$icon} {$table}: {$status} ({$columns} columnas, {$count} registros)\n";
    } else {
        echo "{$icon} {$table}: {$status}\n";
    }
}

// Verificar columnas nuevas en products
echo "\n📦 Verificando tabla 'products'...\n";
$productColumns = Schema::getColumnListing('products');
$newColumns = ['category_id', 'unit_id', 'brand', 'barcode', 'supplier', 'cost_price'];
foreach ($newColumns as $col) {
    $exists = in_array($col, $productColumns);
    $icon = $exists ? '✅' : '❌';
    echo "{$icon} Columna '{$col}': " . ($exists ? 'EXISTE' : 'NO EXISTE') . "\n";
}
```

---

## 🐛 Si las Tablas NO se Crearon

### 1. Verificar Errores

```bash
php artisan migrate --verbose
```

### 2. Ver Último Error

```bash
php artisan migrate:status
```

### 3. Revertir y Re-ejecutar

```bash
# Ver qué migraciones se ejecutaron
php artisan migrate:status

# Si necesitas revertir (CUIDADO: borra datos)
php artisan migrate:rollback --step=6

# Re-ejecutar
php artisan migrate
```

### 4. Verificar Conexión a BD

```bash
php artisan tinker
```

```php
try {
    DB::connection()->getPdo();
    echo "✅ Conexión a BD exitosa\n";
    echo "Base de datos: " . DB::connection()->getDatabaseName() . "\n";
} catch (\Exception $e) {
    echo "❌ Error de conexión: " . $e->getMessage() . "\n";
}
```

---

## 📊 Resumen de Tablas Creadas

| Tabla | Descripción | Columnas Principales |
|-------|-------------|---------------------|
| `categories` | Categorías de productos | id, company_id, name, color, icon |
| `units` | Unidades de medida | id, company_id, name, abbreviation, sunat_code |
| `areas` | Áreas de almacenamiento | id, company_id, branch_id, name, location |
| `product_stocks` | Stock por área | id, product_id, area_id, quantity |
| `product_sales` | Resumen de ventas | id, product_id, quantity_sold, total_revenue |

### Tabla Modificada

| Tabla | Cambios |
|-------|---------|
| `products` | Agregadas: category_id, unit_id, brand, barcode, supplier, cost_price, rating, sold_count, last_restocked_at, metadata |

---

## 🎯 Comando Todo-en-Uno

Copia y pega esto en tu terminal:

```bash
php artisan tinker --execute="
\$tables = ['categories', 'units', 'areas', 'product_stocks', 'product_sales'];
echo '\n🔍 Verificando nuevas tablas...\n\n';
foreach (\$tables as \$table) {
    \$exists = Schema::hasTable(\$table);
    echo (\$exists ? '✅' : '❌') . ' ' . \$table . ': ' . (\$exists ? 'EXISTE' : 'NO EXISTE') . '\n';
}
echo '\n📦 Verificando columnas nuevas en products...\n';
\$cols = ['category_id', 'unit_id', 'brand', 'barcode', 'supplier', 'cost_price'];
\$productCols = Schema::getColumnListing('products');
foreach (\$cols as \$col) {
    \$exists = in_array(\$col, \$productCols);
    echo (\$exists ? '✅' : '❌') . ' Columna ' . \$col . ': ' . (\$exists ? 'EXISTE' : 'NO EXISTE') . '\n';
}
"
```

---

## 💡 Tips

1. **Siempre verifica** con `migrate:status` antes de ejecutar migraciones
2. **Revisa los logs** en `storage/logs/laravel.log` si hay errores
3. **Usa tinker** para explorar la estructura de las tablas
4. **Backup** tu base de datos antes de migraciones en producción

---

## 📝 Notas Importantes

- Las migraciones se ejecutan en orden cronológico (por fecha en el nombre)
- Si una migración falla, las siguientes NO se ejecutan
- La tabla `migrations` en la BD registra qué migraciones se ejecutaron
- Las tablas con `soft deletes` tienen la columna `deleted_at`

