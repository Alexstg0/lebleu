import { NextRequest, NextResponse } from "next/server";
import { getDb } from "@/lib/db";
import { apiGuard } from "@/lib/auth";
import { generarReciboGasto } from "@/lib/recurrentes";

export const dynamic = "force-dynamic";

// Genera (o regenera) el recibo PDF de un gasto operativo y lo adjunta. Solo admin.
export async function POST(req: NextRequest) {
  try {
    if (!(await apiGuard(["admin"]))) return NextResponse.json({ ok: false, error: "No autorizado." }, { status: 403 });
    const b = await req.json();
    if (!b.gasto_id) return NextResponse.json({ ok: false, error: "Falta el gasto." }, { status: 400 });
    const db = await getDb();
    const ok = await generarReciboGasto(db, Number(b.gasto_id));
    if (!ok) return NextResponse.json({ ok: false, error: "Gasto no encontrado." }, { status: 404 });
    return NextResponse.json({ ok: true });
  } catch (e: any) {
    console.error("api error:", e?.message || e);
    return NextResponse.json({ ok: false, error: "No se pudo generar el recibo." }, { status: 400 });
  }
}
