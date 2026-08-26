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

async function saveHorometros(db: any, viajeId: number, horometros: any[]) {
  await db.query(`delete from viaje_horometros where viaje_id=$1`, [viajeId]);
  for (const h of horometros || []) {
    if (h.motor_id && h.inicio !== "" && h.fin !== "") {
      await db.query(
        `insert into viaje_horometros (viaje_id, motor_id, lectura_inicio, lectura_fin)
         values ($1,$2,$3,$4)`,
        [viajeId, h.motor_id, h.inicio, h.fin]
      );
      await db.query(`update motores set horometro_actual=$1 where id=$2`, [h.fin, h.motor_id]);
    }
  }
}

export async function POST(req: NextRequest) {
  try {
    const user = await apiGuard(["admin", "capitan"]);
    if (!user) return NO_AUTH();
    const b = await req.json();
    const db = await getDb();
    const cerrado = await periodoCerradoMsg(db, b.periodo_id);
    if (cerrado) return err(cerrado);
    const r = await db.query<{ id: number }>(
      `insert into viajes
        (periodo_id, embarcacion_id, socio_id, cliente_id, cliente_nombre, marinero, fecha, duracion_horas,
         num_personas, litros, precio_litro, costo_combustible, costo_marinero,
         costo_consumibles, consumibles_comprobante, es_renta, bandera,
         combustible_inicio, combustible_fin)
       values ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$14,$15,$16,$17,$18,$19)
       returning id`,
      [b.periodo_id, b.embarcacion_id || 1, b.socio_id || null, b.cliente_id || null,
       b.cliente_nombre || null, b.marinero || null, b.fecha,
       b.duracion_horas || null, b.num_personas || null, b.litros || 0, b.precio_litro || 0,
       b.costo_combustible || 0, b.costo_marinero || 0, b.costo_consumibles || 0,
       b.consumibles_comprobante || null, !!b.es_renta, !!b.bandera,
       b.combustible_inicio ?? null, b.combustible_fin ?? null]
    );
    await saveHorometros(db, r.rows[0].id, b.horometros);
    await auditar(db, user, "crear", "viajes", r.rows[0].id, `Registró el viaje del ${b.fecha} (${b.cliente_nombre || "cliente " + (b.cliente_id ?? "—")})`);
    return NextResponse.json({ ok: true, id: r.rows[0].id });
  } catch (e) { return err(e); }
}

export async function PATCH(req: NextRequest) {
  try {
    const user = await apiGuard(["admin", "capitan"]);
    if (!user) return NO_AUTH();
    const b = await req.json();
    const db = await getDb();
    const prev = (await db.query(`select periodo_id from viajes where id=$1`, [b.id])).rows[0];
    const cerrado = await periodoCerradoMsg(db, prev?.periodo_id);
    if (cerrado) return err(cerrado);
    await db.query(
      `update viajes set
        socio_id=$2, cliente_id=$3, fecha=$4, duracion_horas=$5, num_personas=$6,
        litros=$7, precio_litro=$8, costo_combustible=$9, costo_marinero=$10,
        costo_consumibles=$11, consumibles_comprobante=$12, bandera=$13,
        combustible_inicio=$14, combustible_fin=$15, cliente_nombre=$16, marinero=$17
       where id=$1`,
      [b.id, b.socio_id || null, b.cliente_id || null, b.fecha, b.duracion_horas || null, b.num_personas || null,
       b.litros || 0, b.precio_litro || 0, b.costo_combustible || 0, b.costo_marinero || 0,
       b.costo_consumibles || 0, b.consumibles_comprobante || null, !!b.bandera,
       b.combustible_inicio ?? null, b.combustible_fin ?? null, b.cliente_nombre || null, b.marinero || null]
    );
    await saveHorometros(db, b.id, b.horometros);
    await auditar(db, user, "editar", "viajes", b.id, `Editó el viaje del ${b.fecha}`);
    return NextResponse.json({ ok: true });
  } catch (e) { return err(e); }
}

export async function DELETE(req: NextRequest) {
  try {
    // Solo el administrador puede eliminar viajes.
    const user = await apiGuard(["admin"]);
    if (!user) return NO_AUTH();
    const id = new URL(req.url).searchParams.get("id");
    const db = await getDb();
    const prev = (await db.query(
      `select v.periodo_id, to_char(v.fecha,'YYYY-MM-DD') f, coalesce(v.cliente_nombre, c.nombre, so.nombre, 'Renta') cliente
         from viajes v left join clientes c on c.id=v.cliente_id left join socios so on so.id=v.socio_id
        where v.id=$1`, [id])).rows[0] as any;
    const cerrado = await periodoCerradoMsg(db, prev?.periodo_id);
    if (cerrado) return err(cerrado);
    await db.query(`delete from viajes where id=$1`, [id]);
    await auditar(db, user, "borrar", "viajes", id, `Borró el viaje del ${prev?.f} (${prev?.cliente})`);
    return NextResponse.json({ ok: true });
  } catch (e) { return err(e); }
}
