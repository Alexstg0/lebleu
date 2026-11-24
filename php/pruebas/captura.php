<?php
// Prueba de los módulos de captura con la base real y los controladores completos
// (permisos, validación, SQL, periodo cerrado, auditoría). Todo se revierte al terminar.
// Uso:  php pruebas/captura.php
require __DIR__ . '/../app/arranque.php';

use Lebleu\Core\Clave;
use Lebleu\Core\Db;
use Lebleu\Core\Peticion;
use Lebleu\Modulos\Aportaciones\AportacionesController;
use Lebleu\Modulos\Auth\AuthRepositorio;
use Lebleu\Modulos\EstadoCuenta\EstadoCuentaServicio;
use Lebleu\Modulos\Extraordinarios\AbonosController;
use Lebleu\Modulos\Extraordinarios\ExtraordinariosController;
use Lebleu\Modulos\GastosOperativos\GastosOperativosController;
use Lebleu\Modulos\IngresosRenta\IngresosRentaController;
use Lebleu\Modulos\Insumos\InsumosController;
use Lebleu\Modulos\Marineros\MarinerosController;
use Lebleu\Modulos\Viajes\ViajesController;

$fallos = 0;
function afirmar(string $nombre, bool $cond, string $extra = ''): void
{
    global $fallos;
    echo ($cond ? 'ok      ' : 'FALLA   ') . $nombre . ($cond || $extra === '' ? '' : "  ($extra)") . "\n";
    $fallos += $cond ? 0 : 1;
}

/** Llama a un controlador como lo haría el navegador. @return array{0:int,1:?array} estado y cuerpo */
function llamar(object $ctrl, string $accion, ?string $token, array $json = [], array $consulta = []): array
{
    $p = new Peticion('POST', '/api/prueba', $json, $token ? ['sid' => $token] : [], $consulta);
    $r = $ctrl->$accion($p, []);
    return [$r->estado(), $r->datos()];
}

function usuario(string $rol): string
{
    Db::ejecutar('INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES (?, ?, ?, ?)',
        ["Prueba $rol", "$rol.captura@prueba.test", Clave::crear('x'), $rol]);
    $token = bin2hex(random_bytes(32));
    (new AuthRepositorio())->crearSesion($token, Db::ultimoId(), 1);
    return $token;
}

function auditoria(): array
{
    return Db::fila('SELECT accion, tabla, registro_id, detalle, usuario_nombre FROM auditoria ORDER BY id DESC LIMIT 1') ?? [];
}

$abierto = (int) Db::fila("SELECT id FROM periodos WHERE estado = 'abierto' ORDER BY anio DESC, mes DESC LIMIT 1")['id'];
$cerrado = (int) Db::fila("SELECT id FROM periodos WHERE estado = 'cerrado' ORDER BY anio, mes LIMIT 1")['id'];
$antes = array_map(fn($t) => (int) Db::fila("SELECT COUNT(*) n FROM `$t`")['n'],
    ['gastos_operativos', 'extraordinarios', 'viajes', 'insumos', 'aportaciones', 'ingresos_renta', 'auditoria', 'usuarios', 'marineros']);

Db::simular(function () use ($abierto, $cerrado) {
    $admin = usuario('admin');
    $capitan = usuario('capitan');
    $socio = usuario('socio');
    $gastos = new GastosOperativosController();

    // ---------------- Permisos
    [$e] = llamar($gastos, 'crear', null, ['periodo_id' => $abierto]);
    afirmar('sin sesión: 403', $e === 403);
    [$e] = llamar($gastos, 'crear', $socio, ['periodo_id' => $abierto]);
    afirmar('socio no puede capturar gastos: 403', $e === 403);
    [$e] = llamar($gastos, 'crear', $capitan, ['periodo_id' => $abierto]);
    afirmar('capitán no puede capturar gastos: 403', $e === 403);

    // ---------------- Gastos operativos
    $nuevo = ['periodo_id' => $abierto, 'fecha' => '2026-08-10', 'proveedor' => 'Ebay Prueba', 'concepto' => 'Válvula',
        'categoria' => 'refaccion', 'moneda' => 'USD', 'monto_original' => 32.85, 'tipo_cambio' => 17.5, 'comprobante_folio' => 'F-1'];
    [$e, $d] = llamar($gastos, 'crear', $admin, $nuevo);
    afirmar('admin crea un gasto en USD', $e === 200 && ($d['ok'] ?? false) === true);
    $g = Db::fila('SELECT * FROM gastos_operativos ORDER BY id DESC LIMIT 1');
    afirmar('monto_mxn lo calcula la base (32.85 × 17.5 = 574.88)', $g['monto_mxn'] === '574.88', (string) $g['monto_mxn']);
    afirmar('en USD se guarda el tipo de cambio', (float) $g['tipo_cambio'] === 17.5);
    afirmar('auditoría del alta con el texto original', auditoria()['detalle'] === 'Gasto "Válvula" (Ebay Prueba) por $32.85 USD'
        && auditoria()['accion'] === 'crear' && auditoria()['usuario_nombre'] === 'Prueba admin', json_encode(auditoria(), JSON_UNESCAPED_UNICODE));

    [$e, $d] = llamar($gastos, 'crear', $admin, ['moneda' => 'MXN', 'tipo_cambio' => 99] + $nuevo);
    $g2 = Db::fila('SELECT * FROM gastos_operativos ORDER BY id DESC LIMIT 1');
    afirmar('en MXN el tipo de cambio siempre es 1', (float) $g2['tipo_cambio'] === 1.0 && $g2['monto_mxn'] === '32.85');

    foreach ([['fecha' => '2026-02-31'], ['monto_original' => 'abc'], ['categoria' => 'inventada'], ['proveedor' => ''], ['moneda' => 'EUR']] as $malo) {
        [$e, $d] = llamar($gastos, 'crear', $admin, $malo + $nuevo);
        afirmar('rechaza dato inválido ' . array_key_first($malo) . ': 400 con mensaje', $e === 400 && !empty($d['error']), json_encode($d));
    }
    [$e, $d] = llamar($gastos, 'crear', $admin, ['periodo_id' => $cerrado] + $nuevo);
    afirmar('periodo cerrado bloquea el alta', $e === 400 && str_contains($d['error'], 'cerrado'));

    [$e] = llamar($gastos, 'editar', $admin, ['id' => $g['id'], 'monto_original' => 40, 'concepto' => 'Válvula 2'] + $nuevo);
    $g = Db::fila('SELECT * FROM gastos_operativos WHERE id = ?', [$g['id']]);
    afirmar('editar actualiza y recalcula (40 × 17.5 = 700)', $e === 200 && $g['monto_mxn'] === '700.00' && $g['concepto'] === 'Válvula 2');
    afirmar('auditoría de la edición', auditoria()['detalle'] === 'Editó el gasto "Válvula 2" → $40 USD');
    [$e] = llamar($gastos, 'editar', $admin, ['id' => 999999] + $nuevo);
    afirmar('editar un gasto inexistente: 404', $e === 404);

    $enCerrado = (int) Db::fila("SELECT id FROM gastos_operativos WHERE periodo_id = ? LIMIT 1", [$cerrado])['id'];
    [$e, $d] = llamar($gastos, 'editar', $admin, ['id' => $enCerrado] + $nuevo);
    afirmar('periodo cerrado bloquea la edición', $e === 400 && str_contains($d['error'], 'cerrado'));
    [$e, $d] = llamar($gastos, 'borrar', $admin, [], ['id' => $enCerrado]);
    afirmar('periodo cerrado bloquea el borrado', $e === 400 && str_contains($d['error'], 'cerrado'));

    [$e] = llamar($gastos, 'borrar', $socio, [], ['id' => $g['id']]);
    afirmar('socio no puede borrar: 403', $e === 403);
    [$e] = llamar($gastos, 'borrar', $admin, [], ['id' => $g['id']]);
    afirmar('admin borra el gasto y queda en auditoría', $e === 200 && !Db::fila('SELECT 1 FROM gastos_operativos WHERE id = ?', [$g['id']])
        && auditoria()['detalle'] === 'Borró el gasto "Válvula 2" de $700.00', auditoria()['detalle']);
    [$e] = llamar($gastos, 'borrar', $admin, [], ['id' => 999999]);
    afirmar('borrar algo que ya no existe es inofensivo (200)', $e === 200);

    // ---------------- El estado de cuenta refleja lo capturado
    $antesBal = (new EstadoCuentaServicio())->obtener($abierto)['kpis']['opTotal'];
    llamar($gastos, 'crear', $admin, ['moneda' => 'MXN', 'monto_original' => 1000.01] + $nuevo);
    $despBal = (new EstadoCuentaServicio())->obtener($abierto);
    afirmar('un gasto nuevo sube el total operativo', abs($despBal['kpis']['opTotal'] - $antesBal - 1000.01) < 0.005);
    $suma = array_sum(array_map(fn($b) => (int) round($b['gastos_generales'] * 100), $despBal['balances']));
    afirmar('el reparto entre socios suma exacto al total (sin centavo fantasma)', $suma === (int) round($despBal['kpis']['opTotal'] * 100));

    // ---------------- Extraordinarios y abonos
    $extra = new ExtraordinariosController();
    $datosE = ['periodo_id' => $abierto, 'fecha' => '2026-08-11', 'proveedor' => 'Marina', 'concepto' => 'Varada anual',
        'categoria' => 'varada', 'moneda' => 'MXN', 'monto_original' => 5000];
    [$e] = llamar($extra, 'crear', $admin, $datosE);
    $x = Db::fila('SELECT * FROM extraordinarios ORDER BY id DESC LIMIT 1');
    afirmar('crea un extraordinario', $e === 200 && $x['monto_mxn'] === '5000.00' && (int) $x['liquidado'] === 0);
    afirmar('auditoría del extraordinario', auditoria()['detalle'] === 'Extraordinario "Varada anual" por $5000 MXN');
    [$e, $d] = llamar($extra, 'crear', $admin, ['categoria' => 'nomina'] + $datosE);
    afirmar('categoría de gasto operativo no vale en extraordinarios', $e === 400);
    [$e] = llamar($extra, 'pagado', $admin, ['id' => $x['id'], 'liquidado' => true]);
    afirmar('marcar pagado', $e === 200 && (int) Db::fila('SELECT liquidado FROM extraordinarios WHERE id = ?', [$x['id']])['liquidado'] === 1);
    [$e] = llamar($extra, 'pagado', $socio, ['id' => $x['id'], 'liquidado' => false]);
    afirmar('socio no puede marcar pagado: 403', $e === 403);

    $abonos = new AbonosController();
    [$e] = llamar($abonos, 'crear', $admin, ['periodo_id' => $abierto, 'socio_id' => 1, 'fecha' => '2026-08-12', 'monto' => 700.5, 'metodo' => 'transferencia']);
    afirmar('crea un abono a extraordinarios', $e === 200 && auditoria()['detalle'] === 'Abono a extraordinarios de $700.5 (socio 1)');
    $cuenta = (new EstadoCuentaServicio())->obtener($abierto)['extraCuenta'];
    afirmar('el abono baja el saldo pendiente del socio 1', abs($cuenta[0]['abonosMes'] - 700.5) < 0.005);

    // ---------------- Aportaciones y rentas
    $apo = new AportacionesController();
    [$e] = llamar($apo, 'crear', $admin, ['periodo_id' => $abierto, 'socio_id' => 2, 'fecha' => '2026-08-13', 'monto' => 25000]);
    $a = Db::fila('SELECT * FROM aportaciones ORDER BY id DESC LIMIT 1');
    afirmar('crea una aportación', $e === 200 && $a['monto'] === '25000.00' && $a['metodo'] === null);
    [$e] = llamar($apo, 'editar', $admin, ['id' => $a['id'], 'socio_id' => 2, 'fecha' => '2026-08-13', 'monto' => 26000, 'metodo' => 'efectivo']);
    afirmar('edita la aportación', $e === 200 && Db::fila('SELECT metodo FROM aportaciones WHERE id = ?', [$a['id']])['metodo'] === 'efectivo'
        && auditoria()['detalle'] === 'Editó la aportación → $26000');
    [$e] = llamar($apo, 'crear', $admin, ['periodo_id' => $abierto, 'fecha' => '2026-08-13', 'monto' => 1]);
    afirmar('aportación sin socio: 400', $e === 400);

    $renta = new IngresosRentaController();
    [$e] = llamar($renta, 'crear', $admin, ['periodo_id' => $abierto, 'cliente' => 'Turista SA', 'fecha' => '2026-08-14', 'monto' => 12000, 'costos_asociados' => 2000]);
    $r = Db::fila('SELECT * FROM ingresos_renta ORDER BY id DESC LIMIT 1');
    afirmar('crea una renta y la utilidad la calcula la base (12000 − 2000)', $e === 200 && $r['utilidad'] === '10000.00');
    $bal = (new EstadoCuentaServicio())->obtener($abierto);
    afirmar('la utilidad de renta se reparte 50/50 en el estado de cuenta', abs($bal['balances'][0]['utilidad_renta'] - 5000) < 0.005 && abs($bal['kpis']['ingresosRenta'] - 12000) < 0.005);
    afirmar('auditoría de la renta', auditoria()['detalle'] === 'Renta "Turista SA" por $12000');

    // ---------------- Viajes y horómetros
    $viajes = new ViajesController();
    $motor = Db::fila('SELECT id, horometro_actual FROM motores ORDER BY id LIMIT 1');
    $viaje = ['periodo_id' => $abierto, 'socio_id' => 1, 'cliente_id' => 1, 'fecha' => '2026-08-15', 'duracion_horas' => 4, 'num_personas' => 6,
        'litros' => 100, 'precio_litro' => 25, 'costo_combustible' => 2500, 'costo_marinero' => 800, 'marinero' => 'Pedro Prueba',
        'horometros' => [['motor_id' => (int) $motor['id'], 'inicio' => '1000.0', 'fin' => '1004.5']]];
    [$e] = llamar($viajes, 'crear', $socio, $viaje);
    afirmar('socio no puede registrar viajes: 403', $e === 403);
    [$e, $d] = llamar($viajes, 'crear', $capitan, $viaje);
    afirmar('el capitán sí registra viajes y recibe el id', $e === 200 && !empty($d['id']));
    $v = Db::fila('SELECT * FROM viajes WHERE id = ?', [$d['id']]);
    afirmar('el total del viaje lo calcula la base (2500 + 800 + 0)', $v['total'] === '3300.00' && (int) $v['embarcacion_id'] === 1 && (int) $v['es_renta'] === 0);
    $h = Db::fila('SELECT * FROM viaje_horometros WHERE viaje_id = ?', [$v['id']]);
    afirmar('guarda la lectura del horómetro y calcula las horas (4.5)', $h && $h['horas'] === '4.50');
    afirmar('deja la última lectura como horómetro actual del motor', Db::fila('SELECT horometro_actual FROM motores WHERE id = ?', [$motor['id']])['horometro_actual'] === '1004.50');
    afirmar('auditoría del viaje con el nombre del cliente', str_starts_with(auditoria()['detalle'], 'Registró el viaje del 2026-08-15 (cliente 1)'), auditoria()['detalle']);

    $malo = $viaje;
    $malo['horometros'][0]['fin'] = '999';
    [$e, $d] = llamar($viajes, 'crear', $admin, $malo);
    afirmar('lectura final menor que la inicial: 400 y no se crea el viaje', $e === 400 && (int) Db::fila('SELECT COUNT(*) n FROM viajes WHERE fecha = ? AND marinero = ? AND id <> ?', ['2026-08-15', 'Pedro Prueba', $v['id']])['n'] === 0);

    [$e] = llamar($viajes, 'editar', $capitan, ['id' => $v['id'], 'costo_marinero' => 900, 'horometros' => [['motor_id' => (int) $motor['id'], 'inicio' => '1000', 'fin' => '1005']]] + $viaje);
    $v = Db::fila('SELECT * FROM viajes WHERE id = ?', [$v['id']]);
    afirmar('el capitán edita el viaje y se recalcula el total', $e === 200 && $v['total'] === '3400.00');
    afirmar('editar reemplaza (no duplica) las lecturas', (int) Db::fila('SELECT COUNT(*) n FROM viaje_horometros WHERE viaje_id = ?', [$v['id']])['n'] === 1);
    [$e] = llamar($viajes, 'borrar', $capitan, [], ['id' => $v['id']]);
    afirmar('el capitán NO puede borrar viajes: 403', $e === 403);

    // ---------------- Insumos: mantienen viajes.costo_consumibles
    $ins = new InsumosController();
    $insumo = ['periodo_id' => $abierto, 'viaje_id' => (int) $v['id'], 'fecha' => '2026-08-15', 'categoria' => 'hielo', 'proveedor' => 'Hielera',
        'concepto' => 'Hielo', 'monto_original' => 300];
    [$e] = llamar($ins, 'crear', $admin, $insumo);
    $i1 = Db::fila('SELECT * FROM insumos ORDER BY id DESC LIMIT 1');
    afirmar('crea un insumo ligado al viaje', $e === 200 && $i1['monto_mxn'] === '300.00');
    afirmar('el costo de consumibles del viaje se sincroniza (300)', Db::fila('SELECT costo_consumibles c FROM viajes WHERE id = ?', [$v['id']])['c'] === '300.00');
    [$e] = llamar($ins, 'crear', $admin, ['monto_original' => 150, 'moneda' => 'USD', 'tipo_cambio' => 2] + $insumo);
    afirmar('un segundo insumo en USD suma al viaje (300 + 150×2 = 600)', Db::fila('SELECT costo_consumibles c FROM viajes WHERE id = ?', [$v['id']])['c'] === '600.00');
    [$e] = llamar($ins, 'editar', $admin, ['id' => $i1['id'], 'viaje_id' => null] + $insumo);
    afirmar('mover un insumo a "sin viaje" lo resta del viaje anterior (300)', Db::fila('SELECT costo_consumibles c FROM viajes WHERE id = ?', [$v['id']])['c'] === '300.00');
    [$e, $d] = llamar($ins, 'editar', $admin, ['id' => $i1['id'], 'periodo_id' => $cerrado] + $insumo);
    afirmar('no se puede mover un insumo a un periodo cerrado', $e === 400 && str_contains($d['error'], 'cerrado'));
    $i2 = Db::fila('SELECT * FROM insumos ORDER BY id DESC LIMIT 1');
    [$e] = llamar($ins, 'borrar', $admin, [], ['id' => $i2['id']]);
    afirmar('borrar un insumo resta su costo del viaje (0)', $e === 200 && Db::fila('SELECT costo_consumibles c FROM viajes WHERE id = ?', [$v['id']])['c'] === '0.00');

    [$e] = llamar($viajes, 'borrar', $admin, [], ['id' => $v['id']]);
    afirmar('el admin borra el viaje (se van sus lecturas y su insumo)', $e === 200 && !Db::fila('SELECT 1 FROM viaje_horometros WHERE viaje_id = ?', [$v['id']])
        && auditoria()['detalle'] === 'Borró el viaje del 2026-08-15 (Familia Acosta)', auditoria()['detalle']);

    // ---------------- Marineros
    $mar = new MarinerosController();
    [$e, $d] = llamar($mar, 'crear', $capitan, ['nombre' => '  juan   carlos  pérez ']);
    afirmar('el capitán da de alta un marinero (nombre normalizado)', $e === 200 && $d['nombre'] === 'juan carlos pérez');
    [$e, $d2] = llamar($mar, 'crear', $admin, ['nombre' => 'JUAN CARLOS PÉREZ']);
    afirmar('un nombre repetido (sin importar mayúsculas) se reutiliza', $e === 200 && $d2['id'] === $d['id']);
    [$e] = llamar($mar, 'crear', $capitan, ['nombre' => 'ab']);
    afirmar('nombre demasiado corto: 400', $e === 400);
    [$e] = llamar($mar, 'crear', $socio, ['nombre' => 'Nombre Valido']);
    afirmar('el socio no puede dar de alta marineros: 403', $e === 403);
});

$despues = array_map(fn($t) => (int) Db::fila("SELECT COUNT(*) n FROM `$t`")['n'],
    ['gastos_operativos', 'extraordinarios', 'viajes', 'insumos', 'aportaciones', 'ingresos_renta', 'auditoria', 'usuarios', 'marineros']);
afirmar('la prueba no dejó rastro en la base', $antes === $despues, json_encode([$antes, $despues]));
echo $fallos ? "\n$fallos prueba(s) fallaron.\n" : "\nCaptura OK.\n";
exit($fallos ? 1 : 0);
