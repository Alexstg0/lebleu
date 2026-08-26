# Inventario de entrega — Le Bleu (control de embarcación)

**Fecha de entrega:** 2026-08-18
**Entrega:** Kevin Flores
**Sistema:** control operativo y contable de la embarcación Le Bleu
**Titular del sistema:** Arrendadora Acma, S. de R.L. de C.V.
**Producción:** https://lebleu.mx (respaldo: https://control-lebleu.vercel.app)

Este paquete es una **exportación del código fuente del estado actual en producción**.
El proyecto local no está bajo control de versiones, por lo que no hay historial de
commits asociado a esta entrega; el repositorio `github.com/kevincosnerflores-ux/le-bleu`
contiene una versión temprana (junio 2026) que **no** corresponde al sistema actual.

---

## Qué incluye

| Ruta | Contenido |
|---|---|
| `app/` | Aplicación Next.js 15 (App Router): estado de cuenta, captura, movimientos, periodos, bitácora, calendario de reservas, usuarios |
| `app/api/` | Route handlers (POST/PATCH/DELETE) de todos los módulos |
| `lib/` | Conexión a BD, autenticación (scrypt + sesiones), consultas, generadores de reporte, logotipos embebidos |
| `db/` | Esquema SQL completo (`schema.sql`), migraciones, auth, reservas y seeds |
| `scripts/` | `setup-db.mjs` (creación de esquema idempotente) |
| `public/` | Activos estáticos, iconos y favicons |
| `.env.example` | Plantilla de variables de entorno, sin secretos reales |

## Qué NO incluye (y por qué)

| Excluido | Razón |
|---|---|
| `.env`, `.env.neon.bak` | Cadenas de conexión con credenciales de producción. Se entregan por canal seguro aparte. |
| `.pgdata/` | 28 MB de base de datos local embebida (PGlite) con datos operativos. No es parte del código y el sistema en producción no la usa. |
| `.vercel/` | Vinculación a la cuenta de Vercel de despliegue. |
| `node_modules/`, `.next/` | Dependencias y artefactos de compilación; se regeneran. |

---

## Arquitectura

- **Framework:** Next.js 15 + React 19 + TypeScript
- **Base de datos:** PostgreSQL en Supabase (proyecto `le-bleu`), vía *transaction pooler*
- **TLS:** certificado de autoridad de Supabase embebido en `lib/supabase-ca.ts`.
  Sin él, Node rechaza la conexión (`self-signed certificate in certificate chain`).
- **Autenticación:** propia. Sesiones en cookie `httpOnly` + tabla `sesiones`;
  hashing de contraseñas con `scrypt` (`lib/password.ts`); guards en `lib/auth.ts`.
- **Roles:** `admin` (todo), `capitan` (bitácora, registro de viajes, calendario),
  `socio` (estado de cuenta y reservas; sin acceso a finanzas).
  El control de acceso está verificado por página y por API.
- **Despliegue:** Vercel, con `lebleu.mx` como dominio principal.

## Variables de entorno requeridas

Ver `.env.example`. La principal es `DATABASE_URL` (cadena del *pooler* de Supabase).

## Cómo levantarlo

```bash
npm install
cp .env.example .env    # y llenar con la cadena de conexión real
node scripts/setup-db.mjs   # idempotente: crea esquema si no existe
npm run dev
```

**Advertencia operativa:** no ubicar el proyecto dentro de una carpeta sincronizada
por OneDrive. OneDrive bloquea los archivos de `.next` y la aplicación devuelve
error 500 (`EBUSY: resource busy or locked`).

---

## Modelo de negocio implementado (reglas de reparto)

| Concepto | Regla |
|---|---|
| Gastos operativos | 50/50 entre los dos socios |
| Gastos variables (combustible, marinero, consumibles de cada viaje) | 100% al socio cuya familia usó la embarcación |
| Extraordinarios (muelle anual, refacciones) | 50/50, con liquidación separada fuera del balance operativo |
| Renta a cliente externo | Utilidad 50/50; no afecta balance de socios |

`balance_operativo = saldo_inicio + aportaciones − operativos×50% − variables_del_socio + utilidad_renta×50%`

El seed `db/seed_mayo_2026.sql` reproduce el estado de cuenta de mayo 2026 al centavo
y sirve como prueba de regresión del modelo de cálculo.

Otras reglas codificadas:
- El capitán captura combustible en **galones**; el sistema convierte a litros (×3.78541).
- El capitán no ve ni captura precios ni costos.
- Los sábados se reservan alternados entre las dos familias, una semana cada una.

## Estado funcional

Sistema **en producción y en uso**. Pendientes conocidos: cambio de contraseña por el
propio usuario, módulo de mantenimientos y gestión de catálogos (socios, clientes,
embarcaciones).

## Pendiente de transferencia (fuera de este paquete)

1. Proyecto de Vercel que aloja `lebleu.mx`.
2. Proyecto de Supabase `le-bleu` (base de datos y respaldos diarios).
3. Dominio `lebleu.mx` (registrado en Hostinger).
4. Respaldo histórico en Neon (proyecto `lebleu`), aún vigente.
