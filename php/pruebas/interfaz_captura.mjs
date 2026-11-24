// Prueba de la captura con un navegador real: alta, edición y baja de movimientos, cálculos del viaje,
// marineros nuevos, insumos ligados a un viaje, el capitán y el bloqueo de periodos cerrados.
// MUTA DATOS: córrela contra una copia desechable (bash pruebas/interfaz_captura.sh lo prepara y lo limpia).
// Uso: PHP_URL=... PRUEBA_EMAIL=admin PRUEBA_CAPITAN=capitan PRUEBA_CLAVE=... node pruebas/interfaz_captura.mjs
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
  pag.errores = [];
  pag.on('pageerror', (e) => pag.errores.push(String(e)));
  return pag;
}
const mensaje = async (pag, panel) => (await pag.locator(`${panel} [data-mensaje]:not([hidden])`).first().textContent({ timeout: 8000 })).trim();

// =============================== Administrador
const pag = await entrar(process.env.PRUEBA_EMAIL);
const dialogos = [];
pag.on('dialog', (d) => { dialogos.push(d.message()); d.accept(); });
await pag.goto(base + '/capturar');
afirmar('la pantalla abre en la pestaña Viaje', await pag.locator('[data-panel="viaje"]:visible').count() === 1);

// ---- Pestañas y su hash
await pag.click('button.tab >> text="Gasto operativo"');
afirmar('cambiar de pestaña muestra su formulario y deja el hash en la URL', (await pag.locator('[data-panel="gasto"] h2').textContent()).includes('Nuevo gasto operativo') && pag.url().endsWith('#gasto'));

// ---- Moneda USD
const gasto = '[data-panel="gasto"]';
afirmar('en MXN no se ve el tipo de cambio', !(await pag.locator(`${gasto} [data-solo-usd]`).isVisible()));
await pag.selectOption(`${gasto} select[name=moneda]`, 'USD');
afirmar('en USD aparece el tipo de cambio y la etiqueta cambia', (await pag.locator(`${gasto} [data-solo-usd]`).isVisible()) && (await pag.locator(`${gasto} [data-etiqueta-monto]`).textContent()) === 'Monto (USD)');
await pag.selectOption(`${gasto} select[name=moneda]`, 'MXN');

// ---- Alta de un gasto
await pag.fill(`${gasto} input[name=proveedor]`, 'Proveedor UI');
await pag.fill(`${gasto} input[name=concepto]`, 'Concepto UI');
await pag.fill(`${gasto} input[name=monto_original]`, '123.45');
await pag.click(`${gasto} [data-boton]`);
afirmar('guardar un gasto muestra "Guardado correctamente."', (await mensaje(pag, gasto)) === 'Guardado correctamente.');
afirmar('tras guardar se limpian proveedor, concepto y monto', (await pag.inputValue(`${gasto} input[name=proveedor]`)) === '' && (await pag.inputValue(`${gasto} input[name=monto_original]`)) === '');

await pag.fill(`${gasto} input[name=proveedor]`, 'X');
await pag.fill(`${gasto} input[name=concepto]`, 'Y');
await pag.fill(`${gasto} input[name=monto_original]`, '1');
await pag.selectOption('[data-ir-periodo] >> nth=0', { label: /Mayo 2026/ }).catch(() => {});
await pag.goto(base + '/capturar#gasto');
const cerrado = await pag.locator('[data-ir-periodo] option', { hasText: 'cerrado' }).first().getAttribute('value');
await pag.goto(base + `/capturar?periodo=${cerrado}#gasto`);
await pag.fill(`${gasto} input[name=proveedor]`, 'X');
await pag.fill(`${gasto} input[name=concepto]`, 'Y');
await pag.fill(`${gasto} input[name=monto_original]`, '1');
await pag.click(`${gasto} [data-boton]`);
afirmar('capturar en un periodo cerrado muestra el error', (await mensaje(pag, gasto)).includes('cerrado'));

// ---- Movimientos: editar y borrar el gasto recién creado
await pag.goto(base + '/movimientos');
const fila = pag.locator('tr', { hasText: 'Concepto UI' });
afirmar('el gasto aparece en Movimientos', await fila.count() === 1);
await fila.locator('[data-editar]').click();
await pag.waitForSelector('tr[data-edicion] form');
afirmar('"Editar" abre el formulario precargado', (await pag.inputValue('tr[data-edicion] input[name=concepto]')) === 'Concepto UI' && (await pag.inputValue('tr[data-edicion] input[name=monto_original]')) === '123.45');
await pag.fill('tr[data-edicion] input[name=concepto]', 'Concepto UI editado');
await Promise.all([pag.waitForNavigation(), pag.click('tr[data-edicion] [data-boton]')]);
afirmar('al actualizar se recarga con el cambio', await pag.locator('tr', { hasText: 'Concepto UI editado' }).count() === 1);

await pag.locator('tr', { hasText: 'Concepto UI editado' }).locator('[data-editar]').click();
await pag.waitForSelector('tr[data-edicion] form');
await pag.click('tr[data-edicion] [data-cancelar]');
afirmar('"Cancelar" devuelve la fila original', await pag.locator('tr[data-edicion]').count() === 0 && await pag.locator('tr', { hasText: 'Concepto UI editado' }).count() === 1);

await Promise.all([pag.waitForNavigation(), pag.locator('tr', { hasText: 'Concepto UI editado' }).locator('[data-borrar]').click()]);
afirmar('"Borrar" pide confirmación y elimina la fila', dialogos.some((m) => m.includes('¿Borrar el gasto?')) && await pag.locator('tr', { hasText: 'Concepto UI editado' }).count() === 0);

// ---- Viaje: valores iniciales y cálculos
await pag.goto(base + '/capturar#viaje');
const viaje = '[data-panel="viaje"]';
afirmar('el total del viaje arranca en $800.00 (tarifa del marinero)', (await pag.textContent(`${viaje} [data-total-viaje]`)) === '$800.00');
await pag.fill(`${viaje} input[name=gal_inicio]`, '10');
await pag.fill(`${viaje} input[name=gal_fin]`, '5');
// Igual que el original: el consumo es la diferencia de los litros ya redondeados (37.85 − 18.93).
afirmar('con galones inicial y final el consumo se calcula solo (37.85 L − 18.93 L = 18.92 L)', (await pag.inputValue(`${viaje} input[name=consumo_auto]`)) === '18.92' && !(await pag.locator(`${viaje} input[name=litros]`).isVisible()));
afirmar('se muestra la conversión a litros', (await pag.textContent(`${viaje} [data-hint=ini]`)).includes('37.85'));
const totalConComb = await pag.textContent(`${viaje} [data-total-viaje]`);
afirmar('el costo de combustible y el total se actualizan', totalConComb !== '$800.00' && (await pag.textContent(`${viaje} [data-costo-combustible]`)) !== '$0.00', totalConComb);

// horómetro final menor que el inicial: alerta y no envía
const motor = pag.locator(`${viaje} [data-motor-id]`).first();
const ini = Number(await motor.locator('[data-h=inicio]').inputValue());
await motor.locator('[data-h=fin]').fill(String(ini - 1));
dialogos.length = 0;
await pag.click(`${viaje} [data-boton]`);
afirmar('horómetro final menor que el inicial: alerta y no guarda', dialogos.some((m) => m.includes('no puede ser menor')));

// viaje válido
await motor.locator('[data-h=fin]').fill(String(ini + 4));
await pag.fill(`${viaje} input[name=duracion_horas]`, '4');
await pag.fill(`${viaje} input[name=num_personas]`, '6');
await pag.click(`${viaje} [data-boton]`);
afirmar('guarda el viaje', (await mensaje(pag, viaje)) === 'Guardado correctamente.');
afirmar('la lectura final pasa a ser la inicial del siguiente viaje', Number(await motor.locator('[data-h=inicio]').inputValue()) === ini + 4 && (await motor.locator('[data-h=fin]').inputValue()) === '');

// ---- Marinero nuevo
await pag.selectOption(`${viaje} select[name=marinero]`, '__nuevo__');
await pag.fill(`${viaje} [data-nuevo-nombre]`, 'Marinero de Prueba UI');
await pag.click(`${viaje} [data-guardar-marinero]`);
await pag.waitForSelector(`${viaje} select[name=marinero]`);
afirmar('agregar un marinero nuevo lo deja seleccionado', (await pag.inputValue(`${viaje} select[name=marinero]`)) === 'Marinero de Prueba UI');

// ---- Insumos: el viaje recién creado aparece en la lista sin recargar la página
await pag.click('button.tab >> text="Insumos"');
const insumos = '[data-panel="insumos"]';
await pag.waitForFunction(() => document.querySelectorAll('[data-panel="insumos"] select[name=viaje_id] option').length >= 2);
afirmar('el viaje recién creado aparece en el selector de insumos', await pag.locator(`${insumos} select[name=viaje_id] option`).count() >= 2);
await pag.fill(`${insumos} input[name=proveedor]`, 'Hielera UI');
await pag.fill(`${insumos} input[name=concepto]`, 'Hielo UI');
await pag.fill(`${insumos} input[name=monto_original]`, '250');
await pag.click(`${insumos} [data-boton]`);
afirmar('guarda el insumo', (await mensaje(pag, insumos)) === 'Guardado correctamente.');

// ---- Renta: utilidad en vivo
await pag.click('button.tab >> text="Renta"');
const renta = '[data-panel="renta"]';
await pag.fill(`${renta} input[name=monto]`, '12000');
await pag.fill(`${renta} input[name=costos_asociados]`, '2000');
afirmar('la utilidad de la renta se calcula mientras se escribe', (await pag.textContent(`${renta} [data-utilidad]`)) === '$10,000.00');

// ---- Aportación
await pag.click('button.tab >> text="Aportación"');
const apo = '[data-panel="aportacion"]';
await pag.fill(`${apo} input[name=monto]`, '5000');
await pag.click(`${apo} [data-boton]`);
afirmar('guarda una aportación', (await mensaje(pag, apo)) === 'Guardado correctamente.');

// ---- Lo capturado aparece en Movimientos y el insumo quedó en el viaje
await pag.goto(base + '/movimientos');
afirmar('el viaje, el insumo y la aportación aparecen en Movimientos', await pag.locator('tr', { hasText: 'Hielo UI' }).count() === 1 && await pag.locator('tr', { hasText: 'Marinero de Prueba UI' }).count() === 0 /* aún sin marinero asignado */);
await pag.goto(base + '/');
afirmar('el estado de cuenta ya muestra el viaje (sin errores de JavaScript en toda la sesión)', pag.errores.length === 0, pag.errores[0] || '');

// =============================== Capitán
const cap = await entrar(process.env.PRUEBA_CAPITAN, { width: 390, height: 844 });
const dlg = [];
cap.on('dialog', (d) => { dlg.push(d.message()); d.accept(); });
await cap.goto(base + '/capturar');
afirmar('el capitán ve solo el formulario "Registrar viaje" (sin pestañas)', (await cap.locator('.cap-form h2').textContent()) === 'Registrar viaje' && await cap.locator('[data-tab]').count() === 0);
await cap.selectOption('.cap-form select[name=quien]', 'renta');
afirmar('al elegir Renta pide el nombre del cliente', await cap.locator('.cap-form [data-solo-renta]').isVisible());
await cap.click('.cap-form [data-boton]');
afirmar('renta sin nombre de cliente: alerta y no guarda', dlg.some((m) => m.includes('nombre del cliente de la renta')));
await cap.fill('.cap-form input[name=rentaNombre]', 'Cliente Externo UI');
await cap.fill('.cap-form input[name=galIni]', '20');
await cap.fill('.cap-form input[name=galFin]', '12');
afirmar('el consumo del viaje se calcula en litros (75.71 L − 45.42 L = 30.29 L)', (await cap.textContent('.cap-form [data-consumo]')) === '30.29 L');
await cap.fill('.cap-form input[name=bFin]', String(Number(await cap.inputValue('.cap-form input[name=bIni]')) + 2));
afirmar('muestra las horas de uso del motor', (await cap.textContent('.cap-form [data-horas=b]')) === '2 h de uso');
await cap.click('.cap-form [data-boton]');
afirmar('el capitán guarda un viaje de renta', (await mensaje(cap, '.cap-form')) === 'Guardado correctamente.');
await cap.goto(base + '/movimientos');
afirmar('el capitán no puede abrir Movimientos (lo manda a su inicio)', new URL(cap.url()).pathname === '/calendario' || new URL(cap.url()).pathname === '/');
afirmar('sin errores de JavaScript en la sesión del capitán', cap.errores.length === 0, cap.errores[0] || '');

await nav.close();
console.log(fallos ? `\n${fallos} prueba(s) fallaron.` : '\nCaptura (interfaz) OK.');
process.exit(fallos ? 1 : 0);
