"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { mxn, fechaCorta } from "@/lib/format";
import { deleteRow } from "../capturar/forms/useSubmit";
import GastoOperativo from "../capturar/forms/GastoOperativo";
import Extraordinario from "../capturar/forms/Extraordinario";
import Viaje from "../capturar/forms/Viaje";
import Aportacion from "../capturar/forms/Aportacion";
import Renta from "../capturar/forms/Renta";
import Insumos from "../capturar/forms/Insumos";
import AbonoExtraordinario from "../capturar/forms/AbonoExtraordinario";
import Adjuntos from "./Adjuntos";
import GenerarRecurrentes from "./GenerarRecurrentes";
import GenerarRecibo from "./GenerarRecibo";
import PdfViewerLink from "../PdfViewerLink";

type Mov = {
  operativos: any[];
  extraordinarios: any[];
  viajes: any[];
  aportaciones: any[];
  renta: any[];
  insumos: any[];
  abonosExtra: any[];
};

export default function MovList({
  periodoId,
  mov,
  socios,
  clientes,
  motores,
  marineros = [],
  precioLitro,
  tipoCambio,
}: {
  periodoId: number;
  mov: Mov;
  socios: any[];
  clientes: any[];
  motores: any[];
  marineros?: any[];
  precioLitro: number;
  tipoCambio: number;
}) {
  const router = useRouter();
  const [edit, setEdit] = useState<string | null>(null);
  const close = () => setEdit(null);
  const viajesOpt = mov.viajes.map((v) => ({ id: v.id, fecha: v.fecha, cliente: v.cliente_disp }));

  async function del(url: string, id: number, label: string) {
    if (!confirm(`¿Borrar ${label}? Esta acción no se puede deshacer.`)) return;
    const r = await deleteRow(url, id);
    if (r.ok) router.refresh();
    else alert(r.error || "No se pudo borrar.");
  }

  const Actions = ({ k, onDel }: { k: string; onDel: () => void }) => (
    <td className="tc" style={{ whiteSpace: "nowrap" }}>
      <button className="btn-link" onClick={() => setEdit(edit === k ? null : k)}>Editar</button>
      <button className="btn-link danger" onClick={onDel}>Borrar</button>
    </td>
  );

  return (
    <>
      <GenerarRecurrentes periodoId={periodoId} />

      {/* Gastos operativos */}
      <div className="section-label">Gastos operativos</div>
      <div className="table-card">
        <table>
          <thead><tr><th>Fecha</th><th>Proveedor</th><th>Concepto</th><th className="tr">Monto</th><th>Respaldo (PDF)</th><th className="tc">Acciones</th></tr></thead>
          <tbody>
            {mov.operativos.length === 0 && <tr><td colSpan={6} className="tc hint">Sin gastos operativos.</td></tr>}
            {mov.operativos.map((g) => {
              const k = `op:${g.id}`;
              return edit === k ? (
                <tr key={k}><td colSpan={6}><GastoOperativo periodoId={periodoId} tipoCambio={tipoCambio} initial={g} onDone={close} /></td></tr>
              ) : (
                <tr key={k}>
                  <td>{fechaCorta(g.fecha)}</td><td>{g.proveedor}</td><td>{g.concepto}</td>
                  <td className="tr mono">{mxn(g.monto_mxn)}</td>
                  <td>
                    <Adjuntos gastoId={g.id} adjuntos={g.adjuntos || []} />
                    <GenerarRecibo gastoId={g.id} />
                  </td>
                  <Actions k={k} onDel={() => del("/api/gastos-operativos", g.id, "el gasto")} />
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      {/* Insumos */}
      <div className="section-label">Insumos</div>
      <div className="table-card">
        <table>
          <thead><tr><th>Fecha</th><th>Viaje</th><th>Proveedor</th><th>Concepto</th><th className="tr">Monto</th><th>Respaldo (PDF)</th><th className="tc">Acciones</th></tr></thead>
          <tbody>
            {mov.insumos.length === 0 && <tr><td colSpan={7} className="tc hint">Sin insumos.</td></tr>}
            {mov.insumos.map((i) => {
              const k = `in:${i.id}`;
              return edit === k ? (
                <tr key={k}><td colSpan={7}><Insumos periodoId={periodoId} tipoCambio={tipoCambio} viajes={viajesOpt} initial={i} onDone={close} /></td></tr>
              ) : (
                <tr key={k}>
                  <td>{fechaCorta(i.fecha)}</td>
                  <td>{i.viaje_id ? `${i.viaje_cliente} · ${fechaCorta(i.viaje_fecha)}` : "—"}</td>
                  <td>{i.proveedor || "—"}</td><td>{i.concepto || "—"}</td>
                  <td className="tr mono">{mxn(i.monto_mxn)}</td>
                  <td><Adjuntos insumoId={i.id} adjuntos={i.adjuntos || []} /></td>
                  <Actions k={k} onDel={() => del("/api/insumos", i.id, "el insumo")} />
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      {/* Viajes */}
      <div className="section-label">Viajes</div>
      <div className="table-card">
        <table>
          <thead><tr><th>Fecha</th><th>Socio / cliente</th><th className="tr">Total</th><th className="tc">Acciones</th></tr></thead>
          <tbody>
            {mov.viajes.length === 0 && <tr><td colSpan={4} className="tc hint">Sin viajes.</td></tr>}
            {mov.viajes.map((v) => {
              const k = `vi:${v.id}`;
              return edit === k ? (
                <tr key={k}><td colSpan={4}><Viaje periodoId={periodoId} socios={socios} clientes={clientes} motores={motores} marineros={marineros} precioLitro={precioLitro} initial={v} onDone={close} /></td></tr>
              ) : (
                <tr key={k}>
                  <td>{fechaCorta(v.fecha)} {v.bandera && (
                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="var(--amber)" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" style={{ verticalAlign: "-2px" }} aria-label="anómalo"><path d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z" /></svg>
                  )}</td>
                  <td>{v.cliente_disp}{v.es_renta ? " · Renta" : ""}</td>
                  <td className="tr mono">{mxn(v.total)}</td>
                  <Actions k={k} onDel={() => del("/api/viajes", v.id, "el viaje")} />
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      {/* Marineros (informativo, derivado de los viajes) */}
      <div className="section-label">Marineros</div>
      <div className="table-card">
        <table>
          <thead><tr><th>Fecha</th><th>Marinero</th><th>Cliente / viaje</th><th className="tr">Pago</th><th className="tc">Recibo</th></tr></thead>
          <tbody>
            {mov.viajes.length === 0 && <tr><td colSpan={5} className="tc hint">Sin viajes.</td></tr>}
            {mov.viajes.map((v) => (
              <tr key={`ma:${v.id}`}>
                <td>{fechaCorta(v.fecha)}</td>
                <td>{v.marinero && String(v.marinero).trim() ? v.marinero : <span className="hint">Sin asignar</span>}</td>
                <td>{v.cliente_disp}{v.es_renta ? " · Renta" : ""}</td>
                <td className="tr mono">{mxn(v.costo_marinero)}</td>
                <td className="tc"><PdfViewerLink className="btn-link" href={`/api/viajes/recibo?id=${v.id}`} titulo="Recibo de pago a marinero">Ver PDF</PdfViewerLink></td>
              </tr>
            ))}
            {mov.viajes.length > 0 && (
              <tr className="row-total">
                <td colSpan={3} className="tr">TOTAL MARINEROS</td>
                <td className="tr mono">{mxn(mov.viajes.reduce((s: number, v: any) => s + Number(v.costo_marinero || 0), 0))}</td>
                <td />
              </tr>
            )}
          </tbody>
        </table>
      </div>
      <div className="hint" style={{ margin: "-8px 0 4px" }}>El marinero y su pago se editan dentro de cada viaje (sección Viajes).</div>

      {/* Aportaciones */}
      <div className="section-label">Aportaciones</div>
      <div className="table-card">
        <table>
          <thead><tr><th>Fecha</th><th>Socio</th><th className="tr">Monto</th><th className="tc">Acciones</th></tr></thead>
          <tbody>
            {mov.aportaciones.length === 0 && <tr><td colSpan={4} className="tc hint">Sin aportaciones.</td></tr>}
            {mov.aportaciones.map((a) => {
              const k = `ap:${a.id}`;
              return edit === k ? (
                <tr key={k}><td colSpan={4}><Aportacion periodoId={periodoId} socios={socios} initial={a} onDone={close} /></td></tr>
              ) : (
                <tr key={k}>
                  <td>{fechaCorta(a.fecha)}</td><td>{a.socio_nombre}</td>
                  <td className="tr mono">{mxn(a.monto)}</td>
                  <Actions k={k} onDel={() => del("/api/aportaciones", a.id, "la aportación")} />
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      {/* Extraordinarios */}
      <div className="section-label" style={{ borderLeftColor: "#c05621", color: "#c05621" }}>Extraordinarios</div>
      <div className="table-card" style={{ border: "1px solid #f5b7a0" }}>
        <table>
          <thead><tr><th>Fecha</th><th>Proveedor</th><th>Concepto</th><th className="tr">Monto</th><th>Respaldo (PDF)</th><th className="tc">Acciones</th></tr></thead>
          <tbody>
            {mov.extraordinarios.length === 0 && <tr><td colSpan={6} className="tc hint">Sin extraordinarios.</td></tr>}
            {mov.extraordinarios.map((e) => {
              const k = `ex:${e.id}`;
              return edit === k ? (
                <tr key={k}><td colSpan={6}><Extraordinario periodoId={periodoId} tipoCambio={tipoCambio} initial={e} onDone={close} /></td></tr>
              ) : (
                <tr key={k}>
                  <td>{fechaCorta(e.fecha)}</td><td>{e.proveedor}</td><td>{e.concepto}</td>
                  <td className="tr mono">{mxn(e.monto_mxn)}</td>
                  <td><Adjuntos extraordinarioId={e.id} adjuntos={e.adjuntos || []} /></td>
                  <Actions k={k} onDel={() => del("/api/extraordinarios", e.id, "el extraordinario")} />
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      {/* Abonos a extraordinarios */}
      <div className="section-label">Abonos a extraordinarios</div>
      <div className="table-card">
        <table>
          <thead><tr><th>Fecha</th><th>Socio</th><th>Método</th><th>Referencia</th><th className="tr">Monto</th><th className="tc">Acciones</th></tr></thead>
          <tbody>
            {mov.abonosExtra.length === 0 && <tr><td colSpan={6} className="tc hint">Sin abonos a extraordinarios.</td></tr>}
            {mov.abonosExtra.map((a) => {
              const k = `ae:${a.id}`;
              return edit === k ? (
                <tr key={k}><td colSpan={6}><AbonoExtraordinario periodoId={periodoId} socios={socios} initial={a} onDone={close} /></td></tr>
              ) : (
                <tr key={k}>
                  <td>{fechaCorta(a.fecha)}</td><td>{a.socio_nombre}</td>
                  <td>{a.metodo || "—"}</td><td>{a.referencia || "—"}</td>
                  <td className="tr mono">{mxn(a.monto)}</td>
                  <Actions k={k} onDel={() => del("/api/abonos-extraordinarios", a.id, "el abono")} />
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      {/* Renta */}
      <div className="section-label">Ingresos por renta</div>
      <div className="table-card">
        <table>
          <thead><tr><th>Fecha</th><th>Cliente</th><th className="tr">Ingreso</th><th className="tr">Utilidad</th><th className="tc">Acciones</th></tr></thead>
          <tbody>
            {mov.renta.length === 0 && <tr><td colSpan={5} className="tc hint">Sin ingresos por renta.</td></tr>}
            {mov.renta.map((r) => {
              const k = `re:${r.id}`;
              return edit === k ? (
                <tr key={k}><td colSpan={5}><Renta periodoId={periodoId} initial={r} onDone={close} /></td></tr>
              ) : (
                <tr key={k}>
                  <td>{fechaCorta(r.fecha)}</td><td>{r.cliente}</td>
                  <td className="tr mono">{mxn(r.monto)}</td><td className="tr mono">{mxn(r.utilidad)}</td>
                  <Actions k={k} onDel={() => del("/api/ingresos-renta", r.id, "el ingreso")} />
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </>
  );
}
