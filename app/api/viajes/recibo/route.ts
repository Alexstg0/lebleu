import { NextRequest, NextResponse } from "next/server";
import { getDb } from "@/lib/db";
import { apiGuard } from "@/lib/auth";
import { generarReciboViaje } from "@/lib/reporte-marineros";

export const dynamic = "force-dynamic";

// Recibo de pago al marinero de un viaje específico (PDF). Solo admin (misma que la página Movimientos).
export async function GET(req: NextRequest) {
  const user = await apiGuard(["admin"]);
  if (!user) return NextResponse.json({ ok: false, error: "No autorizado." }, { status: 403 });
  const id = Number(new URL(req.url).searchParams.get("id"));
  if (!id) return NextResponse.json({ ok: false, error: "Falta el viaje." }, { status: 400 });
  try {
    const db = await getDb();
    const bytes = await generarReciboViaje(db, id);
    if (!bytes) return NextResponse.json({ ok: false, error: "Viaje no encontrado." }, { status: 404 });
    return new NextResponse(Buffer.from(bytes) as any, {
      status: 200,
      headers: {
        "Content-Type": "application/pdf",
        "Content-Disposition": `inline; filename="recibo-marinero-viaje-${id}.pdf"`,
        "Cache-Control": "private, no-store",
      },
    });
  } catch (e: any) {
    console.error("api error:", e?.message || e);
    return NextResponse.json({ ok: false, error: "No se pudo generar el recibo." }, { status: 500 });
  }
}
