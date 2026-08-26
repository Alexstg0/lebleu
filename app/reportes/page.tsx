import type { Metadata } from "next";
import { listPeriodos } from "@/lib/queries";
import { mesNombre } from "@/lib/format";
import Topbar from "../Topbar";
import PeriodoNav from "./PeriodoNav";
import PdfViewerLink from "../PdfViewerLink";
import { requireUser } from "@/lib/auth";

export const dynamic = "force-dynamic";
export const metadata: Metadata = { title: "Le Bleu — Reportes" };

export default async function Reportes({
  searchParams,
}: {
  searchParams: Promise<{ periodo?: string }>;
}) {
  const user = await requireUser(["admin", "socio"]);
  const sp = await searchParams;
  const periodos = await listPeriodos();
  if (periodos.length === 0) return <div className="empty">No hay periodos.</div>;
  const periodoId = Number(sp.periodo) || periodos[0].id;
  const periodo = periodos.find((p) => p.id === periodoId) ?? periodos[0];
  const perTxt = `${mesNombre(periodo.mes)} ${periodo.anio}`;

  return (
    <>
      <Topbar periodos={periodos} periodoId={periodoId} active="reportes" rol={user.rol} nombre={user.nombre} />
      <div className="wrap">
        <div className="rep-head">
          <div>
            <h1 style={{ margin: "4px 0 2px", fontSize: 22 }}>Reportes</h1>
            <div className="hint">Genera y consulta los reportes de {perTxt}</div>
          </div>
          <PeriodoNav periodos={periodos} periodoId={periodoId} />
        </div>

        <div className="rep-grid">
          <div className="rep-card">
            <div className="rep-icon">📄</div>
            <h3>Estado de cuenta</h3>
            <p>Reporte principal por socio: balances, gastos, viajes y extraordinarios.</p>
            <div className="rep-actions">
              <a className="btn" href={`/?periodo=${periodoId}`}>Abrir</a>
              <span className="hint">Usa el botón PDF dentro del reporte para descargarlo.</span>
            </div>
          </div>

          <div className="rep-card">
            <div className="rep-icon">📎</div>
            <h3>Reporte general de soportes</h3>
            <p>Un solo PDF con una portada por partida (gastos operativos, insumos) seguida de todos los comprobantes cargados del periodo.</p>
            <div className="rep-actions">
              <PdfViewerLink className="btn gold" href={`/api/reportes/soportes?periodo=${periodoId}`} titulo="Reporte general de soportes">Ver</PdfViewerLink>
              <span className="hint">Incluye solo los gastos que tengan PDF de respaldo.</span>
            </div>
          </div>

          <div className="rep-card">
            <div className="rep-icon">⚓</div>
            <h3>Reporte de marineros</h3>
            <p>Resumen de pagos por marinero y un recibo de pago por cada uno con el detalle de los viajes en los que estuvo.</p>
            <div className="rep-actions">
              <PdfViewerLink className="btn" href={`/api/reportes/marineros?periodo=${periodoId}`} titulo="Reporte de marineros">Ver</PdfViewerLink>
              <span className="hint">Toma el marinero y el costo capturado en cada viaje.</span>
            </div>
          </div>

          <div className="rep-card">
            <div className="rep-icon">📦</div>
            <h3>Reporte completo</h3>
            <p>Un solo PDF con todo el periodo, en orden: estado de cuenta, reporte general de soportes y reporte de marineros. Listo para enviar.</p>
            <div className="rep-actions">
              <PdfViewerLink className="btn gold" href={`/api/reportes/completo?periodo=${periodoId}`} titulo={`Reporte completo — ${perTxt}`}>Ver</PdfViewerLink>
              <span className="hint">Junta los 3 reportes de arriba con todos los comprobantes.</span>
            </div>
          </div>
        </div>
      </div>
    </>
  );
}
