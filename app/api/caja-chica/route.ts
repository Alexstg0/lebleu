import { NextRequest, NextResponse } from "next/server";
import { getDb } from "@/lib/db";
import { apiGuard } from "@/lib/auth";
import { auditar } from "@/lib/audit";

function err(e: any) {
  if (typeof e === "string") return NextResponse.json({ ok: false, error: e }, { status: 400 });
  console.error("api error:", e?.message || e);
  return NextResponse.json({ ok: false, error: "Ocurrió un error al procesar la solicitud." }, { status: 400 });
}
const NO_AUTH = () => NextResponse.json({ ok: false, error: "No autorizado." }, { status: 403 });
const ROLES = ["admin", "capitan"] as const;

const norm = (b: any) => [
  b.fecha,
  b.tipo === "abono" ? "abono" : "gasto",
  b.caja_numero || null,
  b.factura || null,
  b.proveedor || null,
  b.concepto || null,
  b.tipo === "abono" ? 0 : Number(b.monto || 0),
  b.tipo === "abono" ? Number(b.abono || 0) : 0,
  b.observaciones || null,
];

export async function POST(req: NextRequest) {
  try {
    const user = await apiGuard([...ROLES]);
    if (!user) return NO_AUTH();
    const b = await req.json();
    const db = await getDb();
    const r = await db.query(
      `insert into caja_chica (fecha, tipo, caja_numero, factura, proveedor, concepto, monto, abono, observaciones)
       values ($1,$2,$3,$4,$5,$6,$7,$8,$9) returning id`,
      norm(b)
    );
    await auditar(db, user, "crear", "caja_chica", r.rows[0].id, `Caja ${b.caja_numero ?? "—"}: ${b.tipo === "abono" ? "abono" : "gasto"} "${b.concepto}" por $${b.tipo === "abono" ? b.abono : b.monto}`);
    return NextResponse.json({ ok: true });
  } catch (e) { return err(e); }
}

export async function PATCH(req: NextRequest) {
  try {
    const user = await apiGuard([...ROLES]);
    if (!user) return NO_AUTH();
    const b = await req.json();
    const db = await getDb();
    await db.query(
      `update caja_chica set fecha=$2, tipo=$3, caja_numero=$4, factura=$5, proveedor=$6,
        concepto=$7, monto=$8, abono=$9, observaciones=$10 where id=$1`,
      [b.id, ...norm(b)]
    );
    await auditar(db, user, "editar", "caja_chica", b.id, `Editó el movimiento "${b.concepto}" de la caja ${b.caja_numero ?? "—"}`);
    return NextResponse.json({ ok: true });
  } catch (e) { return err(e); }
}

export async function DELETE(req: NextRequest) {
  try {
    const user = await apiGuard([...ROLES]);
    if (!user) return NO_AUTH();
    const id = new URL(req.url).searchParams.get("id");
    const db = await getDb();
    const prev = (await db.query(`select concepto, caja_numero, monto, abono from caja_chica where id=$1`, [id])).rows[0] as any;
    await db.query(`delete from caja_chica where id=$1`, [id]);
    await auditar(db, user, "borrar", "caja_chica", id, `Borró "${prev?.concepto}" de la caja ${prev?.caja_numero ?? "—"}`);
    return NextResponse.json({ ok: true });
  } catch (e) { return err(e); }
}
