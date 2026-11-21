# Plan de migración de Le Bleu a PHP + MySQL 8 (enero)

Estado: **plan aprobado por Alex; arranca el lunes**. Hasta ahora no hay código PHP. Está hecho leyendo el código
de Le Bleu (este repo) y las convenciones de SICTEGRA (repo `mye`).

## 0. Por qué se migra

Hoy Lebleu depende de terceros (Vercel para la app, Supabase para la base). El objetivo es
**centralizar todo en el servidor propio de Solmex** y que ningún dato viva en servicios
externos. Consecuencias: al terminar el corte se da de baja Vercel y Supabase (tras el periodo de
respaldo de la sección 9), y el respaldo de la base pasa a ser responsabilidad propia.

## 1. Qué hay que mover (medido, no estimado)

| Pieza | Hoy | Tamaño |
|---|---|---|
| Rutas API | `app/api/**/route.ts` | 27 endpoints |
| Páginas | `app/*/page.tsx` | 12 páginas, 1,137 líneas (la de inicio, 381) |
| Componentes de cliente (React) | 32 archivos `"use client"` | 3,009 líneas |
| Lógica de servidor | `lib/*.ts` | ~1,900 líneas (queries 491, reporte-estado 367) |
| PDFs | `pdf-lib` en servidor | 5 generadores |
| Estilos | `app/globals.css` | 673 líneas |
| Esquema | `db/*.sql` | 25 tablas, 3 vistas, 6 columnas generadas |
| Datos | `db/datos_20260826.sql` | 31 MB |

Es un sistema chico. Lo caro no es el backend: son los **32 componentes React** (hay que
reescribirlos como JS simple para que se vea y se comporte igual) y **probar que los números
cuadran al centavo**.

## 2. Lo que dicta el servidor (de MYE, no de suposiciones)

- Windows + IIS en producción (`web.config`, no `.htaccess`); XAMPP en local.
- MySQL 8 con Workbench. En MYE no hay terminal: todo se entrega como archivos + SQL pegable.
  Por eso **sin Composer**: las librerías PHP se copian a `vendor/` y se suben al repo, como ya
  hace MYE (`pdf-lib`, `jspdf`, etc.).
- El cron de Vercel (`vercel.json`, diario 14:00 UTC) pasa a **Programador de tareas de
  Windows** llamando a un script PHP por línea de comandos (no por URL pública).
- HTTPS: certificado en IIS (win-acme o el que ya use SICTEGRA). `app.lebleu.mx` apunta con un
  registro A (Hostinger) a la IP del Lightsail.

## 3. Arquitectura modular (para no repetir el monolito)

MYE tiene 582 archivos planos en `api/`, varios de 2,000 a 6,800 líneas. Para evitarlo:

```
lebleu/
  public/index.php          único punto de entrada (front controller)
  web.config                reescritura de URLs en IIS
  app/
    Core/                   Router, Request, Response, Db, Sesion, Politicas (roles)
    Modulos/
      Auth/                 Controller · Servicio · Repositorio · vistas/
      Usuarios/
      Periodos/             (abrir, cerrar, arrastre de saldo)
      EstadoCuenta/         (queries + vistas de reparto)
      GastosOperativos/  Extraordinarios/  Insumos/  IngresosRenta/  Aportaciones/
      Viajes/               (horómetros, marineros)
      Reservas/             (calendario)
      CajaChica/  Adjuntos/  Recurrentes/  Auditoria/
      Reportes/             (un generador PDF por archivo)
    Compartido/             Formato (mxn, num), Auditar, PeriodoCerrado
  recursos/css/             globals.css tal cual
  recursos/js/              un módulo por componente + api.js
  db/                       migraciones MySQL numeradas
  pruebas/                  paridad con Next y reglas de tamaño
```

Reglas (se vuelven prueba automática, no buena intención):
1. **Capas fijas**: Controller (valida, autoriza, responde) → Servicio (regla de negocio) →
   Repositorio (único lugar con SQL). Un Controller nunca toca SQL.
2. **Un módulo = una carpeta**; un módulo no lee las tablas de otro, le pide al Servicio de ese.
3. **Tope de líneas**: ningún archivo pasa de 300 (aviso) / 400 (falla). `pruebas/tamano_de_archivos.php`
   lo revisa, igual que MYE revisa sus prefijos de ids.
4. Los nombres de módulos y rutas son los mismos que hoy (`/api/gastos-operativos`,
   `/periodos`, ...) para comparar uno a uno.
5. Patrón de ruta mutante intacto: guard de rol → periodo cerrado → consulta parametrizada
   (PDO preparado) → auditar.

## 4. Traducción PostgreSQL → MySQL 8 (riesgos concretos)

- Columnas `GENERATED ALWAYS ... STORED` (6): MySQL 8 las soporta igual.
- `CHECK` (≈10): MySQL ≥ 8.0.16 sí los aplica; confirmar versión real (hoy se habla de "8.5").
- Vistas con "resto al socio de mayor id": MySQL 8 tiene funciones de ventana; se reescriben y se
  **comparan contra las de Postgres periodo por periodo**.
- `TEXT` como llave/`CHECK` → `VARCHAR`; `TIMESTAMPTZ` → `DATETIME` en UTC; `NUMERIC` → `DECIMAL`.
- `ON CONFLICT` → `ON DUPLICATE KEY UPDATE`; `RETURNING` (13 usos en API) → `lastInsertId()` o
  segunda consulta; `::tipo`, `interval`, `to_char` en `queries.ts` → funciones MySQL.
- Migraciones **portables** como las de MYE (sin `IF NOT EXISTS` en columnas/índices): se
  consulta `information_schema`.
- Datos: el dump de 31 MB está en sintaxis Postgres. Se convierte con script y se valida con
  conteo de filas por tabla **y suma de montos por periodo** antes de aceptar.

## 5. Contraseñas (decisión necesaria)

Hoy: `scrypt` de Node, formato `salt:hash`. PHP no trae ese scrypt compatible. Opciones:
- **A (DECIDIDA por Alex):** se restablecen todas las contraseñas y se guardan con
  `password_hash` (Argon2id/bcrypt). Cero código legado. Al cortar, cada usuario recibe una
  contraseña temporal y la cambia en su primer ingreso.
- ~~B: portar un verificador scrypt en PHP puro.~~ Descartada.

## 6. Interfaz: idéntica de verdad

- `globals.css` se copia sin tocar; las plantillas PHP usan **las mismas clases y el mismo HTML**.
- Los 32 componentes cliente se reescriben como módulos JS pequeños (uno por componente) que
  llaman a `api.js`. Orden: login → capturar (10 formularios) → movimientos → caja chica →
  calendario → resto.
- **Prueba de paridad visual**: con Playwright se toma captura de cada página en Next y en PHP,
  mismos datos, y se compara píxel a píxel. Lo que difiera se corrige o se justifica.

## 7. PDFs

Los 5 generadores (`reporte-estado`, `caja`, `marineros`, `soportes`, `recibo`) se portan a una
librería PHP copiada a `vendor/` (FPDF o TCPDF; **a decidir** probando el estado de cuenta, que
es el más exigente). Se mantiene el caché en `adjuntos` (`generado = true`). Criterio de
aceptación: mismo contenido y paginación que el PDF actual.

## 8. Fases y tiempo realista

Una semana de 10 h/día solo alcanza si nada sale mal. Con las pruebas de paridad,
**10–14 días de trabajo** es lo honesto.

| Fase | Contenido | Días |
|---|---|---|
| 0 | Decisiones (sección 10), repo/rama, entorno XAMPP + IIS de pruebas | 0.5 |
| 1 | Núcleo: Router, Db, Sesión, Auth, guards, auditoría, esquema MySQL, datos migrados y validados | 2 |
| 2 | Estado de cuenta + Periodos + vistas (la parte financiera) con **prueba de cuadre contra Postgres** | 2 |
| 3 | Módulos de captura: gastos, extraordinarios, insumos, renta, aportaciones, viajes | 3 |
| 4 | Calendario/Reservas, Caja chica, Adjuntos, Usuarios, Auditoría, Recurrentes | 2 |
| 5 | PDFs | 2 |
| 6 | Paridad visual, pruebas de roles (admin/capitán/socio), ajustes | 1.5 |
| 7 | Despliegue en IIS, tarea programada, HTTPS, DNS, ensayo de corte | 1 |

## 9. Corte a producción

1. Congelar captura en Vercel un momento acordado.
2. Exportar de Supabase, convertir, importar a MySQL local, correr validación de totales.
3. Cambiar el registro DNS de `app.lebleu.mx`. Vercel/Supabase se quedan 2 semanas de respaldo.
4. Respaldo diario de MySQL (hoy lo da Supabase; en el servidor propio hay que programarlo).

## 9b. Respaldos (Alex se encarga; se programan desde el día 1)

- Script `.bat` con `mysqldump --single-transaction --routines --triggers`, usuario de solo
  lectura y credenciales en un archivo de opciones (`--defaults-extra-file`), nunca en la línea
  de comandos ni en el repo.
- Archivo con fecha (`lebleu_AAAA-MM-DD_HHMM.sql`), comprimido; se borran los de más de N días
  (retención por definir, sugiero 14 diarios + 1 mensual).
- Se ejecuta con el **Programador de tareas de Windows**, a diario, fuera del horario de captura.
- Segunda capa: copia a otro disco/ubicación y, si se quiere, instantánea automática de
  Lightsail. Un respaldo en el mismo disco no cuenta como respaldo.
- **Ensayo de restauración** al terminar la fase 1 y una vez al mes: un respaldo que nunca se
  restauró no está probado.

## 10. Decisiones que necesito de Alex antes de programar

Resueltas: contraseñas se restablecen; Alex se hace cargo de la infraestructura y de los
respaldos automáticos (`.bat` + Programador de tareas); arranque el lunes.

Pendientes (se contestan el lunes, no bloquean la fase 0):
1. ~~Contraseñas~~: resuelto, se restablecen.
2. ¿Misma instancia Windows/IIS de SICTEGRA, con su propio sitio y grupo de aplicaciones?
3. ¿Versión exacta de MySQL ("8.5" no es una versión que yo pueda confirmar)? Define si hay `CHECK`.
4. ¿Se hace en este repo (carpeta `php/` en una rama) o en repo nuevo?
5. ¿Quién respalda la base y con qué frecuencia?
6. ¿Se corta en enero con un periodo cerrado, o a mitad de periodo?

## 11. Lunes: arranque

1. Confirmar versión exacta de MySQL (`SELECT VERSION();` en Workbench) y que XAMPP local use la misma.
2. Decidir ubicación del código (pregunta 4 de arriba) y crear la rama/carpeta.
3. Fase 0 y 1: esqueleto modular (Router, Db, Sesión, Auth), esquema MySQL, importar datos de
   `db/datos_20260826.sql` convertidos y validar conteos y sumas por periodo contra Postgres.
4. Dejar listo el `.bat` de respaldo y ensayar una restauración.
5. Crear `pruebas/tamano_de_archivos.php` (tope 300/400 líneas) desde el primer commit.
