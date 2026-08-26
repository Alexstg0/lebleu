"use client";

import { useRouter } from "next/navigation";
import { mesNombre } from "@/lib/format";

type P = { id: number; anio: number; mes: number; estado?: string };

// Selector de periodo que se queda en la misma página (Capturar / Movimientos).
export default function PeriodoPicker({ periodos, periodoId, base }: { periodos: P[]; periodoId: number; base: string }) {
  const router = useRouter();
  return (
    <div className="rep-periodo">
      <label>Periodo a registrar</label>
      <select value={periodoId} onChange={(e) => router.push(`${base}?periodo=${e.target.value}`)}>
        {periodos.map((p) => (
          <option key={p.id} value={p.id}>
            {mesNombre(p.mes)} {p.anio}{p.estado ? ` · ${p.estado}` : ""}
          </option>
        ))}
      </select>
    </div>
  );
}
