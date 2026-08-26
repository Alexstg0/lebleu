import { NextRequest, NextResponse } from "next/server";
import { getDb } from "@/lib/db";
import { apiGuard } from "@/lib/auth";
import { generarReporteMarineros } from "@/lib/reporte-marineros";

export const dynamic = "force-dynamic";

// Reporte de marineros: resumen + un recibo de pago por marinero. Solo admin y socios.
export async function GET(req: NextRequest) {
  const user = await apiGuard(["admin", "socio"]);
  if (!user) return NextResponse.json({ ok: false, error: "No autorizado." }, { status: 403 });
  const periodoId = Number(new URL(req.url).searchParams.get("periodo"));
  if (!periodoId) return NextResponse.json({ ok: false, error: "Falta el periodo." }, { status: 400 });
  try {
    const db = await getDb();
    const bytes = await generarReporteMarineros(db, periodoId);
    if (!bytes) return NextResponse.json({ ok: false, error: "No hay viajes con marinero en este periodo." }, { status: 404 });
    return new NextResponse(Buffer.from(bytes) as any, {
      status: 200,
      headers: {
        "Content-Type": "application/pdf",
        "Content-Disposition": `inline; filename="reporte-marineros-${periodoId}.pdf"`,
        "Cache-Control": "private, no-store",
      },
    });
  } catch (e: any) {
    console.error("api error:", e?.message || e);
    return NextResponse.json({ ok: false, error: "No se pudo generar el reporte." }, { status: 500 });
  }
}
