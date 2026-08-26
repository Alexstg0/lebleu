import { NextRequest, NextResponse } from "next/server";
import { PDFDocument } from "pdf-lib";
import { getDb } from "@/lib/db";
import { apiGuard } from "@/lib/auth";
import { generarEstadoCuentaPDF } from "@/lib/reporte-estado";
import { generarReporteSoportes } from "@/lib/reporte-soportes";
import { generarReporteMarineros } from "@/lib/reporte-marineros";

export const dynamic = "force-dynamic";

// Reporte completo del periodo: estado de cuenta + soportes + marineros en un solo PDF.
export async function GET(req: NextRequest) {
  const user = await apiGuard(["admin", "socio"]);
  if (!user) return NextResponse.json({ ok: false, error: "No autorizado." }, { status: 403 });
  const periodoId = Number(new URL(req.url).searchParams.get("periodo"));
  if (!periodoId) return NextResponse.json({ ok: false, error: "Falta el periodo." }, { status: 400 });
  try {
    const db = await getDb();
    const partes = [
      await generarEstadoCuentaPDF(db, periodoId),
      // Sin la sección de marineros: los recibos van una sola vez, en el reporte de marineros.
      await generarReporteSoportes(db, periodoId, false),
      await generarReporteMarineros(db, periodoId),
    ].filter(Boolean) as Uint8Array[];
    if (!partes.length)
      return NextResponse.json({ ok: false, error: "No hay información en este periodo." }, { status: 404 });

    const merged = await PDFDocument.create();
    for (const bytes of partes) {
      const src = await PDFDocument.load(bytes, { ignoreEncryption: true });
      const pages = await merged.copyPages(src, src.getPageIndices());
      pages.forEach((p) => merged.addPage(p));
    }
    const out = await merged.save();
    return new NextResponse(Buffer.from(out) as any, {
      status: 200,
      headers: {
        "Content-Type": "application/pdf",
        "Content-Disposition": `inline; filename="reporte-completo-${periodoId}.pdf"`,
        "Cache-Control": "private, no-store",
      },
    });
  } catch (e: any) {
    console.error("api error:", e?.message || e);
    return NextResponse.json({ ok: false, error: "No se pudo generar el reporte completo." }, { status: 500 });
  }
}
