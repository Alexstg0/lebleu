# CLAUDE.md

Guía de arquitectura y convenciones para trabajar en el código de **Le Bleu**
(control financiero/operativo de la embarcación). Léela junto con
[`README.md`](README.md), [`docs/DATABASE.md`](docs/DATABASE.md),
[`docs/API.md`](docs/API.md) y [`docs/BUSINESS-RULES.md`](docs/BUSINESS-RULES.md).

## Qué es esto

Una app Next.js 15 (App Router, TypeScript) en español que reproduce el
estado de cuenta mensual de una embarcación compartida entre dos socios
(familias), reparte gastos entre ellos según reglas fijas, y administra
viajes, reservas, caja chica, usuarios y reportes en PDF. Un solo cliente
(la razón social "Arrendadora Acma S de RL de CV" / embarcación "Le Bleu"),
sin multi-tenant.

## Arquitectura

- **Sin ORM.** `lib/db.ts` expone `getDb()` → `{ query, exec }` sobre un pool
  de `pg` (Postgres en Supabase sobre `DATABASE_URL`). Todas las consultas
  son SQL crudo con placeholders `$1, $2, …`. Nunca interpolar valores de
  usuario en el string SQL.
- **Sin auto-migraciones en request path.** El esquema se aplica una sola
  vez con `node scripts/setup-db.mjs` (o al desplegar). Los archivos de
  `db/*.sql` son idempotentes (`CREATE TABLE IF NOT EXISTS`,
  `ALTER TABLE … ADD COLUMN IF NOT EXISTS`) para poder re-ejecutarlos sin
  romper una base existente.
- **Autenticación propia**, sin proveedor externo: cookie `httpOnly` `sid` +
  tabla `sesiones` (token aleatorio, expira a 30 días), contraseñas con
  `scrypt` (`lib/password.ts`, sin dependencias externas).
- **Autorización de dos capas**, siempre en ambas:
  - Página: `requireUser(roles?)` en el `page.tsx` (Server Component) —
    redirige a `/login` o al home del rol si no cumple.
  - API: `apiGuard(roles)` en cada `route.ts` — devuelve `null` y el handler
    responde 401/403 él mismo. No asumas que la protección de página basta;
    cada route handler valida su propio rol.
- **Tres roles**: `admin` (todo), `capitan` (bitácora/viajes/calendario/caja
  chica, sin ver montos financieros), `socio` (solo lectura: estado de
  cuenta, reportes, calendario). Detalle en
  [`docs/BUSINESS-RULES.md`](docs/BUSINESS-RULES.md).
- **PDFs generados con `pdf-lib`** directo (sin Puppeteer/Chromium): los
  generadores están en `lib/recibo.ts` (helpers de dibujo compartidos) y
  `lib/reporte-*.ts` (uno por tipo de reporte). Los PDFs generados por el
  sistema se guardan en base64 en la tabla `adjuntos` (columna `generado =
  true`) para no tener que regenerarlos en cada descarga.
- **Periodo como ancla temporal.** Casi todo movimiento (`gastos_operativos`,
  `extraordinarios`, `viajes`, `aportaciones`, `ingresos_renta`, `insumos`)
  cuelga de un `periodo_id` (mes/año). Un periodo puede estar `abierto` o
  `cerrado`; escribir en uno cerrado está bloqueado por
  `periodoCerradoMsg()` (`lib/audit.ts`) en casi todas las rutas mutantes.
  Excepciones: `caja_chica` y `reservas` no dependen de periodo.
- **Auditoría best-effort.** `auditar()` (`lib/audit.ts`) inserta en la
  tabla `auditoria` en cada mutación; está envuelta en `try/catch` para
  *nunca* tumbar la operación principal si falla el log.

## Convenciones al modificar código

- Los identificadores, comentarios y strings de UI están en **español**
  (México). Sigue esa convención — no mezcles inglés en nombres nuevos.
- Montos: `NUMERIC(14,2)` en BD, formateados con `lib/format.ts` (`mxn()`,
  `num()`) usando `Intl.NumberFormat("es-MX", …)`. No uses `toFixed` suelto
  para dinero en la UI.
- Multi-moneda: los movimientos con `moneda`/`monto_original`/`tipo_cambio`
  guardan `monto_mxn` como columna **generada** (`GENERATED ALWAYS AS
  (monto_original * tipo_cambio) STORED`) — no la calcules ni la escribas a
  mano en INSERT/UPDATE.
- Reparto de porcentajes: sigue el patrón "resto al socio de mayor `id`"
  usado en `v_balance_operativo` y `v_extraordinarios_por_socio` (ver
  `docs/BUSINESS-RULES.md`) para que la suma de las partes nunca quede un
  centavo corta o pasada por redondeo. Replica el mismo patrón si agregas
  un nuevo reparto porcentual.
- Rutas mutantes nuevas: sigue el patrón existente — `apiGuard(roles)` →
  `periodoCerradoMsg()` si aplica a un periodo → query parametrizada →
  `auditar(...)`. Revisa una ruta similar en `app/api/*/route.ts` como
  plantilla antes de escribir una nueva.
- Adjuntos PDF: van en la tabla `adjuntos`, vinculados por **exactamente
  una** FK de `gasto_id / insumo_id / extraordinario_id / caja_id /
  periodo_id`. `app/api/adjuntos/[id]/route.ts` los sirve con
  `Content-Type: application/pdf` forzado y `X-Content-Type-Options:
  nosniff` (no confíes en el MIME que mandó el cliente al subir).
- No hay test suite ni linter de CI configurado más allá de `next lint`
  (`npm run lint`). Antes de dar por terminado un cambio, corre `npm run
  build` para atrapar errores de tipos/rutas — es la validación principal
  disponible.

## Dónde mirar primero

| Si vas a… | Empieza en |
|---|---|
| Entender el modelo de datos | `db/schema.sql`, `db/auth.sql`, `db/reservas.sql`, `db/migraciones.sql`, [`docs/DATABASE.md`](docs/DATABASE.md) |
| Cambiar cómo se calcula el estado de cuenta | `lib/queries.ts` (`getEstadoCuenta`), vistas `v_balance_operativo` / `v_variable_por_socio` / `v_extraordinarios_por_socio` en `db/schema.sql` |
| Agregar/editar un endpoint | archivo análogo en `app/api/**/route.ts`, [`docs/API.md`](docs/API.md) |
| Tocar permisos o sesiones | `lib/auth.ts`, `lib/password.ts` |
| Cambiar un PDF generado | `lib/recibo.ts` (helpers) + el `lib/reporte-*.ts` correspondiente |
| Tocar cargos automáticos (nómina, admin) | `lib/recurrentes.ts`, `app/api/cron/recurrentes/route.ts`, `vercel.json` |
| Abrir/cerrar un periodo o el arrastre de saldo | `lib/periodos.ts` |
