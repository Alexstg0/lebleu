// Estado de cuenta en PDF con el mismo acomodo que la vista de impresión de la app.
import { PDFDocument, rgb, PDFPage } from "pdf-lib";
import { LOGO_LEBLEU_NAVY_B64 } from "./logo-lebleu-navy";
import { ctxFor, type Ctx, W, H, M, mxn, nnum as n, capMes, cortaFecha } from "./recibo";
import { getEstadoCuenta } from "./queries";

// Paleta de la app (globals.css)
const NAVY = rgb(0.102, 0.227, 0.361);   // #1a3a5c
const OCEAN = rgb(0.118, 0.286, 0.463);  // #1e4976
const TEAL = rgb(0.055, 0.486, 0.482);   // #0e7c7b
const GOLD = rgb(0.722, 0.525, 0.043);   // #b8860b
const GREEN = rgb(0.102, 0.478, 0.29);   // #1a7a4a
const RED = rgb(0.753, 0.224, 0.169);    // #c0392b
const AMBER = rgb(0.753, 0.337, 0.129);  // #c05621
const TEXT = rgb(0.118, 0.176, 0.239);   // #1e2d3d
const MUTED = rgb(0.42, 0.478, 0.553);   // #6b7a8d
const BG2 = rgb(0.933, 0.949, 0.969);    // #eef2f7
const BORDER = rgb(0.859, 0.89, 0.925);
const WHITE = rgb(1, 1, 1);
const CREMA = rgb(1, 0.973, 0.941);      // #fff8f0
const CREMA_B = rgb(0.961, 0.718, 0.627); // #f5b7a0
const ZEBRA = rgb(0.975, 0.982, 0.99);

const nf = (v: any, d = 2) =>
  Number(v || 0).toLocaleString("es-MX", { minimumFractionDigits: d, maximumFractionDigits: d });

type Col = { titulo: string; w: number; alinear?: "l" | "r" | "c"; get: (r: any, i: number) => string; color?: (r: any) => any };

export async function generarEstadoCuentaPDF(db: any, periodoId: number): Promise<Uint8Array | null> {
  const data = await getEstadoCuenta(periodoId);
  if (!data) return null;
  const { periodo, balances, variable, extraSocio, operativos, insumos, extraordinarios, extraCuenta, viajes, kpis } = data as any;
  const periodoTxt = `${capMes(periodo.mes)} ${periodo.anio}`;
  const emb = periodo.embarcacion || "Le Bleu";
  const rs = periodo.razon_social || "Arrendadora Acma S de RL de CV";
  const varBy = (sid: number) => (variable as any[]).find((v) => v.socio_id === sid);
  const extraBy = (sid: number) => (extraSocio as any[]).find((e) => e.socio_id === sid);
  const firstSocio = balances[0]?.socio_id;

  const doc = await PDFDocument.create();
  const ctx: Ctx = await ctxFor(doc, LOGO_LEBLEU_NAVY_B64);
  const { font, bold } = ctx;
  const cw = W - 2 * M;

  let page!: PDFPage;
  let y = 0; // distancia desde el borde superior

  const newPage = (conHeader = false) => {
    page = doc.addPage([W, H]);
    y = 46;
    if (conHeader) {
      if (ctx.logo) {
        const lh = 36, lw = (ctx.logo.width / ctx.logo.height) * lh;
        page.drawImage(ctx.logo, { x: M, y: H - y - lh + 6, width: lw, height: lh });
      }
      const xT = M + 104;
      page.drawText(`Estado de cuenta — ${periodoTxt}`, { x: xT, y: H - y - 12, size: 15.5, font: bold, color: NAVY });
      page.drawText(
        `${emb} · ${rs} · T/C $${nf(periodo.tipo_cambio)} · Combustible $${nf(periodo.precio_litro)}/Lt · Cifras en MXN`,
        { x: xT, y: H - y - 27, size: 8.5, font, color: MUTED }
      );
      page.drawLine({ start: { x: M, y: H - y - 40 }, end: { x: W - M, y: H - y - 40 }, thickness: 2, color: NAVY });
      y += 54;
    }
  };
  const need = (h: number) => { if (H - y - h < 50) newPage(); };
  const T = (s: string, x: number, size: number, f = font, c = TEXT) => page.drawText(s ?? "", { x, y: H - y, size, font: f, color: c });
  const TR = (s: string, xr: number, yy: number, size: number, f = font, c = TEXT) =>
    page.drawText(s ?? "", { x: xr - f.widthOfTextAtSize(s ?? "", size), y: yy, size, font: f, color: c });
  const fit = (s: string, size: number, maxW: number, f = font) => {
    let t = s || "";
    if (f.widthOfTextAtSize(t, size) <= maxW) return t;
    while (t.length > 1 && f.widthOfTextAtSize(t + "…", size) > maxW) t = t.slice(0, -1);
    return t + "…";
  };

  const seccion = (titulo: string, color = NAVY) => {
    need(34);
    y += 16;
    page.drawRectangle({ x: M, y: H - y - 3, width: 3, height: 12, color });
    T(titulo.toUpperCase(), M + 9, 9.5, bold, color);
    y += 16;
  };

  const tabla = (cols: Col[], rows: any[], totalRows?: string[][]) => {
    const rowH = 18;
    const xs: number[] = [];
    let acc = M;
    cols.forEach((c) => { xs.push(acc); acc += c.w; });
    const celda = (v: string, k: number, f: any, c: any, yy: number) => {
      const col = cols[k];
      const vv = fit(v ?? "", 8, col.w - 10, f);
      let tx = xs[k] + 5;
      if (col.alinear === "r") tx = xs[k] + col.w - 5 - f.widthOfTextAtSize(vv, 8);
      if (col.alinear === "c") tx = xs[k] + (col.w - f.widthOfTextAtSize(vv, 8)) / 2;
      page.drawText(vv, { x: tx, y: yy, size: 8, font: f, color: c });
    };
    const head = () => {
      need(rowH + 40);
      page.drawRectangle({ x: M, y: H - y - rowH, width: cw, height: rowH, color: BG2 });
      cols.forEach((c, k) => {
        const vv = c.titulo;
        let tx = xs[k] + 5;
        if (c.alinear === "r") tx = xs[k] + c.w - 5 - bold.widthOfTextAtSize(vv, 7.5);
        if (c.alinear === "c") tx = xs[k] + (c.w - bold.widthOfTextAtSize(vv, 7.5)) / 2;
        page.drawText(vv, { x: tx, y: H - y - 12.5, size: 7.5, font: bold, color: NAVY });
      });
      y += rowH;
    };
    head();
    rows.forEach((r, i) => {
      if (H - y - rowH < 50) { newPage(); head(); }
      if (i % 2 === 1) page.drawRectangle({ x: M, y: H - y - rowH, width: cw, height: rowH, color: ZEBRA });
      cols.forEach((c, k) => celda(String(c.get(r, i) ?? ""), k, font, c.color ? c.color(r) : TEXT, H - y - 12.5));
      page.drawLine({ start: { x: M, y: H - y - rowH }, end: { x: M + cw, y: H - y - rowH }, thickness: 0.4, color: BORDER });
      y += rowH;
    });
    (totalRows || []).forEach((tr) => {
      if (H - y - rowH < 50) newPage();
      page.drawRectangle({ x: M, y: H - y - rowH, width: cw, height: rowH, color: BG2 });
      tr.forEach((v, k) => { if (v) celda(v, k, bold, NAVY, H - y - 12.5); });
      y += rowH;
    });
    y += 4;
  };

  // ================= Encabezado + KPIs =================
  newPage(true);

  const kpiDefs: { bg: any; claro?: boolean; label: string; valor: string; notas: string[] }[] = [
    { bg: NAVY, label: "GASTOS OPERATIVOS", valor: mxn(kpis.opTotal), notas: [`50% c/u: ${mxn(kpis.opMitad)}`, `Extraordinarios: ${mxn(kpis.extraTotal)}`] },
    { bg: TEAL, label: "GASTO VARIABLE", valor: mxn(kpis.varTotal), notas: [`Comb. ${mxn(kpis.combTotal)} · Mar. ${mxn(kpis.marTotal)}`, `Cons. ${mxn(kpis.consTotal)}`] },
    { bg: WHITE, claro: true, label: "INGRESOS POR RENTA", valor: mxn(kpis.ingresosRenta), notas: [n(kpis.ingresosRenta) > 0 ? "Renta del mes" : "Sin renta este mes"] },
    { bg: NAVY, label: "VIAJES REALIZADOS", valor: `${kpis.numViajes} viajes`, notas: [`${nf(kpis.litrosTotal)} Lts consumo total`, `PU: $${nf(periodo.precio_litro)} / Lt`] },
  ];
  const kw = (cw - 3 * 8) / 4, kh = 58;
  need(kh + 8);
  kpiDefs.forEach((k, i) => {
    const x = M + i * (kw + 8);
    // Las tarjetas claras (fondo blanco) llevan borde y texto azul para seguir siendo legibles.
    page.drawRectangle({ x, y: H - y - kh, width: kw, height: kh, color: k.bg, borderColor: k.claro ? BORDER : undefined, borderWidth: k.claro ? 1 : 0 });
    page.drawText(k.label, { x: x + 9, y: H - y - 14, size: 6.3, font: bold, color: k.claro ? MUTED : rgb(1, 1, 1), opacity: k.claro ? 1 : 0.85 });
    page.drawText(k.valor, { x: x + 9, y: H - y - 30, size: 12, font: bold, color: k.claro ? NAVY : WHITE });
    k.notas.forEach((nt, j) => page.drawText(fit(nt, 6.2, kw - 16), { x: x + 9, y: H - y - 42 - j * 8, size: 6.2, font, color: k.claro ? MUTED : rgb(1, 1, 1), opacity: k.claro ? 1 : 0.8 }));
  });
  y += kh;

  // ================= Estado de cuenta por socio =================
  seccion("Estado de cuenta por socio");
  {
    const colW = (cw - 12) / 2;
    const rowsH = (b: any) => {
      const x = extraBy(b.socio_id);
      // nombre + 5 filas + 3 subfilas + balance + (extra)
      return 26 + 5 * 16 + 3 * 12 + 46 + (x && n(x.liquidacion_extraordinaria) > 0 ? 42 : 0) + 14;
    };
    const cardH = Math.max(...(balances as any[]).map(rowsH));
    need(cardH + 6);
    (balances as any[]).forEach((b, i) => {
      const v = varBy(b.socio_id);
      const x = extraBy(b.socio_id);
      const cx = M + i * (colW + 12);
      const top = y;
      page.drawRectangle({ x: cx, y: H - top - cardH, width: colW, height: cardH, color: WHITE, borderColor: BORDER, borderWidth: 1 });
      page.drawRectangle({ x: cx, y: H - top - 3, width: colW, height: 3, color: i === 0 ? OCEAN : GREEN });
      let yy = top + 22;
      page.drawText(fit(b.nombre, 11, colW - 24, bold), { x: cx + 12, y: H - yy, size: 11, font: bold, color: TEXT });
      yy += 18;
      const fila = (desc: string, val: string, c: any, descBold = false) => {
        page.drawText(desc, { x: cx + 12, y: H - yy, size: 8, font: descBold ? bold : font, color: descBold ? TEXT : MUTED });
        TR(val, cx + colW - 12, H - yy, 8.5, font, c);
        page.drawLine({ start: { x: cx + 12, y: H - yy - 5 }, end: { x: cx + colW - 12, y: H - yy - 5 }, thickness: 0.4, color: BG2 });
        yy += 16;
      };
      fila("Saldo de inicio", `+ ${mxn(n(b.saldo_inicio))}`, GREEN);
      fila("Aportaciones este mes", `+ ${mxn(n(b.aportaciones))}`, GREEN);
      fila("Gastos generales (50%)", `- ${mxn(n(b.gastos_generales))}`, RED);
      fila("Gastos variables", `- ${mxn(n(b.gastos_variables))}`, RED, true);
      {
        // Los tres renglones se muestran siempre (aunque vayan en cero) para que las tarjetas sean simétricas.
        const sub = (desc: string, val: string) => {
          page.drawText(`•  ${desc}`, { x: cx + 20, y: H - yy, size: 7.2, font, color: MUTED });
          TR(val, cx + colW - 12, H - yy, 7.2, font, MUTED);
          yy += 12;
        };
        sub(`Combustible · ${v?.num_viajes ?? 0} viajes`, `- ${mxn(n(v?.combustible))}`);
        sub("Marinero", `- ${mxn(n(v?.marinero))}`);
        sub("Consumibles", `- ${mxn(n(v?.consumibles))}`);
        yy += 4;
      }
      fila("Utilidad renta", `${n(b.utilidad_renta) > 0 ? "+ " : ""}${mxn(n(b.utilidad_renta))}`, n(b.utilidad_renta) > 0 ? GREEN : MUTED);
      // Balance operativo
      yy += 2;
      page.drawLine({ start: { x: cx + 12, y: H - yy + 8 }, end: { x: cx + colW - 12, y: H - yy + 8 }, thickness: 1.5, color: BG2 });
      page.drawText("BALANCE OPERATIVO", { x: cx + 12, y: H - yy - 4, size: 7, font: bold, color: MUTED });
      page.drawText("Extraordinarios se liquidan por separado", { x: cx + 12, y: H - yy - 13, size: 6.2, font, color: MUTED });
      TR(mxn(n(b.balance_operativo)), cx + colW - 12, H - yy - 10, 15, bold, GREEN);
      yy += 30;
      if (x && n(x.liquidacion_extraordinaria) > 0) {
        page.drawRectangle({ x: cx + 12, y: H - yy - 32, width: colW - 24, height: 32, color: CREMA, borderColor: CREMA_B, borderWidth: 1 });
        page.drawText("PAGO EXTRAORDINARIO", { x: cx + 20, y: H - yy - 12, size: 6.8, font: bold, color: AMBER });
        page.drawText("Liquidación separada", { x: cx + 20, y: H - yy - 21, size: 6, font, color: MUTED });
        TR(mxn(n(x.liquidacion_extraordinaria)), cx + colW - 20, H - yy - 20, 12.5, bold, AMBER);
      }
    });
    y += cardH + 4;
  }

  // ================= Gastos operativos =================
  seccion("Gastos operativos del mes");
  tabla(
    [
      { titulo: "#", w: 22, get: (_r, i) => String(i + 1) },
      { titulo: "FECHA", w: 42, get: (r) => cortaFecha(String(r.fecha instanceof Date ? r.fecha.toISOString() : r.fecha)) },
      { titulo: "PROVEEDOR", w: 112, get: (r) => r.proveedor || "—" },
      { titulo: "CONCEPTO", w: cw - 22 - 42 - 112 - 76 - 86, get: (r) => r.concepto || "—" },
      { titulo: "CARGO", w: 76, alinear: "r", get: (r) => mxn(n(r.monto_mxn)) },
      { titulo: "COMPROBANTE", w: 86, alinear: "c", get: (r) => r.comprobante_folio || "—" },
    ],
    operativos,
    [
      ["", "", "", "SUBTOTAL OPERATIVO", mxn(kpis.opTotal), ""],
      ["", "", "", "50% por socio", mxn(kpis.opMitad), ""],
    ]
  );

  // ================= Insumos =================
  if ((insumos as any[]).length) {
    seccion("Insumos de viajes");
    tabla(
      [
        { titulo: "#", w: 22, get: (_r, i) => String(i + 1) },
        { titulo: "FECHA", w: 42, get: (r) => cortaFecha(String(r.fecha instanceof Date ? r.fecha.toISOString() : r.fecha)) },
        { titulo: "VIAJE", w: 118, get: (r) => (r.viaje_id ? `${r.viaje_cliente} · ${cortaFecha(String(r.viaje_fecha instanceof Date ? r.viaje_fecha.toISOString() : r.viaje_fecha))}` : "—") },
        { titulo: "PROVEEDOR", w: 88, get: (r) => r.proveedor || "—" },
        { titulo: "CONCEPTO", w: cw - 22 - 42 - 118 - 88 - 72 - 78, get: (r) => r.concepto || "—" },
        { titulo: "CARGO", w: 72, alinear: "r", get: (r) => mxn(n(r.monto_mxn)) },
        { titulo: "COMPROBANTE", w: 78, alinear: "c", get: (r) => r.comprobante_folio || "—" },
      ],
      insumos,
      [["", "", "", "", "TOTAL INSUMOS", mxn(kpis.insumosTotal), ""]]
    );
  }

  // ================= Resumen variable por socio =================
  seccion("Resumen de gastos variables por socio");
  tabla(
    [
      { titulo: "SOCIO", w: cw - 5 * 76, get: (b) => b.nombre },
      { titulo: "VIAJES", w: 76, alinear: "c", get: (b) => String(varBy(b.socio_id)?.num_viajes ?? 0) },
      { titulo: "COMBUSTIBLE", w: 76, alinear: "r", get: (b) => mxn(n(varBy(b.socio_id)?.combustible)) },
      { titulo: "MARINERO", w: 76, alinear: "r", get: (b) => mxn(n(varBy(b.socio_id)?.marinero)) },
      { titulo: "CONSUMIBLES", w: 76, alinear: "r", get: (b) => mxn(n(varBy(b.socio_id)?.consumibles)) },
      { titulo: "TOTAL", w: 76, alinear: "r", get: (b) => mxn(n(varBy(b.socio_id)?.total_variable)) },
    ],
    balances,
    [["TOTAL", "", mxn(kpis.combTotal), mxn(kpis.marTotal), mxn(kpis.consTotal), mxn(kpis.varTotal)]]
  );

  // ================= Viajes: detalle por salida =================
  if ((viajes as any[]).length) {
    seccion("Reporte de viajes — detalle por salida");
    const colW = (cw - 12) / 2;
    const cardAlto = (v: any) => 26 + Math.ceil((v.horometros?.length || 0) / 2) * 26 + 3 * 15 + 22 + 10;
    for (let i = 0; i < (viajes as any[]).length; i += 2) {
      const par = (viajes as any[]).slice(i, i + 2);
      const hMax = Math.max(...par.map(cardAlto));
      need(hMax + 8);
      par.forEach((v, k) => {
        const cx = M + k * (colW + 12);
        const top = y;
        page.drawRectangle({ x: cx, y: H - top - hMax, width: colW, height: hMax, color: WHITE, borderColor: BORDER, borderWidth: 1 });
        // Encabezado de color por socio
        const hc = v.socio_id === firstSocio ? OCEAN : GREEN;
        page.drawRectangle({ x: cx, y: H - top - 26, width: colW, height: 26, color: hc });
        page.drawText(`${cortaFecha(String(v.fecha instanceof Date ? v.fecha.toISOString() : v.fecha))} / ${periodo.anio}`, { x: cx + 10, y: H - top - 11, size: 8, font: bold, color: WHITE });
        page.drawText(fit(String(v.cliente ?? v.socio ?? ""), 8.5, colW - 110, bold), { x: cx + 10, y: H - top - 21, size: 8.5, font: bold, color: WHITE });
        const badge = `${v.duracion_horas ? `${nf(v.duracion_horas, 0)} Hrs` : "—"} · ${v.num_personas ?? "NA"} pers.`;
        TR(badge, cx + colW - 10, H - top - 16, 7, font, WHITE);
        let yy = top + 38;
        // Horómetros en dos columnas
        (v.horometros || []).forEach((h: any, j: number) => {
          const hx = cx + 10 + (j % 2) * ((colW - 20) / 2);
          page.drawText(String(h.etiqueta || "").toUpperCase(), { x: hx, y: H - yy, size: 6, font: bold, color: MUTED });
          page.drawText(`${nf(h.lectura_inicio, 2)} › ${nf(h.lectura_fin, 2)}`, { x: hx, y: H - yy - 9, size: 8, font, color: TEXT });
          if (j % 2 === 1) yy += 26;
        });
        if ((v.horometros || []).length % 2 === 1) yy += 26;
        const costo = (lbl: string, sub: string | null, val: string) => {
          page.drawText(lbl, { x: cx + 10, y: H - yy, size: 7.5, font, color: TEXT });
          if (sub) page.drawText(fit(sub, 6.2, colW - 120), { x: cx + 10 + font.widthOfTextAtSize(lbl, 7.5) + 5, y: H - yy, size: 6.2, font, color: MUTED });
          TR(val, cx + colW - 10, H - yy, 8, font, TEXT);
          yy += 15;
        };
        costo("Combustible", `${nf(v.litros)} Lts × $${nf(v.precio_litro)}`, mxn(n(v.costo_combustible)));
        costo("Marinero", v.marinero || null, mxn(n(v.costo_marinero)));
        costo("Consumibles", v.consumibles_comprobante || null, n(v.costo_consumibles) > 0 ? mxn(n(v.costo_consumibles)) : "—");
        page.drawLine({ start: { x: cx + 10, y: H - yy + 8 }, end: { x: cx + colW - 10, y: H - yy + 8 }, thickness: 1, color: BG2 });
        page.drawText("Total viaje", { x: cx + 10, y: H - yy - 2, size: 8, font: bold, color: TEXT });
        TR(mxn(n(v.total)), cx + colW - 10, H - yy - 2, 9.5, bold, NAVY);
      });
      y += hMax + 8;
    }
  }

  // ================= Extraordinarios por socio =================
  const hayExtra = (extraCuenta as any[]).some((c) => c.saldoAnterior !== 0 || c.cargoMes !== 0 || c.abonosMes !== 0 || c.saldoPendiente !== 0);
  if (hayExtra) {
    seccion("Estado de extraordinarios por socio", AMBER);
    const colW = (cw - 12) / 2;
    const cardH = 26 + 3 * 16 + 40 + 12;
    need(cardH + 6);
    (extraCuenta as any[]).forEach((c, i) => {
      const cx = M + i * (colW + 12);
      const top = y;
      page.drawRectangle({ x: cx, y: H - top - cardH, width: colW, height: cardH, color: WHITE, borderColor: BORDER, borderWidth: 1 });
      page.drawRectangle({ x: cx, y: H - top - 3, width: colW, height: 3, color: AMBER });
      let yy = top + 22;
      page.drawText(fit(c.nombre, 11, colW - 24, bold), { x: cx + 12, y: H - yy, size: 11, font: bold, color: TEXT });
      yy += 18;
      const fila = (desc: string, val: string, col: any) => {
        page.drawText(desc, { x: cx + 12, y: H - yy, size: 8, font, color: MUTED });
        TR(val, cx + colW - 12, H - yy, 8.5, font, col);
        page.drawLine({ start: { x: cx + 12, y: H - yy - 5 }, end: { x: cx + colW - 12, y: H - yy - 5 }, thickness: 0.4, color: BG2 });
        yy += 16;
      };
      fila("Saldo anterior pendiente", mxn(n(c.saldoAnterior)), RED);
      fila("Cargo del mes (50%)", `+ ${mxn(n(c.cargoMes))}`, RED);
      fila("Abonos del mes", `${n(c.abonosMes) > 0 ? "- " : ""}${mxn(n(c.abonosMes))}`, n(c.abonosMes) > 0 ? GREEN : MUTED);
      page.drawLine({ start: { x: cx + 12, y: H - yy + 8 }, end: { x: cx + colW - 12, y: H - yy + 8 }, thickness: 1.5, color: BG2 });
      page.drawText("SALDO PENDIENTE", { x: cx + 12, y: H - yy - 4, size: 7, font: bold, color: MUTED });
      page.drawText("Gastos extraordinarios", { x: cx + 12, y: H - yy - 13, size: 6.2, font, color: MUTED });
      TR(mxn(n(c.saldoPendiente)), cx + colW - 12, H - yy - 10, 15, bold, n(c.saldoPendiente) > 0 ? RED : GREEN);
    });
    y += cardH + 4;
  }

  // ================= Gastos extraordinarios =================
  if ((extraordinarios as any[]).length) {
    seccion("Gastos extraordinarios", AMBER);
    tabla(
      [
        { titulo: "#", w: 34, get: (_r, i) => `E-${i + 1}` },
        { titulo: "FECHA", w: 42, get: (r) => cortaFecha(String(r.fecha instanceof Date ? r.fecha.toISOString() : r.fecha)) },
        { titulo: "PROVEEDOR", w: 88, get: (r) => r.proveedor || "—" },
        { titulo: "CONCEPTO", w: cw - 34 - 42 - 88 - 50 - 76 - 50 - 56, get: (r) => r.concepto || "—" },
        { titulo: "USD", w: 50, alinear: "r", get: (r) => (r.moneda === "USD" ? `$${nf(r.monto_original)}` : "—") },
        { titulo: "MXN", w: 76, alinear: "r", get: (r) => mxn(n(r.monto_mxn)) },
        { titulo: "PAGADO", w: 50, alinear: "c", get: (r) => (r.liquidado ? "Pagado" : "Pendiente"), color: (r) => (r.liquidado ? GREEN : AMBER) },
        { titulo: "COMPR.", w: 56, alinear: "c", get: (r) => r.comprobante_folio || "—" },
      ],
      extraordinarios,
      [
        ["", "", "", "Total extraordinarios", "", mxn(kpis.extraTotal), "", ""],
        ["", "", "", "50% por socio", "", mxn(kpis.extraMitad), "", ""],
      ]
    );
  }

  // ================= Pie =================
  need(28);
  y += 14;
  const pie = `${rs} · Embarcación ${emb} · ${periodoTxt}`;
  page.drawText(pie, { x: (W - font.widthOfTextAtSize(pie, 8)) / 2, y: H - y - 8, size: 8, font, color: MUTED });

  return await doc.save();
}
