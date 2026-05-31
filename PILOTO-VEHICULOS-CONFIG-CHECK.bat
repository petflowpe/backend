@echo off
setlocal EnableExtensions EnableDelayedExpansion

title Check Config Vehiculos (API) - PetFlow

rem Si el usuario hace doble click, mantener la ventana abierta siempre.
if /i not "%~1"=="__RUN" (
  cmd /k ""%~f0" __RUN"
  exit /b 0
)

set "ROOT=%~dp0"
set "LOG_FILE=%ROOT%pilot-setup.log"
set "PS1=%ROOT%PILOTO-VEHICULOS-CONFIG-CHECK.ps1"

if not exist "%LOG_FILE%" (
  echo ERROR: No existe "%LOG_FILE%". Primero ejecuta PILOTO-SETUP.bat
  pause
  exit /b 1
)

if not exist "%PS1%" (
  echo ERROR: No existe "%PS1%".
  pause
  exit /b 1
)

echo.
echo Ejecutando verificacion via PowerShell...
powershell -NoProfile -ExecutionPolicy Bypass -File "%PS1%" -LogFile "%LOG_FILE%" -ApiBase "http://127.0.0.1:8000/api"

echo.
echo Si en el paso 4 no aparecen, NO esta persistiendo en ese backend/BD.
pause
exit /b 0

