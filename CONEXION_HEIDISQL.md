# 🔌 Guía de Conexión a Base de Datos con HeidiSQL

Esta guía te ayudará a conectarte a tu base de datos MySQL/MariaDB local desde HeidiSQL para probar y gestionar tu backend Laravel.

---

## 📋 Requisitos Previos

- HeidiSQL instalado ([Descargar aquí](https://www.heidisql.com/download.php))
- Laragon (o XAMPP/WAMP) con MySQL/MariaDB corriendo
- Credenciales de la base de datos (del archivo `.env` de Laravel)

---

## 🔍 Paso 1: Obtener Credenciales de la Base de Datos

### Opción A: Desde el archivo `.env`

1. Abre el archivo `backend-grooming/.env`
2. Busca las siguientes variables:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nombre_base_datos
DB_USERNAME=root
DB_PASSWORD=
```

**Nota**: Si `DB_PASSWORD` está vacío, significa que no hay contraseña.

### Opción B: Desde Laragon

Si usas Laragon, las credenciales por defecto suelen ser:
- **Host**: `127.0.0.1` o `localhost`
- **Puerto**: `3306`
- **Usuario**: `root`
- **Contraseña**: (generalmente vacía en desarrollo)
- **Base de datos**: El nombre que configuraste en `.env`

---

## 🔧 Paso 2: Configurar Conexión en HeidiSQL

### 2.1. Abrir HeidiSQL

1. Abre HeidiSQL
2. Clic en **"Nuevo"** o **"New"** en la barra de herramientas

### 2.2. Configurar Parámetros de Conexión

En la ventana de configuración, completa los siguientes campos:

#### **Pestaña "Configuración" (Settings)**

- **Nombre de red (Network type)**: Selecciona **"MySQL (TCP/IP)"**
- **Hostname / IP**: `127.0.0.1` o `localhost`
- **Usuario**: `root` (o el usuario de tu `.env`)
- **Contraseña**: (déjala vacía si no tienes contraseña, o ingresa la de tu `.env`)
- **Puerto**: `3306`

#### **Pestaña "Avanzado" (Advanced)** (Opcional)

- **Base de datos**: Puedes dejarlo vacío o seleccionar directamente tu base de datos
- **Charset**: `utf8mb4` (recomendado)
- **Timeout**: `30` segundos (por defecto)

### 2.3. Guardar la Conexión

1. En la parte inferior, en **"Guardar como"**, escribe un nombre descriptivo:
   - Ejemplo: `Laravel Local - Grooming`
2. Clic en **"Abrir"** o **"Open"**

---

## ✅ Paso 3: Verificar la Conexión

### 3.1. Probar Conexión

1. Clic en **"Abrir"** en la ventana de configuración
2. Si todo está correcto, deberías ver:
   - El árbol de bases de datos en el panel izquierdo
   - Tu base de datos listada
   - Mensaje de conexión exitosa

### 3.2. Seleccionar Base de Datos

1. En el panel izquierdo, expande tu servidor MySQL
2. Busca tu base de datos (el nombre de `DB_DATABASE` en `.env`)
3. Haz doble clic o clic derecho → **"Abrir"**

---

## 🗄️ Paso 4: Explorar la Base de Datos

### 4.1. Ver Tablas

Una vez conectado, deberías ver todas las tablas de Laravel:

- `users`
- `clients`
- `pets`
- `appointments`
- `vehicles`
- `products`
- `pet_configurations`
- Y todas las demás tablas creadas por las migraciones

### 4.2. Consultar Datos

1. Selecciona una tabla (ej: `clients`)
2. Clic derecho → **"Seleccionar los primeros 1000 filas"** o presiona `F9`
3. Verás los datos en la pestaña de consulta

---

## 🔍 Paso 5: Ejecutar Consultas SQL

### 5.1. Abrir Editor SQL

1. Clic en la pestaña **"Consulta"** o presiona `F9`
2. Escribe tu consulta SQL:

```sql
-- Ver todos los clientes
SELECT * FROM clients;

-- Ver todas las mascotas
SELECT * FROM pets;

-- Ver usuarios
SELECT id, name, email, role_id FROM users;

-- Ver configuraciones de mascotas
SELECT * FROM pet_configurations;
```

### 5.2. Ejecutar Consulta

- Presiona `F9` o clic en el botón **"Ejecutar"** (▶️)

---

## 🛠️ Paso 6: Operaciones Comunes

### Ver Estructura de una Tabla

1. Selecciona la tabla en el panel izquierdo
2. Clic derecho → **"Editar"** o presiona `F4`
3. Verás la estructura completa con tipos de datos, índices, etc.

### Insertar Datos Manualmente

1. Selecciona la tabla
2. Clic derecho → **"Insertar fila"** o presiona `Ctrl+Insert`
3. Completa los campos
4. Presiona `F9` para guardar

### Editar Datos

1. Selecciona la tabla
2. Presiona `F9` para ver los datos
3. Haz doble clic en cualquier celda para editar
4. Presiona `F9` para guardar cambios

### Exportar Datos

1. Selecciona la tabla
2. Clic derecho → **"Exportar datos de la tabla como..."**
3. Elige el formato (SQL, CSV, JSON, etc.)

---

## 🔐 Paso 7: Configuración de Seguridad (Opcional)

### Crear Usuario Específico para HeidiSQL

Si prefieres no usar `root`, puedes crear un usuario específico:

```sql
-- En HeidiSQL, ejecuta:
CREATE USER 'grooming_user'@'localhost' IDENTIFIED BY 'tu_contraseña_segura';
GRANT ALL PRIVILEGES ON nombre_base_datos.* TO 'grooming_user'@'localhost';
FLUSH PRIVILEGES;
```

Luego usa estas credenciales en HeidiSQL:
- **Usuario**: `grooming_user`
- **Contraseña**: `tu_contraseña_segura`

---

## 🐛 Solución de Problemas

### Error: "Can't connect to MySQL server"

**Causas posibles:**
1. MySQL/MariaDB no está corriendo
2. Puerto incorrecto
3. Firewall bloqueando

**Solución:**
```bash
# Verificar que MySQL está corriendo (en Laragon)
# Abre Laragon → MySQL → Start

# O verificar desde línea de comandos
netstat -an | findstr 3306
```

### Error: "Access denied for user"

**Causa**: Credenciales incorrectas

**Solución:**
1. Verifica el archivo `.env` del backend
2. Asegúrate de usar el mismo usuario y contraseña
3. Si no tienes contraseña, déjala vacía en HeidiSQL

### Error: "Unknown database"

**Causa**: La base de datos no existe

**Solución:**
```bash
# Crear la base de datos desde Laravel
cd backend-grooming
php artisan migrate
```

O crear manualmente en HeidiSQL:
```sql
CREATE DATABASE nombre_base_datos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### No se ven las tablas

**Causa**: No has seleccionado la base de datos

**Solución:**
1. En el panel izquierdo, haz doble clic en tu base de datos
2. O escribe en la consulta: `USE nombre_base_datos;`

---

## 📊 Consultas Útiles para Probar

### Ver todas las tablas
```sql
SHOW TABLES;
```

### Ver estructura de una tabla
```sql
DESCRIBE clients;
-- O
SHOW CREATE TABLE clients;
```

### Contar registros
```sql
SELECT COUNT(*) as total FROM clients;
SELECT COUNT(*) as total FROM pets;
SELECT COUNT(*) as total FROM appointments;
```

### Ver relaciones
```sql
-- Ver clientes con sus mascotas
SELECT 
    c.id,
    c.razon_social,
    COUNT(p.id) as total_mascotas
FROM clients c
LEFT JOIN pets p ON p.client_id = c.id
GROUP BY c.id, c.razon_social;
```

### Ver configuraciones de mascotas
```sql
SELECT type, COUNT(*) as cantidad, GROUP_CONCAT(name) as items
FROM pet_configurations
GROUP BY type;
```

---

## ✅ Checklist de Conexión

- [ ] HeidiSQL instalado
- [ ] MySQL/MariaDB corriendo (Laragon)
- [ ] Credenciales obtenidas del `.env`
- [ ] Conexión creada en HeidiSQL
- [ ] Conexión exitosa
- [ ] Base de datos seleccionada
- [ ] Tablas visibles
- [ ] Consultas funcionando

---

## 🔗 Recursos Adicionales

- [Documentación oficial de HeidiSQL](https://www.heidisql.com/help.php)
- [Guía de MySQL](https://dev.mysql.com/doc/)
- [Laravel Database Migrations](https://laravel.com/docs/migrations)

---

## 💡 Tips

1. **Guarda tus conexiones**: HeidiSQL guarda las conexiones, así que solo necesitas configurarlas una vez
2. **Usa atajos de teclado**: `F9` para ejecutar, `F4` para editar tabla, `Ctrl+Insert` para insertar
3. **Exporta backups**: Regularmente exporta tus datos importantes
4. **Usa transacciones**: Para operaciones críticas, usa `BEGIN TRANSACTION` y `COMMIT`

---

**¡Listo para gestionar tu base de datos desde HeidiSQL!** 🎉
