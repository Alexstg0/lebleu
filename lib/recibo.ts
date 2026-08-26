import { PDFDocument, StandardFonts, rgb, PDFFont, PDFPage, PDFImage } from "pdf-lib";

export const BAND = rgb(0.102, 0.227, 0.361); // #1a3a5c
export const NAVY = rgb(0.086, 0.153, 0.28);
export const GOLD = rgb(0.85, 0.68, 0.30);
export const GRAY = rgb(0.5, 0.55, 0.62);
export const LIGHT = rgb(0.94, 0.95, 0.97);
export const BORDER = rgb(0.8, 0.83, 0.87);
export const WHITE = rgb(1, 1, 1);
export const W = 595.28, H = 841.89, M = 46;

const MESES = ["enero", "febrero", "marzo", "abril", "mayo", "junio", "julio", "agosto", "septiembre", "octubre", "noviembre", "diciembre"];
export const mxn = (v: number) => new Intl.NumberFormat("es-MX", { style: "currency", currency: "MXN", minimumFractionDigits: 2 }).format(v || 0);
export const nnum = (v: any) => Number(v || 0);
export const capMes = (m: number) => MESES[m - 1].charAt(0).toUpperCase() + MESES[m - 1].slice(1);
export const cortaFecha = (iso: string) => { const s = String(iso).slice(0, 10).split("-"); return s.length === 3 ? `${s[2]}/${s[1]}` : String(iso); };
export const largaFecha = (iso: string) => { const s = String(iso).slice(0, 10).split("-"); return s.length === 3 ? `${Number(s[2])} de ${MESES[Number(s[1]) - 1]} de ${s[0]}` : String(iso); };

export type Ctx = { font: PDFFont; bold: PDFFont; logo?: PDFImage };

export async function ctxFor(doc: PDFDocument, logoB64?: string): Promise<Ctx> {
  const font = await doc.embedFont(StandardFonts.Helvetica);
  const bold = await doc.embedFont(StandardFonts.HelveticaBold);
  let logo: PDFImage | undefined;
  if (logoB64) { try { logo = await doc.embedPng(Buffer.from(logoB64, "base64")); } catch { logo = undefined; } }
  return { font, bold, logo };
}

export function header(page: PDFPage, ctx: Ctx, titulo: string, periodoTxt: string) {
  page.drawRectangle({ x: 0, y: H - 78, width: W, height: 78, color: BAND });
  if (ctx.logo) {
    const lw = 120, lh = (ctx.logo.height / ctx.logo.width) * lw;
    page.drawImage(ctx.logo, { x: M, y: H - 39 - lh / 2, width: lw, height: lh });
  }
  const t = titulo.toUpperCase();
  page.drawText(t, { x: W - M - ctx.bold.widthOfTextAtSize(t, 13), y: H - 40, size: 13, font: ctx.bold, color: WHITE });
  page.drawText(periodoTxt, { x: W - M - ctx.font.widthOfTextAtSize(periodoTxt, 9.5), y: H - 56, size: 9.5, font: ctx.font, color: rgb(0.82, 0.86, 0.92) });
}

export type ReciboData = {
  titulo: string;
  periodoTxt: string;
  etiqueta?: string;
  nombre: string;
  sub: string;
  pairs: [string, string][][];
  total: number;
  totalLabel: string;
  observaciones: string;
  firmaIzq: { nombre: string; rol: string };
  firmaDer: { nombre: string; rol: string };
};

export function drawRecibo(page: PDFPage, ctx: Ctx, d: ReciboData) {
  const { font, bold } = ctx;
  const T = (s: string, x: number, top: number, size: number, f = font, c = NAVY) => page.drawText(s ?? "", { x, y: H - top, size, font: f, color: c });
  header(page, ctx, d.titulo, d.periodoTxt);

  if (d.etiqueta) T(d.etiqueta, M, 118, 11, bold, NAVY);
  T(d.nombre, M, 146, 22, bold, NAVY);
  if (d.sub) T(d.sub, M, 166, 10.5, font, GRAY);

  const tableTop = 196, rowH = 28, cw = W - 2 * M, colw = cw / 2;
  d.pairs.forEach((r, i) => {
    const rowTop = tableTop + i * rowH;
    const ry = H - rowTop - rowH;
    page.drawRectangle({ x: M, y: ry, width: cw, height: rowH, borderColor: BORDER, borderWidth: 1 });
    r.forEach(([lbl, val], k) => {
      const cx = M + k * colw;
      page.drawRectangle({ x: cx, y: ry, width: 112, height: rowH, color: LIGHT });
      T(lbl, cx + 8, rowTop + 18, 7.5, bold, NAVY);
      T(String(val).slice(0, 34), cx + 120, rowTop + 18, 9.5, font, rgb(0.2, 0.25, 0.32));
    });
  });

  let top = tableTop + d.pairs.length * rowH + 24;
  page.drawRectangle({ x: M, y: H - top - 32, width: cw, height: 32, color: NAVY });
  T(d.totalLabel, M + 14, top + 21, 12, bold, WHITE);
  const tp = `${mxn(d.total)} MXN`;
  T(tp, M + cw - 14 - bold.widthOfTextAtSize(tp, 14), top + 21, 14, bold, WHITE);
  top += 32 + 32;

  T("OBSERVACIONES", M, top, 10, bold, NAVY);
  top += 16;
  const words = (d.observaciones || "").split(" "); let line = "";
  for (const wd of words) { const tt = line ? `${line} ${wd}` : wd; if (font.widthOfTextAtSize(tt, 9) > cw && line) { T(line, M, top, 9, font, rgb(0.3, 0.35, 0.42)); top += 13; line = wd; } else line = tt; }
  if (line) T(line, M, top, 9, font, rgb(0.3, 0.35, 0.42));

  const sigY = 120, sigW = 200;
  const box = (x: number, s: { nombre: string; rol: string }) => {
    page.drawLine({ start: { x, y: sigY }, end: { x: x + sigW, y: sigY }, thickness: 1, color: NAVY });
    page.drawText(s.nombre, { x: x + Math.max(6, (sigW - bold.widthOfTextAtSize(s.nombre, 9)) / 2), y: sigY - 14, size: 9, font: bold, color: NAVY });
    page.drawText(s.rol, { x: x + (sigW - font.widthOfTextAtSize(s.rol, 8)) / 2, y: sigY - 26, size: 8, font, color: GRAY });
  };
  box(M + 20, d.firmaIzq);
  box(W - M - 20 - sigW, d.firmaDer);
}

export async function generarReciboUnaPagina(logoB64: string, d: ReciboData): Promise<Uint8Array> {
  const doc = await PDFDocument.create();
  const ctx = await ctxFor(doc, logoB64);
  const page = doc.addPage([W, H]);
  drawRecibo(page, ctx, d);
  return await doc.save();
}
