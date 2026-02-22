# Exporta la BD local para luego importarla en el VPS.
# Uso: .\export-db.ps1
# Requiere: Laragon con MySQL. Ajusta MYSQL_BIN y DB_* si usas otros valores.

$MYSQL_BIN = "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqldump.exe"
$DB_NAME   = "db_api_sunat"
$DB_USER   = "root"
$OUT_FILE  = "backup_local.sql"

if (-not (Test-Path $MYSQL_BIN)) {
    Write-Host "No se encontró mysqldump en: $MYSQL_BIN" -ForegroundColor Red
    Write-Host "Ajusta MYSQL_BIN en este script si tu Laragon está en otra ruta." -ForegroundColor Yellow
    exit 1
}

Write-Host "Exportando BD '$DB_NAME' a $OUT_FILE ..." -ForegroundColor Cyan
# Usar cmd para que el archivo sea texto plano (UTF-8/ASCII). PowerShell > guarda UTF-16 y MySQL rechaza los \0.
$cmd = "`"$MYSQL_BIN`" -u $DB_USER $DB_NAME --single-transaction --routines --triggers > `"$OUT_FILE`" 2>&1"
cmd /c $cmd
if ($LASTEXITCODE -ne 0) {
    Write-Host "Error al exportar. Si tienes contraseña en MySQL, edita el script y añade -p" -ForegroundColor Red
    exit 1
}
Write-Host "Listo. Siguiente: sube $OUT_FILE al VPS e importa (ver docs/COPIAR_BD_LOCAL_A_REMOTA.md)" -ForegroundColor Green
