"use client";

import { useState, type ReactNode } from "react";
import { createPortal } from "react-dom";

// Fila de tabla que abre el PDF en un visor dentro de la app (no en otra pestaña).
export default function ClickableRow({
  href,
  children,
  className,
}: {
  href?: string | null;
  children: ReactNode;
  className?: string;
}) {
  const [open, setOpen] = useState(false);
  const cls = [className, href ? "row-pdf" : ""].filter(Boolean).join(" ");

  return (
    <>
      <tr
        className={cls || undefined}
        onClick={href ? () => setOpen(true) : undefined}
        title={href ? "Ver PDF de respaldo" : undefined}
      >
        {children}
      </tr>
      {open && href && typeof document !== "undefined" &&
        createPortal(
          <div className="modal-overlay" onClick={() => setOpen(false)}>
            <div className="adj-viewer" onClick={(e) => e.stopPropagation()}>
              <div className="adj-viewer-head">
                <span>Documento de respaldo</span>
                <span style={{ display: "flex", gap: 10 }}>
                  <a href={href} target="_blank" rel="noopener" className="btn-link">Abrir en pestaña</a>
                  <button className="btn-link" onClick={() => setOpen(false)}>Cerrar</button>
                </span>
              </div>
              <iframe src={href} title="Documento de respaldo" />
            </div>
          </div>,
          document.body
        )}
    </>
  );
}
