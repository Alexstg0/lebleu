import { PDFDocument, StandardFonts, rgb, PDFFont, PDFPage, PDFImage } from "pdf-lib";
import { LOGO_LEBLEU_B64 } from "./logo-lebleu-hi";

const NAVY = rgb(0.086, 0.153, 0.28);
const NAVY2 = rgb(0.13, 0.22, 0.38);
const GOLD = rgb(0.85, 0.68, 0.30);
const GRAY = rgb(0.5, 0.55, 0.62);
const LIGHT = rgb(0.93, 0.95, 0.97);

const MESES = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];

function wrap(text: string, font: PDFFont, size: number, maxW: number): string[] {
  const words = (text || "").split(/\s+/);
  const lines: string[] = [];
  let line = "";
  for (const w of words) {
    const t = line ? `${line} ${w}` : w;
    if (font.widthOfTextAtSize(t, size) > maxW && line) { lines.push(line); line = w; } else line = t;
  }
  if (line) lines.push(line);
  return lines;
}

type CoverData = { titulo: string; subtitulo: string; periodoTxt: string; elabora: string; empresa: string; embarcacion: string };

function drawCover(page: PDFPage, font: PDFFont, bold: PDFFont, d: CoverData, logo?: PDFImage) {
  const W = 595.28, H = 841.89, M = 46;
  const T = (s: string, x: number, top: number, size: number, f = font, color = NAVY) => page.drawText(s ?? "", { x, y: H - top, size, font: f, color });
  const TC = (s: string, top: number, size: number, f = font, color = NAVY) => T(s, (W - f.widthOfTextAtSize(s, size)) / 2, top, size, f, color);

  // Banda superior con el azul de las tarjetas del estado de cuenta (#1a3a5c)
  const band = rgb(0.102, 0.227, 0.361);
  page.drawRectangle({ x: 0, y: H - 250, width: W, height: 250, color: band });
  TC(("Arrendadora Acma S de RL de CV · Embarcación " + d.embarcacion).toUpperCase(), 44, 8.5, bold, rgb(1, 1, 1));
  if (logo) {
    const lw = 218, lh = (logo.height / logo.width) * lw;
    page.drawImage(logo, { x: (W - lw) / 2, y: H - 60 - lh, width: lw, height: lh });
  } else {
    TC("Le Bleu", 114, 42, bold, rgb(0.95, 0.96, 0.99));
    TC("L I V I N G   &   S E A", 140, 9, font, rgb(1, 1, 1));
  }
  TC("Reporte de soportes", 205, 11, font, rgb(0.75, 0.8, 0.87));

  // Emblema + etiqueta
  page.drawEllipse({ x: M + 20, y: H - 320, xScale: 20, yScale: 20, borderColor: NAVY, borderWidth: 1.5, color: LIGHT });
  T("DOCUMENTO DE SOPORTE", M + 52, 316, 10, bold, NAVY);

  // Título grande
  let top = 380;
  wrap(d.titulo, bold, 30, W - 2 * M).forEach((ln) => { T(ln, M, top, 30, bold, NAVY); top += 36; });

  // Subtítulo con acento dorado
  top += 6;
  page.drawRectangle({ x: M, y: H - top - 4, width: 3, height: 34, color: NAVY });
  wrap(d.subtitulo, font, 12, W - 2 * M - 16).forEach((ln, i) => T(ln, M + 14, top + i * 16, 12, font, GRAY));
  top += 60;

  // Línea dorada
  page.drawRectangle({ x: M, y: H - top, width: W - 2 * M, height: 1, color: NAVY });
  top += 26;

  // Datos: PERÍODO / ELABORA / EMPRESA
  const col = (W - 2 * M) / 3;
  const cells: [string, string][] = [["PERÍODO", d.periodoTxt], ["ELABORA", d.elabora], ["EMPRESA", d.empresa]];
  cells.forEach(([lbl, val], i) => {
    T(lbl, M + i * col, top, 8, bold, NAVY);
    T(val, M + i * col, top + 15, 11, bold, NAVY);
  });

  // Pie
  page.drawRectangle({ x: 0, y: 0, width: W, height: 64, color: LIGHT });
  T("Le Bleu", M, H - 30, 13, bold, NAVY);
  const r1 = `${d.periodoTxt} · Cifras en MXN`;
  const r2 = "Confidencial — uso interno";
  T(r1, W - M - font.widthOfTextAtSize(r1, 8.5), H - 26, 8.5, font, GRAY);
  T(r2, W - M - bold.widthOfTextAtSize(r2, 8.5), H - 40, 8.5, bold, NAVY2);
}

type Row = { datos: string; concepto: string; comprobante_folio: string | null; monto_mxn: string };

async function appendSupport(merged: PDFDocument, font: PDFFont, bold: PDFFont, r: Row) {
  try {
    const src = await PDFDocument.load(Buffer.from(r.datos, "base64"), { ignoreEncryption: true });
    const pages = await merged.copyPages(src, src.getPageIndices());
    pages.forEach((p) => merged.addPage(p));
  } catch {
    // Si el PDF no se puede incrustar, se agrega una hoja indicándolo.
    const page = merged.addPage([595.28, 841.89]);
    page.drawText("Comprobante no se pudo incrustar", { x: 46, y: 760, size: 14, font: bold, color: NAVY });
    page.drawText(`${r.concepto || ""} · ${r.comprobante_folio || ""}`, { x: 46, y: 738, size: 10, font, color: GRAY });
  }
}

// Genera el PDF consolidado: portada por partida + los PDFs de respaldo de esa partida.
// incluirMarineros=false cuando este reporte va dentro del Reporte completo,
// porque ahí los recibos de marinero ya vienen en su propia sección (evita duplicarlos).
export async function generarReporteSoportes(db: any, periodoId: number, incluirMarineros = true): Promise<Uint8Array | null> {
  const p = (await db.query(
    `select p.anio, p.mes, e.nombre as embarcacion, e.razon_social
       from periodos p join embarcaciones e on e.id = p.embarcacion_id where p.id = $1`, [periodoId])).rows[0];
  if (!p) return null;
  const periodoTxt = `${MESES[p.mes - 1]} ${p.anio}`;

  const operativos = (await db.query(
    `select a.datos, g.concepto, g.comprobante_folio, g.monto_mxn
       from adjuntos a join gastos_operativos g on g.id = a.gasto_id
      where g.periodo_id = $1 order by g.fecha, g.id`, [periodoId])).rows as Row[];
  const insumos = (await db.query(
    `select a.datos, i.concepto, i.comprobante_folio, i.monto_mxn
       from adjuntos a join insumos i on i.id = a.insumo_id
      where i.periodo_id = $1 order by i.fecha, i.id`, [periodoId])).rows as Row[];
  const extraordinarios = (await db.query(
    `select a.datos, e.concepto, e.comprobante_folio, e.monto_mxn
       from adjuntos a join extraordinarios e on e.id = a.extraordinario_id
      where e.periodo_id = $1 order by e.fecha, e.id`, [periodoId])).rows as Row[];
  // Recibos de pago a marineros (se generan al vuelo, uno por viaje).
  const viajesIds = (await db.query(
    `select id from viajes where periodo_id = $1 order by fecha, id`, [periodoId])).rows as Array<{ id: number }>;

  const partidas = [
    { titulo: "Gastos Operativos del Mes", subtitulo: `Relación de gastos generales compartidos al 50% entre socios · ${periodoTxt}`, rows: operativos },
    { titulo: "Insumos de Viajes", subtitulo: `Comprobantes de insumos asignados a los viajes · ${periodoTxt}`, rows: insumos },
    { titulo: "Gastos Extraordinarios", subtitulo: `Comprobantes de gastos extraordinarios (liquidación separada) · ${periodoTxt}`, rows: extraordinarios },
  ];

  const merged = await PDFDocument.create();
  const font = await merged.embedFont(StandardFonts.Helvetica);
  const bold = await merged.embedFont(StandardFonts.HelveticaBold);
  let logo: PDFImage | undefined;
  try { logo = await merged.embedPng(Buffer.from(LOGO_LEBLEU_B64, "base64")); } catch { logo = undefined; }
  const coverData = (titulo: string, subtitulo: string) => ({
    titulo, subtitulo, periodoTxt,
    elabora: "Kevin Flores", empresa: p.razon_social || "Acma S de RL", embarcacion: p.embarcacion || "Le Bleu",
  });

  let any = false;
  for (const part of partidas) {
    if (!part.rows.length) continue;
    any = true;
    const cover = merged.addPage([595.28, 841.89]);
    drawCover(cover, font, bold, coverData(part.titulo, part.subtitulo), logo);
    for (const r of part.rows) await appendSupport(merged, font, bold, r);
  }

  // Pagos a marineros: un recibo generado por cada viaje del periodo.
  if (incluirMarineros && viajesIds.length) {
    const { generarReciboViaje } = await import("./reporte-marineros");
    let coverDrawn = false;
    for (const v of viajesIds) {
      const bytes = await generarReciboViaje(db, v.id);
      if (!bytes) continue;
      if (!coverDrawn) {
        const cover = merged.addPage([595.28, 841.89]);
        drawCover(cover, font, bold, coverData("Pagos a Marineros", `Recibos de pago a marineros por viaje · ${periodoTxt}`), logo);
        coverDrawn = true;
        any = true;
      }
      const src = await PDFDocument.load(bytes);
      const pages = await merged.copyPages(src, src.getPageIndices());
      pages.forEach((pg) => merged.addPage(pg));
    }
  }

  if (!any) return null;
  return await merged.save();
}
