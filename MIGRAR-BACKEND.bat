@echo off
setlocal EnableExtensions EnableDelayedExpansion

title Migrar Backend (Laravel) - ClonSmarpet

set "ROOT=%~dp0"
set "BACKEND=%ROOT%backend"

if not exist "%BACKEND%\artisan" (
  echo ERROR: No se encontro "%BACKEND%\artisan"
  echo Asegurate de ejecutar este .bat dentro de la carpeta ClonSmarpet.
  pause
  exit /b 1
)

cd /d "%BACKEND%"

echo.
echo Ejecutando migraciones en: "%CD%"
echo.

rem 1) PHP desde PATH
where php >nul 2>nul
if %errorlevel%==0 (
  echo Usando PHP: php
  php artisan config:clear
  php artisan migrate
  echo.
  echo Fin.
  pause
  exit /b 0
)

rem 2) Detectar Laragon + PHP (misma estrategia que INICIAR-BACKEND.bat)
set "PHP_EXE="
set "LARAGON_ROOT="

for %%D in ("C:\laragon" "C:\Laragon" "C:\Program Files\Laragon" "C:\Program Files (x86)\Laragon") do (
  if exist "%%~fD\laragon.exe" (
    set "LARAGON_ROOT=%%~fD\"
    goto FIND_PHP_LARAGON
  )
)

set "LARAGON_START=%ProgramData%\Microsoft\Windows\Start Menu\Programs\Laragon"
set "LARAGON_LNK="
if exist "%LARAGON_START%" (
  for /r "%LARAGON_START%" %%L in (*.lnk) do (
    set "LARAGON_LNK=%%~fL"
    goto READ_LNK
  )
)

:READ_LNK
if defined LARAGON_LNK (
  where powershell >nul 2>nul
  if %errorlevel%==0 (
    for /f "usebackq delims=" %%T in (`powershell -NoProfile -ExecutionPolicy Bypass -Command "(New-Object -ComObject WScript.Shell).CreateShortcut('%LARAGON_LNK%').TargetPath"`) do (
      set "LARAGON_EXE=%%T"
    )
    if defined LARAGON_EXE (
      for %%I in ("!LARAGON_EXE!") do set "LARAGON_ROOT=%%~dpI"
    )
  )
)

:FIND_PHP_LARAGON
if defined LARAGON_ROOT (
  set "PHP_DIR=!LARAGON_ROOT!bin\php"
  if exist "!PHP_DIR!" (
    if exist "!PHP_DIR!\php.exe" (
      set "PHP_EXE=!PHP_DIR!\php.exe"
      goto RUN
    )
    for /f "delims=" %%F in ('dir /b /ad /o-n "!PHP_DIR!\php-*" 2^>nul') do (
      if exist "!PHP_DIR!\%%F\php.exe" (
        set "PHP_EXE=!PHP_DIR!\%%F\php.exe"
        goto RUN
      )
    )
    for /r "!PHP_DIR!" %%P in (php.exe) do (
      set "PHP_EXE=%%~fP"
      goto RUN
    )
  )
)

if not defined PHP_EXE (
  echo ERROR: "php" no esta en PATH y no se detecto Laragon.
  echo Solucion rapida: instala/activa PHP o agrega php.exe al PATH.
  pause
  exit /b 1
)

:RUN
echo Usando PHP: "%PHP_EXE%"

rem Asegurar que "php" exista para scripts de composer (cuando PHP_EXE es ruta completa)
for %%I in ("%PHP_EXE%") do set "PHP_BIN=%%~dpI"
set "PATH=!PHP_BIN!;!PATH!"

"%PHP_EXE%" artisan config:clear
"%PHP_EXE%" artisan migrate

echo.
echo Fin.
pause
exit /b 0

