import { NextRequest, NextResponse } from "next/server";
import { getDb } from "@/lib/db";
import { apiGuard } from "@/lib/auth";
import { crearPeriodo } from "@/lib/periodos";
import { auditar } from "@/lib/audit";

export async function POST(req: NextRequest) {
  try {
    const user = await apiGuard(["admin"]);
    if (!user) return NextResponse.json({ ok: false, error: "No autorizado." }, { status: 403 });
    const b = await req.json();
    const db = await getDb();
    const periodoId = await crearPeriodo(db, b);
    await auditar(db, user, "crear", "periodos", periodoId, `Creó el periodo ${b.mes}/${b.anio}`);
    return NextResponse.json({ ok: true, id: periodoId });
  } catch (e: any) {
    console.error("api error:", e?.message || e);
    return NextResponse.json({ ok: false, error: "Ocurrió un error al procesar la solicitud." }, { status: 400 });
  }
}
