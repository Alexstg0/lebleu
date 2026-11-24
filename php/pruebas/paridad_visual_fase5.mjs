// Captura Bitácora, Reportes y Análisis (y la ventana de editar viaje) en Next y PHP. Mismas variables que paridad_visual.mjs.
import { createRequire } from 'node:module';
import fs from 'node:fs';
const { chromium } = createRequire(import.meta.url)('playwright');
const apps = { next: process.env.NEXT_URL, php: process.env.PHP_URL };
const salida = process.env.SALIDA || './capturas5';
fs.mkdirSync(salida, { recursive: true });
const vistas = { escritorio: { width: 1366, height: 900 }, movil: { width: 390, height: 844 } };
const paginas = [['bitacora', '/bitacora'], ['bitacora_ago', '/bitacora?y=2026&m=8'], ['reportes', '/reportes'], ['reportes_p4', '/reportes?periodo=4'], ['analisis', '/analisis']];
const navegador = await chromium.launch();
for (const [vista, tam] of Object.entries(vistas)) {
  for (const [app, base] of Object.entries(apps)) {
    for (const [quien, email] of [['admin', process.env.PRUEBA_EMAIL], ['capitan', process.env.PRUEBA_CAPITAN]]) {
      const ctx = await navegador.newContext({ viewport: tam });
      const r = await ctx.request.post(base + '/api/login', { data: { email, password: process.env.PRUEBA_CLAVE } });
      if (!(await r.json()).ok) throw new Error(`login ${quien} falló en ${app}`);
      const pag = await ctx.newPage();
      for (const [nombre, ruta] of paginas) {
        if (quien === 'capitan' && !ruta.startsWith('/bitacora')) continue;
        await pag.goto(base + ruta, { waitUntil: 'networkidle' });
        await pag.mouse.move(0, 0); await pag.waitForTimeout(300);
        await pag.screenshot({ path: `${salida}/${app}__${nombre}_${quien}__${vista}.png`, fullPage: true });
      }
      if (quien === 'admin') {
        await pag.goto(base + '/bitacora?y=2026&m=8', { waitUntil: 'networkidle' });
        await pag.locator('button.bita-edit').first().click();
        await pag.waitForTimeout(600); await pag.mouse.move(0, 0);
        await pag.screenshot({ path: `${salida}/${app}__bitacora_editar__${vista}.png` });
        await pag.goto(base + '/reportes', { waitUntil: 'networkidle' });
        await pag.locator('.rep-card button').first().click();
        await pag.waitForTimeout(600); await pag.mouse.move(0, 0);
        await pag.screenshot({ path: `${salida}/${app}__reportes_visor__${vista}.png` });
      }
      await ctx.close();
    }
  }
}
await navegador.close();
