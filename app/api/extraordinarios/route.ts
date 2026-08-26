import { NextRequest, NextResponse } from "next/server";
import { getDb } from "@/lib/db";
import { apiGuard } from "@/lib/auth";
import { auditar, periodoCerradoMsg } from "@/lib/audit";

function err(e: any) {
  if (typeof e === "string") return NextResponse.json({ ok: false, error: e }, { status: 400 });
  console.error("api error:", e?.message || e);
  return NextResponse.json({ ok: false, error: "Ocurrió un error al procesar la solicitud." }, { status: 400 });
}
const NO_AUTH = () => NextResponse.json({ ok: false, error: "No autorizado." }, { status: 403 });

export async function POST(req: NextRequest) {
  try {
    const user = await apiGuard(["admin"]);
    if (!user) return NO_AUTH();
    const b = await req.json();
    const db = await getDb();
    const cerrado = await periodoCerradoMsg(db, b.periodo_id);
    if (cerrado) return err(cerrado);
    const r = await db.query(
      `insert into extraordinarios
        (periodo_id, fecha, proveedor, concepto, categoria, moneda,
         monto_original, tipo_cambio, comprobante_folio)
       values ($1,$2,$3,$4,$5,$6,$7,$8,$9) returning id`,
      [b.periodo_id, b.fecha, b.proveedor, b.concepto, b.categoria || "otro", b.moneda || "MXN",
       b.monto_original, b.moneda === "USD" ? b.tipo_cambio || 1 : 1, b.comprobante_folio || null]
    );
    await auditar(db, user, "crear", "extraordinarios", r.rows[0].id, `Extraordinario "${b.concepto}" por $${b.monto_original} ${b.moneda || "MXN"}`);
    return NextResponse.json({ ok: true });
  } catch (e) { return err(e); }
}

export async function PATCH(req: NextRequest) {
  try {
    const user = await apiGuard(["admin"]);
    if (!user) return NO_AUTH();
    const b = await req.json();
    const db = await getDb();
    const prev = (await db.query(`select periodo_id from extraordinarios where id=$1`, [b.id])).rows[0];
    const cerrado = await periodoCerradoMsg(db, prev?.periodo_id);
    if (cerrado) return err(cerrado);
    await db.query(
      `update extraordinarios set
        fecha=$2, proveedor=$3, concepto=$4, categoria=$5, moneda=$6,
        monto_original=$7, tipo_cambio=$8, comprobante_folio=$9
       where id=$1`,
      [b.id, b.fecha, b.proveedor, b.concepto, b.categoria || "otro", b.moneda || "MXN",
       b.monto_original, b.moneda === "USD" ? b.tipo_cambio || 1 : 1, b.comprobante_folio || null]
    );
    await auditar(db, user, "editar", "extraordinarios", b.id, `Editó el extraordinario "${b.concepto}"`);
    return NextResponse.json({ ok: true });
  } catch (e) { return err(e); }
}

export async function DELETE(req: NextRequest) {
  try {
    const user = await apiGuard(["admin"]);
    if (!user) return NO_AUTH();
    const id = new URL(req.url).searchParams.get("id");
    const db = await getDb();
    const prev = (await db.query(`select periodo_id, concepto, monto_mxn from extraordinarios where id=$1`, [id])).rows[0] as any;
    const cerrado = await periodoCerradoMsg(db, prev?.periodo_id);
    if (cerrado) return err(cerrado);
    await db.query(`delete from extraordinarios where id=$1`, [id]);
    await auditar(db, user, "borrar", "extraordinarios", id, `Borró el extraordinario "${prev?.concepto}" de $${prev?.monto_mxn}`);
    return NextResponse.json({ ok: true });
  } catch (e) { return err(e); }
}
