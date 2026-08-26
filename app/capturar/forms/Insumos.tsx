"use client";

import { useState } from "react";
import { useSubmit, today, toDate } from "./useSubmit";
import { fechaCorta } from "@/lib/format";

type ViajeOpt = { id: number; fecha: string; cliente: string };

export default function Insumos({
  periodoId,
  tipoCambio,
  viajes,
  initial,
  onDone,
}: {
  periodoId: number;
  tipoCambio: number;
  viajes: ViajeOpt[];
  initial?: any;
  onDone?: () => void;
}) {
  const editing = !!initial?.id;
  const { busy, msg, submit } = useSubmit("/api/insumos", editing ? "PATCH" : "POST");
  const [f, setF] = useState({
    fecha: editing ? toDate(initial.fecha) : today(),
    viaje_id: String(initial?.viaje_id ?? viajes[0]?.id ?? ""),
    categoria: initial?.categoria ?? "alimentos",
    proveedor: initial?.proveedor ?? "",
    concepto: initial?.concepto ?? "",
    moneda: initial?.moneda ?? "MXN",
    monto_original: initial ? String(initial.monto_original) : "",
    tipo_cambio: String(initial?.tipo_cambio ?? tipoCambio),
    comprobante_folio: initial?.comprobante_folio ?? "",
  });

  function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!f.viaje_id) { alert("Selecciona el viaje al que pertenecen los insumos."); return; }
    submit(
      {
        ...f,
        id: initial?.id,
        periodo_id: periodoId,
        viaje_id: Number(f.viaje_id),
        monto_original: Number(f.monto_original),
        tipo_cambio: Number(f.tipo_cambio),
      },
      () => (editing ? onDone?.() : setF({ ...f, proveedor: "", concepto: "", monto_original: "", comprobante_folio: "" }))
    );
  }

  return (
    <form className="form-card" onSubmit={onSubmit}>
      {!editing && <h2>Nuevos insumos del viaje</h2>}
      {viajes.length === 0 && (
        <div className="msg-err" style={{ marginBottom: 12 }}>
          No hay viajes en este periodo. Registra primero el viaje para poder asignarle insumos.
        </div>
      )}
      <div className="grid">
        <div className="field">
          <label>Fecha</label>
          <input type="date" required value={f.fecha} onChange={(e) => setF({ ...f, fecha: e.target.value })} />
        </div>
        <div className="field">
          <label>Viaje</label>
          <select value={f.viaje_id} onChange={(e) => setF({ ...f, viaje_id: e.target.value })}>
            <option value="">— Selecciona un viaje —</option>
            {viajes.map((v) => (
              <option key={v.id} value={v.id}>{fechaCorta(v.fecha)} · {v.cliente}</option>
            ))}
          </select>
        </div>
        <div className="field">
          <label>Categoría</label>
          <select value={f.categoria} onChange={(e) => setF({ ...f, categoria: e.target.value })}>
            <option value="alimentos">Alimentos</option>
            <option value="bebidas">Bebidas</option>
            <option value="hielo">Hielo</option>
            <option value="limpieza">Limpieza</option>
            <option value="otro">Otro</option>
          </select>
        </div>
        <div className="field">
          <label>Proveedor</label>
          <input value={f.proveedor} onChange={(e) => setF({ ...f, proveedor: e.target.value })} placeholder="ej. Chedraui" />
        </div>
        <div className="field">
          <label>Concepto</label>
          <input value={f.concepto} onChange={(e) => setF({ ...f, concepto: e.target.value })} placeholder="ej. Despensa, hielo, agua" />
        </div>
        <div className="field">
          <label>Moneda</label>
          <select value={f.moneda} onChange={(e) => setF({ ...f, moneda: e.target.value })}>
            <option value="MXN">MXN</option>
            <option value="USD">USD</option>
          </select>
        </div>
        <div className="field">
          <label>{f.moneda === "USD" ? "Monto (USD)" : "Monto (MXN)"}</label>
          <input type="number" step="0.01" required value={f.monto_original} onChange={(e) => setF({ ...f, monto_original: e.target.value })} />
        </div>
        {f.moneda === "USD" && (
          <div className="field">
            <label>Tipo de cambio</label>
            <input type="number" step="0.0001" value={f.tipo_cambio} onChange={(e) => setF({ ...f, tipo_cambio: e.target.value })} />
          </div>
        )}
        <div className="field">
          <label>Comprobante / folio</label>
          <input value={f.comprobante_folio} onChange={(e) => setF({ ...f, comprobante_folio: e.target.value })} placeholder="Factura, ticket…" />
        </div>
      </div>
      <div style={{ marginTop: 16, display: "flex", alignItems: "center", gap: 14 }}>
        <button className="btn" disabled={busy || viajes.length === 0}>{busy ? "Guardando…" : editing ? "Actualizar" : "Guardar insumos"}</button>
        {editing && <button type="button" className="btn-ghost" onClick={onDone}>Cancelar</button>}
        {msg && <span className={msg.ok ? "msg-ok" : "msg-err"}>{msg.t}</span>}
      </div>
    </form>
  );
}
