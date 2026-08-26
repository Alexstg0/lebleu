"use client";

import { useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { mxn, fechaISO, fechaCorta, n } from "@/lib/format";
import Adjuntos from "../movimientos/Adjuntos";
import PdfViewerLink from "../PdfViewerLink";

type Adj = { id: number; nombre: string; generado: boolean };

type Mov = {
  id: number;
  fecha: string;
  tipo: "gasto" | "abono";
  caja_numero: number | null;
  factura: string | null;
  proveedor: string | null;
  concepto: string | null;
  monto: string | number;
  abono: string | number;
  observaciones: string | null;
  balance: number;
  adjuntos: Adj[];
};

type Form = {
  id: number | null;
  fecha: string;
  tipo: "gasto" | "abono";
  caja_numero: string;
  factura: string;
  proveedor: string;
  concepto: string;
  monto: string;
  abono: string;
  observaciones: string;
};

const pad = (x: number) => String(x).padStart(2, "0");
const hoyISO = () => {
  const d = new Date();
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
};

export default function CajaChicaClient({
  movimientos,
  hoyTexto,
  embarcacion,
  razonSocial,
}: {
  movimientos: Mov[];
  hoyTexto: string;
  embarcacion?: string;
  razonSocial?: string | null;
}) {
  const router = useRouter();
  const [filtro, setFiltro] = useState<string>("todas");
  const [responsable, setResponsable] = useState("Gabriel Preciado");
  const [editing, setEditing] = useState<Form | null>(null);
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState("");

  // Cajas disponibles (de mayor a menor; la última es la más reciente).
  const cajas = useMemo(() => {
    const s = new Set<number>();
    for (const m of movimientos) if (m.caja_numero != null) s.add(Number(m.caja_numero));
    return [...s].sort((a, b) => b - a);
  }, [movimientos]);

  const ultimaCaja = cajas.length ? Math.max(...cajas) : 13;
  const esTodas = filtro === "todas";
  const cajaSel = esTodas ? null : Number(filtro);

  const filas = useMemo(
    () => (esTodas ? movimientos : movimientos.filter((m) => Number(m.caja_numero) === cajaSel)),
    [movimientos, esTodas, cajaSel]
  );

  // Totales para la vista por caja (formato "Total a pagar").
  const totalGastos = filas.reduce((s, m) => s + n(m.monto), 0);
  const totalAbonado = filas.reduce((s, m) => s + n(m.abono), 0);
  const totalPagar = Math.round((totalGastos - totalAbonado) * 100) / 100;
  const balanceActual = movimientos.length ? movimientos[movimientos.length - 1].balance : 0;

  function nuevo() {
    setErr("");
    setEditing({
      id: null,
      fecha: hoyISO(),
      tipo: "gasto",
      caja_numero: String(cajaSel ?? ultimaCaja),
      factura: "",
      proveedor: "",
      concepto: "",
      monto: "",
      abono: "",
      observaciones: "",
    });
  }

  function abrir(m: Mov) {
    setErr("");
    setEditing({
      id: m.id,
      fecha: fechaISO(m.fecha),
      tipo: m.tipo,
      caja_numero: m.caja_numero != null ? String(m.caja_numero) : "",
      factura: m.factura ?? "",
      proveedor: m.proveedor ?? "",
      concepto: m.concepto ?? "",
      monto: m.monto != null ? String(m.monto) : "",
      abono: m.abono != null ? String(m.abono) : "",
      observaciones: m.observaciones ?? "",
    });
  }

  const set = (k: keyof Form, v: any) => setEditing((e) => (e ? { ...e, [k]: v } : e));

  async function guardar(e: React.FormEvent) {
    e.preventDefault();
    if (!editing) return;
    setBusy(true);
    setErr("");
    const payload = {
      id: editing.id,
      fecha: editing.fecha,
      tipo: editing.tipo,
      caja_numero: editing.caja_numero ? Number(editing.caja_numero) : null,
      factura: editing.factura,
      proveedor: editing.proveedor,
      concepto: editing.concepto,
      monto: editing.tipo === "abono" ? 0 : Number(editing.monto || 0),
      abono: editing.tipo === "abono" ? Number(editing.abono || 0) : 0,
      observaciones: editing.observaciones,
    };
    const res = await fetch("/api/caja-chica", {
      method: editing.id ? "PATCH" : "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const j = await res.json();
    setBusy(false);
    if (j.ok) {
      setEditing(null);
      router.refresh();
    } else setErr(j.error || "No se pudo guardar.");
  }

  async function borrar() {
    if (!editing?.id) return;
    if (!confirm("¿Borrar este movimiento?")) return;
    setBusy(true);
    const res = await fetch(`/api/caja-chica?id=${editing.id}`, { method: "DELETE" });
    const j = await res.json();
    setBusy(false);
    if (j.ok) {
      setEditing(null);
      router.refresh();
    } else setErr(j.error || "No se pudo borrar.");
  }

  return (
    <>
      {/* Encabezado para PDF */}
      <div className="caja-print-head">
        <img src="/logo-lebleu.png" alt="Le Bleu" />
        <div className="caja-print-info">
          <h2>Balance de caja chica{esTodas ? "" : ` — Caja ${cajaSel}`}</h2>
          <div>{razonSocial || "Arrendadora Acma S de RL de CV"} · Embarcación {embarcacion || "Le Bleu"}</div>
          <div className="caja-print-meta">
            <span>Fecha: {hoyTexto}</span>
            <span>Responsable de caja: {responsable}</span>
          </div>
        </div>
      </div>

      {/* Barra de herramientas (pantalla) */}
      <div className="cc-toolbar">
        <div className="cc-filtros">
          <button className={`cc-pill ${esTodas ? "on" : ""}`} onClick={() => setFiltro("todas")}>Todas</button>
          {cajas.map((c) => (
            <button key={c} className={`cc-pill ${cajaSel === c ? "on" : ""}`} onClick={() => setFiltro(String(c))}>
              Caja {c}
            </button>
          ))}
        </div>
        <div className="cc-tools-right">
          <label className="cc-resp">
            Responsable
            <input value={responsable} onChange={(e) => setResponsable(e.target.value)} />
          </label>
          <PdfViewerLink
            className="btn-ghost"
            titulo={`Reporte de caja chica${esTodas ? "" : ` — Caja ${cajaSel}`}`}
            href={`/api/reportes/caja-chica?caja=${esTodas ? "todas" : cajaSel}&responsable=${encodeURIComponent(responsable)}`}
          >
            Reporte PDF
          </PdfViewerLink>
          <button className="btn" onClick={nuevo}>+ Movimiento</button>
        </div>
      </div>

      {/* Resumen */}
      <div className="cc-cards">
        {esTodas ? (
          <>
            <div className="cc-card"><span>Gastos</span><b>{mxn(totalGastos)}</b></div>
            <div className="cc-card"><span>Abonos</span><b>{mxn(totalAbonado)}</b></div>
            <div className={`cc-card ${balanceActual < 0 ? "neg" : "pos"}`}><span>Balance actual</span><b>{mxn(balanceActual)}</b></div>
          </>
        ) : (
          <>
            <div className="cc-card"><span>Total</span><b>{mxn(totalGastos)}</b></div>
            <div className="cc-card"><span>Abonado</span><b>{mxn(totalAbonado)}</b></div>
            <div className={`cc-card ${totalPagar > 0 ? "neg" : "pos"}`}><span>Total a pagar</span><b>{mxn(totalPagar)}</b></div>
          </>
        )}
      </div>


      {filas.length === 0 ? (
        <div className="cc-empty">Sin movimientos{esTodas ? "" : ` en la Caja ${cajaSel}`}. Usa “+ Movimiento” para registrar gastos o abonos.</div>
      ) : (
        <div className="cc-table-wrap">
          <table className={`resp-table cc-table ${esTodas ? "" : "cc-soporte"}`}>
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Caja</th>
                <th>Factura</th>
                <th>Proveedor</th>
                <th>Concepto</th>
                <th className="r">Monto</th>
                <th className="r cc-abono-col">Abono</th>
                {esTodas && <th className="r">Balance</th>}
                <th>Observaciones</th>
                <th className="cc-soporte-col">Soporte</th>
              </tr>
            </thead>
            <tbody>
              {filas.map((m) => (
                <tr key={m.id} onClick={() => abrir(m)} className="cc-row">
                  <td data-label="Fecha">{fechaCorta(m.fecha)}</td>
                  <td data-label="Caja">{m.caja_numero ?? "—"}</td>
                  <td data-label="Factura">{m.factura || "—"}</td>
                  <td data-label="Proveedor">{m.proveedor || "—"}</td>
                  <td data-label="Concepto">{m.concepto || "—"}</td>
                  <td data-label="Monto" className="r">{n(m.monto) ? mxn(m.monto) : "—"}</td>
                  <td data-label="Abono" className="r cc-abono-col">{n(m.abono) ? mxn(m.abono) : "—"}</td>
                  {esTodas && <td data-label="Balance" className={`r ${m.balance < 0 ? "neg" : ""}`}>{mxn(m.balance)}</td>}
                  <td data-label="Observaciones">{m.observaciones || "—"}</td>
                  <td data-label="Soporte" className="cc-soporte-col" onClick={(e) => e.stopPropagation()}>
                    <Adjuntos cajaId={m.id} adjuntos={m.adjuntos || []} />
                  </td>
                </tr>
              ))}
            </tbody>
            <tfoot>
              <tr>
                <td colSpan={5} className="r"><b>Totales</b></td>
                <td className="r"><b>{mxn(totalGastos)}</b></td>
                <td className="r cc-abono-col"><b>{mxn(totalAbonado)}</b></td>
                {esTodas && <td className="r"><b>{mxn(balanceActual)}</b></td>}
                <td className="cc-apagar">{esTodas ? "" : <b>A pagar: {mxn(totalPagar)}</b>}</td>
                <td className="cc-soporte-col" />
              </tr>
            </tfoot>
          </table>
        </div>
      )}

      {/* Firma (solo PDF) */}
      <div className="caja-sign">
        <div className="caja-sign-box">
          <span className="line" />
          <span>Kevin Flores</span>
          <small>Revisó</small>
        </div>
      </div>

      {editing && (
        <div className="modal-overlay" onClick={() => setEditing(null)}>
          <div className="modal" onClick={(e) => e.stopPropagation()}>
            <h2 style={{ margin: "0 0 14px", fontSize: 18 }}>{editing.id ? "Editar movimiento" : "Nuevo movimiento"}</h2>
            <form onSubmit={guardar}>
              <div className="cc-tipo">
                <button type="button" className={editing.tipo === "gasto" ? "on" : ""} onClick={() => set("tipo", "gasto")}>Gasto</button>
                <button type="button" className={editing.tipo === "abono" ? "on" : ""} onClick={() => set("tipo", "abono")}>Abono</button>
              </div>
              <div className="grid">
                <div className="field">
                  <label>Fecha</label>
                  <input type="date" required value={editing.fecha} onChange={(e) => set("fecha", e.target.value)} />
                </div>
                <div className="field">
                  <label>Número de caja</label>
                  <input type="number" value={editing.caja_numero} onChange={(e) => set("caja_numero", e.target.value)} placeholder="13" />
                </div>
                {editing.tipo === "gasto" ? (
                  <div className="field">
                    <label>Monto</label>
                    <input type="number" step="0.01" value={editing.monto} onChange={(e) => set("monto", e.target.value)} placeholder="0.00" />
                  </div>
                ) : (
                  <div className="field">
                    <label>Abono</label>
                    <input type="number" step="0.01" value={editing.abono} onChange={(e) => set("abono", e.target.value)} placeholder="0.00" />
                  </div>
                )}
                <div className="field">
                  <label>Factura / folio</label>
                  <input value={editing.factura} onChange={(e) => set("factura", e.target.value)} placeholder="Opcional" />
                </div>
                <div className="field">
                  <label>Proveedor</label>
                  <input value={editing.proveedor} onChange={(e) => set("proveedor", e.target.value)} placeholder="Opcional" />
                </div>
                <div className="field full">
                  <label>Concepto</label>
                  <input value={editing.concepto} onChange={(e) => set("concepto", e.target.value)} placeholder="¿En qué se usó?" />
                </div>
                <div className="field full">
                  <label>Observaciones</label>
                  <input value={editing.observaciones} onChange={(e) => set("observaciones", e.target.value)} />
                </div>
              </div>
              {err && <div className="msg-err" style={{ marginTop: 12 }}>{err}</div>}
              <div style={{ marginTop: 18, display: "flex", alignItems: "center", gap: 10 }}>
                <button className="btn" disabled={busy}>{busy ? "Guardando…" : editing.id ? "Actualizar" : "Registrar"}</button>
                <button type="button" className="btn-ghost" onClick={() => setEditing(null)}>Cancelar</button>
                {editing.id && <button type="button" className="btn-link danger" style={{ marginLeft: "auto" }} onClick={borrar}>Borrar</button>}
              </div>
            </form>
          </div>
        </div>
      )}
    </>
  );
}
