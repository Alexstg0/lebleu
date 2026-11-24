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
- ~~Al cerrar un periodo todavía no se archiva el PDF del estado de cuenta~~ → resuelto en la fase 5 (`ArchivadorPdf`).
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
~~Por eso Movimientos todavía no es idéntica a la original~~ → ya lo es desde la fase 5 (ver abajo).

**Fase 4 hecha** (calendario, caja chica, usuarios, auditoría, respaldos y cargos recurrentes):
- Módulos `Reservas` (calendario de escritorio y móvil), `CajaChica` (filtro por caja, totales, balance corrido), `Usuarios`, `Auditoría`,
  `Adjuntos` (subir, ver y borrar PDF; **las fotos JPG/PNG se convierten a PDF**) y `Recurrentes`. La columna *Respaldo (PDF)* y el botón
  *Generar nómina y administrativo* de Movimientos ya funcionan.
- **FPDF** quedó copiado en `php/vendor/fpdf` (con su licencia y las fuentes Helvetica): es la librería con la que se escribirán todos los PDF.
- **Cargos recurrentes idénticos al original** en tres fechas de "hoy" (10, 10 y 11 cargos con montos, folios, claves y fechas iguales). El cron de
  Vercel pasa a `scripts/cron_recurrentes.php` + Programador de tareas de Windows (`herramientas/LEEME-tarea-programada.txt`).
- **Pruebas:** `fase4.php` (57 comprobaciones de permisos, validación, fotos a PDF, límites, auditoría y hora de BCS), `interfaz_fase4` con navegador real y paridad visual
  de calendario (agosto y septiembre, escritorio y móvil, con su ventana), caja chica (general, por caja y ventana), usuarios y auditoría: solo difiere el indicador de Next.js.
- **La auditoría visual encontró tres defectos reales, ya corregidos:** (1) Postgres pone las reservas sin hora al final del día y MySQL al principio;
  (2) en caja chica filtrada, las filas ocultas seguían contando para el color de la franja de cada tarjeta en móvil (ahora se quitan de la tabla);
  (3) un espacio en blanco entre los botones "Contraseña" y "Borrar" desplazaba las columnas de Usuarios.

**Hallazgo importante: la app original que ustedes usan va por delante del repositorio.** En la captura de pantalla del formulario de gasto, la original trae el campo
*Soporte (PDF o foto, opcional)* con la nota "Si no lo subes ahora…", que **no existe en el código de `main`**. Lo repliqué en gasto, insumo y extraordinario
(guarda el movimiento y sube el archivo), pero solo puedo comparar contra lo que está en el repositorio. **Para que la paridad sea real hace falta subir al repositorio el código actual de la original.**

Contraseñas: se conserva el mínimo de 6 caracteres del original (conviene subirlo a 8, como pide el resto del sistema; es un cambio de una línea).

**Fase 5 hecha** (los PDF, y con ella las tres pantallas que faltaban):
- **Pantallas nuevas:** Bitácora (filtro por mes; el administrador edita en ventana y borra, el capitán solo lee), Reportes (4 tarjetas con visor de PDF) y Análisis
  (4 cifras y 3 gráficas en SVG hechas en el servidor). Movimientos ya trae *Generar recibo* en cada gasto y *Ver PDF* del recibo de cada marinero.
- **PDF con FPDF y dos piezas propias, sin dependencias:** `Compartido/Pdf/Lienzo` dibuja con la misma forma que `pdf-lib` (coordenadas desde abajo, texto con su línea
  base), así los reportes se portaron casi renglón por renglón; y `LectorPdf` + `Fusionador` unen los comprobantes que suben los usuarios. Esto último no es opcional:
  FPDF no importa PDF, FPDI gratuito no lee PDF 1.5+ y 100 de los 141 comprobantes reales son 1.6/1.7 con flujos de objetos y xref comprimido. El lector
  también reconstruye archivos con la tabla dañada y rechaza (sin dejar páginas a medias) los cifrados o que no son PDF; en ese caso el reporte pone la hoja
  "Comprobante no se pudo incrustar", como el original. Los 141 comprobantes se fusionan y las 165 páginas salen idénticas a las de origen.
- **Reportes:** estado de cuenta, soportes (portadas + comprobantes), marineros (resumen + un recibo por viaje), completo (los tres en uno), caja chica
  (carátula horizontal + soportes con sello al pie) y los recibos de viaje y de gasto. Al cerrar un periodo se archiva su estado de cuenta (`ArchivadorPdf`) y cada cargo
  recurrente nace con su recibo (`ReciboGastoPdf`); `Servicios` es la raíz de composición que entrega estas implementaciones a los módulos que solo conocen la interfaz.
- **Paridad de PDF contra el original, página por página** (`pruebas/paridad_pdfs.sh`: genera con el código de Next/pdf-lib sobre Postgres y con PHP sobre MySQL, rasteriza con
  `pdftoppm` y compara píxeles y texto): **594 páginas, ninguna diferencia notable (≤ 0.008 %, suavizado de letra)**. Cazó cuatro defectos míos que ya no están: pdf-lib mide
  el texto *con kerning* y FPDF no (centrados y recortes con "…" salían distintos; se exportaron los pares de Helvetica a `kerning.php`), "4 h" salía "4.00 h", un círculo
  dibujado con el eje Y volteado y un orden de comprobantes.
- **Pruebas:** `fase5.php` (48 comprobaciones: permisos, 400/403/404, nosniff, comprobante dañado, archivo al cerrar, recibos, recurrentes con recibo, fusión de los 141
  comprobantes reales), `interfaz_fase5` con navegador real (30) y `rutas_paridad.php`: las 61 rutas del original (12 páginas + 49 métodos de API), 59 existen y 2 son
  excepciones documentadas, ninguna falta. Auditoría visual de las 12 pantallas en escritorio y móvil: solo difiere el indicador de Next.js.

**Diferencias deliberadas de la fase 5** (documentadas, no descuidos):
- `GET /api/logout` del original es un resto de depuración que responde "GET logout test"; no se porta. `GET /api/cron/recurrentes` (Vercel Cron) lo reemplaza `scripts/cron_recurrentes.php`.
- Dos comprobantes del *mismo* gasto: el original no los ordena (el orden queda al azar); PHP los ordena por id. La prueba lo reconoce como "mismas páginas, otro orden".
- "Fecha" del reporte de caja chica y fecha por defecto de los formularios: PHP usa la hora de la embarcación (BCS); el original, la del servidor (UTC en Vercel).
- Mitad de un monto con centavos impares: PHP exacto (ya explicado en la fase 2); el PDF muestra el mismo centavo que la pantalla.
- Las páginas HTML llevan `Cache-Control: no-store` (el original, `no-store, must-revalidate`); el HSTS solo se envía bajo HTTPS.

Siguiente: fase 6 (afinar permisos por rol con una pasada completa) y fase 7 (IIS, tarea programada, HTTPS, ensayo del corte).

**Fase 6 hecha** (pruebas de roles y ajustes):
- `pruebas/roles.php` lee los roles permitidos de **cada página y cada método de API directamente del código original** (`requireUser` / `apiGuard`, resolviendo
  `ROLES` compartidos) y comprueba que PHP responde igual para sin sesión, administrador, capitán y socio: **224 combinaciones ruta × rol coinciden**
  (permitido = no 401/403/405; denegado = 403 en API o redirección en página). Además comprueba la tabla de roles del negocio, que quien no puede ver una
  pantalla vuelve a *su* inicio, y que una cookie inventada, una sesión vencida y un usuario desactivado no abren sesión.
- Ajustes: `X-Powered-By` ya no se envía; `Cache-Control: no-store` en las páginas; el servidor de desarrollo ya no sirve `web.config`; una constante de `viaje.php`
  que avisaba si el formulario se dibujaba dos veces en el mismo proceso.

**Fase 7 hecha hasta donde se puede sin el servidor** (todo lo ejecutable está probado aquí; **lo que corre en tu Windows/IIS lo ejecutas tú**):
- `docs/DESPLIEGUE-IIS.md` (paso a paso: PHP, IIS, FastCGI, permisos, MySQL, HTTPS con win-acme, DNS, tareas, prueba de humo) y `docs/CORTE.md` (el día, con reversa).
- `public/web.config` endurecido (límite de subida, segmentos y extensiones ocultas, errores de la app sin reemplazar, sin cabeceras de versión, regla HTTPS lista para activar).
- `scripts/verificar_servidor.php` (PHP, extensiones, `php.ini`, MySQL ≥ 8.0.16, `max_allowed_packet`, esquema, archivos), `scripts/prueba_humo.php` (sitio publicado: estáticos,
  que no se descargue el código ni `config.php`, cabeceras, cookie, 11 pantallas, PDF, capitán, HTTPS, cierre de sesión; **pasa 58/58 contra el servidor de desarrollo**; encontró
  dos defectos que ya corregí) y `scripts/ensayo_restauracion.php` (respaldo → base temporal → mismas filas, mismas vistas, mismos bytes de PDF; pasa).
- `herramientas/instalar_tareas.bat` registra las dos tareas (recurrentes 07:00, respaldo 02:00).
- **Ensayo de corte de punta a punta** (`pruebas/ensayo_corte.sh`): exporta de Postgres con `scripts/exportar-respaldo.mjs`, importa a MySQL temporal en 2 s y cuadra
  *40/40* contra el origen. Hizo falta que el importador ignore las columnas generadas (`monto_mxn`, `total`).
- **No probado por no tener el entorno:** PHP bajo IIS/FastCGI, HTTPS real, DNS, MySQL 8 real (aquí MariaDB 10.11), `instalar_tareas.bat` y los `.bat` en Windows.
  La prueba de humo y el verificador están hechos justo para detectarlo el primer día.

