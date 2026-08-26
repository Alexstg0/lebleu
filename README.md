# Le Bleu — Control de Embarcación

App web para el control financiero y operativo de la embarcación **Le Bleu**
(Arrendadora Acma S de RL de CV). Reproduce el estado de cuenta mensual y permite
capturar gastos y viajes.

## Stack

- **Next.js 15** (App Router, React 19, TypeScript)
- **PGlite** — PostgreSQL embebido en Node. Cero instalación: la base vive en `./.pgdata`
  y se siembra sola en el primer arranque con `db/schema.sql` + `db/seed_mayo_2026.sql`.

## Correr en local

```bash
npm install
npm run dev      # http://localhost:3000
```

En el primer arranque se crean las tablas y se cargan los datos de Mayo 2026.

## Estructura

```
db/
  schema.sql            11 tablas + mantenimientos + 3 vistas
  seed_mayo_2026.sql    datos de Mayo 2026 (validado al centavo)
lib/
  db.ts                 conexión PGlite + auto-init
  queries.ts            consultas del estado de cuenta
  format.ts             formato de moneda/fechas
app/
  page.tsx              dashboard / estado de cuenta
  capturar/             formularios de gasto operativo y viaje
  api/                  route handlers (POST)
```

## Reglas de negocio

- **Gastos operativos** → 50/50 entre socios.
- **Gastos variables** (costos de viaje) → 100% al socio que usó el barco.
- **Extraordinarios** → 50/50, liquidación separada (fuera del balance operativo).
- `balance_operativo = saldo_inicio + aportaciones − operativos×50% − variables + utilidad_renta×50%`

## Pasar a Postgres hospedado (futuro)

El esquema es Postgres estándar. Para usar un servidor real (Neon, Supabase, RDS),
ejecuta `db/schema.sql` ahí y cambia `lib/db.ts` por un cliente `pg` apuntando a la
`DATABASE_URL`. El resto de la app no cambia.

## Funciones

- **Estado de cuenta** (`/`) — dashboard mensual con selector de periodo.
- **Capturar** (`/capturar`) — pestañas: viaje, gasto operativo, aportación, extraordinario, renta.
- **Calendario** (`/calendario`) — agenda de **reservas** (salidas). Agregar/editar/borrar
  tocando un día o una reserva. Muestra la **hora de salida** de las próximas y la
  **duración** de las que ya pasaron. Sin montos. Lo usan admin y capitán.
- **Movimientos** (`/movimientos`) — listado de todo lo capturado del periodo con
  **editar** (formulario inline) y **borrar** cada movimiento; los balances se recalculan solos.
- **Periodos** (`/periodos`) — abrir un mes nuevo; arrastra el saldo del mes anterior
  (que se cierra automáticamente) como saldo de inicio de cada socio.
- **Exportar a PDF** — botón flotante en el estado de cuenta; usa la impresión del
  navegador ("Guardar como PDF") conservando los colores de marca. El archivo toma el
  nombre del periodo (ej. *Le Bleu — Estado de Cuenta Mayo 2026*).

## Cuentas y roles

Login en `/login`. El administrador inicial se crea al ejecutar
`scripts/setup-db.mjs`, con el correo y la contraseña definidos en las variables
de entorno `ADMIN_EMAIL` y `ADMIN_PW` (mínimo 8 caracteres).

Roles:
- **admin** — todo (estado de cuenta, capturar, movimientos, periodos, calendario, usuarios).
- **capitán** — solo calendario y registrar viajes (no ve finanzas).
- **socio** — solo ver el estado de cuenta.

El admin da de alta capitán y socios desde **Usuarios**. La protección es por página
y también a nivel de API (un capitán/socio no puede mandar peticiones que no le tocan).

## Pendientes / siguientes pasos

- Cada usuario cambie su propia contraseña (hoy la cambia el admin).
- Módulo de mantenimientos por horas de motor (tabla ya existe).
- Gestionar socios, clientes y embarcaciones desde la UI.
- Autenticación.
