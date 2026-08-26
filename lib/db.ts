import { Pool } from "pg";
import { SUPABASE_CA } from "./supabase-ca";

// Postgres hospedado (Supabase en producción) vía DATABASE_URL.
// El esquema y los datos se cargan una sola vez con `node scripts/setup-db.mjs`
// (no se auto-inicializa en cada request, para que funcione en serverless).

const g = globalThis as unknown as { __pgpool?: Pool };

function pool(): Pool {
  if (!g.__pgpool) {
    const url = process.env.DATABASE_URL;
    if (!url) throw new Error("Falta DATABASE_URL");
    // SSL estricto: el pooler de Supabase usa una CA propia (incluida en lib/supabase-ca.ts).
    const ssl = url.includes("localhost") || url.includes("127.0.0.1")
      ? false
      : url.includes("supabase.co")
        ? { ca: SUPABASE_CA, rejectUnauthorized: true }
        : { rejectUnauthorized: true };
    g.__pgpool = new Pool({
      connectionString: url,
      ssl,
      max: 5,
    });
  }
  return g.__pgpool;
}

type Db = {
  query: <T = any>(sql: string, params?: any[]) => Promise<{ rows: T[] }>;
  exec: (sql: string) => Promise<unknown>;
};

export async function getDb(): Promise<Db> {
  const p = pool();
  return {
    query: ((sql: string, params?: any[]) => p.query(sql, params)) as Db["query"],
    exec: (sql: string) => p.query(sql),
  };
}
