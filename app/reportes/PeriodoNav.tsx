"use client";

import { useRouter } from "next/navigation";
import { mesNombre } from "@/lib/format";

type P = { id: number; anio: number; mes: number };

export default function PeriodoNav({ periodos, periodoId }: { periodos: P[]; periodoId: number }) {
  const router = useRouter();
  return (
    <div className="rep-periodo">
      <label>Periodo</label>
      <select value={periodoId} onChange={(e) => router.push(`/reportes?periodo=${e.target.value}`)}>
        {periodos.map((p) => (
          <option key={p.id} value={p.id}>{mesNombre(p.mes)} {p.anio}</option>
        ))}
      </select>
    </div>
  );
}
