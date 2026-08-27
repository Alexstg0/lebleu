# Base de datos

PostgreSQL 14+ hospedado en **Supabase** (proyecto `le-bleu`), vía
*transaction pooler*. Acceso desde la app con el driver `pg` — no hay ORM.
Producción anterior usó Neon; el respaldo histórico de Neon sigue vigente
(ver `INVENTARIO-ENTREGA.md`).

## Cómo se aplica el esquema

`node scripts/setup-db.mjs` es el único mecanismo de setup/migración. Es
**idempotente**: se puede correr tantas veces como se quiera.

1. Si la tabla `periodos` no existe, aplica `db/schema.sql` y siembra
   `db/seed_mayo_2026.sql` (datos reales de mayo 2026, validados al
   centavo — sirven de prueba de regresión del cálculo de balances).
2. Siempre aplica (en orden) `db/auth.sql`, `db/reservas.sql` y
   `db/migraciones.sql` — todos escritos con `CREATE TABLE IF NOT EXISTS` /
   `ADD COLUMN IF NOT EXISTS`, seguros de re-ejecutar sobre una base ya
   poblada.
3. Si no existe ningún usuario `admin`, crea uno con `ADMIN_NOMBRE` /
   `ADMIN_EMAIL` / `ADMIN_PW` (falla si `ADMIN_PW` no tiene mínimo 8
   caracteres).
4. Si la tabla `reservas` está vacía, siembra `db/reservas_seed.sql`.

No hay una carpeta `migrations/` versionada con timestamps: los cambios de
esquema posteriores al `schema.sql` original se van agregando al final de
`db/migraciones.sql` (que ya mezcla varias migraciones históricas — nuevas
migraciones se agregan ahí siguiendo el mismo patrón `IF NOT EXISTS`).

### Variables de entorno

| Variable | Requerida | Uso |
|---|---|---|
| `DATABASE_URL` | Sí | Cadena de conexión Postgres. Si contiene `supabase.co`, `lib/db.ts` fuerza TLS con la CA embebida en `lib/supabase-ca.ts`; si es `localhost`, sin SSL. |
| `ADMIN_EMAIL` | Solo en `setup-db.mjs` | Correo del admin inicial (default `int.financiera@solmex.mx`). |
| `ADMIN_PW` | Solo en `setup-db.mjs`, sin default | Contraseña del admin inicial, mínimo 8 caracteres — el script falla sin ella. |
| `ADMIN_NOMBRE` | Solo en `setup-db.mjs` | Nombre del admin inicial (default "Kevin Flores"). |
| `CRON_SECRET` | Sí, en producción | Token Bearer que Vercel Cron manda a `/api/cron/recurrentes`; la ruta responde 503 si falta y 401 si no coincide. |
| `NODE_ENV` | — | Determina si la cookie de sesión se marca `secure`. |

## Dominios de datos

### 1. Catálogos
- **`embarcaciones`** — solo hay una fila real (Le Bleu / Arrendadora Acma).
- **`socios`** — los dos socios/familias, con su `porcentaje` de reparto
  (default 50/50).
- **`motores`** — motores de la embarcación, con `horometro_actual`
  acumulado (uno por lado, ej. "Motor B / Babor", "Motor E / Estribor").
- **`clientes`** — nombres de familia o terceros de renta; `socio_id` NULL
  = tercero.
- **`marineros`** — catálogo de nombres de marineros (alimenta el selector
  en captura; los viajes también guardan el nombre en texto libre).

### 2. Periodo (ancla del estado de cuenta mensual)
- **`periodos`** — un renglón por mes/año/embarcación (`UNIQUE
  (embarcacion_id, anio, mes)`), con el tipo de cambio y precio de
  combustible de referencia del mes, y `estado` (`abierto`/`cerrado`).
- **`saldos_socio`** — snapshot de `saldo_inicio`/`saldo_fin` por socio y
  periodo; `saldo_fin` se congela al cerrar el periodo y se arrastra como
  `saldo_inicio` del periodo siguiente (`lib/periodos.ts`).
- **`aportaciones`** — capital que cada socio mete al mes.

### 3. Movimientos
- **`gastos_operativos`** — gastos que se reparten 50/50; `categoria`
  restringida a `nomina|impuesto|servicio|refaccion|consumible|admin|otro`.
  `clave_recurrente` (única cuando no es NULL) evita duplicar los cargos
  automáticos de `lib/recurrentes.ts`.
- **`extraordinarios`** — gastos grandes fuera de lo normal (muelle anual,
  refacciones), 50/50 pero **liquidación separada** del balance operativo
  (`liquidado` + `fecha_liquidacion`).
- **`viajes`** — cada salida: costos de combustible/marinero/consumibles
  (100% al `socio_id` responsable, o renta si `es_renta`), lecturas de
  tanque, `bandera` para marcar viajes anómalos. `total` es columna
  generada (`costo_combustible + costo_marinero + costo_consumibles`).
- **`viaje_horometros`** — lectura de horómetro por motor y viaje; `horas`
  es columna generada (`lectura_fin - lectura_inicio`).
- **`ingresos_renta`** — ingresos por renta a terceros; `utilidad` es
  columna generada (`monto - costos_asociados`), repartida 50/50 sin
  afectar el balance de aportaciones de los socios.
- **`insumos`** — consumibles de un viaje capturados como partida aparte;
  la API (`app/api/insumos/route.ts`) resincroniza
  `viajes.costo_consumibles` tras cada cambio.
- **`caja_chica`** — efectivo por número de caja (`tipo` `gasto`/`abono`),
  **no** cuelga de un periodo.
- **`reservas`** — calendario de salidas agendadas, independiente de la
  contabilidad (sin montos).

### 4. Auth y sistema
- **`usuarios`** / **`sesiones`** (`db/auth.sql`) — login propio, rol en
  `usuarios.rol` (`admin|capitan|socio`), `socio_id` opcional para ligar un
  usuario `socio` a su fila en `socios`.
- **`adjuntos`** — PDFs en base64 (`datos`), ligados a **una** de
  `gasto_id / insumo_id / extraordinario_id / caja_id / periodo_id`;
  `generado = true` marca los que el sistema produjo (recibos, snapshots
  del estado de cuenta al cerrar periodo) vs. los subidos a mano.
- **`manuales`** — un PDF de manual de usuario por rol (`rol` es PK).
- **`auditoria`** — bitácora append-only de quién hizo qué
  (`usuario_id`/`usuario_nombre`, `accion`, `tabla`, `registro_id`,
  `detalle`), escrita por `lib/audit.ts::auditar()`.
- **`login_intentos`** *(agregada por `app/api/login/route.ts` vía
  rate-limit, ver ese archivo)* — controla intentos de login por IP+email.
- **`mantenimientos`** — tabla ya creada para un módulo de mantenimiento
  por horómetro, pendiente de UI (ver README § Pendientes).

## Vistas (`db/schema.sql`, redefinidas en `db/migraciones.sql`)

- **`v_variable_por_socio`** — suma de combustible/marinero/consumibles por
  socio y periodo (agregado directo de `viajes`).
- **`v_balance_operativo`** — el cálculo central del estado de cuenta:
  `saldo_inicio + aportaciones − gastos_generales(reparto %) −
  gastos_variables + utilidad_renta(reparto %)`. Usa el patrón "resto al
  socio de `id` mayor" para el reparto porcentual — ver
  [`BUSINESS-RULES.md`](BUSINESS-RULES.md).
- **`v_extraordinarios_por_socio`** — mismo patrón de reparto, pero para la
  liquidación separada de `extraordinarios`.

## Notas de integridad

- Casi todas las tablas de movimientos tienen `ON DELETE CASCADE` desde
  `periodo_id` — borrar un periodo borra sus movimientos. No hay ruta de
  API que borre periodos hoy.
- Los montos generados (`monto_mxn`, `total`, `utilidad`, `horas`) **no se
  escriben directamente**: son `GENERATED ALWAYS AS (...) STORED`. Un
  INSERT/UPDATE que intente setearlas falla.
