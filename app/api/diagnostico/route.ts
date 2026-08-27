import { NextResponse } from "next/server";
import { getDb } from "@/lib/db";

export async function GET() {
  try {
    const diagnostics: any = {};

    // 1. Verificar DATABASE_URL
    diagnostics.databaseUrl = process.env.DATABASE_URL
      ? `${process.env.DATABASE_URL.substring(0, 40)}...`
      : "no configurada";

    // 2. Probar conexión a BD
    try {
      const db = await getDb();
      const result = await db.query("SELECT NOW() as time");
      diagnostics.dbConnection = { ok: true, time: result.rows[0]?.time };
    } catch (e: any) {
      diagnostics.dbConnection = { ok: false, error: String(e?.message || e) };
    }

    return NextResponse.json(diagnostics);
  } catch (e: any) {
    return NextResponse.json(
      { error: String(e?.message || e) },
      { status: 500 }
    );
  }
}
