import { PDFDocument, PDFPage, rgb } from "pdf-lib";
import { LOGO_LEBLEU_NAVY_B64 } from "./logo-lebleu-navy";
import {
  ctxFor, type Ctx,
  NAVY, GRAY, WHITE, BORDER, LIGHT, mxn, nnum as num, cortaFecha, largaFecha,
} from "./recibo";

type Mov = {
  id: number;
  fecha: string;
  tipo: "gasto" | "abono";
  caja_numero: number | null;
  factura: string | null;
  proveedor: string | null;
  concepto: string | null;
  monto: any;
  abono: any;
  observaciones: string | null;
  balance: number;
};

// Página de balance en HORIZONTAL (más legible); los soportes conservan su orientación original.
const PW = 841.89, PH = 595.28, M = 46;

// Reporte de caja chica: carátula horizontal (balance con logo y firma) + soportes de cada movimiento.
export async function generarReporteCaja(
  db: any,
  opts: { caja: number | null; responsable: string }
): Promise<Uint8Array | null> {
  const todos = (await db.query(
    `select id, to_char(fecha, 'YYYY-MM-DD') as fecha, tipo, caja_numero, factura, proveedor, concepto, monto, abono, observaciones
       from caja_chica order by fecha, id`
  )).rows as Mov[];
  let bal = 0;
  for (const m of todos) {
    bal += num(m.abono) - num(m.monto);
    m.balance = Math.round(bal * 100) / 100;
  }
  const movs = opts.caja == null ? todos : todos.filter((m) => Number(m.caja_numero) === opts.caja);
  if (!movs.length) return null;

  const adj = (await db.query(
    `select id, caja_id, nombre, datos from adjuntos where caja_id is not null order by caja_id, id`
  )).rows as Array<{ id: number; caja_id: number; nombre: string; datos: string }>;
  const porMov = new Map<number, typeof adj>();
  for (const a of adj) {
    if (!porMov.has(a.caja_id)) porMov.set(a.caja_id, []);
    porMov.get(a.caja_id)!.push(a);
  }

  const esTodas = opts.caja == null;
  const totalGastos = movs.reduce((s, m) => s + num(m.monto), 0);
  const totalAbonos = movs.reduce((s, m) => s + num(m.abono), 0);
  const balanceActual = movs[movs.length - 1].balance;

  const doc = await PDFDocument.create();
  const ctx: Ctx = await ctxFor(doc, LOGO_LEBLEU_NAVY_B64);
  const { font, bold } = ctx;
  const hoy = new Date();
  const hoyIso = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, "0")}-${String(hoy.getDate()).padStart(2, "0")}`;
  const cajaTxt = esTodas ? "Todas las cajas" : `Caja ${opts.caja}`;
  const ROJO = rgb(0.72, 0.11, 0.11);
  const cw = PW - 2 * M;

  // ---- Columnas (horizontal: caben observaciones completas) ----
  const xFecha = M + 6;
  const xCaja = M + 52;
  const xFactura = M + 92;
  const xProv = M + 178;
  const xConc = M + 300;
  const xMonto = esTodas ? M + 520 : M + 545;   // borde derecho de la cifra
  const xAbono = M + 588;                        // solo "Todas"
  const xBal = M + 652;                          // solo "Todas"
  const xObs = esTodas ? M + 664 : M + 560;
  const concMax = xMonto - 62 - xConc;
  const obsMax = M + cw - xObs - 4;

  const right = (page: PDFPage, s: string, xr: number, y: number, size: number, f = font, color = NAVY) =>
    page.drawText(s, { x: xr - f.widthOfTextAtSize(s, size), y, size, font: f, color });

  const fit = (s: string, size: number, maxW: number, f = font) => {
    let t = s || "";
    if (f.widthOfTextAtSize(t, size) <= maxW) return t;
    while (t.length > 1 && f.widthOfTextAtSize(t + "…", size) > maxW) t = t.slice(0, -1);
    return t + "…";
  };

  function headRow(page: PDFPage, y: number): number {
    page.drawRectangle({ x: M, y: y - 22, width: cw, height: 22, color: LIGHT });
    const hy = y - 14.5;
    page.drawText("FECHA", { x: xFecha, y: hy, size: 8, font: bold, color: NAVY });
    page.drawText("CAJA", { x: xCaja, y: hy, size: 8, font: bold, color: NAVY });
    page.drawText("FACTURA", { x: xFactura, y: hy, size: 8, font: bold, color: NAVY });
    page.drawText("PROVEEDOR", { x: xProv, y: hy, size: 8, font: bold, color: NAVY });
    page.drawText("CONCEPTO", { x: xConc, y: hy, size: 8, font: bold, color: NAVY });
    right(page, "MONTO", xMonto, hy, 8, bold, NAVY);
    if (esTodas) {
      right(page, "ABONO", xAbono, hy, 8, bold, NAVY);
      right(page, "BALANCE", xBal, hy, 8, bold, NAVY);
    }
    page.drawText("OBSERVACIONES", { x: xObs, y: hy, size: 8, font: bold, color: NAVY });
    return y - 22;
  }

  // ---- Carátula horizontal ----
  let page = doc.addPage([PW, PH]);
  const topY = PH - 52;
  if (ctx.logo) {
    const lh = 42, lw = (ctx.logo.width / ctx.logo.height) * lh;
    page.drawImage(ctx.logo, { x: M, y: topY - lh + 10, width: lw, height: lh });
  }
  const xInfo = M + 115;
  page.drawText(`Balance de caja chica — ${cajaTxt}`, { x: xInfo, y: topY, size: 16, font: bold, color: NAVY });
  page.drawText("Arrendadora Acma S de RL de CV · Embarcación Le Bleu", { x: xInfo, y: topY - 17, size: 9.5, font, color: GRAY });
  page.drawText(`Fecha: ${largaFecha(hoyIso)}`, { x: xInfo, y: topY - 31, size: 9, font, color: GRAY });
  page.drawText(`Responsable de caja: ${opts.responsable || "—"}`, { x: xInfo + 200, y: topY - 31, size: 9, font, color: GRAY });
  page.drawLine({ start: { x: M, y: topY - 42 }, end: { x: PW - M, y: topY - 42 }, thickness: 1.5, color: NAVY });

  let y = headRow(page, topY - 54);
  const rh = 22;
  movs.forEach((m) => {
    if (y - rh < 78) {
      page = doc.addPage([PW, PH]);
      page.drawText(`Balance de caja chica — ${cajaTxt} (continuación)`, { x: M, y: PH - 46, size: 11, font: bold, color: NAVY });
      y = headRow(page, PH - 58);
    }
    const ty = y - 14.5;
    page.drawText(cortaFecha(m.fecha), { x: xFecha, y: ty, size: 9, font, color: NAVY });
    page.drawText(m.caja_numero != null ? String(m.caja_numero) : "—", { x: xCaja, y: ty, size: 9, font, color: NAVY });
    page.drawText(fit(m.factura || "—", 9, xProv - xFactura - 8), { x: xFactura, y: ty, size: 9, font, color: NAVY });
    page.drawText(fit(m.proveedor || "—", 9, xConc - xProv - 8), { x: xProv, y: ty, size: 9, font, color: NAVY });
    page.drawText(fit(m.concepto || (m.tipo === "abono" ? "Abono a caja" : "—"), 9, concMax), { x: xConc, y: ty, size: 9, font, color: NAVY });
    right(page, num(m.monto) ? mxn(num(m.monto)) : "—", xMonto, ty, 9);
    if (esTodas) {
      right(page, num(m.abono) ? mxn(num(m.abono)) : "—", xAbono, ty, 9);
      right(page, mxn(m.balance), xBal, ty, 9, font, m.balance < 0 ? ROJO : NAVY);
    }
    page.drawText(fit(m.observaciones || "—", 8.5, obsMax), { x: xObs, y: ty, size: 8.5, font, color: GRAY });
    page.drawLine({ start: { x: M, y: y - rh }, end: { x: M + cw, y: y - rh }, thickness: 0.5, color: BORDER });
    y -= rh;
  });

  // Totales
  if (y - 26 < 78) {
    page = doc.addPage([PW, PH]);
    page.drawText(`Balance de caja chica — ${cajaTxt} (continuación)`, { x: M, y: PH - 46, size: 11, font: bold, color: NAVY });
    y = PH - 58;
  }
  page.drawRectangle({ x: M, y: y - 26, width: cw, height: 26, color: rgb(0.955, 0.965, 0.98) });
  const tly = y - 17;
  right(page, "Totales", xConc + 120, tly, 10, bold);
  right(page, mxn(totalGastos), xMonto, tly, 10, bold);
  if (esTodas) {
    right(page, mxn(totalAbonos), xAbono, tly, 10, bold);
    right(page, mxn(balanceActual), xBal, tly, 10, bold, balanceActual < 0 ? ROJO : NAVY);
  } else {
    const dif = Math.round((totalGastos - totalAbonos) * 100) / 100;
    page.drawText(totalAbonos > 0 ? `Abonado: ${mxn(totalAbonos)} · A pagar: ${mxn(dif)}` : `A pagar: ${mxn(dif)}`, { x: xObs, y: tly, size: 9, font: bold, color: NAVY });
  }
  y -= 26;

  // Firma centrada
  if (y < 130) {
    page = doc.addPage([PW, PH]);
    y = PH - 120;
  }
  const sigY = Math.max(64, y - 92);
  const sigW = 210;
  const sx = (PW - sigW) / 2;
  page.drawLine({ start: { x: sx, y: sigY }, end: { x: sx + sigW, y: sigY }, thickness: 1, color: NAVY });
  page.drawText("Kevin Flores", { x: (PW - bold.widthOfTextAtSize("Kevin Flores", 10)) / 2, y: sigY - 15, size: 10, font: bold, color: NAVY });
  page.drawText("Revisó", { x: (PW - font.widthOfTextAtSize("Revisó", 8.5)) / 2, y: sigY - 28, size: 8.5, font, color: GRAY });

  // ---- Soportes en el orden del balance (con sello al pie) ----
  for (const m of movs) {
    const lista = porMov.get(m.id);
    if (!lista) continue;
    const sello = `Soporte de caja chica · ${cortaFecha(m.fecha)}${m.caja_numero != null ? ` · Caja ${m.caja_numero}` : ""} · ${m.proveedor || m.concepto || (m.tipo === "abono" ? "Abono" : "")} · ${m.tipo === "abono" ? mxn(num(m.abono)) : mxn(num(m.monto))}`;
    for (const a of lista) {
      try {
        const src = await PDFDocument.load(Buffer.from(a.datos, "base64"), { ignoreEncryption: true });
        const pages = await doc.copyPages(src, src.getPageIndices());
        pages.forEach((p, k) => {
          doc.addPage(p);
          if (k === 0) {
            const pw = p.getWidth();
            p.drawRectangle({ x: 0, y: 0, width: pw, height: 18, color: NAVY, opacity: 0.92 });
            p.drawText(fit(sello, 8, pw - 20, bold), { x: 10, y: 5.5, size: 8, font: bold, color: WHITE });
          }
        });
      } catch {
        const err = doc.addPage([PW, PH]);
        err.drawText("El soporte no se pudo incrustar", { x: M, y: PH - 110, size: 14, font: bold, color: NAVY });
        err.drawText(`${a.nombre} · ${sello}`, { x: M, y: PH - 130, size: 10, font, color: GRAY });
      }
    }
  }

  return await doc.save();
}
