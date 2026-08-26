import { generarReciboUnaPagina, type ReciboData, largaFecha } from "./recibo";
import { LOGO_SOLMEX_WHITE_B64 } from "./logo-solmex-white";

export const NOMINA_CAPITAN = 8560;
export const CARGO_ADMIN = 6000;
// "De hoy en adelante": nunca se generan cargos con fecha anterior a esta.
export const RECURRENTES_DESDE = "2026-07-02";

// Fecha de hoy en la zona horaria de la embarcación (Baja California Sur).
export function hoyISO(): string {
  return new Intl.DateTimeFormat("en-CA", {
    timeZone: "America/Mazatlan", year: "numeric", month: "2-digit", day: "2-digit",
  }).format(new Date());
}

const MESES = ["enero", "febrero", "marzo", "abril", "mayo", "junio", "julio", "agosto", "septiembre", "octubre", "noviembre", "diciembre"];
const pad = (n: number) => String(n).padStart(2, "0");
const mesNombre = (m: number) => MESES[m - 1].charAt(0).toUpperCase() + MESES[m - 1].slice(1);

function viernesDelMes(anio: number, mes: number): string[] {
  const out: string[] = [];
  const dias = new Date(Date.UTC(anio, mes, 0)).getUTCDate();
  for (let d = 1; d <= dias; d++) {
    if (new Date(Date.UTC(anio, mes - 1, d)).getUTCDay() === 5) out.push(`${anio}-${pad(mes)}-${pad(d)}`);
  }
  return out;
}

type Periodo = { id: number; anio: number; mes: number; embarcacion: string; razon_social: string | null };

const CAT_LABEL: Record<string, string> = {
  nomina: "Nómina", admin: "Administración", servicio: "Servicio",
  refaccion: "Refacción", consumible: "Consumible", impuesto: "Impuesto", otro: "Gasto",
};

// Genera (o regenera) el recibo PDF de un gasto operativo (formato SOLMEX) y lo adjunta.
export async function generarReciboGasto(db: any, gastoId: number): Promise<boolean> {
  const g = (await db.query(
    `select go.id, go.proveedor, go.concepto, go.categoria, go.monto_mxn, go.comprobante_folio,
            e.nombre as embarcacion, e.razon_social,
            extract(year from go.fecha)::int as anio,
            extract(month from go.fecha)::int as mes,
            to_char(go.fecha, 'YYYY-MM-DD') as fecha_iso
       from gastos_operativos go
       join periodos p on p.id = go.periodo_id
       join embarcaciones e on e.id = p.embarcacion_id
      where go.id = $1`,
    [gastoId]
  )).rows[0];
  if (!g) return false;

  const emb = g.embarcacion || "Le Bleu";
  const rs = g.razon_social || "Arrendadora Acma S de RL de CV";
  const perTxt = `${mesNombre(Number(g.mes))} ${g.anio}`;
  const fecha = largaFecha(g.fecha_iso);
  const monto = Number(g.monto_mxn);
  const partida = CAT_LABEL[g.categoria] || "Gasto";
  const folio = g.comprobante_folio || `GASTO-${g.id}`;
  const esNomina = g.categoria === "nomina";
  const proveedor = g.proveedor || "—";

  const data: ReciboData = {
    titulo: esNomina ? "Recibo de nómina" : "Nota de gasto",
    periodoTxt: perTxt,
    etiqueta: "Recibo",
    nombre: proveedor,
    sub: `${g.concepto} · ${fecha}`,
    pairs: [
      [["EMBARCACIÓN", emb], ["PERÍODO", perTxt]],
      [["PARTIDA", partida], ["FECHA", fecha]],
      [["CONCEPTO", String(g.concepto)], ["COMPROBANTE", folio]],
    ],
    total: monto,
    totalLabel: "TOTAL PAGADO",
    observaciones: `El presente recibo ampara el gasto "${g.concepto}" de la embarcación ${emb} (${rs}) por ${monto.toLocaleString("es-MX", { style: "currency", currency: "MXN", minimumFractionDigits: 2 })} MXN, con fecha ${fecha}. Proveedor: ${proveedor}. Consultas: int.financiera@solmex.mx.`,
    firmaIzq: { nombre: "Kevin Flores", rol: "Revisó y autorizó" },
    firmaDer: { nombre: proveedor, rol: "Recibí de conformidad" },
  };

  const bytes = await generarReciboUnaPagina(LOGO_SOLMEX_WHITE_B64, data);
  const base64 = Buffer.from(bytes).toString("base64");
  await db.query(`delete from adjuntos where gasto_id = $1 and generado = true`, [gastoId]);
  await db.query(
    `insert into adjuntos (gasto_id, nombre, mime, datos, generado) values ($1,$2,'application/pdf',$3,true)`,
    [gastoId, `Recibo ${folio}.pdf`, base64]
  );
  return true;
}

async function insertGasto(
  db: any,
  g: { periodo_id: number; fecha: string; proveedor: string; concepto: string; categoria: string; monto: number; folio: string; clave: string }
): Promise<number | null> {
  const existe = (await db.query(`select id from gastos_operativos where clave_recurrente = $1`, [g.clave])).rows[0];
  if (existe) return null;
  const r = await db.query(
    `insert into gastos_operativos
      (periodo_id, fecha, proveedor, concepto, categoria, moneda, monto_original, tipo_cambio, comprobante_folio, clave_recurrente)
     values ($1,$2,$3,$4,$5,'MXN',$6,1,$7,$8) returning id`,
    [g.periodo_id, g.fecha, g.proveedor, g.concepto, g.categoria, g.monto, g.folio, g.clave]
  );
  return r.rows[0].id as number;
}

// Genera los cargos recurrentes que falten para un periodo, hasta la fecha "hoy".
export async function ensureRecurrentes(
  db: any,
  periodo: Periodo,
  hoy: string,
  desdeISO: string = RECURRENTES_DESDE
): Promise<{ nomina: number; admin: number }> {
  let nomina = 0, admin = 0;
  const perTxt = `${mesNombre(periodo.mes)} ${periodo.anio}`;

  // ---- Nómina semanal del capitán (viernes) ----
  for (const f of viernesDelMes(periodo.anio, periodo.mes)) {
    if (f < desdeISO || f > hoy) continue;
    const gid = await insertGasto(db, {
      periodo_id: periodo.id, fecha: f, proveedor: "Gabriel Preciado",
      concepto: `Nómina del capitán — semana del ${largaFecha(f)}`,
      categoria: "nomina", monto: NOMINA_CAPITAN, folio: `NOM-${f}`, clave: `nom-${f}`,
    });
    if (gid) { await generarReciboGasto(db, gid); nomina++; }
  }

  // ---- Cargo administrativo mensual ----
  const primero = `${periodo.anio}-${pad(periodo.mes)}-01`;
  const fechaAdmin = primero < desdeISO ? desdeISO : primero;
  const mesKey = `${periodo.anio}-${pad(periodo.mes)}`;
  if (fechaAdmin <= hoy && mesKey >= desdeISO.slice(0, 7)) {
    const gid = await insertGasto(db, {
      periodo_id: periodo.id, fecha: fechaAdmin, proveedor: "SOLMEX Administración",
      concepto: `Gasto administrativo mensual — ${perTxt}`,
      categoria: "admin", monto: CARGO_ADMIN, folio: `ADM-${mesKey}`, clave: `adm-${mesKey}`,
    });
    if (gid) { await generarReciboGasto(db, gid); admin++; }
  }

  return { nomina, admin };
}
