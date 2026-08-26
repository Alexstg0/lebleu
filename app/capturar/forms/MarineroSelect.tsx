"use client";

import { useState } from "react";

type Marinero = { id: number; nombre: string };

// Menú desplegable de marineros con opción de agregar uno nuevo (capitán y admin).
export default function MarineroSelect({
  marineros,
  value,
  onChange,
}: {
  marineros: Marinero[];
  value: string;
  onChange: (nombre: string) => void;
}) {
  const [lista, setLista] = useState<Marinero[]>(marineros);
  const [agregando, setAgregando] = useState(false);
  const [nuevo, setNuevo] = useState("");
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState("");

  // Si el valor actual (dato histórico) no está en la lista, se muestra igual.
  const enLista = !value || lista.some((m) => m.nombre === value);

  async function guardarNuevo() {
    setErr("");
    if (nuevo.trim().length < 3) { setErr("Escribe el nombre completo."); return; }
    setBusy(true);
    const res = await fetch("/api/marineros", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ nombre: nuevo }),
    });
    const j = await res.json().catch(() => ({ ok: false }));
    setBusy(false);
    if (j.ok) {
      if (!lista.some((m) => m.id === j.id)) setLista([...lista, { id: j.id, nombre: j.nombre }].sort((a, b) => a.nombre.localeCompare(b.nombre)));
      onChange(j.nombre);
      setNuevo("");
      setAgregando(false);
    } else setErr(j.error || "No se pudo agregar.");
  }

  if (agregando) {
    return (
      <div>
        <div style={{ display: "flex", gap: 8 }}>
          <input value={nuevo} onChange={(e) => setNuevo(e.target.value)} placeholder="Nombre del nuevo marinero" autoFocus
            onKeyDown={(e) => { if (e.key === "Enter") { e.preventDefault(); guardarNuevo(); } }} />
          <button type="button" className="btn" style={{ padding: "8px 14px", whiteSpace: "nowrap" }} disabled={busy} onClick={guardarNuevo}>
            {busy ? "…" : "Guardar"}
          </button>
          <button type="button" className="btn-ghost" style={{ padding: "8px 10px" }} onClick={() => { setAgregando(false); setErr(""); }}>✕</button>
        </div>
        {err && <span className="msg-err" style={{ fontSize: 12 }}>{err}</span>}
      </div>
    );
  }

  return (
    <select
      value={value}
      onChange={(e) => {
        if (e.target.value === "__nuevo__") { setAgregando(true); return; }
        onChange(e.target.value);
      }}
    >
      <option value="">— Sin marinero —</option>
      {!enLista && <option value={value}>{value}</option>}
      {lista.map((m) => <option key={m.id} value={m.nombre}>{m.nombre}</option>)}
      <option value="__nuevo__">➕ Agregar nuevo marinero…</option>
    </select>
  );
}
