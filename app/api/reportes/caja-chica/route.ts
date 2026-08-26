import { NextRequest, NextResponse } from "next/server";
import { getDb } from "@/lib/db";
import { apiGuard } from "@/lib/auth";
import { generarReporteCaja } from "@/lib/reporte-caja";

export const dynamic = "force-dynamic";

// Reporte completo de caja chica (balance + soportes anexos). Solo admin y capitán (misma que la página Caja Chica).
export async function GET(req: NextRequest) {
  const user = await apiGuard(["admin", "capitan"]);
  if (!user) return NextResponse.json({ ok: false, error: "No autorizado." }, { status: 403 });
  const url = new URL(req.url);
  const cajaParam = url.searchParams.get("caja") || "todas";
  const caja = cajaParam === "todas" ? null : Number(cajaParam);
  if (caja != null && !Number.isFinite(caja))
    return NextResponse.json({ ok: false, error: "Caja inválida." }, { status: 400 });
  const responsable = (url.searchParams.get("responsable") || "Gabriel Preciado").slice(0, 80);
  try {
    const db = await getDb();
    const bytes = await generarReporteCaja(db, { caja, responsable });
    if (!bytes) return NextResponse.json({ ok: false, error: "No hay movimientos para este filtro." }, { status: 404 });
    return new NextResponse(Buffer.from(bytes) as any, {
      status: 200,
      headers: {
        "Content-Type": "application/pdf",
        "Content-Disposition": `inline; filename="reporte-caja-chica-${caja ?? "todas"}.pdf"`,
        "Cache-Control": "private, no-store",
      },
    });
  } catch (e: any) {
    console.error("api error:", e?.message || e);
    return NextResponse.json({ ok: false, error: "No se pudo generar el reporte." }, { status: 500 });
  }
}
