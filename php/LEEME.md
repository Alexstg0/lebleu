# Le Bleu · versión PHP + MySQL

Reemplazo de la app Next.js (ver `../docs/MIGRACION-PHP.md`). Misma funcionalidad e interfaz, estructura por módulos.

## Correr en local (XAMPP o PHP en la línea de comandos)
1. Crear la base: `CREATE DATABASE lebleu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;` y un usuario con permisos sobre ella.
2. Copiar `config/config.ejemplo.php` a `config/config.php` y llenar la conexión.
3. `php scripts/migrar.php` (crea tablas y vistas; re-ejecutable).
4. Datos: `php scripts/importar_respaldo.php ../db/datos_20260826.sql` y luego `php scripts/restablecer_claves.php`
   (**usar `datos_20260826.sql`; el `_clean.sql` está corrupto**). Base nueva sin datos: `ADMIN_EMAIL=... ADMIN_PW=... php scripts/crear_admin.php`.
5. `php -S 127.0.0.1:8080 -t public public/index.php` y abrir http://127.0.0.1:8080/api/salud

En IIS el sitio apunta a `public/` (lleva su `web.config`; requiere URL Rewrite).

## Estructura
`public/index.php` único punto de entrada · `app/Core` infraestructura · `app/Compartido` piezas comunes ·
`app/Modulos/<Modulo>` Controller → Servicio → Repositorio (solo el Repositorio escribe SQL) ·
`app/Herramientas` importador del respaldo · `db/migraciones` SQL numerado y portable · `pruebas/` · `herramientas/` respaldos.

## Pruebas
- `php pruebas/tamano_de_archivos.php` — ningún archivo pasa de 400 líneas.
- `php pruebas/paridad_postgres.php` — compara MySQL contra una base Postgres de referencia con los mismos datos
  (conteos, fechas, sumas y las 3 vistas del estado de cuenta). Variables `PG_DSN`, `PG_USER`, `PG_PASS`.
