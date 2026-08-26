// Inicializa una base Postgres (Neon) con el esquema y los datos de Le Bleu.
// Uso:  DATABASE_URL="postgres://..." node scripts/setup-db.mjs
// Es idempotente: se puede correr varias veces sin duplicar datos.

import { Pool } from "pg";
import fs from "node:fs";
import path from "node:path";
import { scryptSync, randomBytes } from "node:crypto";

// Carga DATABASE_URL desde .env si no está en el entorno.
if (!process.env.DATABASE_URL && fs.existsSync(".env")) {
  for (const line of fs.readFileSync(".env", "utf8").split(/\r?\n/)) {
    const m = line.match(/^\s*DATABASE_URL\s*=\s*(.+?)\s*$/);
    if (m) process.env.DATABASE_URL = m[1].replace(/^["']|["']$/g, "");
  }
}

const url = process.env.DATABASE_URL;
if (!url) {
  console.error("Falta DATABASE_URL. Ej: DATABASE_URL=\"postgres://...\" node scripts/setup-db.mjs");
  process.exit(1);
}

const pool = new Pool({
  connectionString: url,
  ssl: url.includes("localhost") || url.includes("127.0.0.1") ? false : { rejectUnauthorized: false },
});
const dir = path.join(process.cwd(), "db");
const read = (f) => fs.readFileSync(path.join(dir, f), "utf8");
const hash = (pw) => {
  const s = randomBytes(16).toString("hex");
  return `${s}:${scryptSync(pw, s, 64).toString("hex")}`;
};

const ADMIN = {
  nombre: process.env.ADMIN_NOMBRE || "Kevin Flores",
  email: process.env.ADMIN_EMAIL || "int.financiera@solmex.mx",
  pw: process.env.ADMIN_PW, // sin default: se exige por variable de entorno (ver abajo)
};

async function main() {
  const c = await pool.connect();
  try {
    const ex = await c.query("select to_regclass('public.periodos') as t");
    if (!ex.rows[0].t) {
      console.log("• Creando esquema + datos de mayo…");
      await c.query(read("schema.sql"));
      await c.query(read("seed_mayo_2026.sql"));
    } else {
      console.log("• Esquema ya existe — omito schema + seed.");
    }

    console.log("• Auth + tabla reservas + migraciones…");
    await c.query(read("auth.sql"));
    await c.query(read("reservas.sql"));
    await c.query(read("migraciones.sql"));

    const adm = await c.query("select 1 from usuarios where rol='admin' limit 1");
    if (adm.rows.length === 0) {
      if (!ADMIN.pw || String(ADMIN.pw).length < 8) {
        throw new Error("Define ADMIN_PW (mínimo 8 caracteres) para crear el admin inicial. Ej: ADMIN_PW=\"...\" node scripts/setup-db.mjs");
      }
      await c.query(
        "insert into usuarios (nombre,email,password_hash,rol) values ($1,$2,$3,'admin')",
        [ADMIN.nombre, ADMIN.email, hash(ADMIN.pw)]
      );
      console.log(`• Admin creado: ${ADMIN.email}`);
    } else {
      console.log("• Admin ya existe.");
    }

    const rv = await c.query("select count(*)::int n from reservas");
    if (rv.rows[0].n === 0) {
      await c.query(read("reservas_seed.sql"));
      console.log("• Reservas sembradas.");
    } else {
      console.log(`• Reservas ya existen (${rv.rows[0].n}).`);
    }

    const p = (await c.query("select count(*)::int n from periodos")).rows[0].n;
    const r = (await c.query("select count(*)::int n from reservas")).rows[0].n;
    const u = (await c.query("select count(*)::int n from usuarios")).rows[0].n;
    console.log(`\n✓ Listo.  periodos=${p}  reservas=${r}  usuarios=${u}`);
  } finally {
    c.release();
    await pool.end();
  }
}

main().catch((e) => {
  console.error("ERROR:", e.message);
  process.exit(1);
});
