import { NextRequest, NextResponse } from "next/server";
import { getDb } from "@/lib/db";
import { apiGuard } from "@/lib/auth";
import { listPeriodos } from "@/lib/queries";
import { ensureRecurrentes, hoyISO } from "@/lib/recurrentes";

export const dynamic = "force-dynamic";

// Genera manualmente (botón "Generar ahora"). Solo admin.
export async function POST(req: NextRequest) {
  try {
    if (!(await apiGuard(["admin"]))) return NextResponse.json({ ok: false, error: "No autorizado." }, { status: 403 });
    const b = await req.json().catch(() => ({}));
    const db = await getDb();
    const periodos = await listPeriodos();
    const objetivo = b.periodoId ? periodos.filter((p) => p.id === Number(b.periodoId)) : periodos;
    let nomina = 0, admin = 0;
    for (const p of objetivo) {
      const r = await ensureRecurrentes(db, p as any, hoyISO());
      nomina += r.nomina; admin += r.admin;
    }
    return NextResponse.json({ ok: true, nomina, admin });
  } catch (e: any) {
    console.error("api error:", e?.message || e);
    return NextResponse.json({ ok: false, error: "No se pudieron generar los cargos." }, { status: 400 });
  }
}
