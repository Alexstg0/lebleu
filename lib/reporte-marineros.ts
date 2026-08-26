import { PDFDocument, rgb } from "pdf-lib";
import { LOGO_LEBLEU_B64 } from "./logo-lebleu-hi";
import {
  ctxFor, header, drawRecibo, generarReciboUnaPagina, type ReciboData,
  W, H, M, NAVY, GOLD, GRAY, WHITE, BORDER, mxn, nnum as num, capMes, cortaFecha, largaFecha,
} from "./recibo";

const VIAJE_SELECT = `
  select v.id, to_char(v.fecha, 'YYYY-MM-DD') as fecha, v.duracion_horas, v.num_personas, v.costo_marinero,
         coalesce(nullif(btrim(v.marinero), ''), 'Sin asignar') as marinero,
         coalesce(v.cliente_nombre, c.nombre, so.nombre, 'Renta') as cliente,
         p.anio, p.mes, e.nombre as embarcacion, e.razon_social
    from viajes v
    join periodos p on p.id = v.periodo_id
    join embarcaciones e on e.id = p.embarcacion_id
    left join clientes c on c.id = v.cliente_id
    left join socios so on so.id = v.socio_id`;

function reciboMarinero(v: any, meta: { emb: string; rs: string; periodoTxt: string; label?: string }): ReciboData {
  return {
    titulo: "Recibo de pago a marinero",
    periodoTxt: meta.periodoTxt,
    etiqueta: `Recibo de pago${meta.label ? `  ·  ${meta.label}` : ""}`,
    nombre: v.marinero,
    sub: `Viaje del ${largaFecha(v.fecha)} · ${v.cliente}`,
    pairs: [
      [["MARINERO", v.marinero], ["FECHA", largaFecha(v.fecha)]],
      [["CLIENTE / VIAJE", String(v.cliente)], ["DURACIÓN", v.duracion_horas != null ? `${num(v.duracion_horas)} h` : "—"]],
      [["EMBARCACIÓN", meta.emb], ["N.º DE PERSONAS", v.num_personas != null ? String(v.num_personas) : "—"]],
    ],
    total: num(v.costo_marinero),
    totalLabel: "TOTAL A PAGAR",
    observaciones: `El presente recibo ampara el pago al marinero ${v.marinero} por el viaje del ${largaFecha(v.fecha)} (${v.cliente}) de la embarcación ${meta.emb} (${meta.rs}), por ${mxn(num(v.costo_marinero))} MXN.`,
    firmaIzq: { nombre: v.marinero, rol: "Recibí de conformidad" },
    firmaDer: { nombre: "Le Bleu — Administración", rol: "Pagó" },
  };
}

// Recibo de UN viaje (para Movimientos / botón por viaje).
export async function generarReciboViaje(db: any, viajeId: number): Promise<Uint8Array | null> {
  const v = (await db.query(`${VIAJE_SELECT} where v.id = $1`, [viajeId])).rows[0];
  if (!v) return null;
  return await generarReciboUnaPagina(LOGO_LEBLEU_B64, reciboMarinero(v, {
    emb: v.embarcacion || "Le Bleu", rs: v.razon_social || "Arrendadora Acma S de RL de CV", periodoTxt: `${capMes(v.mes)} ${v.anio}`,
  }));
}

// Reporte de marineros: resumen (una fila por viaje) + un recibo por viaje.
export async function generarReporteMarineros(db: any, periodoId: number): Promise<Uint8Array | null> {
  const p = (await db.query(
    `select p.anio, p.mes, e.nombre as embarcacion, e.razon_social
       from periodos p join embarcaciones e on e.id = p.embarcacion_id where p.id = $1`, [periodoId])).rows[0];
  if (!p) return null;
  const periodoTxt = `${capMes(p.mes)} ${p.anio}`;
  const emb = p.embarcacion || "Le Bleu";
  const rs = p.razon_social || "Arrendadora Acma S de RL de CV";

  const viajes = (await db.query(`${VIAJE_SELECT} where v.periodo_id = $1 order by v.fecha, v.id`, [periodoId])).rows as any[];
  if (!viajes.length) return null;
  const total = viajes.reduce((s, v) => s + num(v.costo_marinero), 0);

  const doc = await PDFDocument.create();
  const ctx = await ctxFor(doc, LOGO_LEBLEU_B64);
  const { font, bold } = ctx;

  // ---- Página 1: Resumen ----
  const s = doc.addPage([W, H]);
  header(s, ctx, "Reporte de Marineros", periodoTxt);
  s.drawText("Relación de viajes y pagos a marineros", { x: M, y: H - 118, size: 17, font: bold, color: NAVY });
  s.drawText(`${emb} · ${rs}`, { x: M, y: H - 136, size: 10, font, color: GRAY });

  const cw = W - 2 * M;
  const c1 = M + 8, c2 = M + 62, c3 = M + 250, c4 = M + cw - 130, c5 = M + cw - 8;
  let y = H - 162;
  s.drawRectangle({ x: M, y: y - 22, width: cw, height: 22, color: NAVY });
  s.drawText("FECHA", { x: c1, y: y - 15, size: 8.5, font: bold, color: WHITE });
  s.drawText("CLIENTE / VIAJE", { x: c2, y: y - 15, size: 8.5, font: bold, color: WHITE });
  s.drawText("MARINERO", { x: c3, y: y - 15, size: 8.5, font: bold, color: WHITE });
  s.drawText("DUR.", { x: c4, y: y - 15, size: 8.5, font: bold, color: WHITE });
  s.drawText("PAGO", { x: c5 - bold.widthOfTextAtSize("PAGO", 8.5), y: y - 15, size: 8.5, font: bold, color: WHITE });
  y -= 22;
  viajes.forEach((v, i) => {
    const rh = 22;
    if (i % 2 === 1) s.drawRectangle({ x: M, y: y - rh, width: cw, height: rh, color: rgb(0.97, 0.98, 0.99) });
    s.drawText(cortaFecha(v.fecha), { x: c1, y: y - 15, size: 9, font, color: NAVY });
    s.drawText(String(v.cliente).slice(0, 30), { x: c2, y: y - 15, size: 9, font, color: NAVY });
    s.drawText(String(v.marinero).slice(0, 26), { x: c3, y: y - 15, size: 9, font, color: v.marinero === "Sin asignar" ? GRAY : NAVY });
    s.drawText(v.duracion_horas != null ? `${num(v.duracion_horas)} h` : "—", { x: c4, y: y - 15, size: 9, font, color: NAVY });
    const pg = mxn(num(v.costo_marinero));
    s.drawText(pg, { x: c5 - font.widthOfTextAtSize(pg, 9), y: y - 15, size: 9, font, color: NAVY });
    s.drawLine({ start: { x: M, y: y - rh }, end: { x: M + cw, y: y - rh }, thickness: 0.5, color: BORDER });
    y -= rh;
  });
  s.drawRectangle({ x: M, y: y - 26, width: cw, height: 26, color: rgb(0.94, 0.95, 0.97) });
  s.drawText(`TOTAL (${viajes.length} viaje${viajes.length === 1 ? "" : "s"})`, { x: c1, y: y - 17, size: 11, font: bold, color: NAVY });
  const gt = mxn(total);
  s.drawText(gt, { x: c5 - bold.widthOfTextAtSize(gt, 12), y: y - 17, size: 12, font: bold, color: NAVY });

  // ---- Un recibo por viaje ----
  viajes.forEach((v, idx) => {
    const page = doc.addPage([W, H]);
    drawRecibo(page, ctx, reciboMarinero(v, { emb, rs, periodoTxt, label: `N.º ${idx + 1} de ${viajes.length}` }));
  });

  return await doc.save();
}
