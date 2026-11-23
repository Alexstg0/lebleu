// Ejecuta la lógica ORIGINAL (lib/queries.ts, Next.js) contra la base Postgres de referencia y
// escribe el resultado como JSON. Es la "verdad" contra la que se compara la versión PHP.
// Uso: DATABASE_URL=postgres://... node --experimental-strip-types --import ./pruebas/referencia/registro.mjs \
//        pruebas/referencia/estado_cuenta.mjs > salida.json
import { getEstadoCuenta, listPeriodos } from '../../../lib/queries.ts';

const periodos = await listPeriodos();
const salida = { periodos, estados: {} };
for (const p of periodos) salida.estados[p.id] = await getEstadoCuenta(p.id);
console.log(JSON.stringify(salida));
process.exit(0);
