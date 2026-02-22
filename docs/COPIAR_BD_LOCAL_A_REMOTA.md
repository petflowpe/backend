# Copiar base de datos local a remota (VPS Hostinger)

Pasos para exportar tu BD local (Laragon) e importarla en la BD remota del VPS.

---

## Requisitos

- **Local:** Laragon con MySQL, BD `db_api_sunat` (o la que uses en `.env`).
- **Remoto:** Credenciales de la BD en Hostinger (usuario, contraseña, nombre de BD).
- **SSH:** Acceso al VPS (usuario y host que usas para deploy).

---

## Paso 1: Exportar la BD local (en tu PC)

Abre **PowerShell** o **CMD** y ejecuta (ajusta la ruta de Laragon si es distinta):

```powershell
cd C:\laragon\www\Proyecto2026\backend-grooming

# Ruta típica de mysqldump en Laragon (MySQL 8.4)
& "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqldump.exe" -u root db_api_sunat --single-transaction --routines --triggers > backup_local.sql
```

Si tu contraseña de MySQL no está vacía:

```powershell
& "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqldump.exe" -u root -p db_api_sunat --single-transaction --routines --triggers > backup_local.sql
```

Se creará el archivo `backup_local.sql` en `backend-grooming`.  
**Importante:** No subas este archivo a Git (debe estar en `.gitignore` si lo añades).

---

## Paso 2: Subir el archivo al VPS

Desde la misma carpeta del proyecto (donde está `backup_local.sql`):

```powershell
scp -i RUTA_A_TU_CLAVE_SSH backup_local.sql USUARIO@TU_VPS_HOST:/tmp/backup_local.sql
```

Ejemplo (si usas la clave del deploy de GitHub Actions):

```powershell
scp -i C:\Users\oscar\.ssh\deploy_key backup_local.sql root@srv1197160.hstgr.cloud:/tmp/backup_local.sql
```

Sustituye `USUARIO`, `TU_VPS_HOST` y la ruta de la clave por los que uses en Hostinger.

---

## Paso 3: Importar en la BD remota (dentro del VPS)

Conéctate por SSH al VPS:

```powershell
ssh -i RUTA_A_TU_CLAVE_SSH USUARIO@TU_VPS_HOST
```

Luego, **dentro del VPS**, importa el dump. Necesitas el **usuario**, **contraseña** y **nombre de la BD remota** (los tienes en el `.env` de producción en el servidor o en el panel de Hostinger):

```bash
cd /tmp
mysql -u USUARIO_BD_REMOTA -p NOMBRE_BD_REMOTA < backup_local.sql
```

Te pedirá la contraseña de MySQL. Si la BD remota tiene otro nombre que la local, no importa: el dump contiene las tablas; solo asegúrate de importar en la BD que usa tu Laravel en producción.

**Recomendación:** Antes de importar, haz un respaldo de la BD remota por si acaso:

```bash
mysqldump -u USUARIO_BD_REMOTA -p NOMBRE_BD_REMOTA > backup_remoto_antes_import_$(date +%Y%m%d).sql
```

---

## Resumen rápido

| Paso | Dónde   | Acción |
|------|---------|--------|
| 1    | Tu PC   | `mysqldump` → `backup_local.sql` |
| 2    | Tu PC   | `scp backup_local.sql` al VPS en `/tmp/` |
| 3    | VPS SSH | `mysql ... < /tmp/backup_local.sql` |

---

## Si el nombre de la BD remota es distinto al local

El archivo `.sql` no “crea” la base: solo tiene `CREATE TABLE` e `INSERT`. Tienes que importar **en** la base de datos que ya usa tu app en el VPS. Si en producción usas otra BD (por ejemplo `u123456_api`), crea esa BD si no existe y luego:

```bash
mysql -u USUARIO -p NOMBRE_BD_REMOTA < /tmp/backup_local.sql
```

Así los datos locales quedan copiados a la BD remota.
