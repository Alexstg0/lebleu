"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";

export default function DeleteViajeButton({ id, etiqueta }: { id: number; etiqueta: string }) {
  const router = useRouter();
  const [busy, setBusy] = useState(false);

  async function borrar() {
    if (!confirm(`¿Eliminar el viaje de ${etiqueta}? Esta acción no se puede deshacer.`)) return;
    setBusy(true);
    const res = await fetch(`/api/viajes?id=${id}`, { method: "DELETE" });
    const j = await res.json().catch(() => ({ ok: false }));
    setBusy(false);
    if (j.ok) router.refresh();
    else alert(j.error || "No se pudo eliminar el viaje.");
  }

  return (
    <button className="bita-del" onClick={borrar} disabled={busy} title="Eliminar viaje" aria-label="Eliminar viaje">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        <path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6" />
      </svg>
    </button>
  );
}
