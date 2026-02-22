# 🔑 Configurar SSH en el Servidor para GitHub

Para que el despliegue automático funcione, el servidor necesita tener una clave SSH configurada para acceder a GitHub.

## 📋 Pasos Rápidos

### 1. Conéctate a tu VPS

```bash
ssh root@tu-servidor-ip
```

### 2. Genera una clave SSH para GitHub

```bash
# Generar clave SSH sin passphrase (necesario para despliegues automáticos)
ssh-keygen -t ed25519 -C "servidor-vps@github" -f ~/.ssh/github_server -N ""

# Ver la clave pública (cópiala completa)
cat ~/.ssh/github_server.pub
```

### 3. Agrega la clave a GitHub

1. Ve a GitHub: https://github.com/settings/keys
2. Haz clic en **"New SSH key"**
3. **Title**: `Servidor VPS Hostinger` (o el nombre que prefieras)
4. **Key**: Pega la clave pública que copiaste (la que empieza con `ssh-ed25519`)
5. Haz clic en **"Add SSH key"**

### 4. Prueba la conexión

En tu servidor, ejecuta:

```bash
ssh -T git@github.com
```

Deberías ver un mensaje como:
```
Hi oscarcalle! You've successfully authenticated, but GitHub does not provide shell access.
```

Si ves este mensaje, ¡está funcionando! ✅

### 5. Configurar el remote en el repositorio (si es necesario)

Si el repositorio en el servidor aún tiene HTTPS, cámbialo a SSH:

```bash
cd /var/www/facturacion/backend-grooming
git remote set-url origin git@github.com:oscarcalle/backend-grooming.git
git remote -v  # Verificar
```

## ✅ Verificación Final

```bash
# Verificar que el remote está en SSH
cd /var/www/facturacion/backend-grooming
git remote -v

# Debe mostrar:
# origin  git@github.com:oscarcalle/backend-grooming.git (fetch)
# origin  git@github.com:oscarcalle/backend-grooming.git (push)

# Probar fetch
git fetch origin
```

Si el `git fetch` funciona sin pedir credenciales, ¡todo está listo! 🎉

## 🔄 Probar el Despliegue

Una vez configurado, haz un push o re-ejecuta el workflow en GitHub Actions. Debería funcionar correctamente.

## ❓ Solución de Problemas

### Error: "Permission denied (publickey)"

- Verifica que la clave pública esté en GitHub
- Verifica que la clave privada esté en `~/.ssh/github_server` en el servidor
- Prueba: `ssh -vT git@github.com` para ver más detalles

### Error: "Host key verification failed"

```bash
# Agregar GitHub a known_hosts
ssh-keyscan github.com >> ~/.ssh/known_hosts
```

### El remote sigue siendo HTTPS

```bash
# Forzar cambio a SSH
cd /var/www/facturacion/backend-grooming
git remote set-url origin git@github.com:oscarcalle/backend-grooming.git
```

---

**Una vez completado esto, el despliegue automático debería funcionar perfectamente.** 🚀

