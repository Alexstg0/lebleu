"use client";

import { useState } from "react";
import { useSubmit, today, round2, toDate } from "./useSubmit";
import { fechaISO } from "@/lib/format";
import MarineroSelect from "./MarineroSelect";

type Socio = { id: number; nombre: string };
type Cliente = { id: number; nombre: string; socio_id: number | null };
type Motor = { id: number; etiqueta: string; horometro_actual: string };
type MarineroCat = { id: number; nombre: string };
type ViajeExistente = { id: number; fecha: string; cliente: string };

export default function Viaje({
  periodoId,
  socios,
  clientes,
  motores,
  precioLitro,
  marineros = [],
  viajesExistentes = [],
  initial,
  onDone,
}: {
  periodoId: number;
  socios: Socio[];
  clientes: Cliente[];
  motores: Motor[];
  precioLitro: number;
  marineros?: MarineroCat[];
  viajesExistentes?: ViajeExistente[];
  initial?: any;
  onDone?: () => void;
}) {
  const editing = !!initial?.id;
  const { busy, msg, submit } = useSubmit("/api/viajes", editing ? "PATCH" : "POST");
  // El precio del litro NO se captura: viene del periodo (se fija al abrirlo).
  // Al editar un viaje viejo se respeta el precio con el que se registró.
  const precio = Number(initial?.precio_litro ?? precioLitro) || 0;
  // El combustible se captura en GALONES (como lo mide el capitán); se guarda en litros.
  const GAL_A_LITRO = 3.78541;
  const aGal = (litros: any) => (litros != null ? String(round2(Number(litros) / GAL_A_LITRO)) : "");
  const [v, setV] = useState({
    fecha: editing ? toDate(initial.fecha) : today(),
    socio_id: String(initial?.socio_id ?? socios[0]?.id ?? ""),
    cliente_id: String(initial?.cliente_id ?? clientes[0]?.id ?? ""),
    duracion_horas: initial?.duracion_horas != null ? String(initial.duracion_horas) : "",
    num_personas: initial?.num_personas != null ? String(initial.num_personas) : "",
    gal_inicio: aGal(initial?.combustible_inicio),
    gal_fin: aGal(initial?.combustible_fin),
    litros: initial ? String(initial.litros) : "",
    costo_marinero: String(initial?.costo_marinero ?? "800"),
    marinero: initial?.marinero ?? "",
    costo_consumibles: initial ? String(initial.costo_consumibles) : "",
    consumibles_comprobante: initial?.consumibles_comprobante ?? "",
    bandera: !!initial?.bandera,
  });
  const [horos, setHoros] = useState(
    motores.map((m) => {
      const h = initial?.horometros?.find((x: any) => x.motor_id === m.id);
      return {
        motor_id: m.id,
        etiqueta: m.etiqueta,
        inicio: h ? String(h.lectura_inicio) : String(m.horometro_actual),
        fin: h ? String(h.lectura_fin) : "",
      };
    })
  );

  // Lecturas del tanque en litros (a partir de los galones capturados).
  const litrosIni = v.gal_inicio !== "" ? round2(Number(v.gal_inicio) * GAL_A_LITRO) : null;
  const litrosFin = v.gal_fin !== "" ? round2(Number(v.gal_fin) * GAL_A_LITRO) : null;
  // El consumo se calcula solo (no se captura) cuando hay lectura inicial y final.
  const consumoAuto = litrosIni != null && litrosFin != null && litrosIni >= litrosFin ? round2(litrosIni - litrosFin) : null;
  const litrosEfectivos = consumoAuto ?? Number(v.litros || 0);

  // El costo del combustible se calcula siempre con el precio del periodo (no se captura).
  const costoCombustible = round2(litrosEfectivos * precio);
  const total = costoCombustible + Number(v.costo_marinero || 0) + Number(v.costo_consumibles || 0);

  function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    // Validaciones de captura
    for (const h of horos) {
      if (h.inicio !== "" && h.fin !== "" && Number(h.fin) < Number(h.inicio)) {
        alert(`El horómetro final de ${h.etiqueta} (${h.fin}) no puede ser menor al inicial (${h.inicio}). Revisa la lectura.`);
        return;
      }
    }
    if (v.gal_inicio !== "" && v.gal_fin !== "" && Number(v.gal_fin) > Number(v.gal_inicio)) {
      if (!confirm("El combustible final es MAYOR al inicial (¿se cargó combustible durante el viaje?). ¿Continuar de todas formas?")) return;
    }
    if (!editing) {
      const dup = viajesExistentes.find((x) => fechaISO(x.fecha) === v.fecha);
      if (dup && !confirm(`Ya hay un viaje registrado el ${v.fecha.split("-").reverse().join("/")} (${dup.cliente}). ¿Registrar OTRO viaje ese mismo día?`)) return;
    }
    submit(
      {
        id: initial?.id,
        periodo_id: periodoId,
        socio_id: Number(v.socio_id),
        cliente_id: v.cliente_id ? Number(v.cliente_id) : null,
        fecha: v.fecha,
        duracion_horas: v.duracion_horas ? Number(v.duracion_horas) : null,
        num_personas: v.num_personas ? Number(v.num_personas) : null,
        litros: litrosEfectivos,
        precio_litro: precio,
        costo_combustible: costoCombustible,
        combustible_inicio: litrosIni,
        combustible_fin: litrosFin,
        costo_marinero: Number(v.costo_marinero || 0),
        marinero: v.marinero || null,
        costo_consumibles: Number(v.costo_consumibles || 0),
        consumibles_comprobante: v.consumibles_comprobante,
        bandera: v.bandera,
        horometros: horos,
      },
      () => {
        if (editing) return onDone?.();
        setHoros(horos.map((h) => ({ ...h, inicio: h.fin || h.inicio, fin: "" })));
        setV({ ...v, duracion_horas: "", num_personas: "", gal_inicio: "", gal_fin: "", litros: "", costo_consumibles: "", consumibles_comprobante: "", bandera: false });
      }
    );
  }

  return (
    <form className="form-card" onSubmit={onSubmit}>
      {!editing && <h2>Nuevo viaje (gasto variable)</h2>}
      <div className="grid">
        <div className="field">
          <label>Fecha</label>
          <input type="date" required value={v.fecha} onChange={(e) => setV({ ...v, fecha: e.target.value })} />
        </div>
        <div className="field">
          <label>Socio responsable</label>
          <select value={v.socio_id} onChange={(e) => setV({ ...v, socio_id: e.target.value })}>
            {socios.map((s) => <option key={s.id} value={s.id}>{s.nombre}</option>)}
          </select>
        </div>
        <div className="field">
          <label>Cliente / familia</label>
          <select value={v.cliente_id} onChange={(e) => setV({ ...v, cliente_id: e.target.value })}>
            <option value="">—</option>
            {clientes.map((c) => <option key={c.id} value={c.id}>{c.nombre}</option>)}
          </select>
        </div>
        <div className="field">
          <label>Duración (horas)</label>
          <input type="number" step="0.5" value={v.duracion_horas} onChange={(e) => setV({ ...v, duracion_horas: e.target.value })} />
        </div>
        <div className="field">
          <label>Personas</label>
          <input type="number" value={v.num_personas} onChange={(e) => setV({ ...v, num_personas: e.target.value })} />
        </div>
        <div className="field">
          <label>Combustible inicio (galones)</label>
          <input type="number" step="0.01" inputMode="decimal" value={v.gal_inicio} onChange={(e) => setV({ ...v, gal_inicio: e.target.value })} placeholder="Lectura del tanque" />
          {litrosIni != null && <span className="cap-hint">≈ {litrosIni} L</span>}
        </div>
        <div className="field">
          <label>Combustible fin (galones)</label>
          <input type="number" step="0.01" inputMode="decimal" value={v.gal_fin} onChange={(e) => setV({ ...v, gal_fin: e.target.value })} placeholder="Lectura del tanque" />
          {litrosFin != null && <span className="cap-hint">≈ {litrosFin} L</span>}
        </div>
        <div className="field">
          <label>Consumo (litros) — se calcula solo</label>
          {consumoAuto != null ? (
            <input type="number" value={consumoAuto} readOnly style={{ background: "var(--bg2)", cursor: "not-allowed" }} title="Se calcula con las lecturas de combustible" />
          ) : (
            <input type="number" step="0.01" value={v.litros} onChange={(e) => setV({ ...v, litros: e.target.value })} placeholder="Captura inicio y fin arriba" />
          )}
          <span className="cap-hint">× ${precio.toFixed(2)}/L (precio del periodo) = <strong>${costoCombustible.toLocaleString("es-MX", { minimumFractionDigits: 2 })}</strong></span>
        </div>
        <div className="field">
          <label>Marinero</label>
          <MarineroSelect marineros={marineros} value={v.marinero} onChange={(nombre) => setV({ ...v, marinero: nombre })} />
        </div>
        <div className="field">
          <label>Costo marinero</label>
          <input type="number" step="0.01" value={v.costo_marinero} onChange={(e) => setV({ ...v, costo_marinero: e.target.value })} />
        </div>
        <div className="field">
          <label>Costo consumibles</label>
          <input type="number" step="0.01" value={v.costo_consumibles} onChange={(e) => setV({ ...v, costo_consumibles: e.target.value })} />
        </div>
        <div className="field full">
          <label>Comprobante consumibles</label>
          <input value={v.consumibles_comprobante} onChange={(e) => setV({ ...v, consumibles_comprobante: e.target.value })} placeholder="ej. Chedraui SM-91363" />
        </div>
      </div>

      <div className="section-label" style={{ margin: "18px 0 10px" }}>Horómetros</div>
      <div className="grid">
        {horos.map((h, i) => (
          <div key={h.motor_id} className="field">
            <label>{h.etiqueta}</label>
            <div style={{ display: "flex", gap: 8 }}>
              <input type="number" step="0.01" placeholder="inicio" value={h.inicio}
                onChange={(e) => setHoros(horos.map((x, k) => (k === i ? { ...x, inicio: e.target.value } : x)))} />
              <input type="number" step="0.01" placeholder="fin" value={h.fin}
                onChange={(e) => setHoros(horos.map((x, k) => (k === i ? { ...x, fin: e.target.value } : x)))} />
            </div>
          </div>
        ))}
      </div>

      <div style={{ marginTop: 16, display: "flex", alignItems: "center", gap: 14, flexWrap: "wrap" }}>
        <label style={{ display: "flex", alignItems: "center", gap: 6, textTransform: "none" }}>
          <input type="checkbox" checked={v.bandera} onChange={(e) => setV({ ...v, bandera: e.target.checked })} style={{ width: "auto" }} />
          Marcar como viaje anómalo
        </label>
        <span className="hint">Total viaje: <strong className="mono">${round2(total).toLocaleString("es-MX", { minimumFractionDigits: 2 })}</strong></span>
      </div>
      <div style={{ marginTop: 14, display: "flex", alignItems: "center", gap: 14 }}>
        <button className="btn gold" disabled={busy}>{busy ? "Guardando…" : editing ? "Actualizar" : "Guardar viaje"}</button>
        {editing && <button type="button" className="btn-ghost" onClick={onDone}>Cancelar</button>}
        {msg && <span className={msg.ok ? "msg-ok" : "msg-err"}>{msg.t}</span>}
      </div>
    </form>
  );
}
