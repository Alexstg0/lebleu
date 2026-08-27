# Le Bleu — Control de Embarcación

App web para el control financiero y operativo de la embarcación **Le Bleu**
(Arrendadora Acma S de RL de CV). Reproduce el estado de cuenta mensual,
reparte gastos entre socios y administra viajes, reservas, caja chica y
reportes en PDF.

**Producción:** https://lebleu.mx

## Stack

- **Next.js 15** (App Router, React 19, TypeScript)
- **PostgreSQL** hospedado en **Supabase** (vía *transaction pooler*), acceso
  con el driver `pg`. El certificado de la CA de Supabase va embebido en
  `lib/supabase-ca.ts` — sin él Node rechaza la conexión TLS.
- **pdf-lib** — generación de PDFs (estados de cuenta, recibos, reportes) sin
  dependencias de sistema.
- Sesiones propias (cookie `httpOnly` + tabla `sesiones`), sin proveedor
  externo de autenticación.
- Desplegado en **Vercel**, con un *cron job* diario.

> El proyecto usó PGlite (Postgres embebido) en una etapa temprana. Ya no es
> así: hoy la base vive en Supabase y `lib/db.ts` se conecta por
> `DATABASE_URL`. Ver [`docs/DATABASE.md`](docs/DATABASE.md).

## Correr en local

```bash
npm install
cp .env.example .env        # pega tu DATABASE_URL (Supabase/Neon/Postgres)
ADMIN_PW="al-menos-8-caracteres" node scripts/setup-db.mjs   # crea esquema + admin inicial
npm run dev                 # http://localhost:3000
```

`scripts/setup-db.mjs` es idempotente: solo crea el esquema y siembra datos
la primera vez; en corridas siguientes solo aplica migraciones y crea el
admin si aún no existe. Ver variables de entorno en
[`docs/DATABASE.md#variables-de-entorno`](docs/DATABASE.md#variables-de-entorno).

**Advertencia (Windows/OneDrive):** no ubicar el proyecto dentro de una
carpeta sincronizada por OneDrive — bloquea archivos de `.next` y la app
devuelve `500 EBUSY`.

## Documentación

| Documento | Contenido |
|---|---|
| [`CLAUDE.md`](CLAUDE.md) | Guía de arquitectura y convenciones para trabajar en el código (humano o IA). |
| [`docs/DATABASE.md`](docs/DATABASE.md) | Esquema completo: tablas, vistas, variables de entorno, cómo aplicar migraciones. |
| [`docs/API.md`](docs/API.md) | Referencia de las 27 rutas de `app/api/**`: método, rol requerido, body/query, efectos. |
| [`docs/BUSINESS-RULES.md`](docs/BUSINESS-RULES.md) | Reglas de reparto de gastos, cargos recurrentes, roles y permisos. |
| [`INVENTARIO-ENTREGA.md`](INVENTARIO-ENTREGA.md) | Inventario de la entrega de código del 2026-08-18 (histórico). |

## Estructura

```
db/
  schema.sql             tablas base + vistas del estado de cuenta
  auth.sql                usuarios + sesiones
  reservas.sql             calendario de reservas
  migraciones.sql          migraciones idempotentes posteriores al esquema base
  seed_mayo_2026.sql       datos de Mayo 2026 (validado al centavo)
  reservas_seed.sql        reservas de ejemplo
lib/
  db.ts                   pool de conexión Postgres (pg + CA de Supabase)
  auth.ts                  sesiones, roles, guards de página/API
  password.ts               hashing scrypt
  audit.ts                   bitácora de auditoría + guard de periodo cerrado
  queries.ts                 consultas de lectura (estado de cuenta, bitácora, etc.)
  periodos.ts                 crear/cerrar/reabrir periodos, arrastre de saldos
  recurrentes.ts               nómina semanal y cargo administrativo automáticos
  reporte-*.ts, recibo.ts       generadores de PDF (estado de cuenta, recibos, reportes)
  format.ts                    formato de moneda/fecha/hora (es-MX)
app/
  page.tsx                dashboard / estado de cuenta
  capturar/                formularios de captura (viaje, gasto, aportación, etc.)
  bitacora/ movimientos/ calendario/ caja-chica/ periodos/ usuarios/
  auditoria/ analisis/ reportes/ login/
  api/                     route handlers — ver docs/API.md
scripts/
  setup-db.mjs             inicializa esquema + admin en una base nueva
```

Ver [`docs/API.md`](docs/API.md) para el detalle de cada página y ruta.

## Reglas de negocio (resumen)

- **Gastos operativos** → 50/50 entre socios.
- **Gastos variables** (costos de viaje: combustible, marinero, consumibles)
  → 100% al socio que usó el barco.
- **Extraordinarios** (muelle, refacciones grandes) → 50/50, liquidación
  separada (fuera del balance operativo).
- **Renta a terceros** → utilidad 50/50; no afecta el balance de socios.
- `balance_operativo = saldo_inicio + aportaciones − operativos×% − variables_del_socio + utilidad_renta×%`

Detalle completo, incluyendo el reparto "sin centavo fantasma" y los cargos
recurrentes automáticos, en [`docs/BUSINESS-RULES.md`](docs/BUSINESS-RULES.md).

## Funciones

- **Estado de cuenta** (`/`) — dashboard mensual con selector de periodo.
- **Capturar** (`/capturar`) — pestañas: viaje, gasto operativo, insumo,
  aportación, extraordinario, renta (el capitán solo ve viaje).
- **Bitácora** (`/bitacora`) — historial de viajes con lecturas de
  horómetro por motor, filtrable por mes.
- **Calendario** (`/calendario`) — agenda de **reservas** (salidas). Sin
  montos; lo usan admin, capitán y socio.
- **Caja chica** (`/caja-chica`) — control de efectivo por número de caja.
- **Movimientos** (`/movimientos`) — listado de todo lo capturado del
  periodo con editar/borrar; los balances se recalculan solos.
- **Periodos** (`/periodos`) — abrir un mes nuevo; arrastra el saldo del mes
  anterior (que se cierra automáticamente) como saldo de inicio de cada
  socio.
- **Análisis** (`/analisis`) — comparativas entre periodos.
- **Auditoría** (`/auditoria`) — bitácora de quién hizo qué (solo admin).
- **Reportes** (`/reportes`) — PDFs: estado de cuenta, soportes, recibos de
  marineros, caja chica, reporte completo.
- **Cargos recurrentes** — un *cron* diario (`/api/cron/recurrentes`, 07:00
  hora de Baja California Sur) genera la nómina semanal del capitán y el
  cargo administrativo mensual automáticamente.

## Cuentas y roles

Login en `/login`. El administrador inicial se crea al ejecutar
`scripts/setup-db.mjs`, con el correo y la contraseña definidos en las
variables de entorno `ADMIN_EMAIL` y `ADMIN_PW` (mínimo 8 caracteres).

| Rol | Acceso |
|---|---|
| **admin** | Todo (estado de cuenta, capturar, movimientos, periodos, calendario, usuarios, auditoría, reportes). |
| **capitán** | Calendario, bitácora, registrar viajes y caja chica. No ve montos de finanzas ni el estado de cuenta. |
| **socio** | Estado de cuenta, análisis, reportes y calendario (solo lectura de reservas). |

El admin da de alta capitán y socios desde **Usuarios**. La protección es
por página (`requireUser`) y también a nivel de API (`apiGuard`): un
capitán/socio no puede mandar peticiones que no le tocan.

## Pendientes / siguientes pasos

- Cada usuario cambie su propia contraseña (hoy la cambia el admin).
- Módulo de mantenimientos por horas de motor (tabla `mantenimientos` ya existe).
- Gestionar socios, clientes y embarcaciones desde la UI (hoy son fijos por seed).
