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
      `insert into abonos_extraordinarios (periodo_id, socio_id, fecha, monto, metodo, referencia)
       values ($1,$2,$3,$4,$5,$6) returning id`,
      [b.periodo_id, b.socio_id, b.fecha, b.monto, b.metodo || null, b.referencia || null]
    );
    await auditar(db, user, "crear", "abonos_extraordinarios", r.rows[0].id, `Abono a extraordinarios de $${b.monto} (socio ${b.socio_id})`);
    return NextResponse.json({ ok: true });
  } catch (e) { return err(e); }
}

export async function PATCH(req: NextRequest) {
  try {
    const user = await apiGuard(["admin"]);
    if (!user) return NO_AUTH();
    const b = await req.json();
    const db = await getDb();
    const prev = (await db.query(`select periodo_id from abonos_extraordinarios where id=$1`, [b.id])).rows[0];
    const cerrado = await periodoCerradoMsg(db, prev?.periodo_id);
    if (cerrado) return err(cerrado);
    await db.query(
      `update abonos_extraordinarios set socio_id=$2, fecha=$3, monto=$4, metodo=$5, referencia=$6 where id=$1`,
      [b.id, b.socio_id, b.fecha, b.monto, b.metodo || null, b.referencia || null]
    );
    await auditar(db, user, "editar", "abonos_extraordinarios", b.id, `Editó el abono → $${b.monto}`);
    return NextResponse.json({ ok: true });
  } catch (e) { return err(e); }
}

export async function DELETE(req: NextRequest) {
  try {
    const user = await apiGuard(["admin"]);
    if (!user) return NO_AUTH();
    const id = new URL(req.url).searchParams.get("id");
    const db = await getDb();
    const prev = (await db.query(`select periodo_id, monto from abonos_extraordinarios where id=$1`, [id])).rows[0] as any;
    const cerrado = await periodoCerradoMsg(db, prev?.periodo_id);
    if (cerrado) return err(cerrado);
    await db.query(`delete from abonos_extraordinarios where id=$1`, [id]);
    await auditar(db, user, "borrar", "abonos_extraordinarios", id, `Borró el abono de $${prev?.monto}`);
    return NextResponse.json({ ok: true });
  } catch (e) { return err(e); }
}
