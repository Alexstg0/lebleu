@echo off
REM Registra en el Programador de tareas de Windows las dos tareas de Le Bleu:
REM   - LeBleu-Recurrentes : todos los dias 07:00, crea el periodo del mes y genera nomina y cargo administrativo.
REM   - LeBleu-Respaldo    : todos los dias 02:00, respalda la base de datos.
REM Ejecutar UNA VEZ como Administrador, despues de editar las rutas dentro de cron_lebleu.bat y respaldo_lebleu.bat.
REM Uso:  instalar_tareas.bat C:\inetpub\lebleu
setlocal
if "%~1"=="" (
  echo Uso: instalar_tareas.bat CARPETA_DEL_SITIO   ^(ejemplo: C:\inetpub\lebleu^)
  exit /b 1
)
set SITIO=%~1
if not exist "%SITIO%\herramientas\cron_lebleu.bat" (
  echo No se encontro "%SITIO%\herramientas\cron_lebleu.bat". Revise la carpeta.
  exit /b 1
)
schtasks /Create /F /TN "LeBleu-Recurrentes" /SC DAILY /ST 07:00 /RU SYSTEM /RL HIGHEST /TR "\"%SITIO%\herramientas\cron_lebleu.bat\""
if errorlevel 1 exit /b 1
schtasks /Create /F /TN "LeBleu-Respaldo" /SC DAILY /ST 02:00 /RU SYSTEM /RL HIGHEST /TR "\"%SITIO%\herramientas\respaldo_lebleu.bat\""
if errorlevel 1 exit /b 1
echo.
echo Tareas registradas. Para probarlas ahora:
echo   schtasks /Run /TN "LeBleu-Recurrentes"   y revise %SITIO%\cron.log
echo   schtasks /Run /TN "LeBleu-Respaldo"      y revise C:\lebleu_respaldos\respaldo.log
echo Para ver su estado:  schtasks /Query /TN "LeBleu-Recurrentes" /V /FO LIST
endlocal
