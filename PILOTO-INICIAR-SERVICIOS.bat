@echo off
setlocal EnableExtensions

title Iniciar servicios (Piloto) - PetFlow

echo.
echo Abriendo ventanas: API, Queue, Scheduler...
echo.

start "API - Laravel" "%~dp0INICIAR-BACKEND.bat"
start "Queue - Laravel" "%~dp0INICIAR-QUEUE.bat"
start "Scheduler - Laravel" "%~dp0INICIAR-SCHEDULER.bat"

echo Listo.
exit /b 0

