import { listPeriodos } from "@/lib/queries";
import { getDb } from "@/lib/db";
import { mesNombre, mxn } from "@/lib/format";
import Topbar from "../Topbar";
import PeriodoForm from "./PeriodoForm";
import EstadoPeriodo from "./EstadoPeriodo";
import { requireUser } from "@/lib/auth";

export const dynamic = "force-dynamic";

export default async function Periodos() {
  const user = await requireUser(["admin"]);
  const periodos = await listPeriodos();
  const db = await getDb();
  const balRows = (
    await db.query(
      `select periodo_id, sum(balance_operativo) as total from v_balance_operativo group by periodo_id`
    )
  ).rows as Array<{ periodo_id: number; total: string }>;
  const balByPeriodo = new Map(balRows.map((r) => [r.periodo_id, r.total]));

  // Estados de cuenta archivados por periodo (generados al cerrar).
  const archRows = (
    await db.query(`select periodo_id, max(id) as id from adjuntos where periodo_id is not null and generado group by periodo_id`)
  ).rows as Array<{ periodo_id: number; id: number }>;
  const archByPeriodo = new Map(archRows.map((r) => [r.periodo_id, r.id]));

  const latest = periodos[0];
  let nextMes = (latest?.mes ?? 0) + 1;
  let nextAnio = latest?.anio ?? new Date().getFullYear();
  if (nextMes > 12) { nextMes = 1; nextAnio += 1; }

  return (
    <>
      <Topbar periodos={periodos} periodoId={latest?.id ?? 0} active="periodos" rol={user.rol} nombre={user.nombre} />
      <div className="wrap">
        <h1 style={{ margin: "4px 0 14px", fontSize: 22 }}>Periodos</h1>

        <div className="periodo-list">
          {periodos.map((p) => (
            <div key={p.id} className="periodo-row">
              <div>
                <strong>{mesNombre(p.mes)} {p.anio}</strong>{" "}
                <span className={`est ${p.estado === "abierto" ? "est-abierto" : "est-cerrado"}`}>{p.estado}</span>
                <div className="hint">T/C ${Number(p.tipo_cambio).toFixed(2)} · ${Number(p.precio_litro).toFixed(2)}/Lt</div>
              </div>
              <div style={{ textAlign: "right", display: "flex", flexDirection: "column", alignItems: "flex-end", gap: 4 }}>
                <div className="hint">Balance operativo (ambos socios)</div>
                <div className="mono" style={{ fontWeight: 700, fontSize: 16 }}>
                  {mxn(balByPeriodo.get(p.id) ?? 0)}
                </div>
                <a className="hint" href={`/?periodo=${p.id}`}>Ver estado de cuenta →</a>
                <EstadoPeriodo id={p.id} estado={p.estado} etiqueta={`${mesNombre(p.mes)} ${p.anio}`} archivoId={archByPeriodo.get(p.id) ?? null} />
              </div>
            </div>
          ))}
        </div>

        <PeriodoForm
          sugerido={{ anio: nextAnio, mes: nextMes }}
          tipoCambio={Number(latest?.tipo_cambio ?? 1)}
          precioLitro={Number(latest?.precio_litro ?? 0)}
        />
      </div>
    </>
  );
}
