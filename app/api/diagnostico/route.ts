import { NextResponse } from "next/server";
import { getDb } from "@/lib/db";
import * as dns from "dns";
import { promisify } from "util";

const resolve4 = promisify(dns.resolve4);

export async function GET() {
  try {
    const diagnostics: any = {};

    // 1. Verificar DATABASE_URL
    diagnostics.databaseUrl = process.env.DATABASE_URL
      ? `${process.env.DATABASE_URL.substring(0, 30)}...`
      : "no configurada";

    // 2. Probar DNS
    try {
      const ips = await resolve4("db.pzjxdelcjuivsheavzkh.supabase.co");
      diagnostics.dns = { ok: true, ips };
    } catch (e: any) {
      diagnostics.dns = { ok: false, error: e?.message };
    }

    // 3. Probar conexión a BD
    try {
      const db = await getDb();
      const result = await db.query("SELECT NOW() as time");
      diagnostics.dbConnection = { ok: true, time: result.rows[0]?.time };
    } catch (e: any) {
      diagnostics.dbConnection = { ok: false, error: e?.message };
    }

    return NextResponse.json(diagnostics);
  } catch (e: any) {
    return NextResponse.json(
      { error: e?.message || e },
      { status: 500 }
    );
  }
}
