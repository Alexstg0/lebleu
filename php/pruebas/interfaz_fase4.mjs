// Interfaz de la fase 4 con un navegador real: calendario, caja chica, usuarios, auditoría, respaldos y recurrentes.
// MUTA DATOS: córrela contra una copia desechable (bash pruebas/interfaz_captura.sh la prepara y la limpia).
import { createRequire } from 'node:module';
const { chromium } = createRequire(import.meta.url)('playwright');

const base = process.env.PHP_URL, clave = process.env.PRUEBA_CLAVE, PDF = process.env.PRUEBA_PDF, PNG = process.env.PRUEBA_PNG;
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
  pag.on('dialog', async (d) => { pag.dialogos.push(d.message()); await (d.type() === 'prompt' ? d.accept('nuevaclave1') : d.accept()); });
  return pag;
}

// =============================== Calendario (escritorio)
const pag = await entrar(process.env.PRUEBA_EMAIL);
await pag.goto(base + '/calendario?y=2026&m=9');
afirmar('el calendario muestra el mes pedido', (await pag.textContent('.cal-title h1')) === 'Septiembre' && (await pag.textContent('.cal-title .yr')) === '2026');
afirmar('la cuadrícula tiene los 30 días de septiembre', await pag.locator('.cal-cell:not(.empty)').count() === 30);
await pag.click('[data-nueva="2026-09-20"]');
afirmar('"+" abre la ventana de nueva reserva con la fecha del día', (await pag.textContent('.modal h2')) === 'Nueva reserva' && (await pag.inputValue('.modal input[name=fecha]')) === '2026-09-20');
await pag.fill('.modal input[name=cliente]', 'Cliente Calendario UI');
await pag.fill('.modal input[name=hora]', '10:00');
await pag.fill('.modal input[name=num_personas]', '4');
await pag.selectOption('.modal select[name=socio_id]', { index: 1 });
await Promise.all([pag.waitForNavigation(), pag.click('.modal [data-guardar]')]);
const chip = pag.locator('.cal-chip', { hasText: 'Cliente Calendario UI' });
afirmar('la reserva aparece en su día con hora y personas', await chip.count() === 1 && (await chip.textContent()).includes('10:00 AM') && (await chip.textContent()).includes('4 pers'));
afirmar('la reserva de la familia 1 usa su color', (await chip.getAttribute('class')).includes('fam-acosta') || (await chip.getAttribute('class')).includes('fam-garcia'));
await chip.click();
afirmar('al pulsar una reserva se abre en modo edición', (await pag.textContent('.modal h2')) === 'Editar reserva' && (await pag.inputValue('.modal input[name=cliente]')) === 'Cliente Calendario UI');
await pag.fill('.modal input[name=notas]', 'Nota de prueba UI');
await Promise.all([pag.waitForNavigation(), pag.click('.modal [data-guardar]')]);
afirmar('la edición muestra la nota en la celda', (await pag.locator('.cal-chip', { hasText: 'Cliente Calendario UI' }).textContent()).includes('Nota de prueba UI'));
await pag.locator('.cal-chip', { hasText: 'Cliente Calendario UI' }).click();
await Promise.all([pag.waitForNavigation(), pag.click('.modal [data-borrar]')]);
afirmar('borrar pide confirmación y quita la reserva', pag.dialogos.some((m) => m.includes('¿Borrar esta reserva?')) && await pag.locator('.cal-chip', { hasText: 'Cliente Calendario UI' }).count() === 0);
await pag.click('.cal-nav a[aria-label="Mes siguiente"]');
afirmar('"Mes siguiente" navega a octubre', (await pag.textContent('.cal-title h1')) === 'Octubre');

// =============================== Calendario (móvil)
const movil = await entrar(process.env.PRUEBA_EMAIL, { width: 390, height: 844 });
await movil.goto(base + '/calendario?y=2026&m=8');
await movil.click('.ical-day[data-dia="7"]');
afirmar('en móvil, elegir un día muestra su detalle', (await movil.textContent('.ical-detail-head h3')) === '7 de Agosto' && await movil.locator('.ical-item').count() >= 1);
await movil.click('.ical-day[data-dia="4"]');
afirmar('un día sin reservas lo dice', (await movil.textContent('.ical-empty')) === 'Sin reservas este día.');
await movil.click('.ical-add');
afirmar('"+ Reserva" abre la ventana con el día elegido', (await movil.inputValue('.modal input[name=fecha]')) === '2026-08-04');
await movil.click('.modal [data-cancelar]');
afirmar('"Cancelar" cierra la ventana', await movil.locator('.modal').count() === 0);

// =============================== Caja chica
await pag.goto(base + '/caja-chica');
const pastillas = await pag.locator('.cc-pill').count();
afirmar('caja chica muestra "Todas" y una pastilla por caja', pastillas >= 2);
afirmar('vista "Todas": tarjetas Gastos / Abonos / Balance y columna de balance', (await pag.textContent('[data-et="3"]')) === 'Balance actual' && await pag.locator('th.cc-bal').isVisible());
const cajaN = (await pag.locator('.cc-pill').nth(1).getAttribute('data-filtro'));
await pag.locator('.cc-pill').nth(1).click();
afirmar('filtrar por una caja deja solo sus movimientos', await pag.locator(`tr[data-mov]:visible`).count() > 0 && await pag.locator(`tr[data-mov]:not([hidden]):not([data-caja="${cajaN}"])`).count() === 0);
afirmar('vista por caja: tarjetas Total / Abonado / Total a pagar y sin columna de balance', (await pag.textContent('[data-et="3"]')) === 'Total a pagar' && !(await pag.locator('th.cc-bal').isVisible()));
const totalVisible = await pag.evaluate(() => Array.from(document.querySelectorAll('tr[data-mov]:not([hidden])')).reduce((s, tr) => s + Number(tr.dataset.monto), 0));
afirmar('el total de la tarjeta coincide con la suma de las filas visibles', (await pag.textContent('[data-v="1"]')).replace(/[$,]/g, '') === (totalVisible / 100).toFixed(2));
await pag.fill('[data-responsable]', 'Ana Pérez');
afirmar('el responsable viaja en el enlace del reporte PDF y en el encabezado de impresión', (await pag.getAttribute('[data-reporte-pdf]', 'data-visor-pdf')).includes('responsable=Ana%20P%C3%A9rez') && (await pag.textContent('[data-responsable-impresion]')) === 'Ana Pérez');
await pag.click('.cc-pill[data-filtro="todas"]');
await pag.click('[data-nuevo-mov]');
afirmar('"+ Movimiento" abre la ventana con la última caja', (await pag.inputValue('.modal input[name=caja_numero]')) !== '');
await pag.click('.modal [data-tipo="abono"]');
afirmar('al elegir Abono se muestra el campo de abono y se oculta el de monto', await pag.locator('.modal [data-campo="abono"]').isVisible() && !(await pag.locator('.modal [data-campo="gasto"]').isVisible()));
await pag.fill('.modal input[name=abono]', '777.77');
await pag.fill('.modal input[name=concepto]', 'Abono de prueba UI');
await Promise.all([pag.waitForNavigation(), pag.click('.modal [data-guardar]')]);
const filaCaja = pag.locator('tr[data-mov]', { hasText: 'Abono de prueba UI' });
afirmar('el abono aparece en la tabla con su monto', await filaCaja.count() === 1 && (await filaCaja.textContent()).includes('$777.77'));
await filaCaja.locator('td').first().click();
afirmar('pulsar una fila abre su edición', (await pag.textContent('.modal h2')) === 'Editar movimiento');
await Promise.all([pag.waitForNavigation(), pag.click('.modal [data-borrar]')]);
afirmar('borrar el movimiento lo quita de la tabla', await pag.locator('tr[data-mov]', { hasText: 'Abono de prueba UI' }).count() === 0);

// =============================== Respaldos: subir, ver y borrar en Movimientos
await pag.goto(base + '/movimientos?periodo=5');
const filaGasto = pag.locator('table').first().locator('tbody tr').first();
const chipsAntes = await filaGasto.locator('.adj-chip').count();
await filaGasto.locator('input[type=file]').setInputFiles(PDF);
await pag.waitForLoadState('load');
await pag.waitForFunction((n) => document.querySelector('table tbody tr .adj-wrap').querySelectorAll('.adj-chip').length === n, chipsAntes + 1);
afirmar('subir un PDF agrega su chip en la fila', await filaGasto.locator('.adj-chip').count() === chipsAntes + 1);
if (PNG) {
  await pag.locator('table').first().locator('tbody tr').first().locator('input[type=file]').setInputFiles(PNG);
  await pag.waitForFunction((n) => document.querySelector('table tbody tr .adj-wrap').querySelectorAll('.adj-chip').length === n, chipsAntes + 2);
  afirmar('subir una foto PNG también agrega su chip (se guarda como PDF)', true);
}
await pag.locator('table').first().locator('tbody tr').first().locator('.adj-name').last().click();
await pag.waitForSelector('.adj-viewer iframe');
afirmar('el chip abre el visor con el archivo', (await pag.getAttribute('.adj-viewer iframe', 'src')).startsWith('/api/adjuntos/'));
await pag.click('.adj-viewer button');
for (let i = 0; i < 2 && PNG; i++) {
  await pag.locator('table').first().locator('tbody tr').first().locator('.adj-del').last().click();
  await pag.waitForLoadState('load');
}
if (!PNG) { await pag.locator('table').first().locator('tbody tr').first().locator('.adj-del').last().click(); await pag.waitForLoadState('load'); }
await pag.waitForFunction((n) => document.querySelector('table tbody tr .adj-wrap').querySelectorAll('.adj-chip').length === n, chipsAntes);
afirmar('eliminar los archivos de prueba deja la fila como estaba', await pag.locator('table').first().locator('tbody tr').first().locator('.adj-chip').count() === chipsAntes);

// =============================== Soporte al capturar un gasto
await pag.goto(base + '/capturar?periodo=5#gasto');
const g = '[data-panel="gasto"]';
await pag.fill(`${g} input[name=proveedor]`, 'Proveedor Soporte UI');
await pag.fill(`${g} input[name=concepto]`, 'Concepto Soporte UI');
await pag.fill(`${g} input[name=monto_original]`, '99');
await pag.setInputFiles(`${g} input[data-soporte]`, PDF);
await pag.click(`${g} [data-boton]`);
await pag.waitForSelector(`${g} [data-mensaje]:not([hidden])`);
afirmar('guardar un gasto con soporte lo sube junto', (await pag.textContent(`${g} [data-mensaje]`)).trim() === 'Guardado correctamente.');
afirmar('y el campo de archivo se limpia', (await pag.inputValue(`${g} input[data-soporte]`)) === '');
await pag.goto(base + '/movimientos?periodo=5');
afirmar('el gasto aparece en Movimientos con su soporte', await pag.locator('tr', { hasText: 'Concepto Soporte UI' }).locator('.adj-chip').count() === 1);
await pag.fill(`input[type=none]`, '').catch(() => {});
await pag.goto(base + '/capturar?periodo=5#gasto');
await pag.fill(`${g} input[name=proveedor]`, 'X'); await pag.fill(`${g} input[name=concepto]`, 'Y'); await pag.fill(`${g} input[name=monto_original]`, '1');
await pag.setInputFiles(`${g} input[data-soporte]`, { name: 'virus.exe', mimeType: 'application/octet-stream', buffer: Buffer.from('MZ') });
await pag.click(`${g} [data-boton]`);
afirmar('un archivo que no es PDF/foto se rechaza antes de guardar', (await pag.textContent(`${g} [data-mensaje]`)).includes('Solo PDF o imagen'));

// =============================== Recurrentes
await pag.goto(base + '/movimientos?periodo=5');
await pag.click('[data-generar-recurrentes]');
await pag.waitForSelector('[data-msg-recurrentes]:not([hidden])');
const texto = (await pag.textContent('[data-msg-recurrentes]')).trim();
afirmar('"Generar nómina y administrativo" responde con un resumen', /Ya está todo al día|Se generaron \d+ nómina\(s\) y \d+ cargo\(s\) administrativo\(s\)\./.test(texto), texto);
await pag.click('[data-generar-recurrentes]');
await pag.waitForFunction(() => /Ya está todo al día/.test(document.querySelector('[data-msg-recurrentes]').textContent));
afirmar('al repetirlo ya no genera nada (no duplica)', true);

// =============================== Usuarios
await pag.goto(base + '/usuarios');
afirmar('usuarios lista las cuentas', await pag.locator('tbody tr').count() >= 3);
afirmar('el campo "Socio asociado" solo aparece para el rol socio', !(await pag.locator('[data-solo-socio]').isVisible()));
await pag.selectOption('select[name=rol]', 'socio');
afirmar('al elegir socio aparece el campo', await pag.locator('[data-solo-socio]').isVisible());
await pag.fill('input[name=nombre]', 'Cuenta de Prueba UI');
await pag.fill('input[name=email]', 'cuenta.ui@prueba.test');
await pag.fill('input[name=password]', 'abc123');
await pag.click('[data-form] [data-boton]');
await pag.waitForSelector('[data-form] [data-mensaje]:not([hidden])');
afirmar('crear una cuenta muestra el mensaje y actualiza la tabla sin recargar', (await pag.textContent('[data-form] [data-mensaje]')).trim() === 'Guardado correctamente.');
await pag.waitForFunction(() => document.body.textContent.includes('cuenta.ui@prueba.test'));
afirmar('la cuenta nueva aparece en la lista', true);
await pag.fill('input[name=nombre]', 'Otra'); await pag.fill('input[name=email]', 'cuenta.ui@prueba.test'); await pag.fill('input[name=password]', 'abc123');
await pag.click('[data-form] [data-boton]');
await pag.waitForFunction(() => document.querySelector('[data-form] [data-mensaje]').textContent.includes('Ya existe'));
afirmar('un correo repetido muestra su error', true);
pag.dialogos.length = 0;
await pag.locator('tr', { hasText: 'cuenta.ui@prueba.test' }).locator('[data-clave]').click();
await pag.waitForFunction(() => true);
for (let i = 0; i < 40 && !pag.dialogos.includes('Contraseña actualizada.'); i++) await pag.waitForTimeout(100);
afirmar('"Contraseña" pide la nueva y confirma', pag.dialogos[0].includes('Nueva contraseña para Cuenta de Prueba UI') && pag.dialogos.some((m) => m === 'Contraseña actualizada.'), JSON.stringify(pag.dialogos));
await pag.locator('tr', { hasText: 'cuenta.ui@prueba.test' }).locator('[data-borrar-usuario]').click();
await pag.waitForFunction(() => !document.body.textContent.includes('cuenta.ui@prueba.test'));
afirmar('borrar la cuenta la quita de la lista', true);
afirmar('no hay botón de borrar en la propia cuenta', await pag.locator(`tr:has(td:text-is("${process.env.PRUEBA_EMAIL}"))`).locator('[data-borrar-usuario]').count() === 0);

// =============================== Auditoría
await pag.goto(base + '/auditoria');
afirmar('auditoría muestra la bitácora con fecha, usuario, acción y detalle', await pag.locator('tbody tr').count() > 20 && /^\d\d\/\d\d\/\d{4} \d\d:\d\d$/.test((await pag.locator('tbody tr td').first().textContent()).trim()));
afirmar('registra lo hecho en esta sesión (subir y borrar archivos)', await pag.locator('tbody tr', { hasText: 'Subió' }).count() > 0 && await pag.locator('tbody tr', { hasText: 'Eliminó el archivo' }).count() > 0);

// =============================== Permisos por rol
const cap = await entrar(process.env.PRUEBA_CAPITAN, { width: 390, height: 844 });
for (const [ruta, destino] of [['/usuarios', 1], ['/auditoria', 1], ['/movimientos', 1]]) {
  await cap.goto(base + ruta);
  afirmar(`el capitán no entra a ${ruta}`, new URL(cap.url()).pathname !== ruta);
}
await cap.goto(base + '/caja-chica');
afirmar('el capitán sí entra a caja chica y al calendario', new URL(cap.url()).pathname === '/caja-chica');
await cap.goto(base + '/calendario');
afirmar('el calendario abre para el capitán', new URL(cap.url()).pathname === '/calendario' && await cap.locator('.cal-card').count() === 1);
afirmar('sin errores de JavaScript en toda la prueba', pag.errores.length + movil.errores.length + cap.errores.length === 0, [...pag.errores, ...movil.errores, ...cap.errores][0] || '');

await nav.close();
console.log(fallos ? `\n${fallos} prueba(s) fallaron.` : '\nFase 4 (interfaz) OK.');
process.exit(fallos ? 1 : 0);
