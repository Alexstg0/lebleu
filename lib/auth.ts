import { cookies } from "next/headers";
import { redirect } from "next/navigation";
import { getDb } from "./db";

export type Rol = "admin" | "capitan" | "socio";
export type Usuario = {
  id: number;
  nombre: string;
  email: string;
  rol: Rol;
  socio_id: number | null;
};

export const COOKIE = "sid";

export function homeFor(rol: Rol): string {
  return rol === "capitan" ? "/calendario" : "/";
}

export async function getSession(): Promise<Usuario | null> {
  const c = await cookies();
  const token = c.get(COOKIE)?.value;
  if (!token) return null;
  const db = await getDb();
  const r = await db.query(
    `select u.id, u.nombre, u.email, u.rol, u.socio_id
       from sesiones s join usuarios u on u.id = s.usuario_id
      where s.token = $1 and s.expires_at > now() and u.activo`,
    [token]
  );
  return (r.rows[0] as Usuario) ?? null;
}

// Guard para route handlers: devuelve el usuario o null (responde 403 tú).
export async function apiGuard(roles: Rol[]): Promise<Usuario | null> {
  const u = await getSession();
  if (!u || !roles.includes(u.rol)) return null;
  return u;
}

// Guard para usar al inicio de cada página protegida.
export async function requireUser(roles?: Rol[]): Promise<Usuario> {
  const u = await getSession();
  if (!u) redirect("/login");
  if (roles && !roles.includes(u.rol)) redirect(homeFor(u.rol));
  return u;
}
