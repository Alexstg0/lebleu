# Reglas de negocio

## Reparto de gastos e ingresos

| Concepto | Regla | Dónde vive |
|---|---|---|
| **Gastos operativos** (`gastos_operativos`) | 50/50 entre los dos socios (según `socios.porcentaje`, que hoy es 50/50 pero es configurable por socio). | Vista `v_balance_operativo` |
| **Gastos variables** (costos de viaje: combustible, marinero, consumibles) | 100% al socio (`viajes.socio_id`) cuya familia usó la embarcación. No se reparten. | Vista `v_variable_por_socio` |
| **Extraordinarios** (`extraordinarios`: muelle anual, refacciones grandes, varadas) | 50/50, pero con **liquidación separada**, fuera del `balance_operativo` normal — se paga aparte y se marca `liquidado`. | Vista `v_extraordinarios_por_socio`, tabla `abonos_extraordinarios` |
| **Renta a terceros** (`ingresos_renta`) | La utilidad (`monto − costos_asociados`) se reparte 50/50 entre los socios; no afecta sus aportaciones. | Vista `v_balance_operativo` (columna `utilidad_renta`) |

### Fórmula del balance operativo

```
balance_operativo = saldo_inicio
                   + aportaciones
                   − gastos_generales (reparto % de gastos_operativos)
                   − gastos_variables (100% del socio, viajes)
                   + utilidad_renta (reparto % de ingresos_renta)
```

Se calcula en la vista `v_balance_operativo` (`db/schema.sql`,
redefinida en `db/migraciones.sql`) y se expone en `lib/queries.ts::getEstadoCuenta()`.

### Reparto "sin centavo fantasma"

Cuando un total no es divisible exacto entre los porcentajes de reparto
(ej. 50.005 y 49.995 redondeados a centavos), la suma de las partes
redondeadas puede no cuadrar con el total original. Para evitarlo, tanto
`v_balance_operativo` como `v_extraordinarios_por_socio` usan el mismo
patrón:

- Al **socio de `id` menor** se le calcula su parte con `ROUND(total *
  porcentaje/100, 2)`.
- Al **socio de `id` mayor** (`= (SELECT MAX(id) FROM socios)`) se le
  asigna el **resto**: `total − parte_del_otro_socio`.

Así la suma de las partes es siempre exactamente igual al total, sin
importar el redondeo. Si se agrega un tercer socio o un nuevo reparto
porcentual en otra parte del código, replica este mismo patrón de "resto al
último" en vez de redondear cada parte por separado.

## Periodos

- Un periodo (`periodos`) representa un mes/año para una embarcación
  (`UNIQUE (embarcacion_id, anio, mes)`).
- **Crear un periodo** (`lib/periodos.ts::crearPeriodo`, usado por
  `POST /api/periodos`): arrastra el `saldo_fin` del periodo anterior (o lo
  calcula desde `v_balance_operativo` si aún no se congeló) como
  `saldo_inicio` de cada socio activo, y **cierra automáticamente** el
  periodo anterior.
- **Cerrar un periodo** (`cerrarPeriodo`, vía `POST
  /api/periodos/estado` con `accion: "cerrar"`): marca `estado = 'cerrado'`
  (bloqueando nuevas escrituras vía `periodoCerradoMsg()`) y archiva un
  snapshot PDF del estado de cuenta en `adjuntos` (reemplazando cualquier
  snapshot previo del mismo periodo).
- **Reabrir un periodo** (`reabrirPeriodo`, `accion: "reabrir"`): vuelve el
  `estado` a `abierto` para permitir correcciones; no borra el PDF
  archivado.
- **Auto-creación del periodo del mes actual**: `ensurePeriodoActual()`
  corre desde el cron diario (`/api/cron/recurrentes`) y crea el periodo si
  no existe, copiando `tipo_cambio`, `precio_litro` y
  `cargo_administracion` del periodo más reciente.

## Cargos recurrentes automáticos

`lib/recurrentes.ts::ensureRecurrentes()` genera dos tipos de cargo en
`gastos_operativos`, cada uno idempotente por una `clave_recurrente` única
(no se duplican aunque se vuelva a correr):

1. **Nómina semanal del capitán** — `NOMINA_CAPITAN = $8,560 MXN`, un cargo
   por cada **viernes** transcurrido del mes (categoría `nomina`, proveedor
   fijo "Gabriel Preciado", clave `nom-<fecha>`).
2. **Cargo administrativo mensual** — `CARGO_ADMIN = $6,000 MXN`, uno por
   mes (categoría `admin`, proveedor "SOLMEX Administración", clave
   `adm-<mes>`).

Ambos:
- Nunca se generan con fecha anterior a `RECURRENTES_DESDE = "2026-07-02"`
  (piso duro para no crear cargos retroactivos antes de esa fecha).
- Se generan solo hasta la fecha de "hoy" (zona horaria
  `America/Mazatlan`, la de la embarcación) — no se adelantan al futuro.
- Generan automáticamente su recibo PDF (`generarReciboGasto`) al crearse.

Se disparan de dos formas: automáticamente por el **cron diario**
(`vercel.json`, `0 14 * * *` UTC = 07:00 hora Baja California Sur, sobre
`/api/cron/recurrentes`, protegido con `CRON_SECRET`), o manualmente por un
admin desde `POST /api/recurrentes` (botón "Generar ahora").

## Roles y permisos

| Rol | Puede | No puede |
|---|---|---|
| **admin** | Todo: estado de cuenta, capturar, movimientos, periodos, calendario, usuarios, auditoría, reportes, caja chica, bitácora. | — |
| **capitán** | Calendario, bitácora, registrar/editar viajes, caja chica, catálogo de marineros. | Ver montos financieros, estado de cuenta, gastos operativos/extraordinarios, aportaciones, usuarios, auditoría. |
| **socio** | Ver estado de cuenta, análisis, reportes, calendario (solo lectura de reservas). | Capturar cualquier movimiento, ver/editar caja chica o bitácora, administrar usuarios. |

La protección se aplica en **dos capas** para cada funcionalidad: en la
página (`requireUser(roles)`, redirige) y en la API que la respalda
(`apiGuard(roles)`, responde 401/403) — un capitán o socio no puede lograr
una escritura fuera de su alcance mandando la petición HTTP directamente,
aunque la UI se lo oculte.

El admin inicial se crea desde `scripts/setup-db.mjs` con `ADMIN_EMAIL` /
`ADMIN_PW`. A partir de ahí, el admin da de alta capitán y socios desde
`/usuarios`; un usuario `socio` se liga a su fila en `socios` vía
`usuarios.socio_id` para que el sistema sepa qué balance mostrarle.

## Otras reglas codificadas

- El capitán captura combustible en la UI en **galones**; el sistema
  convierte a litros (`× 3.78541`) antes de guardar (ver formularios en
  `app/capturar/forms/`).
- Los `viajes` marcados `bandera = true` se resaltan como anómalos en la
  bitácora (⚠) — es una señal manual, no una validación automática.
- Un viaje con `es_renta = true` no tiene `socio_id` (renta a un tercero);
  su costo no entra al reparto de gastos variables de ningún socio.
