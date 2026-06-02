@echo off
setlocal EnableExtensions EnableDelayedExpansion

title Piloto Smoke (API) - PetFlow

set "ROOT=%~dp0"
set "LOG_FILE=%ROOT%pilot-setup.log"

if not exist "%LOG_FILE%" (
  echo ERROR: No existe "%LOG_FILE%". Primero ejecuta PILOTO-SETUP.bat
  pause
  exit /b 1
)

set "TOKEN="
for /f "usebackq delims=" %%L in (`powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$p='%LOG_FILE%';" ^
  "$line=(Get-Content -Path $p -ErrorAction Stop | Select-String -Pattern '^PILOT_BEARER_TOKEN=' | Select-Object -Last 1).Line;" ^
  "$prefix='PILOT_BEARER_TOKEN='; if($line -and $line.StartsWith($prefix)){ $line.Substring($prefix.Length) }"`) do (
  set "TOKEN=%%L"
)

if not defined TOKEN (
  echo ERROR: No pude leer el token desde el log.
  echo Asegurate de haber ejecutado PILOTO-SETUP.bat con la version nueva.
  pause
  exit /b 1
)

rem IMPORTANTE: el token contiene '|' y CMD lo trata como pipe si se incrusta en la linea.
rem Lo pasamos por variable de entorno a PowerShell para evitar escapes.
set "PILOT_TOKEN=%TOKEN%"

echo Token OK (desde pilot-setup.log).
echo.

echo 1) GET /api/system/info
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$r=Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/system/info' -Method GET; $r | ConvertTo-Json -Depth 5"

echo.
echo 2) GET /api/v2/config/masters (Bearer)
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$t=$env:PILOT_TOKEN; $h=@{ Authorization=('Bearer ' + $t); Accept='application/json' };" ^
  "$r=Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/v2/config/masters' -Method GET -Headers $h;" ^
  "$r.success"

echo.
echo 3) GET /api/v2/clients?perPage=5 (Bearer)
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$t=$env:PILOT_TOKEN; $h=@{ Authorization=('Bearer ' + $t); Accept='application/json' };" ^
  "$r=Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/v2/clients?perPage=5' -Method GET -Headers $h;" ^
  "$r.meta"

echo.
echo Smoke OK si no hubo errores arriba.
pause

