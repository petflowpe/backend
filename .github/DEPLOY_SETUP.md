# 🚀 Configuración de Despliegue Automático con GitHub Actions

Esta guía te ayudará a configurar el despliegue automático de tu aplicación Laravel en el VPS de Hostinger cada vez que hagas push a GitHub.

## 📋 Requisitos Previos

1. ✅ Repositorio en GitHub
2. ✅ Acceso SSH al VPS de Hostinger
3. ✅ Git configurado en el servidor VPS
4. ✅ Composer instalado en el servidor
5. ✅ PHP y extensiones necesarias instaladas

## 🔧 Paso 1: Configurar el Repositorio en el VPS

Primero, asegúrate de que tu proyecto esté clonado en el VPS:

```bash
# Conectarte al VPS
ssh usuario@tu-servidor-ip

# Navegar al directorio donde está tu aplicación (ejemplo: /var/www/facturacion)
cd /var/www/facturacion

# Si aún no has clonado el repositorio:
git clone https://github.com/tu-usuario/tu-repositorio.git .

# O si ya está clonado, verifica que esté configurado correctamente:
git remote -v
```

## 🔑 Paso 2: Generar Clave SSH para GitHub Actions

Necesitas crear una clave SSH específica para que GitHub Actions se conecte a tu servidor:

```bash
# En tu VPS, generar una nueva clave SSH SIN passphrase
# ⚠️ IMPORTANTE: Cuando pregunte por la passphrase, presiona ENTER (déjalo vacío)
# GitHub Actions NO puede ingresar contraseñas interactivamente

ssh-keygen -t ed25519 -C "github-actions-deploy" -f ~/.ssh/github_actions_deploy -N ""

# O si prefieres el método interactivo, cuando pregunte:
# "Enter passphrase (empty for no passphrase):" → Presiona ENTER
# "Enter same passphrase again:" → Presiona ENTER

# Ver la clave pública
cat ~/.ssh/github_actions_deploy.pub

# Agregar la clave pública al archivo authorized_keys
cat ~/.ssh/github_actions_deploy.pub >> ~/.ssh/authorized_keys
chmod 700 ~/.ssh
chmod 600 ~/.ssh/authorized_keys

# Copiar la clave PRIVADA (la necesitarás en el siguiente paso)
cat ~/.ssh/github_actions_deploy
```

**⚠️ IMPORTANTE**: 
- La clave debe generarse **SIN passphrase** (sin contraseña)
- Copia la clave **PRIVADA** completa (incluyendo `-----BEGIN OPENSSH PRIVATE KEY-----` y `-----END OPENSSH PRIVATE KEY-----`)
- El parámetro `-N ""` significa "sin passphrase" y es necesario para GitHub Actions

## 🔐 Paso 3: Configurar Secrets en GitHub

**⚠️ IMPORTANTE**: Los secrets se configuran en el **REPOSITORIO**, no en tu cuenta personal.

### Pasos detallados:

1. **Ve a tu repositorio en GitHub**
   - Abre tu navegador y ve a: `https://github.com/tu-usuario/tu-repositorio`
   - Por ejemplo: `https://github.com/oscarcalle/backend-grooming`

2. **Abre la configuración del repositorio**
   - En la parte superior del repositorio, haz clic en la pestaña **Settings** (Configuración)
   - Si no ves la pestaña Settings, verifica que tengas permisos de administrador en el repositorio

3. **Navega a Secrets and variables**
   - En el menú lateral izquierdo, busca la sección **"Secrets and variables"**
   - Haz clic en **"Actions"** (dentro de Secrets and variables)
   - Verás una página con el título "Secrets" y un botón verde **"New repository secret"**

4. **Agrega cada secret**
   - Haz clic en **"New repository secret"** para agregar cada uno de los siguientes secrets:

### Secret: `SSH_HOST`
- **Valor**: La IP o dominio de tu servidor VPS
- **Ejemplo**: `123.456.789.0` o `tudominio.com`

### Secret: `SSH_USER`
- **Valor**: El usuario SSH con el que te conectas al servidor
- **Ejemplo**: `root` o `usuario`

### Secret: `SSH_PRIVATE_KEY`
- **Valor**: La clave privada SSH que copiaste en el paso anterior
- **Ejemplo**: 
```
-----BEGIN OPENSSH PRIVATE KEY-----
b3BlbnNzaC1rZXktdjEAAAAABG5vbmUAAAAEbm9uZQAAAAAAAAABAAAAMwAAAAtzc2gtZW
...
(toda la clave privada)
...
-----END OPENSSH PRIVATE KEY-----
```

### Secret: `DEPLOY_PATH`
- **Valor**: La ruta completa donde está tu proyecto en el servidor
- **Ejemplo**: `/var/www/facturacion` o `/home/usuario/proyecto`

## 📝 Paso 4: Verificar la Configuración

Una vez configurados los secrets, el workflow se ejecutará automáticamente cada vez que hagas push a la rama `main` o `master`.

### Probar el Despliegue

1. Haz un pequeño cambio en tu código
2. Haz commit y push:
```bash
git add .
git commit -m "Test: Probar despliegue automático"
git push origin main
```

3. Ve a la pestaña **Actions** en tu repositorio de GitHub
4. Deberías ver el workflow ejecutándose
5. Haz clic en el workflow para ver los logs en tiempo real

## 🔍 Solución de Problemas

### No encuentro "Secrets and variables" en el menú

**Problema**: Estás en la configuración de tu cuenta personal, no del repositorio.

**Solución**:
1. Asegúrate de estar en la página del **repositorio** (no en tu perfil personal)
2. La URL debe ser: `https://github.com/tu-usuario/nombre-repositorio`
3. Haz clic en la pestaña **Settings** en la parte superior del repositorio
4. En el menú lateral izquierdo, busca **"Secrets and variables"** (está en la sección de "Security")
5. Si aún no lo ves, verifica que tengas permisos de **administrador** o **mantenedor** en el repositorio

**Ruta completa**:
```
Repositorio → Settings (pestaña superior) → Secrets and variables (menú lateral) → Actions
```

### Error: "Permission denied (publickey, password)"

Este es el error más común. Significa que la autenticación SSH está fallando.

**Solución paso a paso**:

#### 1. Verificar que la clave pública esté en el servidor

Conéctate a tu VPS y ejecuta:

```bash
# Verificar que el archivo authorized_keys existe
ls -la ~/.ssh/authorized_keys

# Ver el contenido (debe incluir tu clave pública)
cat ~/.ssh/authorized_keys

# Si no existe o está vacío, crea el directorio y archivo
mkdir -p ~/.ssh
touch ~/.ssh/authorized_keys
chmod 700 ~/.ssh
chmod 600 ~/.ssh/authorized_keys
```

#### 2. Agregar la clave pública al servidor

**Opción A: Si ya generaste la clave en el servidor**

```bash
# En tu VPS, ver la clave pública que generaste
cat ~/.ssh/github_actions_deploy.pub

# Agregarla a authorized_keys (si no está ya)
cat ~/.ssh/github_actions_deploy.pub >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
```

**Opción B: Si tienes la clave privada localmente**

```bash
# En tu máquina local, extraer la clave pública de la privada
ssh-keygen -y -f ruta/a/tu/clave_privada > clave_publica.pub

# Copiar la clave pública al servidor
scp clave_publica.pub usuario@tu-servidor:~/.ssh/github_actions_deploy.pub

# En el servidor, agregarla
ssh usuario@tu-servidor
cat ~/.ssh/github_actions_deploy.pub >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
```

#### 3. Verificar que la clave privada en GitHub Secrets sea correcta

1. Ve a GitHub → Tu Repositorio → Settings → Secrets and variables → Actions
2. Verifica que `SSH_PRIVATE_KEY` contenga:
   - La línea `-----BEGIN OPENSSH PRIVATE KEY-----` al inicio
   - La línea `-----END OPENSSH PRIVATE KEY-----` al final
   - Todo el contenido entre estas líneas (sin espacios extra al inicio/final)

**Formato correcto**:
```
-----BEGIN OPENSSH PRIVATE KEY-----
b3BlbnNzaC1rZXktdjEAAAAABG5vbmUAAAAEbm9uZQAAAAAAAAABAAAAMwAAAAtzc2gtZW
... (más líneas) ...
-----END OPENSSH PRIVATE KEY-----
```

#### 4. Verificar los permisos en el servidor

```bash
# En tu VPS, verificar permisos
ls -la ~/.ssh/

# Debe mostrar:
# drwx------ (700) para .ssh/
# -rw------- (600) para authorized_keys

# Si no, corregir:
chmod 700 ~/.ssh
chmod 600 ~/.ssh/authorized_keys
```

#### 5. Probar la conexión manualmente

Desde tu máquina local, prueba conectarte con la clave:

```bash
# Probar conexión SSH
ssh -i ruta/a/clave_privada usuario@tu-servidor

# Si funciona, el problema está en cómo GitHub Actions está usando la clave
# Si no funciona, el problema está en la configuración del servidor
```

#### 6. Verificar que el usuario SSH sea correcto

- Si usas `root`: `SSH_USER=root`
- Si usas otro usuario: `SSH_USER=nombre_usuario`

Verifica que el usuario tenga acceso SSH habilitado:

```bash
# En el servidor, verificar usuarios con acceso SSH
cat /etc/passwd | grep /bin/bash
```

#### 7. Verificar que SSH esté configurado correctamente

```bash
# En el servidor, verificar configuración SSH
sudo nano /etc/ssh/sshd_config

# Asegúrate de que estas líneas estén así:
# PubkeyAuthentication yes
# AuthorizedKeysFile .ssh/authorized_keys

# Reiniciar SSH (cuidado, no te desconectes)
sudo systemctl restart sshd
```

#### 8. Debug avanzado

Si nada funciona, agrega más información de debug al workflow temporalmente:

```yaml
- name: 🔍 Debug SSH
  run: |
    echo "Testing SSH connection..."
    ssh -v -i ~/.ssh/deploy_key -o StrictHostKeyChecking=no ${SSH_USER}@${SSH_HOST} "echo 'Connection successful'"
```

**Checklist de verificación**:
- [ ] La clave pública está en `~/.ssh/authorized_keys` del servidor
- [ ] Los permisos de `~/.ssh` son 700
- [ ] Los permisos de `authorized_keys` son 600
- [ ] La clave privada en GitHub Secrets tiene el formato correcto (con BEGIN/END)
- [ ] El usuario SSH (`SSH_USER`) es correcto
- [ ] El host (`SSH_HOST`) es correcto (IP o dominio)
- [ ] Puedes conectarte manualmente con la clave desde tu máquina local

### Error: "git: command not found"

**Solución**:
- Instala Git en el servidor: `sudo apt install git -y`

### Error: "composer: command not found"

**Solución**:
- Instala Composer en el servidor o usa la ruta completa
- Puedes modificar el workflow para usar: `/usr/local/bin/composer` o `php /usr/local/bin/composer.phar`

### Error: "migrate: command not found" o errores de permisos

**Solución**:
- Verifica que el usuario SSH tenga permisos para ejecutar `php artisan`
- Puede que necesites agregar el usuario al grupo `www-data`:
```bash
sudo usermod -a -G www-data tu-usuario
```

### El despliegue se ejecuta pero no hay cambios

**Solución**:
- Verifica que la rama en el workflow coincida con tu rama principal
- El workflow está configurado para `main` o `master`, ajusta si usas otra rama

## 🎯 Personalización del Workflow

Si necesitas personalizar el workflow, edita el archivo `.github/workflows/deploy.yml`:

### Cambiar la rama que activa el despliegue:
```yaml
on:
  push:
    branches:
      - develop  # Cambia aquí
```

### Agregar comandos adicionales:
```yaml
echo "🔧 Ejecutando comando personalizado..."
php artisan tu-comando-personalizado
```

### Desplegar solo en tags:
```yaml
on:
  push:
    tags:
      - 'v*'
```

## 📊 Monitoreo del Despliegue

- **Logs en tiempo real**: Ve a la pestaña **Actions** en GitHub
- **Logs en el servidor**: Revisa los logs de Laravel: `tail -f storage/logs/laravel.log`
- **Notificaciones**: Configura notificaciones de GitHub para recibir emails cuando el despliegue falle

## 🔒 Seguridad

- ✅ Nunca compartas tus secrets públicamente
- ✅ Usa claves SSH específicas para CI/CD (no tu clave personal)
- ✅ Limita el acceso SSH por IP si es posible
- ✅ Revisa regularmente los logs de acceso SSH

## ✅ Checklist de Configuración

- [ ] Repositorio clonado en el VPS
- [ ] Clave SSH generada y agregada a `authorized_keys`
- [ ] Secret `SSH_HOST` configurado en GitHub
- [ ] Secret `SSH_USER` configurado en GitHub
- [ ] Secret `SSH_PRIVATE_KEY` configurado en GitHub
- [ ] Secret `DEPLOY_PATH` configurado en GitHub
- [ ] Permisos de archivos correctos en el servidor
- [ ] Workflow probado con un push de prueba

---

**¿Necesitas ayuda?** Revisa los logs en la pestaña **Actions** de GitHub o los logs del servidor para más detalles sobre cualquier error.

