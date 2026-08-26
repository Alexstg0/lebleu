import { NextRequest, NextResponse } from "next/server";
import { getDb } from "@/lib/db";
import { apiGuard } from "@/lib/auth";

export const dynamic = "force-dynamic";

// Marca un gasto extraordinario como pagado (liquidado) o pendiente. Solo admin.
export async function POST(req: NextRequest) {
  try {
    if (!(await apiGuard(["admin"]))) return NextResponse.json({ ok: false, error: "No autorizado." }, { status: 403 });
    const b = await req.json();
    if (!b.id) return NextResponse.json({ ok: false, error: "Falta el gasto." }, { status: 400 });
    const db = await getDb();
    await db.query(`update extraordinarios set liquidado = $2 where id = $1`, [b.id, !!b.liquidado]);
    return NextResponse.json({ ok: true });
  } catch (e: any) {
    console.error("api error:", e?.message || e);
    return NextResponse.json({ ok: false, error: "No se pudo actualizar." }, { status: 400 });
  }
}
