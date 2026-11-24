// Captura las mismas pantallas en la app original (Next.js) y en la versión PHP, con los mismos datos.
// Uso: NEXT_URL=http://127.0.0.1:3000 PHP_URL=http://127.0.0.1:8099 SALIDA=/ruta/capturas \
//      PRUEBA_EMAIL=... PRUEBA_CLAVE=... node pruebas/paridad_visual.mjs
// Luego: python3 pruebas/comparar_capturas.py /ruta/capturas
import { createRequire } from 'node:module';
import fs from 'node:fs';
const { chromium } = createRequire(import.meta.url)('playwright');

const apps = { next: process.env.NEXT_URL, php: process.env.PHP_URL };
const salida = process.env.SALIDA || './capturas';
const email = process.env.PRUEBA_EMAIL, clave = process.env.PRUEBA_CLAVE;
fs.mkdirSync(salida, { recursive: true });

const vistas = { escritorio: { width: 1366, height: 900 }, movil: { width: 390, height: 844 } };
const paginas = [
  ['dashboard', '/'], ['dashboard_periodo4', '/?periodo=4'], ['periodos', '/periodos'],
  ['calendario_ago', '/calendario?y=2026&m=8'], ['calendario_sep', '/calendario?y=2026&m=9'],
  ['usuarios', '/usuarios'], ['auditoria', '/auditoria'], ['caja_chica', '/caja-chica'],
];
// Pestañas de Capturar (administrador) y pantallas de Movimientos.
const pestanas = ['Viaje', 'Gasto operativo', 'Insumos', 'Aportación', 'Extraordinario', 'Abono extraord.', 'Renta'];

const navegador = await chromium.launch();
for (const [vista, tam] of Object.entries(vistas)) {
  for (const [app, base] of Object.entries(apps)) {
    // Sin sesión: pantalla de login.
    let ctx = await navegador.newContext({ viewport: tam });
    let pag = await ctx.newPage();
    await pag.goto(base + '/login', { waitUntil: 'networkidle' });
    await pag.screenshot({ path: `${salida}/${app}__login__${vista}.png`, fullPage: true });
    await ctx.close();

    // Con sesión de administrador.
    ctx = await navegador.newContext({ viewport: tam });
    const r = await ctx.request.post(base + '/api/login', { data: { email, password: clave } });
    if (!(await r.json()).ok) throw new Error(`login falló en ${app}`);
    pag = await ctx.newPage();
    for (const [nombre, ruta] of paginas) {
      await pag.goto(base + ruta, { waitUntil: 'networkidle' });
      await pag.screenshot({ path: `${salida}/${app}__${nombre}__${vista}.png`, fullPage: true });
    }
    for (const t of pestanas) {
      await pag.goto(base + '/capturar', { waitUntil: 'networkidle' });
      await pag.click(`button.tab >> text="${t}"`);
      await pag.mouse.move(0, 0); // sin cursor encima y sin transición de color a medias
      await pag.waitForTimeout(500);
      await pag.screenshot({ path: `${salida}/${app}__capturar_${t.replace(/\W+/g, '')}__${vista}.png`, fullPage: true });
    }
    await pag.goto(base + '/movimientos', { waitUntil: 'networkidle' });
    await pag.screenshot({ path: `${salida}/${app}__movimientos__${vista}.png`, fullPage: true });
    // Calendario con la ventana de reserva abierta y caja chica filtrada / con ventana de movimiento.
    await pag.goto(base + '/calendario?y=2026&m=8', { waitUntil: 'networkidle' });
    await pag.locator('.cal-chip, .ical-item').first().click().catch(() => {});
    if (vista === 'movil') { await pag.locator('.ical-day:not(.empty)').nth(6).click(); await pag.locator('.ical-item').first().click(); }
    await pag.waitForTimeout(400);
    await pag.screenshot({ path: `${salida}/${app}__calendario_modal__${vista}.png` });
    await pag.goto(base + '/caja-chica', { waitUntil: 'networkidle' });
    await pag.locator('.cc-pill').nth(1).click();
    await pag.mouse.move(0, 0); await pag.waitForTimeout(400);
    await pag.screenshot({ path: `${salida}/${app}__caja_chica_filtrada__${vista}.png`, fullPage: true });
    await pag.getByRole('button', { name: '+ Movimiento' }).click();
    await pag.waitForTimeout(400);
    await pag.screenshot({ path: `${salida}/${app}__caja_chica_modal__${vista}.png` });
    await pag.goto(base + '/', { waitUntil: 'networkidle' });
    if (vista === 'escritorio') {
      await pag.click('text=Administración');
      await pag.screenshot({ path: `${salida}/${app}__menu_admin__${vista}.png` });
    } else {
      await pag.click('.topbar-burger');
      await pag.waitForTimeout(500);
      await pag.screenshot({ path: `${salida}/${app}__menu_lateral__${vista}.png` });
    }
    await ctx.close();

    // Capitán: formulario simplificado de viaje.
    if (process.env.PRUEBA_CAPITAN) {
      ctx = await navegador.newContext({ viewport: tam });
      const rc = await ctx.request.post(base + '/api/login', { data: { email: process.env.PRUEBA_CAPITAN, password: clave } });
      if (!(await rc.json()).ok) throw new Error(`login de capitán falló en ${app}`);
      pag = await ctx.newPage();
      await pag.goto(base + '/capturar', { waitUntil: 'networkidle' });
      await pag.screenshot({ path: `${salida}/${app}__capitan_capturar__${vista}.png`, fullPage: true });
      await pag.selectOption('select[name=quien], .cap-form select >> nth=0', { index: 0 }).catch(() => {});
      await ctx.close();
    }
  }
}
await navegador.close();
console.log('capturas en', salida);
