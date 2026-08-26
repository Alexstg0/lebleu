import { NextRequest, NextResponse } from "next/server";
import { getDb } from "@/lib/db";
import { verifyPassword, newToken } from "@/lib/password";
import { COOKIE } from "@/lib/auth";

// Hash "señuelo" con formato válido salt:hash (64 bytes) para gastar el mismo
// tiempo de scrypt cuando el usuario no existe (evita enumeración por temporización).
const DUMMY_HASH = "00000000000000000000000000000000:" + "0".repeat(128);

const MAX = 8;
const VENTANA_MIN = 10; // minutos

export async function POST(req: NextRequest) {
  try {
    const { email, password } = await req.json();
    const ip = req.headers.get("x-forwarded-for")?.split(",")[0]?.trim() || "local";
    const clave = `${ip}:${String(email || "").toLowerCase()}`;
    const db = await getDb();

    // Rate limiting respaldado en BD (compartido entre instancias serverless).
    const bloqueo = (await db.query(
      `select intentos, (updated_at > now() - ($2 || ' minutes')::interval) as vigente
         from login_intentos where clave = $1`,
      [clave, String(VENTANA_MIN)]
    )).rows[0] as { intentos: number; vigente: boolean } | undefined;
    if (bloqueo && bloqueo.vigente && bloqueo.intentos >= MAX) {
      return NextResponse.json(
        { ok: false, error: "Demasiados intentos. Espera unos minutos e inténtalo de nuevo." },
        { status: 429 }
      );
    }

    const u = (
      await db.query(`select * from usuarios where lower(email) = lower($1) and activo`, [email])
    ).rows[0] as any;

    // verifyPassword se ejecuta SIEMPRE (contra el hash real o el señuelo) para que
    // la respuesta tarde lo mismo exista o no el usuario.
    const passwordOk = verifyPassword(password, u ? u.password_hash : DUMMY_HASH);
    if (!u || !passwordOk) {
      await db.query(
        `insert into login_intentos (clave, intentos, updated_at) values ($1, 1, now())
           on conflict (clave) do update set
             intentos = case when login_intentos.updated_at > now() - ($2 || ' minutes')::interval
                             then login_intentos.intentos + 1 else 1 end,
             updated_at = now()`,
        [clave, String(VENTANA_MIN)]
      );
      return NextResponse.json({ ok: false, error: "Correo o contraseña incorrectos." }, { status: 401 });
    }

    await db.query(`delete from login_intentos where clave = $1`, [clave]);
    const token = newToken();
    await db.query(
      `insert into sesiones (token, usuario_id, expires_at) values ($1, $2, now() + interval '30 days')`,
      [token, u.id]
    );

    const res = NextResponse.json({ ok: true, rol: u.rol });
    res.cookies.set(COOKIE, token, {
      httpOnly: true,
      secure: process.env.NODE_ENV === "production",
      sameSite: "lax",
      path: "/",
      maxAge: 60 * 60 * 24 * 30,
    });
    return res;
  } catch (e: any) {
    console.error("login error:", e?.message || e);
    return NextResponse.json({ ok: false, error: "No se pudo iniciar sesión." }, { status: 400 });
  }
}
