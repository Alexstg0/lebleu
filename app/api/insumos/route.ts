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

// Mantiene viajes.costo_consumibles = suma de los insumos asignados a ese viaje,
// para que el estado de cuenta (gasto variable por socio) quede correcto.
async function syncViaje(db: any, viajeId: number | null) {
  if (!viajeId) return;
  await db.query(
    `update viajes set costo_consumibles =
        (select coalesce(sum(monto_mxn), 0) from insumos where viaje_id = $1)
      where id = $1`,
    [viajeId]
  );
}

const fields = (b: any) => [
  b.periodo_id,
  b.viaje_id || null,
  b.fecha,
  b.categoria || null,
  b.proveedor || null,
  b.concepto || null,
  b.moneda === "USD" ? "USD" : "MXN",
  Number(b.monto_original || 0),
  // En MXN el tipo de cambio es 1; solo aplica en USD.
  b.moneda === "USD" ? Number(b.tipo_cambio || 1) : 1,
  b.comprobante_folio || null,
];

export async function POST(req: NextRequest) {
  try {
    const user = await apiGuard(["admin"]);
    if (!user) return NO_AUTH();
    const b = await req.json();
    const db = await getDb();
    const cerrado = await periodoCerradoMsg(db, b.periodo_id);
    if (cerrado) return err(cerrado);
    const r = await db.query(
      `insert into insumos
        (periodo_id, viaje_id, fecha, categoria, proveedor, concepto, moneda, monto_original, tipo_cambio, comprobante_folio)
       values ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10) returning id`,
      fields(b)
    );
    await syncViaje(db, b.viaje_id || null);
    await auditar(db, user, "crear", "insumos", r.rows[0].id, `Insumo "${b.concepto}" por $${b.monto_original} (viaje ${b.viaje_id ?? "—"})`);
    return NextResponse.json({ ok: true });
  } catch (e) { return err(e); }
}

export async function PATCH(req: NextRequest) {
  try {
    const user = await apiGuard(["admin"]);
    if (!user) return NO_AUTH();
    const b = await req.json();
    const db = await getDb();
    const prev = (await db.query(`select periodo_id, viaje_id from insumos where id=$1`, [b.id])).rows[0] as { periodo_id: number; viaje_id: number | null } | undefined;
    const cerrado = await periodoCerradoMsg(db, prev?.periodo_id);
    if (cerrado) return err(cerrado);
    await db.query(
      `update insumos set periodo_id=$2, viaje_id=$3, fecha=$4, categoria=$5, proveedor=$6,
        concepto=$7, moneda=$8, monto_original=$9, tipo_cambio=$10, comprobante_folio=$11
       where id=$1`,
      [b.id, ...fields(b)]
    );
    if (prev && prev.viaje_id && prev.viaje_id !== (b.viaje_id || null)) await syncViaje(db, prev.viaje_id);
    await syncViaje(db, b.viaje_id || null);
    await auditar(db, user, "editar", "insumos", b.id, `Editó el insumo "${b.concepto}" → $${b.monto_original}`);
    return NextResponse.json({ ok: true });
  } catch (e) { return err(e); }
}

export async function DELETE(req: NextRequest) {
  try {
    const user = await apiGuard(["admin"]);
    if (!user) return NO_AUTH();
    const id = new URL(req.url).searchParams.get("id");
    const db = await getDb();
    const prev = (await db.query(`select periodo_id, viaje_id, concepto, monto_mxn from insumos where id=$1`, [id])).rows[0] as any;
    const cerrado = await periodoCerradoMsg(db, prev?.periodo_id);
    if (cerrado) return err(cerrado);
    await db.query(`delete from insumos where id=$1`, [id]);
    if (prev) await syncViaje(db, prev.viaje_id);
    await auditar(db, user, "borrar", "insumos", id, `Borró el insumo "${prev?.concepto}" de $${prev?.monto_mxn}`);
    return NextResponse.json({ ok: true });
  } catch (e) { return err(e); }
}
