import type { Metadata } from "next";
import { listPeriodos, getAnalisis } from "@/lib/queries";
import { mesNombre, mxn, n, num } from "@/lib/format";
import Topbar from "../Topbar";
import { requireUser } from "@/lib/auth";

export const dynamic = "force-dynamic";
export const metadata: Metadata = { title: "Le Bleu — Análisis" };

const NAVY = "#1a3a5c", TEAL = "#0e7c7b", GOLD = "#d99e2b", GRAY = "#8a96a3";
const fCorta = (iso: string) => { const s = String(iso).split("-"); return `${s[2]}/${s[1]}`; };
const kFmt = (v: number) => (v >= 1000 ? `$${Math.round(v / 1000)}k` : `$${Math.round(v)}`);

// Gráfica de barras agrupadas (SVG puro, sin librerías).
function BarrasAgrupadas({ grupos, series }: {
  grupos: { etiqueta: string; valores: number[] }[];
  series: { nombre: string; color: string }[];
}) {
  const W = 720, H = 260, padL = 52, padB = 34, padT = 14;
  const max = Math.max(1, ...grupos.flatMap((g) => g.valores));
  const gw = (W - padL - 10) / Math.max(1, grupos.length);
  const bw = Math.min(34, (gw - 14) / series.length);
  const yFor = (v: number) => padT + (H - padT - padB) * (1 - v / max);
  const ticks = [0, 0.25, 0.5, 0.75, 1].map((t) => t * max);
  return (
    <svg viewBox={`0 0 ${W} ${H}`} style={{ width: "100%", height: "auto" }} role="img">
      {ticks.map((t, i) => (
        <g key={i}>
          <line x1={padL} y1={yFor(t)} x2={W - 6} y2={yFor(t)} stroke="#e6ebf1" strokeWidth={1} />
          <text x={padL - 6} y={yFor(t) + 3.5} fontSize={9.5} fill={GRAY} textAnchor="end">{kFmt(t)}</text>
        </g>
      ))}
      {grupos.map((g, gi) => (
        <g key={gi}>
          {g.valores.map((v, si) => {
            const x = padL + gi * gw + (gw - series.length * bw) / 2 + si * bw;
            const y = yFor(v);
            return (
              <g key={si}>
                <rect x={x} y={y} width={bw - 3} height={H - padB - y} rx={3} fill={series[si].color} />
                {v > 0 && <text x={x + (bw - 3) / 2} y={y - 4} fontSize={8.5} fill={NAVY} textAnchor="middle">{kFmt(v)}</text>}
              </g>
            );
          })}
          <text x={padL + gi * gw + gw / 2} y={H - padB + 16} fontSize={10.5} fill={NAVY} fontWeight={600} textAnchor="middle">{g.etiqueta}</text>
        </g>
      ))}
    </svg>
  );
}

// Gráfica de barras simple.
function Barras({ datos, color, unidad }: { datos: { etiqueta: string; valor: number; sub?: string }[]; color: string; unidad: string }) {
  const W = 720, H = 240, padL = 46, padB = 40, padT = 14;
  const max = Math.max(1, ...datos.map((d) => d.valor));
  const gw = (W - padL - 10) / Math.max(1, datos.length);
  const bw = Math.min(44, gw - 10);
  const yFor = (v: number) => padT + (H - padT - padB) * (1 - v / max);
  return (
    <svg viewBox={`0 0 ${W} ${H}`} style={{ width: "100%", height: "auto" }} role="img">
      {[0, 0.5, 1].map((t, i) => (
        <g key={i}>
          <line x1={padL} y1={yFor(t * max)} x2={W - 6} y2={yFor(t * max)} stroke="#e6ebf1" strokeWidth={1} />
          <text x={padL - 6} y={yFor(t * max) + 3.5} fontSize={9.5} fill={GRAY} textAnchor="end">{Math.round(t * max)}</text>
        </g>
      ))}
      {datos.map((d, i) => {
        const x = padL + i * gw + (gw - bw) / 2;
        const y = yFor(d.valor);
        return (
          <g key={i}>
            <rect x={x} y={y} width={bw} height={H - padB - y} rx={3} fill={color} />
            <text x={x + bw / 2} y={y - 4} fontSize={8.5} fill={NAVY} textAnchor="middle">{num(d.valor, 0)}{unidad}</text>
            <text x={x + bw / 2} y={H - padB + 14} fontSize={9.5} fill={NAVY} fontWeight={600} textAnchor="middle">{d.etiqueta}</text>
            {d.sub && <text x={x + bw / 2} y={H - padB + 26} fontSize={8} fill={GRAY} textAnchor="middle">{d.sub.slice(0, 14)}</text>}
          </g>
        );
      })}
    </svg>
  );
}

export default async function Analisis() {
  const user = await requireUser(["admin", "socio"]);
  const periodos = await listPeriodos();
  const { porMes, viajes } = await getAnalisis();

  const grupos = porMes.map((p: any) => ({
    etiqueta: `${mesNombre(p.mes).slice(0, 3)} ${p.anio}`,
    valores: [n(p.operativos), n(p.variables), n(p.extraordinarios)],
  }));
  const series = [
    { nombre: "Gastos operativos", color: NAVY },
    { nombre: "Gasto variable (viajes)", color: TEAL },
    { nombre: "Extraordinarios", color: GOLD },
  ];
  const litrosViajes = viajes.filter((v: any) => n(v.litros) > 0).slice(-14);
  const viajesMes = porMes.map((p: any) => ({ etiqueta: `${mesNombre(p.mes).slice(0, 3)} ${p.anio}`, valor: n(p.num_viajes) }));
  const totalGasto = porMes.reduce((s: number, p: any) => s + n(p.operativos) + n(p.variables), 0);
  const totalLitros = porMes.reduce((s: number, p: any) => s + n(p.litros), 0);
  const totalViajes = porMes.reduce((s: number, p: any) => s + n(p.num_viajes), 0);

  return (
    <>
      <Topbar periodos={periodos} periodoId={periodos[0]?.id ?? 0} active="analisis" rol={user.rol} nombre={user.nombre} />
      <div className="wrap">
        <h1 style={{ margin: "4px 0 2px", fontSize: 22 }}>Análisis</h1>
        <div className="hint">Tendencias de gasto y operación de la embarcación · Cifras en MXN</div>
        <div style={{ height: 16 }} />

        <div className="kpi-grid">
          <div className="kpi-card k-navy">
            <div className="kpi-label">Gasto acumulado (op + variable)</div>
            <div className="kpi-value mono">{mxn(totalGasto)}</div>
            <div className="kpi-note">{porMes.length} periodos</div>
          </div>
          <div className="kpi-card k-teal">
            <div className="kpi-label">Viajes totales</div>
            <div className="kpi-value mono">{totalViajes}</div>
            <div className="kpi-note">desde {porMes[0] ? `${mesNombre(porMes[0].mes)} ${porMes[0].anio}` : "—"}</div>
          </div>
          <div className="kpi-card k-gold">
            <div className="kpi-label">Combustible consumido</div>
            <div className="kpi-value mono">{num(totalLitros, 0)} L</div>
            <div className="kpi-note">{totalViajes ? `${num(totalLitros / totalViajes, 1)} L promedio por viaje` : "—"}</div>
          </div>
          <div className="kpi-card k-navy">
            <div className="kpi-label">Gasto promedio mensual</div>
            <div className="kpi-value mono">{mxn(porMes.length ? totalGasto / porMes.length : 0)}</div>
            <div className="kpi-note">operativo + variable</div>
          </div>
        </div>

        <div className="section-label">Gastos por mes</div>
        <div className="chart-card">
          <div className="chart-legend">
            {series.map((s) => <span key={s.nombre}><i style={{ background: s.color }} /> {s.nombre}</span>)}
          </div>
          <BarrasAgrupadas grupos={grupos} series={series} />
        </div>

        <div className="section-label">Consumo de combustible por viaje (últimos {litrosViajes.length})</div>
        <div className="chart-card">
          <Barras datos={litrosViajes.map((v: any) => ({ etiqueta: fCorta(v.fecha), valor: n(v.litros), sub: v.cliente }))} color={TEAL} unidad=" L" />
        </div>

        <div className="section-label">Viajes por mes</div>
        <div className="chart-card">
          <Barras datos={viajesMes} color={NAVY} unidad="" />
        </div>
      </div>
    </>
  );
}
