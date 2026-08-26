import { scryptSync, randomBytes, timingSafeEqual } from "node:crypto";

// Hashing de contraseñas con scrypt (sin dependencias externas).
// Formato almacenado: "salt:hash" en hex.

export function hashPassword(pw: string): string {
  const salt = randomBytes(16).toString("hex");
  const hash = scryptSync(pw, salt, 64).toString("hex");
  return `${salt}:${hash}`;
}

export function verifyPassword(pw: string, stored: string): boolean {
  const [salt, hash] = String(stored).split(":");
  if (!salt || !hash) return false;
  const h = scryptSync(pw, salt, 64);
  const hb = Buffer.from(hash, "hex");
  return h.length === hb.length && timingSafeEqual(h, hb);
}

export const newToken = () => randomBytes(32).toString("hex");
