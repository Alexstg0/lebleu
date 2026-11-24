// Corre la lógica ORIGINAL de cargos recurrentes (lib/recurrentes.ts) sobre Postgres y escribe los cargos que quedan.
// Uso: DATABASE_URL=... node --experimental-strip-types --import ./pruebas/referencia/registro.mjs pruebas/referencia/recurrentes.mjs 2026-11-24
import { listPeriodos } from '../../../lib/queries.ts';
import { ensureRecurrentes } from '../../../lib/recurrentes.ts';
import { getDb } from '../../../lib/db.ts';

const hoy = process.argv[2];
const db = await getDb();
const resumen = [];
for (const p of await listPeriodos()) resumen.push({ periodo: p.id, ...(await ensureRecurrentes(db, p, hoy)) });
const gastos = (await db.query(
  `select periodo_id, to_char(fecha,'YYYY-MM-DD') fecha, proveedor, concepto, categoria, moneda, monto_original, tipo_cambio, monto_mxn,
          comprobante_folio, clave_recurrente
     from gastos_operativos where clave_recurrente is not null order by clave_recurrente`)).rows;
const recibos = (await db.query(`select count(*)::int n from adjuntos a join gastos_operativos g on g.id=a.gasto_id where g.clave_recurrente is not null and a.generado`)).rows[0].n;
console.log(JSON.stringify({ resumen: resumen.sort((a, b) => a.periodo - b.periodo), gastos, recibos_generados: recibos }));
process.exit(0);
