import type { Usuario } from "./auth";

// Registra una acción en la bitácora de auditoría. Nunca rompe la operación principal.
export async function auditar(
  db: any,
  user: Usuario | null,
  accion: "crear" | "editar" | "borrar" | "cerrar" | "reabrir" | "subir" | "generar",
  tabla: string,
  registroId: number | string | null,
  detalle: string
) {
  try {
    await db.query(
      `insert into auditoria (usuario_id, usuario_nombre, accion, tabla, registro_id, detalle)
       values ($1,$2,$3,$4,$5,$6)`,
      [user?.id ?? null, user?.nombre ?? "sistema", accion, tabla, registroId ? Number(registroId) : null, detalle.slice(0, 400)]
    );
  } catch (e: any) {
    console.error("auditoria error:", e?.message || e);
  }
}

// Devuelve null si el periodo está abierto; o un mensaje de error si está cerrado / no existe.
export async function periodoCerradoMsg(db: any, periodoId: number | null | undefined): Promise<string | null> {
  if (!periodoId) return null;
  const r = (await db.query(`select estado, anio, mes from periodos where id = $1`, [periodoId])).rows[0];
  if (!r) return "El periodo no existe.";
  if (r.estado === "cerrado") {
    return `El periodo está cerrado. Reábrelo en la pestaña Periodos para poder capturar o editar.`;
  }
  return null;
}
