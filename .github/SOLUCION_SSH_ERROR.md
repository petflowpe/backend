# 🔧 Solución Rápida: Error "Permission denied (publickey)"

## ⚠️ Problema Común

El error "Permission denied (publickey, password)" generalmente se debe a:

1. **La clave SSH tiene una passphrase** (contraseña) - GitHub Actions NO puede ingresar contraseñas
2. La clave privada en GitHub Secrets no está completa o tiene formato incorrecto
3. Problemas de permisos en el servidor

**⚠️ IMPORTANTE**: Para GitHub Actions, la clave SSH debe generarse **SIN passphrase** (sin contraseña).

## ✅ Solución Paso a Paso

### Paso 1: Regenerar la clave SSH SIN passphrase

**Si ya generaste la clave con passphrase, debes regenerarla sin contraseña.**

Conéctate a tu VPS y ejecuta:

```bash
# Eliminar la clave anterior (si existe)
rm -f ~/.ssh/github_actions_deploy*

# Generar nueva clave SIN passphrase (presiona ENTER cuando pida la contraseña)
ssh-keygen -t ed25519 -C "github-actions-deploy" -f ~/.ssh/github_actions_deploy -N ""

# Verificar que se generó correctamente
ls -la ~/.ssh/github_actions_deploy*

# Agregar la clave pública a authorized_keys
cat ~/.ssh/github_actions_deploy.pub >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
chmod 700 ~/.ssh

# Ver la clave privada completa (cópiala)
cat ~/.ssh/github_actions_deploy
```

**Nota**: El parámetro `-N ""` significa "sin passphrase" (contraseña vacía). Esto es necesario para GitHub Actions.

**⚠️ IMPORTANTE**: Debes copiar TODO el contenido, incluyendo:
- `-----BEGIN OPENSSH PRIVATE KEY-----` (al inicio)
- Todo el contenido en el medio
- `-----END OPENSSH PRIVATE KEY-----` (al final)

### Paso 2: Verificar el formato de la clave

La clave privada debe verse así (ejemplo):

```
-----BEGIN OPENSSH PRIVATE KEY-----
b3BlbnNzaC1rZXktdjEAAAAACmFlczI1NiljdHIAAAAGYmNyeXBOAAAAGAAAABD7kPsWsP
znAnVQ0Usnbw3RAAAAGAAAAAEAAAAAAAAC3NzaC1lZDI1NTE5AAAAIOHFDb1oEogA+F1k
733+dl0eb0Cc//00/uKaR/GNb1KNAAAAoMkN73mgZRkeANgXnnaCrhU5YEHXWqF7SCdXqS
... (más líneas) ...
-----END OPENSSH PRIVATE KEY-----
```

### Paso 3: Actualizar el Secret en GitHub

1. Ve a tu repositorio en GitHub
2. **Settings** → **Secrets and variables** → **Actions**
3. Haz clic en el secret `SSH_PRIVATE_KEY` (icono de lápiz para editar)
4. **Borra todo el contenido actual**
5. Pega la clave privada completa que copiaste del servidor
6. **Asegúrate de que:**
   - No haya espacios al inicio o al final
   - No haya líneas en blanco antes de `-----BEGIN`
   - No haya líneas en blanco después de `-----END`
   - Los saltos de línea estén preservados
7. Haz clic en **"Update secret"**

### Paso 4: Verificar que la clave pública esté en el servidor

En tu VPS, ejecuta:

```bash
# Ver las claves autorizadas
cat ~/.ssh/authorized_keys

# Debe mostrar tu clave pública (la que empieza con ssh-ed25519)
# Si no está, agrégala:
cat ~/.ssh/github_actions_deploy.pub >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
```

### Paso 5: Verificar que las claves coincidan

En el servidor, verifica que la clave pública y privada sean del mismo par:

```bash
# Extraer la clave pública de la privada
ssh-keygen -y -f ~/.ssh/github_actions_deploy

# Comparar con la que está en authorized_keys
cat ~/.ssh/authorized_keys | grep github-actions-deploy
```

Ambas deben mostrar la misma clave pública.

### Paso 6: Verificar permisos en el servidor

```bash
# Verificar permisos
ls -la ~/.ssh/

# Debe mostrar:
# drwx------ (700) para .ssh/
# -rw------- (600) para authorized_keys
# -rw------- (600) para github_actions_deploy

# Si no, corregir:
chmod 700 ~/.ssh
chmod 600 ~/.ssh/authorized_keys
chmod 600 ~/.ssh/github_actions_deploy
```

### Paso 7: Probar la conexión manualmente

Desde tu máquina local (si tienes la clave privada):

```bash
# Probar conexión
ssh -i ruta/a/github_actions_deploy root@tu-servidor-ip

# Si funciona, el problema está en cómo GitHub Actions está usando la clave
# Si no funciona, hay un problema en el servidor
```

## 🔍 Verificación de Otros Secrets

Asegúrate de que estos secrets estén correctos:

- **SSH_HOST**: La IP o dominio de tu VPS
  - Ejemplo: `srv1197160.hostinger.com` o `123.456.789.0`
  
- **SSH_USER**: El usuario SSH
  - Ejemplo: `root`

- **DEPLOY_PATH**: La ruta completa del proyecto
  - Ejemplo: `/var/www/facturacion/backend-grooming`

## 🐛 Debug Avanzado

Si después de seguir estos pasos aún no funciona, el workflow mejorado mostrará más información:

1. Verifica el tamaño de la clave (debe ser > 100 bytes)
2. Verifica que tenga BEGIN y END
3. Muestra información detallada del error SSH

Revisa los logs en GitHub Actions para ver estos detalles.

## ✅ Checklist Final

- [ ] La clave privada en GitHub Secrets tiene BEGIN y END
- [ ] La clave privada está completa (no truncada)
- [ ] La clave pública está en `~/.ssh/authorized_keys` del servidor
- [ ] Los permisos del servidor son correctos (700/600)
- [ ] SSH_HOST es correcto (IP o dominio)
- [ ] SSH_USER es correcto (probablemente `root`)
- [ ] DEPLOY_PATH es correcto (ruta completa)

## 💡 Regenerar Clave SSH (Solución Completa)

Si tu clave tiene passphrase o quieres empezar de cero:

```bash
# En el servidor - PASO A PASO

# 1. Eliminar claves anteriores
rm -f ~/.ssh/github_actions_deploy*

# 2. Generar nueva clave SIN passphrase
# Cuando pregunte "Enter passphrase", simplemente presiona ENTER (déjalo vacío)
# Cuando pregunte "Enter same passphrase again", presiona ENTER de nuevo
ssh-keygen -t ed25519 -C "github-actions-deploy" -f ~/.ssh/github_actions_deploy

# O mejor aún, usa el parámetro -N "" para no pedir passphrase:
ssh-keygen -t ed25519 -C "github-actions-deploy" -f ~/.ssh/github_actions_deploy -N ""

# 3. Agregar clave pública a authorized_keys
cat ~/.ssh/github_actions_deploy.pub >> ~/.ssh/authorized_keys

# 4. Configurar permisos correctos
chmod 700 ~/.ssh
chmod 600 ~/.ssh/authorized_keys
chmod 600 ~/.ssh/github_actions_deploy

# 5. Verificar que la clave pública se agregó
cat ~/.ssh/authorized_keys | grep github-actions-deploy

# 6. Copiar la clave PRIVADA (necesitarás esto para GitHub)
cat ~/.ssh/github_actions_deploy
```

Luego actualiza el secret `SSH_PRIVATE_KEY` en GitHub con la nueva clave privada (sin passphrase).

