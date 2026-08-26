import { NextRequest, NextResponse } from "next/server";
import { getDb } from "@/lib/db";
import { hashPassword } from "@/lib/password";
import { requireUser, getSession } from "@/lib/auth";

function err(e: any, code = 400) {
  if (typeof e === "string") return NextResponse.json({ ok: false, error: e }, { status: code });
  console.error("api error:", e?.message || e);
  return NextResponse.json({ ok: false, error: "Ocurrió un error al procesar la solicitud." }, { status: code });
}

export async function POST(req: NextRequest) {
  try {
    await requireUser(["admin"]);
    const b = await req.json();
    if (!b.password || String(b.password).length < 6) {
      return err("La contraseña debe tener al menos 6 caracteres.");
    }
    const db = await getDb();
    await db.query(
      `insert into usuarios (nombre, email, password_hash, rol, socio_id)
       values ($1,$2,$3,$4,$5)`,
      [b.nombre, b.email, hashPassword(b.password), b.rol, b.rol === "socio" ? b.socio_id || null : null]
    );
    return NextResponse.json({ ok: true });
  } catch (e: any) {
    if (String(e?.message || e).includes("unique")) return err("Ya existe un usuario con ese correo.");
    return err(e);
  }
}

export async function PATCH(req: NextRequest) {
  // Cambiar contraseña o activar/desactivar.
  try {
    await requireUser(["admin"]);
    const b = await req.json();
    const db = await getDb();
    if (b.password) {
      if (String(b.password).length < 6) return err("La contraseña debe tener al menos 6 caracteres.");
      await db.query(`update usuarios set password_hash=$2 where id=$1`, [b.id, hashPassword(b.password)]);
      // Al cambiar la contraseña se invalidan las sesiones abiertas de ese usuario.
      await db.query(`delete from sesiones where usuario_id=$1`, [b.id]);
    }
    if (typeof b.activo === "boolean") {
      await db.query(`update usuarios set activo=$2 where id=$1`, [b.id, b.activo]);
    }
    return NextResponse.json({ ok: true });
  } catch (e) { return err(e); }
}

export async function DELETE(req: NextRequest) {
  try {
    const me = await getSession();
    if (!me || me.rol !== "admin") return err("No autorizado.", 403);
    const id = Number(new URL(req.url).searchParams.get("id"));
    if (id === me.id) return err("No puedes borrar tu propia cuenta.");
    const db = await getDb();
    await db.query(`delete from usuarios where id=$1`, [id]);
    return NextResponse.json({ ok: true });
  } catch (e) { return err(e); }
}
