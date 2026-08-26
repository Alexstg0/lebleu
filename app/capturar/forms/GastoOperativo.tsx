"use client";

import { useState } from "react";
import { useSubmit, today, toDate } from "./useSubmit";

export default function GastoOperativo({
  periodoId,
  tipoCambio,
  initial,
  onDone,
}: {
  periodoId: number;
  tipoCambio: number;
  initial?: any;
  onDone?: () => void;
}) {
  const editing = !!initial?.id;
  const { busy, msg, submit } = useSubmit("/api/gastos-operativos", editing ? "PATCH" : "POST");
  const [f, setF] = useState({
    fecha: editing ? toDate(initial.fecha) : today(),
    proveedor: initial?.proveedor ?? "",
    concepto: initial?.concepto ?? "",
    categoria: initial?.categoria ?? "servicio",
    moneda: initial?.moneda ?? "MXN",
    monto_original: initial ? String(initial.monto_original) : "",
    tipo_cambio: String(initial?.tipo_cambio ?? tipoCambio),
    comprobante_folio: initial?.comprobante_folio ?? "",
  });

  function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    submit(
      {
        ...f,
        id: initial?.id,
        periodo_id: periodoId,
        monto_original: Number(f.monto_original),
        tipo_cambio: Number(f.tipo_cambio),
      },
      () => (editing ? onDone?.() : setF({ ...f, proveedor: "", concepto: "", monto_original: "", comprobante_folio: "" }))
    );
  }

  return (
    <form className="form-card" onSubmit={onSubmit}>
      {!editing && <h2>Nuevo gasto operativo (50/50)</h2>}
      <div className="grid">
        <div className="field">
          <label>Fecha</label>
          <input type="date" required value={f.fecha} onChange={(e) => setF({ ...f, fecha: e.target.value })} />
        </div>
        <div className="field">
          <label>Categoría</label>
          <select value={f.categoria} onChange={(e) => setF({ ...f, categoria: e.target.value })}>
            <option value="nomina">Nómina</option>
            <option value="impuesto">Impuesto</option>
            <option value="servicio">Servicio</option>
            <option value="refaccion">Refacción</option>
            <option value="consumible">Consumible</option>
            <option value="admin">Administración</option>
            <option value="otro">Otro</option>
          </select>
        </div>
        <div className="field">
          <label>Proveedor</label>
          <input required value={f.proveedor} onChange={(e) => setF({ ...f, proveedor: e.target.value })} />
        </div>
        <div className="field">
          <label>Concepto</label>
          <input required value={f.concepto} onChange={(e) => setF({ ...f, concepto: e.target.value })} />
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
          <input value={f.comprobante_folio} onChange={(e) => setF({ ...f, comprobante_folio: e.target.value })} placeholder="Factura, NA, SIPARE…" />
        </div>
      </div>
      <div style={{ marginTop: 16, display: "flex", alignItems: "center", gap: 14 }}>
        <button className="btn" disabled={busy}>{busy ? "Guardando…" : editing ? "Actualizar" : "Guardar gasto"}</button>
        {editing && <button type="button" className="btn-ghost" onClick={onDone}>Cancelar</button>}
        {msg && <span className={msg.ok ? "msg-ok" : "msg-err"}>{msg.t}</span>}
      </div>
    </form>
  );
}
