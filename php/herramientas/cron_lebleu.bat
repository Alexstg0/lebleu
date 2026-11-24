@echo off
REM Tarea diaria de Le Bleu: crea el periodo del mes y genera nómina y cargo administrativo.
REM Programar con el Programador de tareas de Windows (ver LEEME-tarea-programada.txt).
setlocal
set PHP="C:\xampp\php\php.exe"
set SITIO=C:\inetpub\wwwroot\lebleu
set LOG=%SITIO%\cron.log

cd /d "%SITIO%"
%PHP% scripts\cron_recurrentes.php >> "%LOG%" 2>&1
if errorlevel 1 echo [%date% %time%] ERROR, revisar el mensaje anterior >> "%LOG%"
endlocal
