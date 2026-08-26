import { NextRequest, NextResponse } from "next/server";
import { getDb } from "@/lib/db";
import { apiGuard } from "@/lib/auth";
import { auditar } from "@/lib/audit";

export const dynamic = "force-dynamic";

// Alta de marinero (capitán y admin). Devuelve el nombre normalizado.
export async function POST(req: NextRequest) {
  try {
    const user = await apiGuard(["admin", "capitan"]);
    if (!user) return NextResponse.json({ ok: false, error: "No autorizado." }, { status: 403 });
    const b = await req.json();
    const nombre = String(b.nombre || "").trim().replace(/\s+/g, " ");
    if (nombre.length < 3) return NextResponse.json({ ok: false, error: "Escribe el nombre completo del marinero." }, { status: 400 });
    const db = await getDb();
    // Si ya existe (sin distinguir mayúsculas), lo reutiliza.
    const existe = (await db.query(`select id, nombre from marineros where lower(btrim(nombre)) = lower($1)`, [nombre])).rows[0];
    if (existe) {
      await db.query(`update marineros set activo = true where id = $1`, [existe.id]);
      return NextResponse.json({ ok: true, id: existe.id, nombre: existe.nombre });
    }
    const r = await db.query(`insert into marineros (nombre) values ($1) returning id, nombre`, [nombre]);
    await auditar(db, user, "crear", "marineros", r.rows[0].id, `Agregó al marinero ${nombre}`);
    return NextResponse.json({ ok: true, id: r.rows[0].id, nombre: r.rows[0].nombre });
  } catch (e: any) {
    console.error("api error:", e?.message || e);
    return NextResponse.json({ ok: false, error: "No se pudo agregar el marinero." }, { status: 400 });
  }
}
