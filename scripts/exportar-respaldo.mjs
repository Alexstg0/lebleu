// Exporta la base Postgres (Supabase) al formato de respaldo que lee el importador de PHP (php/scripts/importar_respaldo.php):
// un INSERT por fila, tabla por tabla. Es lo que se corre el día del corte, con la captura congelada.
// Uso:  DATABASE_URL="postgres://..." node scripts/exportar-respaldo.mjs > respaldo.sql
import pg from "pg";

const url = process.env.DATABASE_URL;
if (!url) { console.error("Falta DATABASE_URL."); process.exit(1); }
pg.types.setTypeParser(1082, (v) => v); // date: texto AAAA-MM-DD tal cual, sin zona horaria de por medio

const pool = new pg.Pool({ connectionString: url, ssl: /localhost|127\.0\.0\.1/.test(url) ? false : { rejectUnauthorized: false } });
const literal = (v) => {
  if (v === null || v === undefined) return "NULL";
  if (typeof v === "boolean") return v ? "TRUE" : "FALSE";
  if (typeof v === "number" || typeof v === "bigint") return String(v);
  if (v instanceof Date) return `'${v.toISOString()}'`;
  if (Buffer.isBuffer(v)) return `'${v.toString("base64")}'`;
  const t = typeof v === "object" ? JSON.stringify(v) : String(v);
  return `'${t.replace(/'/g, "''")}'`;
};

const tablas = (await pool.query(
  `select table_name from information_schema.tables where table_schema = 'public' and table_type = 'BASE TABLE' order by 1`
)).rows.map((r) => r.table_name);

console.log(`-- Respaldo generado ${new Date().toISOString().slice(0, 10)}`);
console.log("SET session_replication_role = replica;");
for (const t of tablas) {
  // Las columnas generadas (monto_mxn, total…) no se exportan: la base destino las calcula sola.
  const generadas = (await pool.query(
    `select column_name from information_schema.columns where table_schema = 'public' and table_name = $1 and is_generated = 'ALWAYS'`, [t]
  )).rows.map((r) => r.column_name);
  const { rows, fields: todos } = await pool.query(`select * from "${t}" order by 1`);
  const fields = todos.filter((f) => !generadas.includes(f.name));
  console.log(`\n-- Tabla: ${t} (${rows.length} filas)`);
  const cols = fields.map((f) => `"${f.name}"`).join(", ");
  for (const r of rows) console.log(`INSERT INTO "${t}" (${cols}) VALUES (${fields.map((f) => literal(r[f.name])).join(", ")});`);
}
await pool.end();
