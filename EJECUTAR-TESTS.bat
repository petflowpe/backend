@echo off
setlocal EnableExtensions EnableDelayedExpansion

title Tests Backend (Laravel) - PetFlow

set "ROOT=%~dp0"
set "BACKEND=%ROOT%backend"
set "LOG_FILE=%ROOT%tests-run.log"

echo [%date% %time%] START > "%LOG_FILE%"

if not exist "%BACKEND%\artisan" (
  echo ERROR: No se encontro "%BACKEND%\artisan"
  echo ERROR: artisan no encontrado.>> "%LOG_FILE%"
  start "" notepad "%LOG_FILE%"
  pause
  exit /b 1
)

cd /d "%BACKEND%"

echo.
echo ==========================================================
echo  Ejecutando tests del backend (Laravel / Pest)
echo  Log: "%LOG_FILE%"
echo ==========================================================
echo.

rem 1) PHP desde PATH
set "PHP_EXE="
where php >nul 2>nul
if %errorlevel%==0 (
  set "PHP_EXE=php"
  echo PHP: PATH>> "%LOG_FILE%"
  goto RUN
)

rem 2) Detectar Laragon
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
  echo LARAGON_ROOT: "!LARAGON_ROOT!">> "%LOG_FILE%"
  set "PHP_DIR=!LARAGON_ROOT!bin\php"
  echo PHP_DIR: "!PHP_DIR!">> "%LOG_FILE%"

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
  echo ERROR: php.exe no detectado.>> "%LOG_FILE%"
  echo.
  echo ERROR: No se pudo detectar php.exe.
  echo Revisa Laragon ^> Menu ^> PHP ^> Version.
  start "" notepad "%LOG_FILE%"
  pause
  exit /b 1
)

:RUN
echo Usando PHP: "%PHP_EXE%"
echo PHP_EXE: "%PHP_EXE%">> "%LOG_FILE%"

rem Asegurar que "php" exista para scripts de composer (cuando PHP_EXE es ruta completa)
if not "%PHP_EXE%"=="php" (
  for %%I in ("%PHP_EXE%") do set "PHP_BIN=%%~dpI"
  set "PATH=!PHP_BIN!;!PATH!"
  echo PATH+PHP_BIN: "!PHP_BIN!">> "%LOG_FILE%"
)

echo.
echo Instalando dependencias (composer) si aplica...
where composer >nul 2>nul
if %errorlevel%==0 (
  call composer install >> "%LOG_FILE%" 2>&1
) else (
  if defined LARAGON_ROOT if exist "!LARAGON_ROOT!bin\composer\composer.bat" (
    call "!LARAGON_ROOT!bin\composer\composer.bat" install >> "%LOG_FILE%" 2>&1
  ) else (
    echo composer no encontrado.>> "%LOG_FILE%"
  )
)

echo.
echo Corriendo: php artisan test
echo.

if /i "%PHP_EXE%"=="php" (
  php artisan test >> "%LOG_FILE%" 2>&1
) else (
  "%PHP_EXE%" artisan test >> "%LOG_FILE%" 2>&1
)

type "%LOG_FILE%"
start "" notepad "%LOG_FILE%"
echo.
echo Fin.
pause

