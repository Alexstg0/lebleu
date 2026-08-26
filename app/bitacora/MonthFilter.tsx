"use client";

import { useRouter } from "next/navigation";
import { mesNombre } from "@/lib/format";

export default function MonthFilter({
  meses,
  anio,
  mes,
}: {
  meses: Array<{ anio: number; mes: number }>;
  anio: number;
  mes: number;
}) {
  const router = useRouter();
  const val = `${anio}-${mes}`;
  return (
    <select
      className="bita-filter"
      value={val}
      onChange={(e) => {
        const [y, m] = e.target.value.split("-");
        router.push(`/bitacora?y=${y}&m=${m}`);
      }}
      aria-label="Filtrar por mes"
    >
      {meses.map((x) => (
        <option key={`${x.anio}-${x.mes}`} value={`${x.anio}-${x.mes}`}>
          {mesNombre(x.mes)} {x.anio}
        </option>
      ))}
    </select>
  );
}
