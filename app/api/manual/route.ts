import { NextRequest, NextResponse } from "next/server";
import { getDb } from "@/lib/db";
import { getSession } from "@/lib/auth";

export const dynamic = "force-dynamic";

// Manual de uso del rol del usuario (el admin puede pedir cualquiera con ?rol=).
export async function GET(req: NextRequest) {
  const user = await getSession();
  if (!user) return NextResponse.json({ ok: false, error: "No autorizado." }, { status: 403 });
  const pedido = new URL(req.url).searchParams.get("rol");
  const rol = user.rol === "admin" && pedido && ["admin", "capitan", "socio"].includes(pedido) ? pedido : user.rol;
  try {
    const db = await getDb();
    const r = await db.query(`select nombre, datos from manuales where rol = $1`, [rol]);
    if (!r.rows.length) return NextResponse.json({ ok: false, error: "El manual aún no está cargado." }, { status: 404 });
    const { nombre, datos } = r.rows[0] as { nombre: string; datos: string };
    // El encabezado HTTP no acepta acentos: se usa el nombre sin diacríticos.
    const nombreAscii = nombre.normalize("NFD").replace(/[̀-ͯ]/g, "");
    return new NextResponse(Buffer.from(datos, "base64") as any, {
      status: 200,
      headers: {
        "Content-Type": "application/pdf",
        "Content-Disposition": `inline; filename="${nombreAscii}"`,
        "Cache-Control": "private, max-age=300",
      },
    });
  } catch (e: any) {
    console.error("api error:", e?.message || e);
    return NextResponse.json({ ok: false, error: "No se pudo cargar el manual." }, { status: 500 });
  }
}
