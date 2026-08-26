import type { Metadata } from "next";
import { listPeriodos } from "@/lib/queries";
import { getDb } from "@/lib/db";
import Topbar from "../Topbar";
import { requireUser } from "@/lib/auth";

export const dynamic = "force-dynamic";
export const metadata: Metadata = { title: "Le Bleu — Auditoría" };

const TABLA_LABEL: Record<string, string> = {
  gastos_operativos: "Gasto operativo",
  insumos: "Insumo",
  viajes: "Viaje",
  aportaciones: "Aportación",
  extraordinarios: "Extraordinario",
  abonos_extraordinarios: "Abono extraordinario",
  ingresos_renta: "Renta",
  caja_chica: "Caja chica",
  reservas: "Reserva",
  periodos: "Periodo",
  marineros: "Marinero",
  adjuntos: "Archivo",
  usuarios: "Usuario",
};

const ACCION_CLS: Record<string, string> = {
  crear: "g", editar: "b", borrar: "a", cerrar: "a", reabrir: "b", subir: "g", generar: "b",
};

export default async function Auditoria() {
  const user = await requireUser(["admin"]);
  const periodos = await listPeriodos();
  const db = await getDb();
  const rows = (
    await db.query(
      `select id, usuario_nombre, accion, tabla, registro_id, detalle,
              to_char(created_at at time zone 'America/Mazatlan', 'DD/MM/YYYY HH24:MI') as cuando
         from auditoria order by id desc limit 300`
    )
  ).rows as any[];

  return (
    <>
      <Topbar periodos={periodos} periodoId={periodos[0]?.id ?? 0} active="auditoria" rol={user.rol} nombre={user.nombre} />
      <div className="wrap">
        <h1 style={{ margin: "4px 0 2px", fontSize: 22 }}>Auditoría</h1>
        <div className="hint">Registro de quién hizo qué y cuándo (últimos 300 movimientos, hora de BCS)</div>
        <div style={{ height: 18 }} />
        {rows.length === 0 ? (
          <div className="empty">Aún no hay actividad registrada. A partir de ahora, cada cambio quedará anotado aquí.</div>
        ) : (
          <div className="table-card">
            <table className="resp-table">
              <thead>
                <tr><th>Fecha y hora</th><th>Usuario</th><th className="tc">Acción</th><th>Sección</th><th>Detalle</th></tr>
              </thead>
              <tbody>
                {rows.map((r) => (
                  <tr key={r.id}>
                    <td data-label="Fecha" style={{ whiteSpace: "nowrap" }}>{r.cuando}</td>
                    <td data-label="Usuario">{r.usuario_nombre || "sistema"}</td>
                    <td data-label="Acción" className="tc"><span className={`tag ${ACCION_CLS[r.accion] || ""}`}>{r.accion}</span></td>
                    <td data-label="Sección">{TABLA_LABEL[r.tabla] || r.tabla}</td>
                    <td data-label="Detalle">{r.detalle}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </>
  );
}
