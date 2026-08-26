"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { hora12, mesNombre } from "@/lib/format";

type Reserva = {
  id: number | null;
  fecha: string;
  hora: string;
  cliente: string;
  socio_id: number | null;
  socio_nombre?: string;
  num_personas: number | string | null;
  duracion_horas: number | string | null;
  notas: string;
};
type Socio = { id: number; nombre: string };

const DIAS_FULL = ["Dom", "Lun", "Mar", "Mié", "Jue", "Vie", "Sáb"];
const DIAS_SHORT = ["D", "L", "M", "M", "J", "V", "S"];
const pad = (n: number) => String(n).padStart(2, "0");
const famClass = (r: Reserva) => (r.socio_id === 1 ? "fam-acosta" : r.socio_id === 2 ? "fam-garcia" : "fam-ext");
const famColor = (r: Reserva) => (r.socio_id === 1 ? "var(--ocean)" : r.socio_id === 2 ? "var(--teal)" : "var(--muted)");

const Clock = () => (
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>
);
const Plus = () => (
  <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
);

export default function CalendarClient({
  anio,
  mes,
  reservas,
  socios,
}: {
  anio: number;
  mes: number;
  reservas: Reserva[];
  socios: Socio[];
}) {
  const router = useRouter();
  const [editing, setEditing] = useState<Reserva | null>(null);
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState("");

  const hoy = new Date();
  const todayISO = `${hoy.getFullYear()}-${pad(hoy.getMonth() + 1)}-${pad(hoy.getDate())}`;
  const esMesActual = hoy.getFullYear() === anio && hoy.getMonth() + 1 === mes;

  const primerDia = new Date(Date.UTC(anio, mes - 1, 1)).getUTCDay();
  const diasEnMes = new Date(Date.UTC(anio, mes, 0)).getUTCDate();
  const celdas: (number | null)[] = [];
  for (let i = 0; i < primerDia; i++) celdas.push(null);
  for (let d = 1; d <= diasEnMes; d++) celdas.push(d);

  const porDia = new Map<number, Reserva[]>();
  for (const r of reservas) {
    const d = Number(r.fecha.slice(8, 10));
    if (!porDia.has(d)) porDia.set(d, []);
    porDia.get(d)!.push(r);
  }

  const primerConReserva = [...porDia.keys()].sort((a, b) => a - b)[0];
  const [sel, setSel] = useState<number>(esMesActual ? hoy.getDate() : primerConReserva || 1);
  const reservasSel = porDia.get(sel) ?? [];

  const detalleDe = (r: Reserva) => {
    const pasado = r.fecha < todayISO;
    const tieneDur = r.duracion_horas != null;
    return pasado ? (tieneDur ? `${r.duracion_horas} h` : (r.hora ? hora12(r.hora) : "—")) : (r.hora ? hora12(r.hora) : "Sin hora");
  };
  // Info completa del viaje (hora · personas · duración) para la celda.
  const metaText = (r: Reserva) =>
    [r.hora ? hora12(r.hora) : null, r.num_personas ? `${r.num_personas} pers` : null, r.duracion_horas != null ? `${r.duracion_horas} h` : null]
      .filter(Boolean).join(" · ") || "—";

  function nuevo(day: number) {
    setErr("");
    setEditing({ id: null, fecha: `${anio}-${pad(mes)}-${pad(day)}`, hora: "", cliente: "", socio_id: null, num_personas: "", duracion_horas: "", notas: "" });
  }

  async function guardar(e: React.FormEvent) {
    e.preventDefault();
    if (!editing) return;
    setBusy(true); setErr("");
    const payload = {
      ...editing,
      socio_id: editing.socio_id || null,
      num_personas: editing.num_personas ? Number(editing.num_personas) : null,
      duracion_horas: editing.duracion_horas ? Number(editing.duracion_horas) : null,
    };
    const res = await fetch("/api/reservas", {
      method: editing.id ? "PATCH" : "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const j = await res.json();
    setBusy(false);
    if (j.ok) { setEditing(null); router.refresh(); }
    else setErr(j.error || "No se pudo guardar.");
  }

  async function borrar() {
    if (!editing?.id) return;
    if (!confirm("¿Borrar esta reserva?")) return;
    setBusy(true);
    const res = await fetch(`/api/reservas?id=${editing.id}`, { method: "DELETE" });
    const j = await res.json();
    setBusy(false);
    if (j.ok) { setEditing(null); router.refresh(); }
    else setErr(j.error || "No se pudo borrar.");
  }

  const set = (k: keyof Reserva, v: any) => setEditing((e) => (e ? { ...e, [k]: v } : e));

  return (
    <>
      <div className="cal-legend">
        <span><i style={{ background: "var(--ocean)" }} /> Familia Acosta</span>
        <span><i style={{ background: "var(--teal)" }} /> Familia García</span>
        <span><i style={{ background: "var(--muted)" }} /> Externo / renta</span>
      </div>

      {/* ===== Vista escritorio: cuadrícula con reservas dentro ===== */}
      <div className="cal-desktop">
        <div className="cal-card">
          <div className="cal-head">{DIAS_FULL.map((d, i) => <div key={i}>{d}</div>)}</div>
          <div className="cal-body">
            {celdas.map((d, i) => {
              const col = i % 7;
              const fecha = d ? `${anio}-${pad(mes)}-${pad(d)}` : "";
              const klass = ["cal-cell"];
              if (!d) klass.push("empty");
              else { if (col === 0 || col === 6) klass.push("wknd"); if (fecha === todayISO) klass.push("today"); }
              return (
                <div key={i} className={klass.join(" ")}>
                  {d && (
                    <>
                      <span className="cal-daynum">{d}</span>
                      <button className="cal-add" onClick={() => nuevo(d)} aria-label={`Agregar reserva el ${d}`}><Plus /></button>
                      {(porDia.get(d) ?? []).map((r) => (
                        <button key={r.id} className={`cal-chip ${famClass(r)} ${r.fecha < todayISO ? "past" : ""}`} onClick={() => { setErr(""); setEditing(r); }}>
                          <span className="dot" />
                          <span className="info">
                            <span className="name">{r.cliente || r.socio_nombre || "Reserva"}</span>
                            {metaText(r) !== "—" && <span className="meta"><Clock />{metaText(r)}</span>}
                            {r.notas && !/^s[áa]bado reservado/i.test(r.notas) && <span className="cal-chip-notas">{r.notas}</span>}
                          </span>
                        </button>
                      ))}
                    </>
                  )}
                </div>
              );
            })}
          </div>
        </div>
      </div>

      {/* ===== Vista móvil: estilo iPhone (puntos + lista) ===== */}
      <div className="cal-mobile">
        <div className="ical-card">
          <div className="ical-grid ical-dow-row">{DIAS_SHORT.map((d, i) => <div key={i} className="ical-dow">{d}</div>)}</div>
          <div className="ical-grid">
            {celdas.map((d, i) => {
              if (!d) return <div key={i} className="ical-day empty" />;
              const fecha = `${anio}-${pad(mes)}-${pad(d)}`;
              const dots = (porDia.get(d) ?? []).slice(0, 3);
              return (
                <button key={i} className={`ical-day ${fecha === todayISO ? "today" : ""} ${d === sel ? "sel" : ""}`} onClick={() => setSel(d)}>
                  <span className="ical-num">{d}</span>
                  <span className="ical-dots">{dots.map((r, k) => <i key={k} style={{ background: famColor(r) }} />)}</span>
                </button>
              );
            })}
          </div>
        </div>

        <div className="ical-detail">
          <div className="ical-detail-head">
            <h3>{sel} de {mesNombre(mes)}</h3>
            <button className="ical-add" onClick={() => nuevo(sel)}>+ Reserva</button>
          </div>
          {reservasSel.length === 0 ? (
            <div className="ical-empty">Sin reservas este día.</div>
          ) : (
            <div className="ical-list">
              {reservasSel.map((r) => (
                <button key={r.id} className="ical-item" onClick={() => { setErr(""); setEditing(r); }}>
                  <span className="ical-item-dot" style={{ background: famColor(r) }} />
                  <span className="ical-item-info">
                    <span className="ical-item-name">{r.cliente || r.socio_nombre || "Reserva"}</span>
                    <span className="ical-item-meta">{detalleDe(r)}{r.num_personas ? ` · ${r.num_personas} pers` : ""}</span>
                  </span>
                  <span className="ical-item-chevron">›</span>
                </button>
              ))}
            </div>
          )}
        </div>
      </div>

      {editing && (
        <div className="modal-overlay" onClick={() => setEditing(null)}>
          <div className="modal" onClick={(e) => e.stopPropagation()}>
            <h2 style={{ margin: "0 0 14px", fontSize: 18 }}>{editing.id ? "Editar reserva" : "Nueva reserva"}</h2>
            <form onSubmit={guardar}>
              <div className="grid">
                <div className="field">
                  <label>Fecha</label>
                  <input type="date" required value={editing.fecha} onChange={(e) => set("fecha", e.target.value)} />
                </div>
                <div className="field">
                  <label>Hora de salida</label>
                  <input type="time" value={editing.hora} onChange={(e) => set("hora", e.target.value)} />
                </div>
                <div className="field">
                  <label>Cliente</label>
                  <input value={editing.cliente} onChange={(e) => set("cliente", e.target.value)} placeholder="Familia / nombre" />
                </div>
                <div className="field">
                  <label>Socio</label>
                  <select value={editing.socio_id ?? ""} onChange={(e) => set("socio_id", e.target.value ? Number(e.target.value) : null)}>
                    <option value="">— (externo)</option>
                    {socios.map((s) => <option key={s.id} value={s.id}>{s.nombre}</option>)}
                  </select>
                </div>
                <div className="field">
                  <label>Personas</label>
                  <input type="number" value={editing.num_personas ?? ""} onChange={(e) => set("num_personas", e.target.value)} />
                </div>
                <div className="field">
                  <label>Duración (horas)</label>
                  <input type="number" step="0.5" value={editing.duracion_horas ?? ""} onChange={(e) => set("duracion_horas", e.target.value)} placeholder="Al pasar el viaje" />
                </div>
                <div className="field full">
                  <label>Notas</label>
                  <input value={editing.notas} onChange={(e) => set("notas", e.target.value)} />
                </div>
              </div>
              {err && <div className="msg-err" style={{ marginTop: 12 }}>{err}</div>}
              <div style={{ marginTop: 18, display: "flex", alignItems: "center", gap: 10 }}>
                <button className="btn" disabled={busy}>{busy ? "Guardando…" : editing.id ? "Actualizar" : "Crear reserva"}</button>
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
