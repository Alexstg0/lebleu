// Prueba de interfaz de la versión PHP con un navegador real (Playwright).
// Uso: PHP_URL=http://127.0.0.1:8099 PRUEBA_EMAIL=... PRUEBA_CLAVE=... node pruebas/interfaz.mjs
import { createRequire } from 'node:module';
const { chromium } = createRequire(import.meta.url)('playwright');

const base = process.env.PHP_URL, email = process.env.PRUEBA_EMAIL, clave = process.env.PRUEBA_CLAVE;
let fallos = 0;
const afirmar = (nombre, cond) => { console.log((cond ? 'ok      ' : 'FALLA   ') + nombre); if (!cond) fallos++; };

const nav = await chromium.launch();
const pag = await (await nav.newContext({ viewport: { width: 1366, height: 900 } })).newPage();
const errores = [];
pag.on('pageerror', (e) => errores.push(String(e)));
// El 401 del intento con clave incorrecta es esperado: Chromium lo registra como error de recurso.
pag.on('console', (m) => { if (m.type() === 'error' && !m.text().includes('status of 401')) errores.push(m.text()); });

await pag.goto(base + '/');
afirmar('sin sesión, "/" manda a /login', new URL(pag.url()).pathname === '/login');

await pag.fill('input[name=email]', email);
await pag.fill('input[name=password]', 'incorrecta');
await pag.click('.auth-submit');
await pag.waitForSelector('[data-error]:not([hidden])');
afirmar('clave incorrecta muestra el error', (await pag.textContent('[data-error]')).includes('incorrectos'));

await pag.click('[data-ver-clave]');
afirmar('el ojo muestra la contraseña', (await pag.getAttribute('input[name=password]', 'type')) === 'text');

await pag.fill('input[name=password]', clave);
await Promise.all([pag.waitForURL(base + '/'), pag.click('.auth-submit')]);
afirmar('login correcto entra al estado de cuenta', (await pag.textContent('h1')).includes('Estado de cuenta'));

const antes = await pag.textContent('h1');
await pag.selectOption('.topbar-nav [data-periodo-select]', '4');
await pag.waitForURL('**/?periodo=4');
afirmar('el selector de periodo cambia de periodo', (await pag.textContent('h1')) !== antes && (await pag.textContent('h1')).includes('Julio'));

await pag.click('tr.row-pdf >> nth=0');
await pag.waitForSelector('.adj-viewer iframe');
afirmar('clic en una fila abre el visor de PDF', (await pag.getAttribute('.adj-viewer iframe', 'src')).startsWith('/api/adjuntos/'));
await pag.click('.adj-viewer button');
afirmar('"Cerrar" cierra el visor', (await pag.locator('.adj-viewer').count()) === 0);

await pag.goto(base + '/?periodo=1');
const toggle = pag.locator('[data-pagado-id]').first();
if (await toggle.count()) {
  const t0 = (await toggle.textContent()).trim();
  await Promise.all([pag.waitForNavigation(), toggle.click()]);
  const t1 = (await pag.locator('[data-pagado-id]').first().textContent()).trim();
  afirmar(`el interruptor cambia Pagado/Pendiente (${t0} → ${t1})`, t0 !== t1);
  await Promise.all([pag.waitForNavigation(), pag.locator('[data-pagado-id]').first().click()]);
  afirmar('y se restaura al volver a pulsar', (await pag.locator('[data-pagado-id]').first().textContent()).trim() === t0);
}

await pag.click('text=Administración');
await Promise.all([pag.waitForURL('**/periodos'), pag.click('.nav-menu >> text=Periodos')]);
afirmar('menú Administración → Periodos', (await pag.textContent('h1')) === 'Periodos');
afirmar('la pantalla de Periodos lista los periodos', (await pag.locator('.periodo-row').count()) >= 4);

await pag.click('.nav-user');
await Promise.all([pag.waitForURL('**/login'), pag.click('[data-salir] >> visible=true')]);
afirmar('cerrar sesión regresa a /login', true);
await pag.goto(base + '/');
afirmar('tras salir, la sesión ya no sirve', new URL(pag.url()).pathname === '/login');

afirmar('sin errores de JavaScript en consola' + (errores.length ? ': ' + errores[0] : ''), errores.length === 0);
await nav.close();
console.log(fallos ? `\n${fallos} prueba(s) fallaron.` : '\nInterfaz OK.');
process.exit(fallos ? 1 : 0);
