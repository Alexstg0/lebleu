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

## 12. Avance

**Fase 0 y 1 hechas** (código en `php/`, decisión provisional: carpeta `php/` de este repo):
- Núcleo modular (Ruteador, Petición/Respuesta, Db con PDO preparado, Config) y módulo **Auth**
  completo (login con límite de 8 intentos/10 min y respuesta igual para usuario inexistente,
  sesiones de 30 días, logout, guardias por rol), probado de punta a punta.
- Esquema MySQL consolidado (25 tablas, 3 vistas, columnas generadas y `CHECK`), portable a
  MySQL 8 y MariaDB; migrador re-ejecutable.
- Importador del respaldo con conversión de fechas (America/Mazatlan), booleanos y verificación de
  alineación; 730 filas cargadas.
- **Prueba de cuadre contra Postgres: 40 de 40 comparaciones iguales** (conteos, fechas, sumas de
  montos por periodo, y las tres vistas del estado de cuenta).
- `.bat` de respaldo con instrucciones. El ensayo de restauración (dump y carga en otra base) encontró
  que las vistas dependían del usuario que las creó (`DEFINER`); se pasaron a `SQL SECURITY INVOKER`
  y la base restaurada da exactamente los mismos totales.

**Hallazgo:** `db/datos_20260826_clean.sql` está corrupto (en `viajes`, `gastos_operativos`,
`extraordinarios`, `insumos` y `viaje_horometros` hay más valores que columnas). El bueno es
`db/datos_20260826.sql`. Conviene borrar el `_clean`.

Probado en MariaDB 10.11 y PHP 8.3; falta confirmarlo en el MySQL 8 y el PHP del servidor.

**Fase 2 hecha** (estado de cuenta, periodos, login y sus pantallas):
- Módulos `Periodos` (crear con arrastre de saldos, cerrar, reabrir, bloqueo de periodos cerrados), `EstadoCuenta`,
  `Auditoria`, y los endpoints que esas pantallas necesitan (`/api/adjuntos/{id}`, `/api/manual`,
  `/api/extraordinarios/pagado`). Aritmética de dinero en centavos exactos (`Dinero`).
- **El estado de cuenta de los 4 periodos coincide con la lógica original** (`lib/queries.ts` ejecutado sobre Postgres):
  balances, reparto de extraordinarios, viajes con horómetros y KPIs.
- **Visualmente casi idéntico:** `globals.css` se copió sin cambios. Comparando capturas de ambas apps (escritorio y móvil,
  login, estado de cuenta, periodos, menú desplegable y menú lateral) difieren 0.02 %–0.3 % de los píxeles y miden lo mismo; lo que
  queda es suavizado de texto y el indicador de desarrollo de Next.js. Prueba de interfaz con navegador real: 14/14.
- Regla de capas automática (`pruebas/capas.php`): ya cazó una violación propia durante el desarrollo.

**Desviaciones y pendientes conocidos**
- **Error del original (no replicado):** el tablero calcula "50% c/u" con flotantes y en totales de centavos nones queda un
  centavo abajo (periodo julio: muestra $33,754.46, pero el socio 1 realmente paga $33,754.47 según la vista). PHP calcula exacto.
  La prueba lo marca como NOTA y no como fallo.
- Al cerrar un periodo todavía **no se archiva el PDF** del estado de cuenta (llega con Reportes, fase 5); la pantalla lo avisa
  igual que cuando falla en el original. El código ya tiene el punto de enganche (`ArchivadorEstado`).
- Un capitán que inicia sesión es enviado a `/calendario`, que se porta en la fase 4.
- "Recordarme" del login no hace nada, igual que en el original.

**Fase 3 hecha** (captura de movimientos):
- Módulos `GastosOperativos`, `Extraordinarios` (con abonos), `Aportaciones`, `IngresosRenta`, `Insumos`, `Viajes`
  (horómetros por motor) y `Marineros`, más `Catalogos`, `Captura` y `Movimientos`. Los siete de movimientos comparten una base
  (`Compartido/Movimientos`: permiso → periodo cerrado → validar → guardar en transacción → auditar); cada módulo solo declara sus campos y su texto
  de auditoría, y todo el SQL queda en su Repositorio.
- Pantallas **Capturar** (administrador, 7 pestañas; capitán, formulario simplificado) y **Movimientos** (editar con el mismo formulario y borrar).
  Los formularios sirven para alta y edición y los cálculos del viaje (galones → litros, consumo, costo, total) son los del original.
- **Pruebas:** `captura.php` 62/62 (permisos, validación, periodo cerrado, cálculos de la base, insumos que alimentan el costo del viaje, auditoría
  con los textos originales); `interfaz_captura` 34/34 con navegador real sobre una copia desechable; paridad visual de las 7 pestañas, el formulario del
  capitán y el login en escritorio y móvil: solo difiere el indicador de desarrollo de Next.js (0.09 %–0.3 %). Cazó un error mío: el "Total viaje" abría en $0.00.
- Modo SQL estricto en cada conexión: un dato inválido es un error, nunca se recorta ni se convierte en silencio.

**Diferencias deliberadas respecto al original** (todas hacen el sistema más estricto o seguro):
- Se valida en el servidor (fechas reales, números, textos obligatorios y categorías) y se contesta con un mensaje; el original dejaba que la base fallara con un error genérico.
- Editar algo que no existe responde 404 (el original decía "ok"). No se puede mover un insumo a un periodo cerrado (el original lo permitía).
- Alta de viaje y sus lecturas de horómetro van en una sola transacción (el original podía dejar un viaje sin lecturas).

**Pendiente de esta pantalla** (depende de módulos que vienen después): en Movimientos faltan la columna *Respaldo (PDF)* (subir/borrar archivos; las
fotos se convierten a PDF, fase 4/5), el botón *Generar nómina y administrativo* (Recurrentes, fase 4) y los recibos PDF de gasto y de marinero (fase 5).
Por eso Movimientos todavía no es idéntica a la original.

Siguiente: fase 4 (Calendario/Reservas, Caja chica, Adjuntos, Usuarios, Auditoría, Recurrentes).
