"use client";

import { useState } from "react";
import { useSubmit, today, round2, toDate } from "./useSubmit";

export default function Renta({
  periodoId,
  initial,
  onDone,
}: {
  periodoId: number;
  initial?: any;
  onDone?: () => void;
}) {
  const editing = !!initial?.id;
  const { busy, msg, submit } = useSubmit("/api/ingresos-renta", editing ? "PATCH" : "POST");
  const [f, setF] = useState({
    fecha: editing ? toDate(initial.fecha) : today(),
    cliente: initial?.cliente ?? "",
    monto: initial ? String(initial.monto) : "",
    costos_asociados: initial ? String(initial.costos_asociados) : "",
  });

  const utilidad = round2(Number(f.monto || 0) - Number(f.costos_asociados || 0));

  function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    submit(
      {
        id: initial?.id,
        periodo_id: periodoId,
        cliente: f.cliente,
        fecha: f.fecha,
        monto: Number(f.monto || 0),
        costos_asociados: Number(f.costos_asociados || 0),
      },
      () => (editing ? onDone?.() : setF({ ...f, cliente: "", monto: "", costos_asociados: "" }))
    );
  }

  return (
    <form className="form-card" onSubmit={onSubmit}>
      {!editing && (
        <>
          <h2>Nuevo ingreso por renta</h2>
          <div className="hint" style={{ marginBottom: 14 }}>
            La utilidad (ingreso − costos) se reparte 50/50 y suma al balance operativo.
          </div>
        </>
      )}
      <div className="grid">
        <div className="field">
          <label>Fecha</label>
          <input type="date" required value={f.fecha} onChange={(e) => setF({ ...f, fecha: e.target.value })} />
        </div>
        <div className="field">
          <label>Cliente</label>
          <input required value={f.cliente} onChange={(e) => setF({ ...f, cliente: e.target.value })} />
        </div>
        <div className="field">
          <label>Ingreso (MXN)</label>
          <input type="number" step="0.01" required value={f.monto} onChange={(e) => setF({ ...f, monto: e.target.value })} />
        </div>
        <div className="field">
          <label>Costos asociados (MXN)</label>
          <input type="number" step="0.01" value={f.costos_asociados} onChange={(e) => setF({ ...f, costos_asociados: e.target.value })} />
        </div>
      </div>
      <div style={{ marginTop: 16, display: "flex", alignItems: "center", gap: 14, flexWrap: "wrap" }}>
        <button className="btn" disabled={busy}>{busy ? "Guardando…" : editing ? "Actualizar" : "Guardar renta"}</button>
        {editing && <button type="button" className="btn-ghost" onClick={onDone}>Cancelar</button>}
        <span className="hint">Utilidad: <strong className="mono">${utilidad.toLocaleString("es-MX", { minimumFractionDigits: 2 })}</strong></span>
        {msg && <span className={msg.ok ? "msg-ok" : "msg-err"}>{msg.t}</span>}
      </div>
    </form>
  );
}
