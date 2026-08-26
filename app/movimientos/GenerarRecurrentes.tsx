"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";

export default function GenerarRecurrentes({ periodoId }: { periodoId: number }) {
  const router = useRouter();
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState("");

  async function generar() {
    setBusy(true);
    setMsg("");
    const res = await fetch("/api/recurrentes", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ periodoId }),
    });
    const j = await res.json().catch(() => ({ ok: false }));
    setBusy(false);
    if (j.ok) {
      const total = (j.nomina || 0) + (j.admin || 0);
      setMsg(total === 0 ? "Ya está todo al día — no había cargos pendientes." : `Se generaron ${j.nomina} nómina(s) y ${j.admin} cargo(s) administrativo(s).`);
      router.refresh();
    } else setMsg(j.error || "No se pudo generar.");
  }

  return (
    <div className="gen-rec">
      <button className="btn" onClick={generar} disabled={busy}>
        {busy ? "Generando…" : "Generar nómina y administrativo"}
      </button>
      <span className="hint">Agrega la nómina del capitán (viernes) y el cargo administrativo mensual que falten.</span>
      {msg && <span className="msg-ok" style={{ marginLeft: 4 }}>{msg}</span>}
    </div>
  );
}
