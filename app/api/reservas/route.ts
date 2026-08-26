import { NextRequest, NextResponse } from "next/server";
import { getDb } from "@/lib/db";
import { apiGuard } from "@/lib/auth";

function err(e: any) {
  if (typeof e === "string") return NextResponse.json({ ok: false, error: e }, { status: 400 });
  console.error("api error:", e?.message || e);
  return NextResponse.json({ ok: false, error: "Ocurrió un error al procesar la solicitud." }, { status: 400 });
}
const NO_AUTH = () => NextResponse.json({ ok: false, error: "No autorizado." }, { status: 403 });
const ROLES = ["admin", "capitan", "socio"] as const;

const norm = (b: any) => [
  b.fecha,
  b.hora || null,
  b.cliente || null,
  b.socio_id || null,
  b.num_personas || null,
  b.duracion_horas || null,
  b.notas || null,
];

export async function POST(req: NextRequest) {
  try {
    if (!(await apiGuard([...ROLES]))) return NO_AUTH();
    const b = await req.json();
    const db = await getDb();
    await db.query(
      `insert into reservas (fecha, hora, cliente, socio_id, num_personas, duracion_horas, notas)
       values ($1,$2,$3,$4,$5,$6,$7)`,
      norm(b)
    );
    return NextResponse.json({ ok: true });
  } catch (e) { return err(e); }
}

export async function PATCH(req: NextRequest) {
  try {
    if (!(await apiGuard([...ROLES]))) return NO_AUTH();
    const b = await req.json();
    const db = await getDb();
    await db.query(
      `update reservas set fecha=$2, hora=$3, cliente=$4, socio_id=$5,
        num_personas=$6, duracion_horas=$7, notas=$8 where id=$1`,
      [b.id, ...norm(b)]
    );
    return NextResponse.json({ ok: true });
  } catch (e) { return err(e); }
}

export async function DELETE(req: NextRequest) {
  try {
    if (!(await apiGuard([...ROLES]))) return NO_AUTH();
    const id = new URL(req.url).searchParams.get("id");
    const db = await getDb();
    await db.query(`delete from reservas where id=$1`, [id]);
    return NextResponse.json({ ok: true });
  } catch (e) { return err(e); }
}
