// Genera con el código ORIGINAL (pdf-lib) los PDF de referencia: estado de cuenta, soportes, marineros, completo,
// recibos de viaje y de gasto, y caja chica. Escribe en la carpeta SALIDA. Usa una BD desechable (escribe adjuntos).
// Uso: DATABASE_URL=... SALIDA=/ruta node --experimental-strip-types --import ./pruebas/referencia/registro.mjs pruebas/referencia/pdfs.mjs
import fs from 'node:fs';
import { PDFDocument } from 'pdf-lib';
import { getDb } from '../../../lib/db.ts';
import { generarEstadoCuentaPDF } from '../../../lib/reporte-estado.ts';
import { generarReporteSoportes } from '../../../lib/reporte-soportes.ts';
import { generarReporteMarineros, generarReciboViaje } from '../../../lib/reporte-marineros.ts';
import { generarReporteCaja } from '../../../lib/reporte-caja.ts';
import { generarReciboGasto } from '../../../lib/recurrentes.ts';

const salida = process.env.SALIDA;
fs.mkdirSync(salida, { recursive: true });
const db = await getDb();
const guardar = (nombre, bytes) => { if (bytes) fs.writeFileSync(`${salida}/${nombre}.pdf`, Buffer.from(bytes)); else console.log('sin contenido:', nombre); };

for (const p of (await db.query('select id from periodos order by id')).rows) {
  const e = await generarEstadoCuentaPDF(db, p.id);
  const s = await generarReporteSoportes(db, p.id);
  const sSin = await generarReporteSoportes(db, p.id, false);
  const m = await generarReporteMarineros(db, p.id);
  guardar(`estado_${p.id}`, e); guardar(`soportes_${p.id}`, s); guardar(`soportes_sin_marineros_${p.id}`, sSin); guardar(`marineros_${p.id}`, m);
  const partes = [e, sSin, m].filter(Boolean);
  if (partes.length) {
    const merged = await PDFDocument.create();
    for (const b of partes) { const src = await PDFDocument.load(b, { ignoreEncryption: true }); (await merged.copyPages(src, src.getPageIndices())).forEach((pg) => merged.addPage(pg)); }
    guardar(`completo_${p.id}`, await merged.save());
  }
}
for (const v of (await db.query('select id from viajes order by id limit 6')).rows) guardar(`recibo_viaje_${v.id}`, await generarReciboViaje(db, v.id));
guardar('caja_todas', await generarReporteCaja(db, { caja: null, responsable: 'Gabriel Preciado' }));
for (const c of (await db.query('select distinct caja_numero from caja_chica where caja_numero is not null order by 1')).rows) guardar(`caja_${c.caja_numero}`, await generarReporteCaja(db, { caja: c.caja_numero, responsable: 'Gabriel Preciado' }));
for (const g of (await db.query(`select id from gastos_operativos where categoria in ('nomina','admin','servicio') order by id limit 4`)).rows) {
  await generarReciboGasto(db, g.id);
  const a = (await db.query('select datos from adjuntos where gasto_id=$1 and generado=true order by id desc limit 1', [g.id])).rows[0];
  if (a) guardar(`recibo_gasto_${g.id}`, Buffer.from(a.datos, 'base64'));
}
process.exit(0);
