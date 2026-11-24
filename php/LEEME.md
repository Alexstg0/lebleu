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

## Tarea diaria
`scripts/cron_recurrentes.php` (crea el periodo del mes y genera nómina y cargo administrativo). Se programa con el Programador de tareas de Windows: ver `herramientas/LEEME-tarea-programada.txt`.

## Estructura
`public/index.php` único punto de entrada · `app/Core` infraestructura · `app/Compartido` piezas comunes ·
`app/Modulos/<Modulo>` Controller → Servicio → Repositorio (solo el Repositorio escribe SQL) ·
`app/Herramientas` importador del respaldo · `db/migraciones` SQL numerado y portable · `pruebas/` · `herramientas/` respaldos.

## Pruebas
- `bash pruebas/correr_todas.sh` corre todas las de abajo que no necesitan navegador (con `PG_URL=postgres://...` incluye el cuadre).
- `php pruebas/tamano_de_archivos.php` — ningún archivo pasa de 400 líneas.
- `php pruebas/capas.php` — el SQL vive solo en los Repositorios (Controllers y Servicios no llaman a `Db::`).
- `php pruebas/periodos.php` — crear/cerrar/reabrir periodos y arrastre de saldos; se revierte solo.
- `php pruebas/captura.php` — los módulos de captura completos (permisos, validación, periodo cerrado, cálculos, auditoría); se revierte solo.
- `php pruebas/fase4.php` — reservas, caja chica, usuarios, adjuntos (incluye fotos a PDF), recurrentes y auditoría; se revierte solo.
- `php pruebas/paridad_postgres.php` — compara MySQL contra una base Postgres de referencia con los mismos datos
  (conteos, fechas, sumas y las 3 vistas). Variables `PG_DSN`, `PG_USER`, `PG_PASS`.
- `bash pruebas/paridad_estado_cuenta.sh` — el estado de cuenta de cada periodo contra la lógica ORIGINAL de Next.js
  (necesita Node 22 y `npm install` en la raíz; variable `PG_URL`).
- `bash pruebas/paridad_recurrentes.sh` — los cargos recurrentes contra la lógica ORIGINAL en tres fechas de "hoy" (usa una copia desechable de Postgres).
- `bash pruebas/interfaz_captura.sh` — captura, calendario, caja chica, usuarios, respaldos, recurrentes, bitácora, reportes y recibos con navegador real contra una **copia desechable** de la base.
- `php pruebas/fase5.php` — reportes PDF, fusionador de PDF, recibos, archivo al cerrar un periodo y recibos de recurrentes; se revierte solo.
- `php pruebas/rutas_paridad.php` — toda página y método de API del original existe en la tabla de rutas de PHP (salvo 2 excepciones documentadas).
- `bash pruebas/paridad_pdfs.sh` — los PDF contra los del ORIGINAL (Node + pdf-lib) página por página, en píxeles y texto (copias desechables de ambas bases; requiere `pdftoppm`/`pdftotext`).
- `node pruebas/paridad_visual_fase5.mjs` — Bitácora, Reportes y Análisis en ambas apps (después, `comparar_capturas.py`).
- `node pruebas/interfaz.mjs` — recorre la interfaz con un navegador real (login, selector, visor de PDF, menús, salir).
- `node pruebas/paridad_visual.mjs` + `python3 pruebas/comparar_capturas.py` — capturas de ambas apps y % de píxeles distintos.
  Requieren Playwright y Pillow; `PRUEBA_EMAIL`/`PRUEBA_CLAVE` de un administrador de prueba en las dos bases.
