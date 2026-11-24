// Interfaz de la fase 5 con un navegador real: Bitácora, Reportes (visor de PDF), recibos en Movimientos,
// reporte de caja chica y archivo al cerrar un periodo. MUTA DATOS: usar la copia desechable (interfaz_captura.sh).
import { createRequire } from 'node:module';
const { chromium } = createRequire(import.meta.url)('playwright');

const base = process.env.PHP_URL, clave = process.env.PRUEBA_CLAVE;
let fallos = 0;
const afirmar = (nombre, cond, extra = '') => { console.log((cond ? 'ok      ' : 'FALLA   ') + nombre + (!cond && extra ? `  (${extra})` : '')); if (!cond) fallos++; };

const nav = await chromium.launch();
async function entrar(email, viewport = { width: 1366, height: 900 }) {
  const ctx = await nav.newContext({ viewport });
  const r = await ctx.request.post(base + '/api/login', { data: { email, password: clave } });
  if (!(await r.json()).ok) throw new Error('login falló: ' + email);
  const pag = await ctx.newPage();
  pag.errores = []; pag.dialogos = [];
  pag.on('pageerror', (e) => pag.errores.push(String(e)));
  pag.on('dialog', async (d) => { pag.dialogos.push(d.message()); await d.accept(); });
  pag.ctx = ctx;
  return pag;
}
const esPdf = async (pag, url) => { const r = await pag.ctx.request.get(base + url); return r.status() === 200 && (r.headers()['content-type'] || '').startsWith('application/pdf') && (await r.body()).subarray(0, 5).toString() === '%PDF-'; };

// =============================== Bitácora (administrador)
const pag = await entrar(process.env.PRUEBA_EMAIL);
await pag.goto(base + '/bitacora?y=2026&m=8');
afirmar('la bitácora muestra el mes pedido con sus viajes', (await pag.locator('.bita-table tbody tr').count()) === 9 && (await pag.textContent('.hint')).includes('9 viajes en Agosto 2026'));
afirmar('el filtro de mes lista los meses con viajes', (await pag.locator('select.bita-filter option').count()) >= 3);
const otroMes = await pag.locator('select.bita-filter option:not(:checked)').first().getAttribute('value');
await Promise.all([pag.waitForURL((u) => !u.search.includes('y=2026&m=8')), pag.selectOption('select.bita-filter', otroMes)]);
await pag.waitForLoadState('networkidle');
afirmar('cambiar el mes recarga la bitácora de ese mes', pag.url().includes('/bitacora?y=') && !(await pag.textContent('.hint')).includes('Agosto 2026'), pag.url() + ' | ' + (await pag.textContent('.hint, .empty')));
await pag.goto(base + '/bitacora?y=2026&m=8');
await pag.locator('button.bita-edit').first().click();
await pag.waitForSelector('.modal form[data-form]');
afirmar('"Editar" abre la ventana con el formulario del viaje cargado', (await pag.textContent('.modal h2')) === 'Editar viaje' && (await pag.inputValue('.modal input[name=fecha]')) === '2026-08-01');
await pag.click('.modal [data-cancelar]');
afirmar('"Cancelar" cierra la ventana', await pag.locator('.modal').count() === 0);
await pag.locator('button.bita-edit').nth(1).click();
await pag.waitForSelector('.modal form[data-form]');
const personas = await pag.inputValue('.modal input[name=num_personas]');
await pag.fill('.modal input[name=num_personas]', '9');
await Promise.all([pag.waitForNavigation(), pag.click('.modal [data-boton]')]);
afirmar('guardar la edición actualiza la fila (personas = 9)', (await pag.locator('.bita-table tbody tr').nth(1).locator('td[data-label=Personas]').textContent()) === '9', `antes ${personas}`);
const filas = await pag.locator('.bita-table tbody tr').count();
await pag.locator('button.bita-del').last().click();
await pag.waitForLoadState('networkidle');
afirmar('"Eliminar" pide confirmación y quita el viaje', pag.dialogos.some((m) => m.includes('¿Eliminar el viaje de')) && (await pag.locator('.bita-table tbody tr').count()) === filas - 1);
afirmar('la bitácora no produjo errores de JavaScript', pag.errores.length === 0, pag.errores.join(' | '));

// =============================== Bitácora (capitán): solo lectura
const cap = await entrar(process.env.PRUEBA_CAPITAN);
await cap.goto(base + '/bitacora?y=2026&m=8');
afirmar('el capitán ve la bitácora sin botones de editar ni borrar', await cap.locator('.bita-table tbody tr').count() > 0 && await cap.locator('button.bita-edit, button.bita-del').count() === 0);
afirmar('el capitán no puede abrir el formulario de edición', (await cap.ctx.request.get(base + '/movimientos/formulario?k=vi:1')).status() === 403);
afirmar('el capitán no puede abrir Reportes ni Análisis (lo redirige a su inicio)', await (async () => { await cap.goto(base + '/reportes'); const a = new URL(cap.url()).pathname; await cap.goto(base + '/analisis'); return a !== '/reportes' && new URL(cap.url()).pathname !== '/analisis'; })());

// =============================== Reportes (administrador)
await pag.goto(base + '/reportes');
afirmar('Reportes muestra las 4 tarjetas', await pag.locator('.rep-card').count() === 4);
const periodoOpt = await pag.locator('.rep-periodo select option').count();
await Promise.all([pag.waitForURL(/\/reportes\?periodo=\d+/), pag.selectOption('.rep-periodo select', { index: 1 })]);
await pag.waitForLoadState('networkidle');
afirmar('cambiar el periodo recarga Reportes con ese periodo', pag.url().includes('/reportes?periodo=') && periodoOpt >= 2);
const periodoId = new URL(pag.url()).searchParams.get('periodo');
await pag.locator('.rep-card:has(h3:text-is("Reporte de marineros")) button').click();
await pag.waitForSelector('.adj-viewer iframe');
afirmar('"Ver" abre el visor con el PDF del reporte', (await pag.getAttribute('.adj-viewer iframe', 'src')) === `/api/reportes/marineros?periodo=${periodoId}` && (await pag.textContent('.adj-viewer-head span')).includes('Reporte de marineros'));
await pag.click('.adj-viewer-head button');
afirmar('"Cerrar" cierra el visor', await pag.locator('.adj-viewer').count() === 0);
for (const r of ['soportes', 'marineros', 'completo']) afirmar(`el reporte ${r} descarga un PDF válido`, await esPdf(pag, `/api/reportes/${r}?periodo=${periodoId}`));
afirmar('la tarjeta "Estado de cuenta" abre el reporte del periodo', (await pag.getAttribute('.rep-card >> text=Abrir', 'href')) === `/?periodo=${periodoId}`);

// =============================== Análisis
await pag.goto(base + '/analisis');
afirmar('Análisis muestra 4 tarjetas de cifras y 3 gráficas', await pag.locator('.kpi-card').count() === 4 && await pag.locator('.chart-card svg').count() === 3);
afirmar('las gráficas dibujan barras', await pag.locator('.chart-card svg rect').count() > 10);

// =============================== Movimientos: recibos
await pag.goto(base + '/movimientos?periodo=' + periodoId);
await pag.locator('table:has(th:text-is("Recibo")) button[data-visor-pdf]').first().click();
await pag.waitForSelector('.adj-viewer iframe');
afirmar('"Ver PDF" del marinero abre el recibo del viaje', (await pag.getAttribute('.adj-viewer iframe', 'src')).startsWith('/api/viajes/recibo?id='));
await pag.click('.adj-viewer-head button');
const sinRecibo = pag.locator('button[data-generar-recibo]').first();
const gastoId = await sinRecibo.getAttribute('data-generar-recibo');
await sinRecibo.click();
await pag.waitForLoadState('networkidle');
await pag.waitForTimeout(500);
const chip = pag.locator(`[data-registro="${gastoId}"][data-columna="gasto_id"] .adj-chip.gen`);
afirmar('"Generar recibo" adjunta el recibo PDF al gasto', await chip.count() >= 1);
await chip.first().locator('.adj-name').click();
await pag.waitForSelector('.adj-viewer iframe');
afirmar('el recibo generado se abre en el visor', (await pag.getAttribute('.adj-viewer iframe', 'src')).startsWith('/api/adjuntos/'));
await pag.click('.adj-viewer-head button');

// =============================== Caja chica: reporte PDF
await pag.goto(base + '/caja-chica');
await pag.locator('[data-reporte-pdf]').click();
await pag.waitForSelector('.adj-viewer iframe');
const src = await pag.getAttribute('.adj-viewer iframe', 'src');
afirmar('"Reporte PDF" abre el reporte de caja chica con el responsable', src.startsWith('/api/reportes/caja-chica?caja=todas&responsable='));
afirmar('el reporte de caja chica es un PDF válido', await esPdf(pag, src));
await pag.click('.adj-viewer-head button');
await pag.locator('.cc-pill').nth(1).click();
await pag.locator('[data-reporte-pdf]').click();
await pag.waitForSelector('.adj-viewer iframe');
afirmar('con una caja elegida el reporte es solo de esa caja', /caja=\d+&/.test(await pag.getAttribute('.adj-viewer iframe', 'src')));
await pag.click('.adj-viewer-head button');

// =============================== Periodos: cerrar archiva el estado de cuenta
await pag.goto(base + '/periodos');
const abierto = pag.locator('[data-periodo-id]', { has: pag.locator('[data-accion-periodo="cerrar"]') }).last();
const id = await abierto.getAttribute('data-periodo-id');
await abierto.locator('[data-accion-periodo="cerrar"]').click();
await pag.waitForLoadState('networkidle');
await pag.waitForTimeout(800);
afirmar('cerrar un periodo muestra el botón "Archivo" del estado de cuenta', await pag.locator(`[data-periodo-id="${id}"] [data-visor-pdf]`).count() === 1);
await pag.locator(`[data-periodo-id="${id}"] [data-visor-pdf]`).click();
await pag.waitForSelector('.adj-viewer iframe');
afirmar('el archivo del periodo se abre y es un PDF', await esPdf(pag, await pag.getAttribute('.adj-viewer iframe', 'src')));
afirmar('Reportes/Movimientos/Periodos sin errores de JavaScript', pag.errores.length === 0, pag.errores.join(' | '));

// =============================== Arrastrar y soltar el archivo de respaldo
import fs from 'node:fs';
const pdfB64 = fs.readFileSync(process.env.PRUEBA_PDF).toString('base64');
/** Simula arrastrar un archivo sobre un elemento: dragover y luego drop, con un DataTransfer real. */
const soltar = (p, selector, nombre, tipo, b64, solo = 'drop') => p.evaluate(({ selector, nombre, tipo, b64, solo }) => {
  const el = document.querySelector(selector);
  const dt = new DataTransfer();
  dt.items.add(new File([Uint8Array.from(atob(b64), (c) => c.charCodeAt(0))], nombre, { type: tipo }));
  const ev = (t) => el.dispatchEvent(new DragEvent(t, { bubbles: true, cancelable: true, dataTransfer: dt }));
  ev('dragenter');
  const over = ev('dragover');
  const resaltado = !!document.querySelector('.soltando');
  if (solo === 'drop') ev('drop');
  return { prevenido: !over, resaltado };
}, { selector, nombre, tipo, b64, solo });

await pag.ctx.request.post(base + '/api/periodos/estado', { data: { id: Number(id), accion: 'reabrir' } }); // el periodo se cerró arriba
await pag.goto(base + '/capturar#gasto');
const g = '[data-panel="gasto"]';
let r = await soltar(pag, `${g} input[name=proveedor]`, 'ticket.pdf', 'application/pdf', pdfB64, 'sobre');
afirmar('al arrastrar un archivo sobre el formulario la zona se resalta', r.prevenido && r.resaltado);
await pag.evaluate(() => document.dispatchEvent(new DragEvent('dragleave', { bubbles: true, relatedTarget: document.body })));
afirmar('al salir del formulario se quita el resaltado', await pag.locator('.soltando').count() === 0);
await soltar(pag, `${g} input[name=proveedor]`, 'ticket.pdf', 'application/pdf', pdfB64);
afirmar('al soltar un PDF queda elegido en el campo de soporte', (await pag.evaluate((s) => document.querySelector(s + ' [data-soporte]').files[0]?.name, g)) === 'ticket.pdf');
await soltar(pag, `${g} input[name=proveedor]`, 'notas.txt', 'text/plain', btoa('hola'));
afirmar('un archivo que no es PDF o imagen se rechaza con aviso', (await pag.locator(`${g} .adj-err[data-soltar]`).textContent()).includes('Solo PDF o imagen'));
await soltar(pag, `${g} input[name=proveedor]`, 'arrastrado.pdf', 'application/pdf', pdfB64);
await pag.fill(`${g} input[name=proveedor]`, 'Proveedor Arrastre');
await pag.fill(`${g} input[name=concepto]`, 'Gasto con archivo arrastrado');
await pag.fill(`${g} input[name=monto_original]`, '50');
await pag.click(`${g} [data-boton]`);
await pag.waitForSelector(`${g} [data-mensaje]:not([hidden])`);
const msgG = await pag.textContent(`${g} [data-mensaje]`);
afirmar('guardar sube el archivo arrastrado junto con el gasto', msgG.includes('Guardado correctamente'), msgG);
await pag.goto(base + '/movimientos'); // el periodo más reciente, donde se capturó el gasto
const fila = pag.locator('tr', { hasText: 'Gasto con archivo arrastrado' });
afirmar('el gasto aparece en Movimientos con su archivo adjunto', await fila.locator('.adj-chip').count() === 1 && (await fila.locator('.adj-name').getAttribute('data-titulo')) === 'arrastrado.pdf');
await pag.evaluate(() => { [...document.querySelectorAll('tr')].find((t) => t.textContent.includes('Gasto con archivo arrastrado')).querySelector('[data-adjuntos]').id = 'caja-arrastre'; });
const sel = '#caja-arrastre';
r = await soltar(pag, sel, 'segundo.pdf', 'application/pdf', pdfB64, 'sobre');
afirmar('en Movimientos la caja de respaldos se resalta al arrastrar encima', r.prevenido && r.resaltado);
await Promise.all([pag.waitForNavigation(), soltar(pag, sel, 'segundo.pdf', 'application/pdf', pdfB64)]);
afirmar('soltar un archivo sobre el gasto lo adjunta', await pag.locator('tr', { hasText: 'Gasto con archivo arrastrado' }).locator('.adj-chip').count() === 2);
afirmar('arrastrar y soltar sin errores de JavaScript', pag.errores.length === 0, pag.errores.join(' | '));

// =============================== Calendario estático y sin agendar hacia atrás
const hoyISO = new Date().toISOString().slice(0, 10);
const fut = new Date(); fut.setDate(1); fut.setMonth(fut.getMonth() + 1);
const [yF, mF] = [fut.getFullYear(), fut.getMonth() + 1];
const diaF = (d) => `${yF}-${String(mF).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
for (let i = 0; i < 6; i++) {
  const r = await pag.ctx.request.post(base + '/api/reservas', { data: { fecha: diaF(10), hora: `${String(9 + i).padStart(2, "0")}:00`, cliente: `Cliente muy largo número ${i + 1} para probar el recorte`, num_personas: 4, notas: 'Una nota larga para probar que no deforma el calendario' } });
  if (!(await r.json()).ok) throw new Error('no se pudo crear la reserva de prueba');
}
const medidas = () => pag.evaluate(() => ({
  anchos: [...new Set([...document.querySelectorAll('.cal-head div')].map((d) => Math.round(d.getBoundingClientRect().width)))],
  altos: [...new Set([...document.querySelectorAll('.cal-cell')].map((c) => Math.round(c.getBoundingClientRect().height)))],
  celdas: [...new Set([...document.querySelectorAll('.cal-cell')].slice(0, 7).map((c) => Math.round(c.getBoundingClientRect().width)))],
}));
await pag.goto(base + '/calendario?y=2026&m=8');
let m = await medidas();
afirmar('el calendario tiene siete columnas iguales y todas las filas del mismo alto', m.anchos.length === 1 && m.celdas.length === 1 && m.altos.length === 1, JSON.stringify(m));
const antes = JSON.stringify(m);
await pag.goto(base + `/calendario?y=${yF}&m=${mF}`);
m = await medidas();
afirmar('con un día cargado de reservas la cuadrícula sigue idéntica', m.anchos.length === 1 && m.celdas.length === 1 && m.altos.length === 1 && JSON.stringify(m).split('"altos"')[1] === antes.split('"altos"')[1].replace(antes.split('"altos"')[1], JSON.stringify(m).split('"altos"')[1]), JSON.stringify(m));
const celda = pag.locator('.cal-cell', { has: pag.locator(`[data-nueva="${diaF(10)}"]`) });
afirmar('lo que no cabe se resume en "+N más"', (await celda.locator('.cal-mas').count()) === 1 && /^\+\d+ más$/.test(await celda.locator('.cal-mas').textContent()));
afirmar('el texto largo se recorta dentro de su celda', await celda.locator('.cal-chip').first().evaluate((c) => c.getBoundingClientRect().right <= c.closest('.cal-cell').getBoundingClientRect().right + 1));
await celda.locator('.cal-mas').click();
afirmar('"+N más" despliega todas las reservas del día', (await celda.evaluate((c) => c.classList.contains('abierta'))) && (await celda.locator('.cal-chip:visible').count()) === 6);
m = await medidas();
afirmar('desplegar un día no mueve la cuadrícula (las filas conservan su alto)', m.celdas.length === 1 && m.anchos.length === 1 && (await pag.evaluate(() => [...document.querySelectorAll('.cal-body')].map((b) => Math.round(b.getBoundingClientRect().height))[0])) === await pag.evaluate(() => Math.round(document.querySelector('.cal-body').getBoundingClientRect().height)));
await pag.mouse.click(5, 300);
afirmar('al pulsar fuera, el día vuelve a recogerse', !(await celda.evaluate((c) => c.classList.contains('abierta'))));
const mal = await pag.evaluate((hoy) => [...document.querySelectorAll('[data-nueva]')].map((b) => b.getAttribute('data-nueva')).filter((f) => f < hoy), hoyISO);
afirmar('ningún día tiene "+" si ya pasó', mal.length === 0, mal.join(','));
await pag.goto(base + '/calendario?y=2026&m=8');
afirmar('un mes que ya pasó no tiene ningún "+" para agendar', await pag.locator('.cal-add').count() === 0);
await pag.click('.cal-chip:not(.oculta)');
afirmar('una reserva pasada se abre pero su fecha no se puede mover', (await pag.locator('.modal input[name=fecha]').getAttribute('readonly')) !== null);
await pag.click('.modal [data-cancelar]');
const rp = await pag.ctx.request.post(base + '/api/reservas', { data: { fecha: '2026-08-20', cliente: 'Retroactiva' } });
const jp = await rp.json();
afirmar('el servidor rechaza agendar en una fecha pasada', rp.status() === 400 && !jp.ok && jp.error.includes('ya pasaron'), JSON.stringify(jp));
const movil = await entrar(process.env.PRUEBA_EMAIL, { width: 390, height: 844 });
await movil.goto(base + '/calendario?y=2026&m=8');
await movil.click('.ical-day[data-dia="7"]');
afirmar('en móvil un día pasado no ofrece "+ Reserva"', await movil.locator('.ical-add').count() === 0);
await movil.goto(base + `/calendario?y=${yF}&m=${mF}`);
await movil.click('.ical-day[data-dia="10"]');
afirmar('en móvil un día futuro sí la ofrece', await movil.locator('.ical-add').count() === 1);
afirmar('calendario sin errores de JavaScript', pag.errores.length === 0 && movil.errores.length === 0, pag.errores.join(' | '));

await nav.close();
console.log(fallos ? `\n${fallos} prueba(s) fallaron.` : '\nINTERFAZ FASE 5 OK');
process.exit(fallos ? 1 : 0);
