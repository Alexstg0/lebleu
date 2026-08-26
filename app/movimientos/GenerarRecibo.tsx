"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";

export default function GenerarRecibo({ gastoId }: { gastoId: number }) {
  const router = useRouter();
  const [busy, setBusy] = useState(false);

  async function generar() {
    setBusy(true);
    const res = await fetch("/api/gastos-operativos/recibo", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ gasto_id: gastoId }),
    });
    const j = await res.json().catch(() => ({ ok: false }));
    setBusy(false);
    if (j.ok) router.refresh();
    else alert(j.error || "No se pudo generar el recibo.");
  }

  return (
    <button className="adj-recibo" onClick={generar} disabled={busy} title="Generar recibo PDF de este gasto">
      {busy ? "…" : "Generar recibo"}
    </button>
  );
}
