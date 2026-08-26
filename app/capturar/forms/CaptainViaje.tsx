"use client";

import { useState } from "react";
import { useSubmit, today, round2 } from "./useSubmit";
import { fechaISO } from "@/lib/format";
import MarineroSelect from "./MarineroSelect";

type Cliente = { id: number; nombre: string; socio_id: number | null };
type Motor = { id: number; etiqueta: string; horometro_actual: string };
type MarineroCat = { id: number; nombre: string };
type ViajeExistente = { id: number; fecha: string; cliente: string };

const GAL_A_LITRO = 3.78541;
const MARINERO = 800; // tarifa fija del marinero por viaje

export default function CaptainViaje({
  periodoId,
  clientes,
  motores,
  precioLitro,
  marineros = [],
  viajesExistentes = [],
}: {
  periodoId: number;
  clientes: Cliente[];
  motores: Motor[];
  precioLitro: number;
  marineros?: MarineroCat[];
  viajesExistentes?: ViajeExistente[];
}) {
  const { busy, msg, submit } = useSubmit("/api/viajes");
  const motB = motores[0];
  const motE = motores[1];

  const [f, setF] = useState({
    fecha: today(),
    quien: clientes[0] ? `cli:${clientes[0].id}` : "renta",
    rentaNombre: "",
    marinero: "",
    num_personas: "",
    duracion_horas: "",
    bIni: motB ? String(motB.horometro_actual) : "",
    bFin: "",
    eIni: motE ? String(motE.horometro_actual) : "",
    eFin: "",
    galIni: "",
    galFin: "",
  });
  const set = (k: string, v: string) => setF((s) => ({ ...s, [k]: v }));
  const esRenta = f.quien === "renta";

  const litrosIni = round2(Number(f.galIni || 0) * GAL_A_LITRO);
  const litrosFin = round2(Number(f.galFin || 0) * GAL_A_LITRO);
  const consumo = round2(litrosIni - litrosFin);
  const horasB = f.bFin && f.bIni ? round2(Number(f.bFin) - Number(f.bIni)) : null;
  const horasE = f.eFin && f.eIni ? round2(Number(f.eFin) - Number(f.eIni)) : null;

  function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    let socio_id: number | null = null;
    let cliente_id: number | null = null;
    let cliente_nombre: string | null = null;

    if (esRenta) {
      if (!f.rentaNombre.trim()) { alert("Escribe el nombre del cliente de la renta."); return; }
      cliente_nombre = f.rentaNombre.trim();
    } else {
      const cli = clientes.find((c) => `cli:${c.id}` === f.quien);
      if (!cli?.socio_id) { alert("Selecciona una familia."); return; }
      socio_id = cli.socio_id;
      cliente_id = cli.id;
    }

    // Validaciones de captura
    if (f.bIni && f.bFin && Number(f.bFin) < Number(f.bIni)) {
      alert(`El horómetro final del ${motB?.etiqueta ?? "Motor B"} (${f.bFin}) no puede ser menor al inicial (${f.bIni}). Revisa la lectura.`);
      return;
    }
    if (f.eIni && f.eFin && Number(f.eFin) < Number(f.eIni)) {
      alert(`El horómetro final del ${motE?.etiqueta ?? "Motor E"} (${f.eFin}) no puede ser menor al inicial (${f.eIni}). Revisa la lectura.`);
      return;
    }
    if (f.galIni && f.galFin && Number(f.galFin) > Number(f.galIni)) {
      if (!confirm("El combustible final es MAYOR al inicial (¿se cargó combustible durante el viaje?). ¿Continuar de todas formas?")) return;
    }
    const dup = viajesExistentes.find((v) => fechaISO(v.fecha) === f.fecha);
    if (dup && !confirm(`Ya hay un viaje registrado el ${f.fecha.split("-").reverse().join("/")} (${dup.cliente}). ¿Registrar OTRO viaje ese mismo día?`)) return;

    submit(
      {
        periodo_id: periodoId,
        socio_id,
        cliente_id,
        cliente_nombre,
        marinero: f.marinero.trim() || null,
        es_renta: esRenta,
        fecha: f.fecha,
        num_personas: f.num_personas ? Number(f.num_personas) : null,
        duracion_horas: f.duracion_horas ? Number(f.duracion_horas) : null,
        litros: consumo > 0 ? consumo : 0,
        precio_litro: precioLitro,
        costo_combustible: round2((consumo > 0 ? consumo : 0) * precioLitro),
        costo_marinero: MARINERO,
        costo_consumibles: 0,
        combustible_inicio: litrosIni,
        combustible_fin: litrosFin,
        horometros: [
          { motor_id: motB?.id, inicio: f.bIni, fin: f.bFin },
          { motor_id: motE?.id, inicio: f.eIni, fin: f.eFin },
        ],
      },
      () => setF((s) => ({ ...s, rentaNombre: "", marinero: "", num_personas: "", duracion_horas: "", bIni: s.bFin || s.bIni, bFin: "", eIni: s.eFin || s.eIni, eFin: "", galIni: "", galFin: "" }))
    );
  }

  return (
    <form className="form-card cap-form" onSubmit={onSubmit}>
      <h2>Registrar viaje</h2>

      <div className="cap-section">Datos del viaje</div>
      <div className="grid">
        <div className="field">
          <label>Fecha</label>
          <input type="date" required value={f.fecha} onChange={(e) => set("fecha", e.target.value)} />
        </div>
        <div className="field">
          <label>Familia / cliente</label>
          <select value={f.quien} onChange={(e) => set("quien", e.target.value)}>
            {clientes.map((c) => <option key={c.id} value={`cli:${c.id}`}>{c.nombre}</option>)}
            <option value="renta">Renta (cliente externo)</option>
          </select>
        </div>
        {esRenta && (
          <div className="field full">
            <label>Nombre del cliente (renta)</label>
            <input value={f.rentaNombre} onChange={(e) => set("rentaNombre", e.target.value)} placeholder="Ej. Sra. Gardenia" />
          </div>
        )}
        <div className="field">
          <label>No. de personas</label>
          <input type="number" inputMode="numeric" value={f.num_personas} onChange={(e) => set("num_personas", e.target.value)} placeholder="Ej. 8" />
        </div>
        <div className="field">
          <label>Duración del viaje (horas)</label>
          <input type="number" inputMode="decimal" step="0.5" value={f.duracion_horas} onChange={(e) => set("duracion_horas", e.target.value)} placeholder="Ej. 4" />
        </div>
        <div className="field full">
          <label>Marinero</label>
          <MarineroSelect marineros={marineros} value={f.marinero} onChange={(nombre) => set("marinero", nombre)} />
        </div>
      </div>

      <div className="cap-section">Horómetros</div>
      <div className="grid">
        <div className="field">
          <label>{motB?.etiqueta ?? "Motor B"} — inicial</label>
          <input type="number" inputMode="decimal" step="0.01" value={f.bIni} onChange={(e) => set("bIni", e.target.value)} />
        </div>
        <div className="field">
          <label>{motB?.etiqueta ?? "Motor B"} — final</label>
          <input type="number" inputMode="decimal" step="0.01" value={f.bFin} onChange={(e) => set("bFin", e.target.value)} />
          {horasB !== null && <span className="cap-hint">{horasB} h de uso</span>}
        </div>
        <div className="field">
          <label>{motE?.etiqueta ?? "Motor E"} — inicial</label>
          <input type="number" inputMode="decimal" step="0.01" value={f.eIni} onChange={(e) => set("eIni", e.target.value)} />
        </div>
        <div className="field">
          <label>{motE?.etiqueta ?? "Motor E"} — final</label>
          <input type="number" inputMode="decimal" step="0.01" value={f.eFin} onChange={(e) => set("eFin", e.target.value)} />
          {horasE !== null && <span className="cap-hint">{horasE} h de uso</span>}
        </div>
      </div>

      <div className="cap-section">Combustible <span className="cap-section-note">en galones — se convierte a litros</span></div>
      <div className="grid">
        <div className="field">
          <label>Combustible inicio (galones)</label>
          <input type="number" inputMode="decimal" step="0.01" value={f.galIni} onChange={(e) => set("galIni", e.target.value)} placeholder="Lectura del tanque" />
          {f.galIni && <span className="cap-hint">≈ {litrosIni} L</span>}
        </div>
        <div className="field">
          <label>Combustible fin (galones)</label>
          <input type="number" inputMode="decimal" step="0.01" value={f.galFin} onChange={(e) => set("galFin", e.target.value)} placeholder="Lectura del tanque" />
          {f.galFin && <span className="cap-hint">≈ {litrosFin} L</span>}
        </div>
      </div>

      <div className="cap-resumen">
        <span>Consumo del viaje</span>
        <strong className="mono">{consumo > 0 ? consumo : 0} L</strong>
      </div>

      <div style={{ marginTop: 18, display: "flex", alignItems: "center", gap: 14 }}>
        <button className="btn cap-btn" disabled={busy}>{busy ? "Guardando…" : "Guardar viaje"}</button>
        {msg && <span className={msg.ok ? "msg-ok" : "msg-err"}>{msg.t}</span>}
      </div>
    </form>
  );
}
