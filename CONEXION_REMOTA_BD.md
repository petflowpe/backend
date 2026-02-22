# 🔌 Guía de Conexión Remota a Base de Datos Laravel en VPS Hostinger

Esta guía te ayudará a configurar el acceso remoto a tu base de datos MySQL/MariaDB alojada en tu VPS de Hostinger.

---

## 📋 Requisitos Previos

- Acceso SSH a tu VPS de Hostinger
- Credenciales de la base de datos (usuario, contraseña, nombre de BD)
- IP pública de tu VPS
- Cliente MySQL instalado localmente (MySQL Workbench, DBeaver, phpMyAdmin, etc.)

---

## 🔐 Paso 1: Configurar Usuario de Base de Datos para Acceso Remoto

### 1.1. Conectarse al VPS por SSH

```bash
ssh root@tu-ip-vps
# O si usas un usuario específico:
ssh usuario@tu-ip-vps
```

### 1.2. Acceder a MySQL/MariaDB

```bash
mysql -u root -p
# Ingresa la contraseña de root de MySQL
```

### 1.3. Crear o Modificar Usuario para Acceso Remoto

```sql
-- Opción A: Crear un nuevo usuario específico para acceso remoto
CREATE USER 'usuario_remoto'@'%' IDENTIFIED BY 'contraseña_segura_aqui';
GRANT ALL PRIVILEGES ON nombre_base_datos.* TO 'usuario_remoto'@'%';
FLUSH PRIVILEGES;

-- Opción B: Permitir acceso remoto al usuario root (NO RECOMENDADO para producción)
-- Solo si es necesario para desarrollo
GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' IDENTIFIED BY 'tu_contraseña_root' WITH GRANT OPTION;
FLUSH PRIVILEGES;

-- Verificar usuarios creados
SELECT user, host FROM mysql.user;
```

**Nota:** `'%'` permite conexiones desde cualquier IP. Para mayor seguridad, puedes especificar tu IP:
```sql
CREATE USER 'usuario_remoto'@'tu-ip-local' IDENTIFIED BY 'contraseña_segura';
GRANT ALL PRIVILEGES ON nombre_base_datos.* TO 'usuario_remoto'@'tu-ip-local';
FLUSH PRIVILEGES;
```

---

## 🔓 Paso 2: Configurar Firewall en el VPS

### 2.1. Verificar si UFW está activo

```bash
sudo ufw status
```

### 2.2. Permitir Puerto MySQL (3306)

```bash
# Permitir conexiones MySQL desde cualquier IP (desarrollo)
sudo ufw allow 3306/tcp

# O solo desde tu IP específica (más seguro)
sudo ufw allow from tu-ip-local to any port 3306
```

### 2.3. Verificar reglas del firewall

```bash
sudo ufw status numbered
```

---

## ⚙️ Paso 3: Configurar MySQL/MariaDB para Escuchar en Todas las Interfaces

### 3.1. Editar archivo de configuración

```bash
# Para MySQL
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf

# Para MariaDB
sudo nano /etc/mysql/mariadb.conf.d/50-server.cnf
```

### 3.2. Modificar la línea `bind-address`

Busca la línea:
```ini
bind-address = 127.0.0.1
```

Cámbiala por:
```ini
bind-address = 0.0.0.0
```

O comenta la línea:
```ini
# bind-address = 127.0.0.1
```

### 3.3. Guardar y reiniciar MySQL/MariaDB

```bash
# Para MySQL
sudo systemctl restart mysql

# Para MariaDB
sudo systemctl restart mariadb

# Verificar que está escuchando en todas las interfaces
sudo netstat -tlnp | grep 3306
# Deberías ver: 0.0.0.0:3306
```

---

## 🛡️ Paso 4: Configurar Firewall de Hostinger (Panel de Control)

Si Hostinger tiene un firewall en su panel de control:

1. Accede al panel de control de Hostinger
2. Ve a **Firewall** o **Seguridad**
3. Agrega una regla para permitir el puerto **3306** desde tu IP
4. Guarda los cambios

---

## 🧪 Paso 5: Probar Conexión Remota

### 5.1. Desde línea de comandos (local)

```bash
mysql -h tu-ip-vps -u usuario_remoto -p nombre_base_datos
```

### 5.2. Desde MySQL Workbench

1. Abre MySQL Workbench
2. Clic en **"+"** para nueva conexión
3. Configura:
   - **Connection Name:** `VPS Hostinger`
   - **Hostname:** `tu-ip-vps`
   - **Port:** `3306`
   - **Username:** `usuario_remoto`
   - **Password:** `contraseña_segura`
   - **Default Schema:** `nombre_base_datos`
4. Clic en **Test Connection**
5. Si funciona, guarda y conecta

### 5.3. Desde DBeaver

1. Clic derecho en **Databases** → **New Database Connection**
2. Selecciona **MySQL**
3. Configura:
   - **Host:** `tu-ip-vps`
   - **Port:** `3306`
   - **Database:** `nombre_base_datos`
   - **Username:** `usuario_remoto`
   - **Password:** `contraseña_segura`
4. Clic en **Test Connection**

### 5.4. Desde Laravel (archivo .env local)

```env
DB_CONNECTION=mysql
DB_HOST=tu-ip-vps
DB_PORT=3306
DB_DATABASE=nombre_base_datos
DB_USERNAME=usuario_remoto
DB_PASSWORD=contraseña_segura
```

Luego prueba:
```bash
php artisan migrate:status
```

---

## 🔒 Paso 6: Seguridad Adicional (Recomendado)

### 6.1. Usar SSH Tunnel (Más Seguro)

En lugar de exponer el puerto 3306 directamente, puedes usar un túnel SSH:

```bash
# Crear túnel SSH
ssh -L 3307:localhost:3306 usuario@tu-ip-vps

# En otra terminal, conectar a través del túnel
mysql -h 127.0.0.1 -P 3307 -u usuario_remoto -p nombre_base_datos
```

### 6.2. Configurar SSL/TLS (Opcional)

Si tu servidor MySQL soporta SSL:

1. Obtén los certificados del servidor
2. Configúralos en tu cliente MySQL
3. En Laravel `.env`:
```env
DB_SSL_CA=/ruta/al/ca-cert.pem
DB_SSL_CERT=/ruta/al/client-cert.pem
DB_SSL_KEY=/ruta/al/client-key.pem
```

### 6.3. Limitar Acceso por IP

En lugar de `'%'`, usa tu IP específica:
```sql
CREATE USER 'usuario_remoto'@'tu-ip-local' IDENTIFIED BY 'contraseña_segura';
```

---

## 🐛 Solución de Problemas

### Error: "Can't connect to MySQL server"

**Causas posibles:**
- Firewall bloqueando el puerto 3306
- MySQL no está escuchando en `0.0.0.0`
- Usuario no tiene permisos desde IP remota

**Solución:**
```bash
# Verificar que MySQL está escuchando
sudo netstat -tlnp | grep 3306

# Verificar firewall
sudo ufw status

# Verificar configuración de bind-address
sudo grep bind-address /etc/mysql/mysql.conf.d/mysqld.cnf
```

### Error: "Access denied for user"

**Causa:** Usuario no tiene permisos desde tu IP

**Solución:**
```sql
-- Verificar usuarios y hosts
SELECT user, host FROM mysql.user;

-- Otorgar permisos específicos
GRANT ALL PRIVILEGES ON nombre_base_datos.* TO 'usuario_remoto'@'tu-ip-local';
FLUSH PRIVILEGES;
```

### Error: "Too many connections"

**Causa:** Límite de conexiones alcanzado

**Solución:**
```sql
-- Ver conexiones actuales
SHOW PROCESSLIST;

-- Aumentar límite (temporal)
SET GLOBAL max_connections = 200;

-- O editar /etc/mysql/mysql.conf.d/mysqld.cnf
max_connections = 200
```

---

## 📝 Configuración Recomendada para Producción

1. **No usar root para acceso remoto**
2. **Crear usuario específico con permisos limitados**
3. **Usar IP específica en lugar de `%`**
4. **Configurar firewall para permitir solo tu IP**
5. **Usar SSH Tunnel si es posible**
6. **Habilitar SSL/TLS**
7. **Cambiar puerto MySQL (opcional, seguridad por oscuridad)**

---

## 🔗 Recursos Adicionales

- [Documentación MySQL - Usuarios y Privilegios](https://dev.mysql.com/doc/refman/8.0/en/user-management.html)
- [Documentación MariaDB - Acceso Remoto](https://mariadb.com/kb/en/configuring-mariadb-for-remote-client-access/)
- [Hostinger - Guía SSH](https://www.hostinger.com/tutoriales/como-usar-ssh)

---

## ✅ Checklist Final

- [ ] Usuario de BD creado con permisos remotos
- [ ] Firewall configurado (UFW y Hostinger)
- [ ] MySQL/MariaDB escuchando en `0.0.0.0`
- [ ] Puerto 3306 abierto
- [ ] Conexión probada desde cliente local
- [ ] Laravel `.env` configurado
- [ ] Migraciones probadas
- [ ] Seguridad implementada (IP específica, usuario limitado)

---

**⚠️ IMPORTANTE:** El acceso remoto a bases de datos expone un riesgo de seguridad. Asegúrate de:
- Usar contraseñas fuertes
- Limitar acceso por IP cuando sea posible
- Considerar usar SSH Tunnel para mayor seguridad
- Monitorear logs de acceso regularmente
