"use client";

import { useState } from "react";
import { useSubmit, today, toDate } from "./useSubmit";

type Socio = { id: number; nombre: string };

export default function AbonoExtraordinario({
  periodoId,
  socios,
  initial,
  onDone,
}: {
  periodoId: number;
  socios: Socio[];
  initial?: any;
  onDone?: () => void;
}) {
  const editing = !!initial?.id;
  const { busy, msg, submit } = useSubmit("/api/abonos-extraordinarios", editing ? "PATCH" : "POST");
  const [f, setF] = useState({
    fecha: editing ? toDate(initial.fecha) : today(),
    socio_id: String(initial?.socio_id ?? socios[0]?.id ?? ""),
    monto: initial ? String(initial.monto) : "",
    metodo: initial?.metodo ?? "transferencia",
    referencia: initial?.referencia ?? "",
  });

  function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    submit(
      { ...f, id: initial?.id, periodo_id: periodoId, socio_id: Number(f.socio_id), monto: Number(f.monto) },
      () => (editing ? onDone?.() : setF({ ...f, monto: "", referencia: "" }))
    );
  }

  return (
    <form className="form-card" onSubmit={onSubmit}>
      {!editing && <h2>Nuevo abono a extraordinarios</h2>}
      <p className="hint" style={{ margin: "-6px 0 12px" }}>Pago de un socio a su cuenta de gastos extraordinarios (se liquidan por separado).</p>
      <div className="grid">
        <div className="field">
          <label>Fecha</label>
          <input type="date" required value={f.fecha} onChange={(e) => setF({ ...f, fecha: e.target.value })} />
        </div>
        <div className="field">
          <label>Socio</label>
          <select value={f.socio_id} onChange={(e) => setF({ ...f, socio_id: e.target.value })}>
            {socios.map((s) => <option key={s.id} value={s.id}>{s.nombre}</option>)}
          </select>
        </div>
        <div className="field">
          <label>Monto (MXN)</label>
          <input type="number" step="0.01" required value={f.monto} onChange={(e) => setF({ ...f, monto: e.target.value })} />
        </div>
        <div className="field">
          <label>Método</label>
          <select value={f.metodo} onChange={(e) => setF({ ...f, metodo: e.target.value })}>
            <option value="transferencia">Transferencia</option>
            <option value="efectivo">Efectivo</option>
            <option value="cheque">Cheque</option>
            <option value="otro">Otro</option>
          </select>
        </div>
        <div className="field full">
          <label>Referencia</label>
          <input value={f.referencia} onChange={(e) => setF({ ...f, referencia: e.target.value })} placeholder="Folio / nota" />
        </div>
      </div>
      <div style={{ marginTop: 16, display: "flex", alignItems: "center", gap: 14 }}>
        <button className="btn" disabled={busy}>{busy ? "Guardando…" : editing ? "Actualizar" : "Guardar abono"}</button>
        {editing && <button type="button" className="btn-ghost" onClick={onDone}>Cancelar</button>}
        {msg && <span className={msg.ok ? "msg-ok" : "msg-err"}>{msg.t}</span>}
      </div>
    </form>
  );
}
