"use client";

import { useState, type ReactNode } from "react";
import { createPortal } from "react-dom";

// Botón que abre un PDF en un visor dentro de la app (no en pestaña nueva).
export default function PdfViewerLink({
  href,
  children,
  className,
  titulo = "Reporte",
}: {
  href: string;
  children: ReactNode;
  className?: string;
  titulo?: string;
}) {
  const [open, setOpen] = useState(false);
  return (
    <>
      <button type="button" className={className} onClick={() => setOpen(true)}>{children}</button>
      {open && typeof document !== "undefined" &&
        createPortal(
          <div className="modal-overlay" onClick={() => setOpen(false)}>
            <div className="adj-viewer" onClick={(e) => e.stopPropagation()}>
              <div className="adj-viewer-head">
                <span>{titulo}</span>
                <span style={{ display: "flex", gap: 10 }}>
                  <a href={href} target="_blank" rel="noopener" className="btn-link">Descargar / pestaña</a>
                  <button className="btn-link" onClick={() => setOpen(false)}>Cerrar</button>
                </span>
              </div>
              <iframe src={href} title={titulo} />
            </div>
          </div>,
          document.body
        )}
    </>
  );
}
