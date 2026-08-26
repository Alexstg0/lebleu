import { NextResponse } from "next/server";
import { cookies } from "next/headers";
import { getDb } from "@/lib/db";
import { COOKIE } from "@/lib/auth";

export async function POST() {
  const c = await cookies();
  const token = c.get(COOKIE)?.value;
  if (token) {
    const db = await getDb();
    await db.query(`delete from sesiones where token = $1`, [token]);
  }
  const res = NextResponse.json({ ok: true });
  res.cookies.delete(COOKIE);
  return res;
}
