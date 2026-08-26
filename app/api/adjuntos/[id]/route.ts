import { NextRequest, NextResponse } from "next/server";
import { getDb } from "@/lib/db";
import { getSession } from "@/lib/auth";

export const dynamic = "force-dynamic";

// Sirve el PDF para el visor en línea. Requiere sesión iniciada.
export async function GET(_req: NextRequest, ctx: { params: Promise<{ id: string }> }) {
  const user = await getSession();
  if (!user) return NextResponse.json({ ok: false, error: "No autorizado." }, { status: 403 });
  const { id } = await ctx.params;
  const db = await getDb();
  const r = await db.query(`select nombre, datos, caja_id from adjuntos where id=$1`, [id]);
  const row = r.rows[0] as { nombre: string; datos: string; caja_id: number | null } | undefined;
  if (!row) return NextResponse.json({ ok: false, error: "No encontrado." }, { status: 404 });
  // El capitán solo puede ver soportes de caja chica (mismo criterio que la subida/borrado).
  if (user.rol === "capitan" && !row.caja_id) return NextResponse.json({ ok: false, error: "No autorizado." }, { status: 403 });
  const buf = Buffer.from(row.datos, "base64");
  return new NextResponse(buf as any, {
    status: 200,
    headers: {
      // Todos los adjuntos se almacenan como PDF; se fuerza el tipo y se impide el sniffing
      // para que un MIME manipulado nunca se interprete como HTML (evita XSS almacenado).
      "Content-Type": "application/pdf",
      "X-Content-Type-Options": "nosniff",
      "Content-Disposition": `inline; filename="${encodeURIComponent(row.nombre || "documento.pdf")}"`,
      "Cache-Control": "private, max-age=0, no-store",
    },
  });
}
