"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";

export default function PagadoToggle({ id, pagado }: { id: number; pagado: boolean }) {
  const router = useRouter();
  const [busy, setBusy] = useState(false);

  async function toggle() {
    setBusy(true);
    const res = await fetch("/api/extraordinarios/pagado", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id, liquidado: !pagado }),
    });
    const j = await res.json().catch(() => ({ ok: false }));
    setBusy(false);
    if (j.ok) router.refresh();
    else alert(j.error || "No se pudo actualizar.");
  }

  return (
    <button
      className={`pago-badge ${pagado ? "si" : "no"}`}
      onClick={(e) => { e.stopPropagation(); toggle(); }}
      disabled={busy}
      title="Clic para cambiar el estado de pago"
    >
      {busy ? "…" : pagado ? "Pagado" : "Pendiente"}
    </button>
  );
}
