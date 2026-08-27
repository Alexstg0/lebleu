# API — referencia de rutas

Todas las rutas viven bajo `app/api/**/route.ts` (Next.js Route Handlers).
Convenciones comunes, salvo que se indique lo contrario:

- Autorización con `apiGuard(roles)` (`lib/auth.ts`) — si el usuario no
  tiene sesión o no cumple el rol, la ruta responde `401`/`403` y **no**
  ejecuta la mutación.
- Las rutas que escriben sobre un `periodo_id` llaman a
  `periodoCerradoMsg()` (`lib/audit.ts`) primero y rechazan la escritura si
  el periodo está `cerrado`.
- Toda mutación exitosa llama a `auditar(...)` (`lib/audit.ts`), que
  inserta en `auditoria` sin poder tumbar la respuesta si falla.
- Los montos van en `NUMERIC` de Postgres; el body JSON los manda como
  `number`.

## CRUD de movimientos (rol `admin` salvo que se indique)

| Ruta | Métodos | Rol | Tabla | Notas |
|---|---|---|---|---|
| `/api/gastos-operativos` | POST/PATCH/DELETE | admin | `gastos_operativos` | Body: `periodo_id, fecha, proveedor, concepto, categoria, moneda, monto_original, tipo_cambio, comprobante_folio`. |
| `/api/gastos-operativos/recibo` | POST | admin | `adjuntos` | `{ gasto_id }` → (re)genera el PDF de recibo vía `generarReciboGasto()` (`lib/recurrentes.ts`) y lo adjunta. |
| `/api/extraordinarios` | POST/PATCH/DELETE | admin | `extraordinarios` | Mismos campos que gastos operativos + `categoria` propia (`muelle\|refaccion\|varada\|otro`). |
| `/api/extraordinarios/pagado` | POST | admin | `extraordinarios` | `{ id, liquidado }` — marca pagado/pendiente. |
| `/api/aportaciones` | POST/PATCH/DELETE | admin | `aportaciones` | `{ periodo_id, socio_id, fecha, monto, metodo, referencia }`. |
| `/api/abonos-extraordinarios` | POST/PATCH/DELETE | admin | `abonos_extraordinarios` | Pagos de cada socio hacia la liquidación de extraordinarios. |
| `/api/ingresos-renta` | POST/PATCH/DELETE | admin | `ingresos_renta` | `{ periodo_id, cliente, fecha, monto, costos_asociados }`. |
| `/api/insumos` | POST/PATCH/DELETE | admin | `insumos` | Tras cada cambio, `syncViaje()` recalcula `viajes.costo_consumibles` del viaje afectado (y del anterior si `viaje_id` cambió). |
| `/api/viajes` | POST/PATCH · DELETE | admin, capitán (POST/PATCH) · admin (DELETE) | `viajes`, `viaje_horometros`, `motores` | Body completo del viaje + `horometros[]`; `saveHorometros()` reescribe `viaje_horometros` y actualiza `motores.horometro_actual`. |
| `/api/viajes/recibo` | GET | admin | — | `?id=` → PDF de pago al marinero de un viaje (`generarReciboViaje`). |
| `/api/caja-chica` | POST/PATCH/DELETE | admin, capitán | `caja_chica` | `{ fecha, tipo, caja_numero, factura, proveedor, concepto, monto, abono, observaciones }`. No depende de periodo. |
| `/api/marineros` | POST | admin, capitán | `marineros` | `{ nombre }` — crea o reactiva (dedupe case-insensitive). |
| `/api/reservas` | POST/PATCH/DELETE | admin, capitán, socio | `reservas` | `{ fecha, hora, cliente, socio_id, num_personas, duracion_horas, notas }`. Sin periodo, sin auditoría. |

## Adjuntos

| Ruta | Métodos | Rol | Notas |
|---|---|---|---|
| `/api/adjuntos` | POST/DELETE | admin, capitán (solo caja chica) | POST sube PDF o imagen (JPG/PNG se convierte a PDF de 1 página con `pdf-lib`), ligado a **uno** de `gasto_id/insumo_id/extraordinario_id/caja_id`; límite ~3 MB, valida MIME real. DELETE por `?id=`. |
| `/api/adjuntos/[id]` | GET | cualquier sesión (capitán limitado a adjuntos de caja chica) | Sirve el PDF (`base64` → buffer) con `Content-Type: application/pdf` forzado y `X-Content-Type-Options: nosniff`. |

## Periodos

| Ruta | Métodos | Rol | Notas |
|---|---|---|---|
| `/api/periodos` | POST | admin | Crea un periodo con `crearPeriodo()` (`lib/periodos.ts`): arrastra `saldo_fin` del periodo anterior como `saldo_inicio` de cada socio y cierra automáticamente el periodo anterior. |
| `/api/periodos/estado` | POST | admin | `{ id, accion }` — `cerrar` (archiva snapshot PDF del estado de cuenta vía `cerrarPeriodo()`) o `reabrir`. |
| `/api/recurrentes` | POST | admin | Botón manual "Generar ahora": corre `ensureRecurrentes()` para todos los periodos abiertos o uno (`{ periodoId }`). Idempotente. |

## Reportes (PDF)

| Ruta | Métodos | Rol | Query | Genera |
|---|---|---|---|---|
| `/api/reportes/caja-chica` | GET | admin, capitán | `caja` (número o `todas`), `responsable` | Balance de caja chica + recibos adjuntos (`generarReporteCaja`, `lib/reporte-caja.ts`). |
| `/api/reportes/marineros` | GET | admin, socio | `periodo` | Resumen de pagos a marineros + recibos individuales (`generarReporteMarineros`, `lib/reporte-marineros.ts`). |
| `/api/reportes/soportes` | GET | admin, socio | `periodo` | Portada por categoría de gasto + todos los PDFs adjuntos del periodo (`generarReporteSoportes`, `lib/reporte-soportes.ts`). |
| `/api/reportes/completo` | GET | admin, socio | `periodo` (requerido) | Fusiona estado de cuenta + soportes + reporte de marineros en un solo PDF (`pdf-lib`). |
| `/api/manual` | GET | cualquier sesión | `rol` (solo admin puede pedir el de otro rol) | Manual de usuario en PDF por rol, desde la tabla `manuales`. |

## Usuarios

| Ruta | Métodos | Rol | Notas |
|---|---|---|---|
| `/api/usuarios` | POST/PATCH/DELETE | admin (`requireUser`, redirige en vez de 403 — ojo, es inconsistente con el resto que usa `apiGuard`) | POST crea (hash de contraseña, mínimo 6 caracteres); PATCH cambia contraseña (invalida sesiones del usuario) y/o `activo`; DELETE por `?id=`, bloquea auto-borrado. |

## Autenticación

| Ruta | Métodos | Rol | Notas |
|---|---|---|---|
| `/api/login` | POST | pública | Rate limit en BD (`login_intentos`, máx. 8 intentos/10 min por IP+email) y comparación de tiempo constante contra un hash señuelo cuando el usuario no existe (evita enumeración por timing). Crea sesión de 30 días y cookie `sid` `httpOnly`. |
| `/api/logout` | POST | pública (requiere cookie) | Borra la fila de `sesiones` del token actual y limpia la cookie. |

## Cron

| Ruta | Métodos | Auth | Notas |
|---|---|---|---|
| `/api/cron/recurrentes` | GET | Header `Authorization: Bearer <CRON_SECRET>` (503 si `CRON_SECRET` no está definido, 401 si no coincide — **no** usa sesión de usuario) | Disparada por Vercel Cron diario a las 14:00 UTC (`vercel.json` → 07:00 hora Baja California Sur). Corre `ensurePeriodoActual()` (crea el periodo del mes si falta) y luego `ensureRecurrentes()` sobre cada periodo abierto: genera la nómina semanal del capitán (viernes) y el cargo administrativo mensual, ambos idempotentes por clave única. Ver [`BUSINESS-RULES.md`](BUSINESS-RULES.md#cargos-recurrentes-automáticos). |

## Páginas y su protección

Todas usan `requireUser(roles?)` en el `page.tsx` (Server Component); sin
rol pasado, solo exige sesión activa.

| Ruta | Rol | Contenido |
|---|---|---|
| `/` | admin, socio | Estado de cuenta del periodo (dashboard principal). |
| `/analisis` | admin, socio | Comparativas entre periodos (gráficas SVG hechas a mano). |
| `/auditoria` | admin | Últimas 300 filas de `auditoria`. |
| `/bitacora` | admin, capitán | Historial de viajes con horómetros, filtrable por mes. |
| `/caja-chica` | admin, capitán | Ledger de caja chica. |
| `/calendario` | admin, capitán, socio | Calendario de reservas. |
| `/capturar` | admin, capitán | Formularios de captura (el capitán solo ve "Registrar viaje"). |
| `/login` | pública | Login; redirige al home del rol si ya hay sesión. |
| `/movimientos` | admin | Listado/edición/borrado de todo lo capturado en el periodo. |
| `/periodos` | admin | Alta y apertura/cierre de periodos. |
| `/reportes` | admin, socio | Hub de descargas de PDF. |
| `/usuarios` | admin | Alta/edición/baja de usuarios. |
