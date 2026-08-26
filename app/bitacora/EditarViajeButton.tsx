"use client";

import { useState } from "react";
import Viaje from "../capturar/forms/Viaje";

export default function EditarViajeButton({
  viaje,
  periodoId,
  socios,
  clientes,
  motores,
  marineros = [],
  precioLitro,
}: {
  viaje: any;
  periodoId: number;
  socios: any[];
  clientes: any[];
  motores: any[];
  marineros?: any[];
  precioLitro: number;
}) {
  const [open, setOpen] = useState(false);
  return (
    <>
      <button className="bita-edit" onClick={() => setOpen(true)} title="Editar viaje">Editar</button>
      {open && (
        <div className="modal-overlay" onClick={() => setOpen(false)}>
          <div className="modal" style={{ width: "min(720px, 96vw)", maxWidth: "min(720px, 96vw)", maxHeight: "92vh", overflowY: "auto" }} onClick={(e) => e.stopPropagation()}>
            <h2 style={{ margin: "0 0 12px", fontSize: 18 }}>Editar viaje</h2>
            <Viaje periodoId={periodoId} socios={socios} clientes={clientes} motores={motores} marineros={marineros} precioLitro={precioLitro} initial={viaje} onDone={() => setOpen(false)} />
          </div>
        </div>
      )}
    </>
  );
}
