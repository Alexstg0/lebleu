"use client";

import { useRef, useState } from "react";
import { useRouter } from "next/navigation";

type Adj = { id: number; nombre: string; generado: boolean };

export default function Adjuntos({ gastoId, insumoId, extraordinarioId, cajaId, adjuntos }: { gastoId?: number; insumoId?: number; extraordinarioId?: number; cajaId?: number; adjuntos: Adj[] }) {
  const router = useRouter();
  const fileRef = useRef<HTMLInputElement>(null);
  const [busy, setBusy] = useState(false);
  const [ver, setVer] = useState<Adj | null>(null);
  const [err, setErr] = useState("");

  async function subir(file: File) {
    setErr("");
    const esPdf = file.type === "application/pdf" || /\.pdf$/i.test(file.name);
    const esImagen = /^image\/(jpe?g|png)$/i.test(file.type) || /\.(jpe?g|png)$/i.test(file.name);
    if (!esPdf && !esImagen) { setErr("Solo PDF o imagen (JPG/PNG)."); return; }
    if (file.size > 3.2 * 1024 * 1024) { setErr("Máximo 3 MB. Si es foto, tómala en tamaño reducido."); return; }
    setBusy(true);
    const base64: string = await new Promise((res, rej) => {
      const r = new FileReader();
      r.onload = () => res(String(r.result));
      r.onerror = rej;
      r.readAsDataURL(file);
    });
    const res = await fetch("/api/adjuntos", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        gasto_id: gastoId, insumo_id: insumoId, extraordinario_id: extraordinarioId, caja_id: cajaId,
        nombre: file.name, mime: esPdf ? "application/pdf" : file.type, base64,
      }),
    });
    const j = await res.json().catch(() => ({ ok: false }));
    setBusy(false);
    if (fileRef.current) fileRef.current.value = "";
    if (j.ok) router.refresh();
    else setErr(j.error || "No se pudo subir.");
  }

  async function borrar(id: number) {
    if (!confirm("¿Eliminar este archivo?")) return;
    const res = await fetch(`/api/adjuntos?id=${id}`, { method: "DELETE" });
    const j = await res.json().catch(() => ({ ok: false }));
    if (j.ok) router.refresh();
  }

  return (
    <div className="adj-wrap">
      {adjuntos.map((a) => (
        <span key={a.id} className={`adj-chip ${a.generado ? "gen" : ""}`}>
          <button className="adj-name" onClick={() => setVer(a)} title="Ver PDF">
            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><path d="M14 2v6h6" /></svg>
            {a.generado ? "Recibo" : "PDF"}
          </button>
          <button className="adj-del" onClick={() => borrar(a.id)} title="Eliminar" aria-label="Eliminar">×</button>
        </span>
      ))}
      <button className="adj-add" onClick={() => fileRef.current?.click()} disabled={busy}>
        {busy ? "Subiendo…" : "+ PDF/Foto"}
      </button>
      <input ref={fileRef} type="file" accept="application/pdf,image/jpeg,image/png" hidden
        onChange={(e) => { const f = e.target.files?.[0]; if (f) subir(f); }} />
      {err && <span className="adj-err">{err}</span>}

      {ver && (
        <div className="modal-overlay" onClick={() => setVer(null)}>
          <div className="adj-viewer" onClick={(e) => e.stopPropagation()}>
            <div className="adj-viewer-head">
              <span>{ver.nombre}</span>
              <span style={{ display: "flex", gap: 10 }}>
                <a href={`/api/adjuntos/${ver.id}`} target="_blank" rel="noopener" className="btn-link">Abrir en pestaña</a>
                <button className="btn-link" onClick={() => setVer(null)}>Cerrar</button>
              </span>
            </div>
            <iframe src={`/api/adjuntos/${ver.id}`} title={ver.nombre} />
          </div>
        </div>
      )}
    </div>
  );
}
