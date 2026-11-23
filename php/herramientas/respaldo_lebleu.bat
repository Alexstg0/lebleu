@echo off
REM Respaldo diario de la base de Le Bleu (MySQL). Se programa con el Programador de tareas de Windows.
REM Requisitos: editar las 4 variables de abajo y crear C:\lebleu_respaldos\mysql.cnf (ver LEEME-respaldos.txt).
setlocal

set MYSQLDUMP="C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqldump.exe"
set BASE=lebleu
set DESTINO=C:\lebleu_respaldos
set DIAS_A_CONSERVAR=14

if not exist "%DESTINO%" mkdir "%DESTINO%"

for /f %%i in ('powershell -NoProfile -Command "Get-Date -Format yyyy-MM-dd_HHmm"') do set FECHA=%%i
set ARCHIVO=%DESTINO%\lebleu_%FECHA%.sql

REM Las credenciales van en un archivo de opciones, nunca en este .bat ni en la línea de comandos.
%MYSQLDUMP% --defaults-extra-file="%DESTINO%\mysql.cnf" --single-transaction --routines --triggers --default-character-set=utf8mb4 --result-file="%ARCHIVO%" %BASE%
if errorlevel 1 (
  echo [%date% %time%] ERROR en el respaldo >> "%DESTINO%\respaldo.log"
  del "%ARCHIVO%" 2>nul
  exit /b 1
)

powershell -NoProfile -Command "Compress-Archive -Path '%ARCHIVO%' -DestinationPath '%ARCHIVO%.zip' -Force; Remove-Item '%ARCHIVO%'"
if errorlevel 1 (
  echo [%date% %time%] ERROR al comprimir %ARCHIVO% >> "%DESTINO%\respaldo.log"
  exit /b 1
)

REM Borra los respaldos con más de N días.
forfiles /p "%DESTINO%" /m lebleu_*.sql.zip /d -%DIAS_A_CONSERVAR% /c "cmd /c del @path" 2>nul

echo [%date% %time%] OK %ARCHIVO%.zip >> "%DESTINO%\respaldo.log"
endlocal
