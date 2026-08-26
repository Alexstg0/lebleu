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
      `insert into ingresos_renta (periodo_id, cliente, fecha, monto, costos_asociados)
       values ($1,$2,$3,$4,$5) returning id`,
      [b.periodo_id, b.cliente, b.fecha, b.monto, b.costos_asociados || 0]
    );
    await auditar(db, user, "crear", "ingresos_renta", r.rows[0].id, `Renta "${b.cliente}" por $${b.monto}`);
    return NextResponse.json({ ok: true });
  } catch (e) { return err(e); }
}

export async function PATCH(req: NextRequest) {
  try {
    const user = await apiGuard(["admin"]);
    if (!user) return NO_AUTH();
    const b = await req.json();
    const db = await getDb();
    const prev = (await db.query(`select periodo_id from ingresos_renta where id=$1`, [b.id])).rows[0];
    const cerrado = await periodoCerradoMsg(db, prev?.periodo_id);
    if (cerrado) return err(cerrado);
    await db.query(
      `update ingresos_renta set cliente=$2, fecha=$3, monto=$4, costos_asociados=$5 where id=$1`,
      [b.id, b.cliente, b.fecha, b.monto, b.costos_asociados || 0]
    );
    await auditar(db, user, "editar", "ingresos_renta", b.id, `Editó la renta "${b.cliente}" → $${b.monto}`);
    return NextResponse.json({ ok: true });
  } catch (e) { return err(e); }
}

export async function DELETE(req: NextRequest) {
  try {
    const user = await apiGuard(["admin"]);
    if (!user) return NO_AUTH();
    const id = new URL(req.url).searchParams.get("id");
    const db = await getDb();
    const prev = (await db.query(`select periodo_id, cliente, monto from ingresos_renta where id=$1`, [id])).rows[0] as any;
    const cerrado = await periodoCerradoMsg(db, prev?.periodo_id);
    if (cerrado) return err(cerrado);
    await db.query(`delete from ingresos_renta where id=$1`, [id]);
    await auditar(db, user, "borrar", "ingresos_renta", id, `Borró la renta "${prev?.cliente}" de $${prev?.monto}`);
    return NextResponse.json({ ok: true });
  } catch (e) { return err(e); }
}
