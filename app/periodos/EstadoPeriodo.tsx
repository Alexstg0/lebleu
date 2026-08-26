"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import PdfViewerLink from "../PdfViewerLink";

// Botones Cerrar/Reabrir de un periodo + acceso al estado de cuenta archivado.
export default function EstadoPeriodo({
  id,
  estado,
  etiqueta,
  archivoId,
}: {
  id: number;
  estado: string;
  etiqueta: string;
  archivoId: number | null;
}) {
  const router = useRouter();
  const [busy, setBusy] = useState(false);

  async function accion(a: "cerrar" | "reabrir") {
    const msg = a === "cerrar"
      ? `¿Cerrar ${etiqueta}? Ya no se podrá capturar ni editar en este periodo y se archivará su estado de cuenta en PDF.`
      : `¿Reabrir ${etiqueta}? Se podrá volver a capturar y editar.`;
    if (!confirm(msg)) return;
    setBusy(true);
    const res = await fetch("/api/periodos/estado", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id, accion: a }),
    });
    const j = await res.json().catch(() => ({ ok: false }));
    setBusy(false);
    if (j.ok) router.refresh();
    else alert(j.error || "No se pudo cambiar el estado.");
  }

  return (
    <div style={{ display: "flex", alignItems: "center", gap: 10, flexWrap: "wrap" }}>
      {archivoId && (
        <PdfViewerLink className="btn-link" href={`/api/adjuntos/${archivoId}`} titulo={`Estado de cuenta archivado — ${etiqueta}`}>
          📄 Archivo
        </PdfViewerLink>
      )}
      {estado === "abierto" ? (
        <button className="btn-ghost" style={{ padding: "6px 14px", fontSize: 12 }} disabled={busy} onClick={() => accion("cerrar")}>
          {busy ? "…" : "Cerrar periodo"}
        </button>
      ) : (
        <button className="btn-ghost" style={{ padding: "6px 14px", fontSize: 12 }} disabled={busy} onClick={() => accion("reabrir")}>
          {busy ? "…" : "Reabrir"}
        </button>
      )}
    </div>
  );
}
