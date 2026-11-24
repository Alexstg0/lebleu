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

await nav.close();
console.log(fallos ? `\n${fallos} prueba(s) fallaron.` : '\nINTERFAZ FASE 5 OK');
process.exit(fallos ? 1 : 0);
