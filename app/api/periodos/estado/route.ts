import { NextRequest, NextResponse } from "next/server";
import { getDb } from "@/lib/db";
import { apiGuard } from "@/lib/auth";
import { cerrarPeriodo, reabrirPeriodo } from "@/lib/periodos";
import { auditar } from "@/lib/audit";

export const dynamic = "force-dynamic";

// Cerrar (con archivo PDF) o reabrir un periodo. Solo admin.
export async function POST(req: NextRequest) {
  try {
    const user = await apiGuard(["admin"]);
    if (!user) return NextResponse.json({ ok: false, error: "No autorizado." }, { status: 403 });
    const b = await req.json();
    const id = Number(b.id);
    if (!id) return NextResponse.json({ ok: false, error: "Falta el periodo." }, { status: 400 });
    const db = await getDb();
    const p = (await db.query(`select anio, mes from periodos where id=$1`, [id])).rows[0];
    if (!p) return NextResponse.json({ ok: false, error: "Periodo no encontrado." }, { status: 404 });
    const etiqueta = `${p.mes}/${p.anio}`;

    if (b.accion === "reabrir") {
      await reabrirPeriodo(db, id);
      await auditar(db, user, "reabrir", "periodos", id, `Reabrió el periodo ${etiqueta}`);
      return NextResponse.json({ ok: true });
    }
    // cerrar (o regenerar archivo si ya está cerrado)
    const okSnap = await cerrarPeriodo(db, id);
    await auditar(db, user, "cerrar", "periodos", id, `Cerró el periodo ${etiqueta} (archivo ${okSnap ? "generado" : "falló"})`);
    return NextResponse.json({ ok: true, archivo: okSnap });
  } catch (e: any) {
    console.error("api error:", e?.message || e);
    return NextResponse.json({ ok: false, error: "No se pudo cambiar el estado del periodo." }, { status: 400 });
  }
}
