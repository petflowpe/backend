@echo off
setlocal EnableExtensions EnableDelayedExpansion

title Backend Laravel - ClonSmarpet

cd /d "%~dp0backend"
echo.
echo Iniciando backend en http://localhost:8000
echo No cierres esta ventana mientras uses la aplicacion.
echo Para comprobar: http://localhost:8000/api/system/info
echo.

rem 1) PHP desde PATH
where php >nul 2>nul
if %errorlevel%==0 (
  php artisan serve
  exit /b %errorlevel%
)

rem 2) Detectar Laragon + PHP
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
  echo ERROR: No se encontro php.exe.
  echo Abre Laragon ^> Menu ^> PHP ^> Version.
  pause
  exit /b 1
)

:RUN
"%PHP_EXE%" artisan serve
exit /b %errorlevel%

