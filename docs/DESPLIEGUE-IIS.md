# Despliegue de Le Bleu en IIS (Windows Server / Lightsail)

Guía paso a paso para el servidor propio. Todo lo que se pueda verificar tiene un script: `scripts/verificar_servidor.php` (antes de abrir el sitio)
y `scripts/prueba_humo.php` (después). Si un paso falla, **no se sigue**: se corrige y se vuelve a correr el verificador.

Convención de rutas de esta guía: sitio en `C:\inetpub\lebleu` (el contenido de la carpeta `php/` del repositorio), PHP en `C:\php`,
respaldos en `C:\lebleu_respaldos`. Si usan otras, ajusten los `.bat` y los comandos.

## 1. Requisitos en el servidor
1. **IIS** con las funciones *CGI* y *Compresión de contenido dinámico*. (Administrador del servidor → Agregar roles → Servidor web.)
2. **URL Rewrite** de IIS (módulo oficial de Microsoft; sin él `web.config` no funciona y todas las rutas dan 404).
3. **PHP 8.3 x64 NTS** (Non Thread Safe, "VS16" o superior) desde windows.php.net, descomprimido en `C:\php`, más el *Visual C++ Redistributable* que pide su página.
4. **MySQL 8.0.16 o superior**. Confirmar con `SELECT VERSION();`. La app usa restricciones `CHECK`, columnas generadas y vistas con `SQL SECURITY INVOKER`.

## 2. PHP (`C:\php\php.ini`, copiar de `php.ini-production`)
```ini
extension_dir = "ext"
extension=pdo_mysql
extension=mbstring
extension=gd
extension=openssl
extension=zlib
extension=iconv
extension=ctype
extension=fileinfo
display_errors = Off
log_errors = On
error_log = "C:\php\logs\php_errors.log"
memory_limit = 256M
post_max_size = 8M
upload_max_filesize = 8M
max_execution_time = 120
date.timezone = "America/Mazatlan"
expose_php = Off
zend_extension=opcache
opcache.enable=1
```
Crear `C:\php\logs\` y darle permiso de **modificar** a `IIS_IUSRS`. Las fotos y los PDF se guardan en base64 en la base de datos: no se escribe nada en disco salvo la carpeta temporal.

## 3. IIS
1. **Controlador de PHP:** *Administrador de IIS → (servidor) → Asignaciones de controlador → Agregar asignación de módulo*: ruta `*.php`, módulo `FastCgiModule`, ejecutable `C:\php\php-cgi.exe`, nombre `PHP_FastCGI`.
2. **FastCGI:** *(servidor) → Configuración de FastCGI → php-cgi.exe → Propiedades*: `ActivityTimeout` = 120, `RequestTimeout` = 120, `InstanceMaxRequests` = 10000. (El reporte completo puede tardar unos segundos.)
3. **Grupo de aplicaciones** nuevo `LeBleu`: *Sin código administrado*, canalización *Integrada*, *Cargar perfil de usuario* = True.
4. **Sitio** nuevo `LeBleu`: ruta física **`C:\inetpub\lebleu\public`** (la carpeta `public`, nunca la raíz del proyecto), grupo `LeBleu`, enlace HTTP en el puerto 80 con el nombre de host `app.lebleu.mx`.
5. **Permisos NTFS:** `IIS_IUSRS` solo **lectura y ejecución** sobre `C:\inetpub\lebleu`. Nada de escritura. `config\config.php` solo lectura para `IIS_IUSRS` y `Administradores`.
6. `public\web.config` ya trae la reescritura a `index.php`, los límites de subida (8 MB), los segmentos ocultos (`config`, `app`, `vendor`, `db`, `scripts`), las cabeceras y el paso de errores de la app.

## 4. Base de datos
En MySQL Workbench, como administrador:
```sql
CREATE DATABASE lebleu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'lebleu'@'localhost' IDENTIFIED BY 'UNA-CLAVE-LARGA-Y-UNICA';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES, CREATE VIEW, SHOW VIEW, LOCK TABLES ON lebleu.* TO 'lebleu'@'localhost';
```
En `my.ini` (sección `[mysqld]`): `max_allowed_packet=16M` (los PDF van en base64) y `bind-address=127.0.0.1`. **El puerto 3306 no se abre en el firewall.** Reiniciar MySQL.

## 5. Aplicación
```bat
cd C:\inetpub\lebleu
copy config\config.ejemplo.php config\config.php
notepad config\config.php         :: contraseña real del usuario `lebleu`; zona_horaria America/Mazatlan; base_url ''
C:\php\php.exe scripts\migrar.php
C:\php\php.exe scripts\verificar_servidor.php
```
El verificador debe terminar en `Sin fallas` o `Servidor listo`. (Un aviso por MariaDB no aplica en producción; si sale, el MySQL no es el correcto.)

Datos y usuarios: ver **docs/CORTE.md** (importar el respaldo y restablecer contraseñas). Sin respaldo, para un sitio vacío:
`set ADMIN_EMAIL=... & set ADMIN_PW=... & C:\php\php.exe scripts\crear_admin.php`.

## 6. HTTPS y DNS
1. **Lightsail → Redes → Firewall de IPv4:** abrir 80 y 443 (y 3389 solo a su IP). No abrir 3306.
2. **DNS en Hostinger:** registro `A` de `app.lebleu.mx` hacia la IP estática del Lightsail. **Bajar el TTL a 300 s un día antes del corte** (así una reversa tarda minutos).
3. **Certificado:** con *win-acme* (Let's Encrypt, `wacs.exe`): elegir el sitio `LeBleu`, acepta el desafío HTTP-01 por el puerto 80 y renueva solo con una tarea programada. Con un certificado de la empresa: importarlo y crear el enlace HTTPS 443 del sitio.
4. **Activar la redirección:** en `public\web.config`, regla `HTTP a HTTPS`, cambiar `enabled="false"` por `enabled="true"`.
5. Las cabeceras de seguridad (HSTS incluida) las envía la app solo cuando la petición llega por HTTPS.

## 7. Tareas programadas
Editar la ruta de `php.exe` y la carpeta del sitio dentro de `herramientas\cron_lebleu.bat`, y las 4 variables de `herramientas\respaldo_lebleu.bat`
(ver `herramientas\LEEME-respaldos.txt`: usuario de solo lectura y `mysql.cnf`). Luego, **como Administrador**:
```bat
herramientas\instalar_tareas.bat C:\inetpub\lebleu
schtasks /Run /TN "LeBleu-Recurrentes"
schtasks /Run /TN "LeBleu-Respaldo"
```
Revisar `cron.log` (debe decir `{"ok":true,...}`) y `C:\lebleu_respaldos\respaldo.log` (línea `OK`).
Después, el **ensayo de restauración** (obligatorio y mensual):
```bat
C:\php\php.exe scripts\ensayo_restauracion.php --admin=C:\lebleu_respaldos\admin.cnf --mysqldump="C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqldump.exe" --mysql="C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe"
```
(`admin.cnf`: `[client]`, `user=root`, `password=...`, con permisos solo para Administradores.) Debe terminar en `RESTAURACIÓN OK`.
Segunda copia de los respaldos fuera del disco del servidor y, además, instantáneas automáticas de Lightsail.

## 8. Prueba de humo (con el sitio ya publicado)
```bat
C:\php\php.exe scripts\prueba_humo.php https://app.lebleu.mx correo-admin clave-admin --http=http://app.lebleu.mx --capitan=correo-capitan:clave
```
Verifica salud, archivos estáticos y su tipo MIME, que ni `config.php` ni el código ni el `.git` se descargan, cabeceras, cookie `HttpOnly/SameSite/Secure`,
las 11 pantallas del administrador, los PDF, lo que el capitán sí y no puede ver, la redirección a HTTPS y que cerrar sesión invalida la cookie.
No cambia datos. Debe terminar en `PRUEBA DE HUMO OK`.

## 9. Operación
- **Actualizar la app:** copiar los archivos nuevos (sin pisar `config\config.php`), correr `php scripts\migrar.php` (solo aplica lo nuevo) y la prueba de humo.
- **Errores:** `C:\php\logs\php_errors.log`. Cada operación importante queda además en *Administración → Auditoría*.
- **Contraseña olvidada:** el administrador la restablece en *Usuarios*; si es la del administrador, `php scripts\restablecer_claves.php`.
