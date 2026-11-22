# Le Bleu: qué es y qué hace

## En una frase

Le Bleu es la aplicación web con la que se controla el dinero y la operación de la embarcación
**Le Bleu** (Arrendadora Acma S de RL de CV), que pertenece a dos socios (dos familias). Lleva el
estado de cuenta mensual de cada socio y reparte los gastos entre ellos con reglas fijas, sin
hojas de cálculo.

Es un sistema de un solo cliente: una embarcación, dos socios. Está en español (México) y se usa
en `app.lebleu.mx`.

## Para qué sirve

- Saber **cuánto debe o tiene a favor cada socio** al cierre de cada mes.
- Registrar **quién usó el barco, cuándo y cuánto costó cada salida**.
- Llevar los **gastos, aportaciones, rentas a terceros, caja chica y reservas** en un solo lugar.
- Generar **comprobantes y reportes en PDF** y dejar **rastro de quién hizo qué**.

## Quién lo usa (3 roles)

| Rol | Qué hace |
|---|---|
| **Administrador** | Todo: captura movimientos, abre y cierra periodos, da de alta usuarios, ve auditoría y reportes. |
| **Capitán** | Registra los viajes (con lecturas de horómetro), maneja la caja chica y ve el calendario. **No ve montos financieros.** |
| **Socio** | Solo lectura: su estado de cuenta, análisis, reportes y el calendario de reservas. |

Cada rol está protegido en la pantalla y también en el servidor: aunque alguien mande la petición
a mano, no puede hacer lo que su rol no permite.

## Qué hace, pantalla por pantalla

- **Estado de cuenta:** tablero mensual con el balance de cada socio y selector de periodo.
- **Capturar:** formularios de viaje, gasto operativo, insumo, aportación, gasto extraordinario y
  renta. El capitán solo ve el de viaje.
- **Bitácora:** historial de viajes con las lecturas de horómetro por motor, filtrable por mes.
  Los viajes marcados con bandera se resaltan como anómalos.
- **Calendario:** reservas (salidas) sin montos, visible para admin, capitán y socio.
- **Caja chica:** control de efectivo por número de caja.
- **Movimientos:** todo lo capturado en el periodo, con editar y borrar; los balances se
  recalculan solos.
- **Periodos:** abrir un mes nuevo. Arrastra el saldo del mes anterior como saldo inicial de cada
  socio y cierra el anterior.
- **Análisis:** comparativas entre periodos.
- **Reportes (PDF):** estado de cuenta, soportes, recibos de marineros, caja chica y reporte
  completo.
- **Usuarios y Auditoría** (solo admin): altas de usuarios y bitácora de quién hizo qué.

## Cómo se reparten los números

| Concepto | Regla |
|---|---|
| Gastos operativos | 50/50 entre los socios (el porcentaje es configurable por socio). |
| Gastos variables de viaje (combustible, marinero, consumibles) | 100% al socio que usó el barco. |
| Extraordinarios (muelle, refacciones grandes, varada) | 50/50, pero se liquidan aparte, fuera del balance normal. |
| Renta a terceros | La utilidad (monto menos costos) se reparte 50/50 y no toca las aportaciones. |

```
balance = saldo inicial + aportaciones
          - gastos operativos (su %) - gastos variables (los suyos) + utilidad de renta (su %)
```

El reparto está hecho para que la suma de las partes sea **exactamente** el total: al socio de
menor id se le calcula su parte redondeada y al de mayor id se le asigna el resto, así nunca
sobra ni falta un centavo.

## Lo que hace solo

Cada día a las 07:00 (hora de Baja California Sur) el sistema genera automáticamente, sin duplicar:

- La **nómina semanal del capitán** ($8,560 MXN por cada viernes transcurrido).
- El **cargo administrativo mensual** ($6,000 MXN).
- El **periodo del mes actual**, si todavía no existe.

Cada cargo automático genera su recibo en PDF. Un administrador también puede dispararlo a mano
desde "Generar ahora".

## Reglas que conviene recordar

- Un periodo **cerrado** no se puede modificar; se puede reabrir para corregir. Al cerrarlo se
  guarda un PDF del estado de cuenta.
- Los montos en dólares se guardan con su tipo de cambio y el valor en pesos se calcula solo.
- El capitán captura el combustible en galones y el sistema lo convierte a litros.
- Toda alta, edición o baja queda registrada en la auditoría.

## Tamaño y tecnología (hoy)

- Next.js 15 con TypeScript, PostgreSQL en Supabase, PDFs con `pdf-lib`, desplegado en Vercel.
- 12 pantallas, 27 rutas de API, 5 generadores de PDF, 25 tablas y 3 vistas.
- Sin ORM: SQL directo con parámetros. Sesiones propias con cookie y contraseñas con scrypt.

## Hacia dónde va

Se está migrando a **PHP + MySQL 8 en el servidor propio de Solmex** (Windows/IIS), con la misma
interfaz y una estructura por módulos. Ver [`MIGRACION-PHP.md`](MIGRACION-PHP.md).

## Dónde leer más

[`README.md`](../README.md) · [`BUSINESS-RULES.md`](BUSINESS-RULES.md) · [`DATABASE.md`](DATABASE.md) ·
[`API.md`](API.md) · [`MIGRACION-PHP.md`](MIGRACION-PHP.md)
