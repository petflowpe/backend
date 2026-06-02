@echo off
setlocal EnableExtensions

title Iniciar Frontend (React/Vite) - PetFlow

set "ROOT=%~dp0"
set "FRONTEND=%ROOT%frontend"

if not exist "%FRONTEND%\package.json" (
  echo ERROR: No se encontro "%FRONTEND%\package.json"
  echo Asegurate de ejecutar este .bat desde la raiz del repo.
  pause
  exit /b 1
)

cd /d "%FRONTEND%"

where npm >nul 2>nul
if %errorlevel% neq 0 (
  echo ERROR: npm no esta en PATH.
  echo Instala Node.js LTS y vuelve a intentar.
  pause
  exit /b 1
)

echo.
echo Iniciando Frontend...
echo URL: http://localhost:3000
echo.

call npm install
call npm run dev

