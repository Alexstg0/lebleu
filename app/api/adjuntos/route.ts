import { NextRequest, NextResponse } from "next/server";
import { PDFDocument } from "pdf-lib";
import { getDb } from "@/lib/db";
import { apiGuard } from "@/lib/auth";
import { auditar } from "@/lib/audit";

export const dynamic = "force-dynamic";

const NO_AUTH = () => NextResponse.json({ ok: false, error: "No autorizado." }, { status: 403 });
const MAX_BYTES = 3.2 * 1024 * 1024; // límite práctico del servidor (~3 MB)

// Convierte una imagen (JPG/PNG) a un PDF de una página, centrada en A4.
async function imagenAPdf(base64: string, mime: string): Promise<string> {
  const bytes = Buffer.from(base64, "base64");
  const doc = await PDFDocument.create();
  const img = /png/i.test(mime) ? await doc.embedPng(bytes) : await doc.embedJpg(bytes);
  const W = 595.28, H = 841.89, M = 24;
  const esc = Math.min((W - 2 * M) / img.width, (H - 2 * M) / img.height, 1.5);
  const w = img.width * esc, h = img.height * esc;
  const page = doc.addPage([W, H]);
  page.drawImage(img, { x: (W - w) / 2, y: (H - h) / 2, width: w, height: h });
  return Buffer.from(await doc.save()).toString("base64");
}

// Sube un PDF o imagen de respaldo a un gasto, insumo, extraordinario o movimiento de caja chica.
export async function POST(req: NextRequest) {
  try {
    const user = await apiGuard(["admin", "capitan"]);
    if (!user) return NO_AUTH();
    const b = await req.json();
    if (!b.gasto_id && !b.insumo_id && !b.extraordinario_id && !b.caja_id)
      return NextResponse.json({ ok: false, error: "Falta el registro al que pertenece el archivo." }, { status: 400 });
    // El capitán solo puede subir soportes de caja chica.
    if (user.rol === "capitan" && !b.caja_id) return NO_AUTH();

    let base64 = String(b.base64 || "").replace(/^data:[^,]+,/, "");
    if (!base64) return NextResponse.json({ ok: false, error: "Archivo vacío." }, { status: 400 });
    if ((base64.length * 3) / 4 > MAX_BYTES)
      return NextResponse.json({ ok: false, error: "El archivo supera 3 MB. Toma la foto en tamaño reducido o comprímela." }, { status: 400 });

    let mime = b.mime || "application/pdf";
    let nombre = String(b.nombre || "documento.pdf").slice(0, 200);
    if (/^image\/(jpe?g|png)$/i.test(mime)) {
      // Las imágenes se convierten a PDF para que el visor y los reportes sean uniformes.
      base64 = await imagenAPdf(base64, mime);
      mime = "application/pdf";
      nombre = nombre.replace(/\.(jpe?g|png)$/i, "") + ".pdf";
    } else if (mime !== "application/pdf") {
      // Igualdad estricta: un MIME tipo "text/html;pdf" ya no pasa (evita XSS almacenado).
      return NextResponse.json({ ok: false, error: "Solo se permiten PDF o imágenes (JPG/PNG)." }, { status: 400 });
    }

    const db = await getDb();
    const r = await db.query(
      `insert into adjuntos (gasto_id, insumo_id, extraordinario_id, caja_id, nombre, mime, datos, generado)
       values ($1,$2,$3,$4,$5,$6,$7,false) returning id`,
      [b.gasto_id || null, b.insumo_id || null, b.extraordinario_id || null, b.caja_id || null, nombre, mime, base64]
    );
    await auditar(db, user, "subir", "adjuntos", r.rows[0].id, `Subió "${nombre}"`);
    return NextResponse.json({ ok: true });
  } catch (e: any) {
    console.error("api error:", e?.message || e);
    return NextResponse.json({ ok: false, error: "No se pudo subir el archivo. Verifica que sea PDF o JPG/PNG válido." }, { status: 400 });
  }
}

export async function DELETE(req: NextRequest) {
  try {
    const user = await apiGuard(["admin", "capitan"]);
    if (!user) return NO_AUTH();
    const id = new URL(req.url).searchParams.get("id");
    const db = await getDb();
    const prev = (await db.query(`select nombre, caja_id from adjuntos where id=$1`, [id])).rows[0] as any;
    if (user.rol === "capitan" && !prev?.caja_id) return NO_AUTH();
    await db.query(`delete from adjuntos where id=$1`, [id]);
    await auditar(db, user, "borrar", "adjuntos", id, `Eliminó el archivo "${prev?.nombre}"`);
    return NextResponse.json({ ok: true });
  } catch (e: any) {
    console.error("api error:", e?.message || e);
    return NextResponse.json({ ok: false, error: "No se pudo eliminar." }, { status: 400 });
  }
}
