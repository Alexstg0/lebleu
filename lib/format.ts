export const n = (v: unknown): number => Number(v ?? 0);

export const mxn = (v: unknown): string =>
  new Intl.NumberFormat("es-MX", {
    style: "currency",
    currency: "MXN",
    minimumFractionDigits: 2,
  }).format(n(v));

export const num = (v: unknown, d = 2): string =>
  n(v).toLocaleString("es-MX", {
    minimumFractionDigits: d,
    maximumFractionDigits: d,
  });

// "14:30" -> "2:30 PM"
export const hora12 = (hhmm: unknown): string => {
  const s = String(hhmm ?? "").slice(0, 5);
  const [h, m] = s.split(":").map(Number);
  if (Number.isNaN(h)) return "";
  const ap = h >= 12 ? "PM" : "AM";
  const h12 = h % 12 === 0 ? 12 : h % 12;
  return `${h12}:${String(m || 0).padStart(2, "0")} ${ap}`;
};

const MESES = [
  "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio",
  "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre",
];
export const mesNombre = (m: number): string => MESES[m - 1] ?? String(m);

// Normaliza una fecha (Date de PGlite o string) a "YYYY-MM-DD" usando UTC
// para evitar el corrimiento de zona horaria.
export const fechaISO = (v: unknown): string => {
  if (v instanceof Date) {
    const y = v.getUTCFullYear();
    const m = String(v.getUTCMonth() + 1).padStart(2, "0");
    const d = String(v.getUTCDate()).padStart(2, "0");
    return `${y}-${m}-${d}`;
  }
  return String(v ?? "").slice(0, 10);
};

// "2026-05-10" -> "10/05"
export const fechaCorta = (v: unknown): string => {
  const p = fechaISO(v).split("-");
  return p.length === 3 ? `${p[2]}/${p[1]}` : fechaISO(v);
};
