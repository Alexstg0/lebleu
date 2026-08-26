import { NextRequest, NextResponse } from "next/server";
import { getDb } from "@/lib/db";
import { listPeriodos } from "@/lib/queries";
import { ensureRecurrentes, hoyISO } from "@/lib/recurrentes";
import { ensurePeriodoActual } from "@/lib/periodos";
import { auditar } from "@/lib/audit";

export const dynamic = "force-dynamic";

// Tarea programada (Vercel Cron, diaria). Genera los cargos recurrentes que falten.
// Vercel envía el header Authorization: Bearer <CRON_SECRET> cuando está configurado.
export async function GET(req: NextRequest) {
  const secret = process.env.CRON_SECRET;
  // Fail-closed: sin CRON_SECRET configurado el endpoint NO se ejecuta (evita quedar público).
  if (!secret) return NextResponse.json({ ok: false, error: "Cron no configurado." }, { status: 503 });
  const auth = req.headers.get("authorization");
  if (auth !== `Bearer ${secret}`) return NextResponse.json({ ok: false, error: "No autorizado." }, { status: 401 });
  try {
    const db = await getDb();
    // Crea el periodo del mes en curso si aún no existe (copiando T/C y precio de litro).
    const nuevoPeriodo = await ensurePeriodoActual(db, hoyISO());
    if (nuevoPeriodo) await auditar(db, null, "crear", "periodos", nuevoPeriodo, `El sistema creó automáticamente el periodo del mes ${hoyISO().slice(0, 7)}`);
    const periodos = await listPeriodos();
    let nomina = 0, admin = 0;
    for (const p of periodos) {
      if ((p as any).estado === "cerrado") continue; // no genera cargos en periodos cerrados
      const r = await ensureRecurrentes(db, p as any, hoyISO());
      nomina += r.nomina; admin += r.admin;
    }
    return NextResponse.json({ ok: true, hoy: hoyISO(), nuevoPeriodo, nomina, admin });
  } catch (e: any) {
    console.error("cron error:", e?.message || e);
    return NextResponse.json({ ok: false, error: "Error al generar recurrentes." }, { status: 500 });
  }
}
