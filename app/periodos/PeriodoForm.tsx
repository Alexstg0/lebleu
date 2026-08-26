"use client";

import { useState } from "react";
import { useSubmit } from "../capturar/forms/useSubmit";

const MESES = [
  "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio",
  "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre",
];

export default function PeriodoForm({
  sugerido,
  tipoCambio,
  precioLitro,
}: {
  sugerido: { anio: number; mes: number };
  tipoCambio: number;
  precioLitro: number;
}) {
  const { busy, msg, submit } = useSubmit("/api/periodos");
  const [f, setF] = useState({
    anio: String(sugerido.anio),
    mes: String(sugerido.mes),
    tipo_cambio: String(tipoCambio),
    precio_litro: String(precioLitro),
    cargo_administracion: "6000",
  });

  function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    submit({
      embarcacion_id: 1,
      anio: Number(f.anio),
      mes: Number(f.mes),
      tipo_cambio: Number(f.tipo_cambio),
      precio_litro: Number(f.precio_litro),
      cargo_administracion: Number(f.cargo_administracion),
    });
  }

  return (
    <form className="form-card" onSubmit={onSubmit}>
      <h2>Abrir nuevo periodo</h2>
      <div className="hint" style={{ marginBottom: 14 }}>
        El saldo de inicio de cada socio se arrastra automáticamente del balance del mes anterior,
        que queda cerrado.
      </div>
      <div className="grid">
        <div className="field">
          <label>Mes</label>
          <select value={f.mes} onChange={(e) => setF({ ...f, mes: e.target.value })}>
            {MESES.map((m, i) => <option key={i} value={i + 1}>{m}</option>)}
          </select>
        </div>
        <div className="field">
          <label>Año</label>
          <input type="number" required value={f.anio} onChange={(e) => setF({ ...f, anio: e.target.value })} />
        </div>
        <div className="field">
          <label>Tipo de cambio</label>
          <input type="number" step="0.0001" value={f.tipo_cambio} onChange={(e) => setF({ ...f, tipo_cambio: e.target.value })} />
        </div>
        <div className="field">
          <label>Precio combustible / litro</label>
          <input type="number" step="0.0001" value={f.precio_litro} onChange={(e) => setF({ ...f, precio_litro: e.target.value })} />
        </div>
        <div className="field">
          <label>Cargo por administración</label>
          <input type="number" step="0.01" value={f.cargo_administracion} onChange={(e) => setF({ ...f, cargo_administracion: e.target.value })} />
        </div>
      </div>
      <div style={{ marginTop: 16, display: "flex", alignItems: "center", gap: 14 }}>
        <button className="btn" disabled={busy}>{busy ? "Creando…" : "Abrir periodo"}</button>
        {msg && <span className={msg.ok ? "msg-ok" : "msg-err"}>{msg.t}</span>}
      </div>
    </form>
  );
}
