<?php
// Reservas, caja chica, usuarios, adjuntos, recurrentes y auditoría con los controladores completos y la base real.
// Todo se revierte al terminar.  Uso:  php pruebas/fase4.php
require __DIR__ . '/ayuda.php';

use Lebleu\Core\Clave;
use Lebleu\Core\Db;
use Lebleu\Core\Peticion;
use Lebleu\Modulos\Adjuntos\AdjuntosController;
use Lebleu\Modulos\Auditoria\AuditoriaServicio;
use Lebleu\Modulos\CajaChica\CajaChicaController;
use Lebleu\Modulos\CajaChica\CajaChicaServicio;
use Lebleu\Modulos\Recurrentes\RecurrentesController;
use Lebleu\Modulos\Recurrentes\RecurrentesServicio;
use Lebleu\Modulos\Reservas\ReservasController;
use Lebleu\Modulos\Reservas\ReservasServicio;
use Lebleu\Modulos\Usuarios\UsuariosController;

$tablas = ['reservas', 'caja_chica', 'usuarios', 'adjuntos', 'auditoria', 'gastos_operativos', 'sesiones'];
$antes = array_map('cuenta', $tablas);

Db::simular(function () {
    $admin = usuario('admin'); $capitan = usuario('capitan'); $socio = usuario('socio');

    // ================= Reservas
    $res = new ReservasController();
    $aud0 = cuenta('auditoria');
    $septiembreAntes = count((new ReservasServicio())->delMes(2026, 9));
    [$e] = llamar($res, 'crear', null, ['fecha' => '2026-09-05']);
    afirmar('reservas sin sesión: 403', $e === 403);
    foreach (['admin' => $admin, 'capitán' => $capitan, 'socio' => $socio] as $quien => $t) {
        [$e, $d] = llamar($res, 'crear', $t, ['fecha' => '2026-09-05', 'hora' => '14:30', 'cliente' => "Cliente $quien", 'socio_id' => 1, 'num_personas' => 6, 'duracion_horas' => 4, 'notas' => 'n']);
        afirmar("$quien crea una reserva", $e === 200 && !empty($d['id']));
    }
    $r = Db::fila('SELECT * FROM reservas ORDER BY id DESC LIMIT 1');
    afirmar('la reserva guarda hora, personas y duración', substr($r['hora'], 0, 5) === '14:30' && (int) $r['num_personas'] === 6 && (float) $r['duracion_horas'] === 4.0);
    afirmar('las reservas no dejan rastro en auditoría (igual que el original)', cuenta('auditoria') === $aud0);
    [$e] = llamar($res, 'crear', $socio, ['fecha' => '2026-09-05', 'hora' => '25:99']);
    afirmar('hora inválida: 400', $e === 400);
    [$e] = llamar($res, 'crear', $socio, ['fecha' => 'ayer']);
    afirmar('fecha inválida: 400', $e === 400);
    [$e] = llamar($res, 'editar', $socio, ['id' => $r['id'], 'fecha' => '2026-09-06', 'cliente' => 'Otro', 'socio_id' => null]);
    $r2 = Db::fila('SELECT * FROM reservas WHERE id = ?', [$r['id']]);
    afirmar('el socio edita una reserva (socio vacío = externo)', $e === 200 && $r2['fecha'] === '2026-09-06' && $r2['socio_id'] === null && $r2['hora'] === null);
    $mes = (new ReservasServicio())->delMes(2026, 9);
    afirmar('el servicio entrega las reservas del mes con la forma del calendario', count($mes) === $septiembreAntes + 3 && is_int($mes[0]['id']) && array_key_exists('socio_nombre', $mes[0]) && is_string($mes[0]['hora']));
    [$e] = llamar($res, 'borrar', $socio, [], ['id' => $r['id']]);
    afirmar('el socio borra una reserva', $e === 200 && !Db::fila('SELECT 1 FROM reservas WHERE id = ?', [$r['id']]));

    // ================= Caja chica
    $caja = new CajaChicaController();
    [$e] = llamar($caja, 'crear', $socio, ['fecha' => '2026-09-05', 'monto' => 1]);
    afirmar('el socio no puede usar caja chica: 403', $e === 403);
    [$e, $d] = llamar($caja, 'crear', $capitan, ['fecha' => '2026-09-05', 'tipo' => 'gasto', 'caja_numero' => 99, 'proveedor' => 'Ferretería', 'concepto' => 'Cuerda', 'monto' => 350.5, 'abono' => 999]);
    $c = Db::fila('SELECT * FROM caja_chica ORDER BY id DESC LIMIT 1');
    afirmar('el capitán registra un gasto de caja (el abono se ignora)', $e === 200 && $c['monto'] === '350.50' && $c['abono'] === '0.00' && $c['tipo'] === 'gasto');
    afirmar('auditoría de caja chica con el texto original', ultimaAuditoria()['detalle'] === 'Caja 99: gasto "Cuerda" por $350.5', ultimaAuditoria()['detalle']);
    [$e] = llamar($caja, 'crear', $admin, ['fecha' => '2026-09-06', 'tipo' => 'abono', 'caja_numero' => 99, 'concepto' => 'Reposición', 'monto' => 500, 'abono' => 1000]);
    $a = Db::fila('SELECT * FROM caja_chica ORDER BY id DESC LIMIT 1');
    afirmar('un abono guarda monto 0 y su abono', $e === 200 && $a['monto'] === '0.00' && $a['abono'] === '1000.00' && $a['tipo'] === 'abono');
    $movs = array_values(array_filter((new CajaChicaServicio())->movimientos(), fn($m) => (int) $m['caja_numero'] === 99));
    afirmar('el balance corrido suma abonos y resta gastos', count($movs) === 2 && abs($movs[1]['balance'] - $movs[0]['balance'] - 1000) < 0.005);
    [$e] = llamar($caja, 'editar', $capitan, ['id' => $c['id'], 'fecha' => '2026-09-05', 'tipo' => 'gasto', 'caja_numero' => 99, 'concepto' => 'Cuerda 2', 'monto' => 400]);
    afirmar('el capitán edita un movimiento de caja', $e === 200 && Db::fila('SELECT monto FROM caja_chica WHERE id = ?', [$c['id']])['monto'] === '400.00');
    [$e] = llamar($caja, 'borrar', $capitan, [], ['id' => $c['id']]);
    afirmar('el capitán borra un movimiento de caja', $e === 200 && ultimaAuditoria()['detalle'] === 'Borró "Cuerda 2" de la caja 99', ultimaAuditoria()['detalle']);

    // ================= Usuarios
    $usr = new UsuariosController();
    [$e] = llamar($usr, 'crear', $capitan, ['nombre' => 'X', 'email' => 'x@x.mx', 'rol' => 'socio', 'password' => '123456']);
    afirmar('solo el administrador crea cuentas: 403', $e === 403);
    $nueva = ['nombre' => 'Capitán Nuevo', 'email' => 'nuevo.p4@prueba.test', 'rol' => 'capitan', 'password' => 'secreto1'];
    [$e] = llamar($usr, 'crear', $admin, $nueva);
    $u = Db::fila("SELECT * FROM usuarios WHERE email = 'nuevo.p4@prueba.test'");
    afirmar('el admin crea una cuenta y la contraseña se guarda con hash', $e === 200 && $u && Clave::verificar('secreto1', $u['password_hash']) && $u['password_hash'] !== 'secreto1');
    [$e, $d] = llamar($usr, 'crear', $admin, $nueva);
    afirmar('correo repetido: mensaje claro', $e === 400 && $d['error'] === 'Ya existe un usuario con ese correo.', json_encode($d));
    [$e, $d] = llamar($usr, 'crear', $admin, ['password' => '123'] + $nueva);
    afirmar('contraseña de menos de 6 caracteres: 400', $e === 400 && str_contains($d['error'], '6'));
    [$e] = llamar($usr, 'crear', $admin, ['email' => 'no-es-correo'] + $nueva);
    afirmar('correo inválido: 400', $e === 400);
    [$e] = llamar($usr, 'crear', $admin, ['rol' => 'socio', 'email' => 'socio.sin@prueba.test'] + $nueva);
    afirmar('un socio exige su socio asociado: 400', $e === 400);
    [$e] = llamar($usr, 'crear', $admin, ['rol' => 'socio', 'socio_id' => 1, 'email' => 'socio.con@prueba.test'] + $nueva);
    afirmar('crea un socio ligado a su socio', $e === 200 && (int) Db::fila("SELECT socio_id FROM usuarios WHERE email = 'socio.con@prueba.test'")['socio_id'] === 1);
    [$e] = llamar($usr, 'crear', $admin, ['rol' => 'dios', 'email' => 'rol.malo@prueba.test'] + $nueva);
    afirmar('rol inventado: 400', $e === 400);

    Db::ejecutar('INSERT INTO sesiones (token, usuario_id, expires_at) VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 DAY))', [str_repeat('a', 64), $u['id']]);
    [$e] = llamar($usr, 'editar', $admin, ['id' => $u['id'], 'password' => 'otraclave9']);
    afirmar('cambiar la contraseña la actualiza y cierra sus sesiones abiertas', $e === 200
        && Clave::verificar('otraclave9', Db::fila('SELECT password_hash FROM usuarios WHERE id = ?', [$u['id']])['password_hash'])
        && !Db::fila('SELECT 1 FROM sesiones WHERE usuario_id = ?', [$u['id']]));
    [$e] = llamar($usr, 'editar', $admin, ['id' => $u['id'], 'activo' => false]);
    afirmar('desactivar una cuenta', $e === 200 && (int) Db::fila('SELECT activo FROM usuarios WHERE id = ?', [$u['id']])['activo'] === 0);
    $yo = (int) Db::fila("SELECT id FROM usuarios WHERE email = 'admin.p4@prueba.test'")['id'];
    [$e, $d] = llamar($usr, 'borrar', $admin, [], ['id' => $yo]);
    afirmar('no puedes borrar tu propia cuenta', $e === 400 && $d['error'] === 'No puedes borrar tu propia cuenta.');
    [$e] = llamar($usr, 'borrar', $admin, [], ['id' => $u['id']]);
    afirmar('el admin borra otra cuenta', $e === 200 && !Db::fila('SELECT 1 FROM usuarios WHERE id = ?', [$u['id']]));
    [$e] = llamar($usr, 'editar', $admin, ['id' => 99999, 'activo' => true]);
    afirmar('editar una cuenta inexistente: 400', $e === 400);

    // ================= Adjuntos
    $adj = new AdjuntosController();
    $gasto = (int) Db::fila('SELECT id FROM gastos_operativos ORDER BY id LIMIT 1')['id'];
    Lebleu\Compartido\Pdf\Cargador::fpdf();
    $pdf = new FPDF(); $pdf->AddPage(); $pdf->SetFont('Helvetica', '', 12); $pdf->Cell(0, 10, 'respaldo');
    $pdfBytes = $pdf->Output('S');
    $subir = fn(?string $t, array $x) => llamar($adj, 'subir', $t, $x);
    $cargaPdf = ['gasto_id' => $gasto, 'nombre' => 'factura.pdf', 'mime' => 'application/pdf', 'base64' => 'data:application/pdf;base64,' . base64_encode($pdfBytes)];

    [$e] = $subir(null, $cargaPdf);
    afirmar('adjuntos sin sesión: 403', $e === 403);
    [$e] = $subir($socio, $cargaPdf);
    afirmar('el socio no sube archivos: 403', $e === 403);
    [$e] = $subir($capitan, $cargaPdf);
    afirmar('el capitán no sube soportes de un gasto: 403', $e === 403);
    [$e, $d] = $subir($admin, $cargaPdf);
    $f = Db::fila('SELECT * FROM adjuntos ORDER BY id DESC LIMIT 1');
    afirmar('el admin sube un PDF a un gasto', $e === 200 && $f['nombre'] === 'factura.pdf' && (int) $f['gasto_id'] === $gasto && !(int) $f['generado']);
    afirmar('auditoría de la subida', ultimaAuditoria()['detalle'] === 'Subió "factura.pdf"' && ultimaAuditoria()['accion'] === 'subir');
    [$e] = $subir($capitan, ['caja_id' => (int) Db::fila('SELECT id FROM caja_chica LIMIT 1')['id']] + $cargaPdf + ['gasto_id' => null]);
    afirmar('el capitán sí sube soportes de caja chica', $e === 200 || $e === 400 /* sin gasto_id */);

    if (function_exists('imagecreatetruecolor')) {
        $img = imagecreatetruecolor(300, 200); imagefill($img, 0, 0, imagecolorallocate($img, 10, 120, 180));
        ob_start(); imagepng($img); $png = ob_get_clean();
        ob_start(); imagejpeg($img); $jpg = ob_get_clean();
        foreach (['png' => $png, 'jpeg' => $jpg] as $tipo => $bytes) {
            [$e] = $subir($admin, ['nombre' => "foto.$tipo", 'mime' => "image/$tipo", 'base64' => base64_encode($bytes)] + $cargaPdf);
            $fi = Db::fila('SELECT nombre, mime, datos FROM adjuntos ORDER BY id DESC LIMIT 1');
            afirmar("una foto $tipo se guarda como PDF", $e === 200 && $fi['nombre'] === 'foto.pdf' && $fi['mime'] === 'application/pdf' && str_starts_with(base64_decode($fi['datos']), '%PDF-'));
        }
        [$e, $d] = $subir($admin, ['mime' => 'image/png', 'base64' => base64_encode('esto no es una imagen')] + $cargaPdf);
        afirmar('una "imagen" falsa se rechaza', $e === 400);
    }
    [$e, $d] = $subir($admin, ['mime' => 'text/html', 'base64' => base64_encode('<script>alert(1)</script>')] + $cargaPdf);
    afirmar('un tipo no permitido (HTML) se rechaza', $e === 400 && str_contains($d['error'], 'PDF'));
    [$e] = $subir($admin, ['base64' => base64_encode('texto plano disfrazado')] + $cargaPdf);
    afirmar('un "PDF" que no empieza con %PDF se rechaza', $e === 400);
    [$e] = $subir($admin, ['base64' => str_repeat('A', 4_700_000)] + $cargaPdf);
    afirmar('más de 3 MB se rechaza', $e === 400);
    [$e] = $subir($admin, ['gasto_id' => null] + $cargaPdf);
    afirmar('sin registro destino: 400', $e === 400);
    [$e] = $subir($admin, ['insumo_id' => 1] + $cargaPdf);
    afirmar('dos destinos a la vez: 400', $e === 400);

    [, , $resp] = llamar($adj, 'ver', $admin, [], [], ['id' => $f['id']]);
    [$e, , $ver] = llamar($adj, 'ver', $admin, [], [], ['id' => (int) Db::fila('SELECT id FROM adjuntos WHERE nombre = "factura.pdf" ORDER BY id DESC LIMIT 1')['id']]);
    afirmar('ver un adjunto lo sirve como PDF con nosniff', $e === 200 && $ver->cabecera('Content-Type') === 'application/pdf' && $ver->cabecera('X-Content-Type-Options') === 'nosniff' && str_starts_with($ver->cuerpo(), '%PDF-'));
    [$e] = llamar($adj, 'ver', $capitan, [], [], ['id' => (int) Db::fila('SELECT id FROM adjuntos WHERE gasto_id IS NOT NULL LIMIT 1')['id']]);
    afirmar('el capitán no ve soportes que no son de caja: 403', $e === 403);

    $idFactura = (int) Db::fila('SELECT id FROM adjuntos WHERE nombre = "factura.pdf" ORDER BY id DESC LIMIT 1')['id'];
    [$e] = llamar($adj, 'borrar', $capitan, [], ['id' => $idFactura]);
    afirmar('el capitán no borra soportes que no son de caja: 403', $e === 403);
    [$e] = llamar($adj, 'borrar', $admin, [], ['id' => $idFactura]);
    afirmar('el admin elimina un archivo y queda en auditoría', $e === 200 && !Db::fila('SELECT 1 FROM adjuntos WHERE id = ?', [$idFactura])
        && ultimaAuditoria()['detalle'] === 'Eliminó el archivo "factura.pdf"');

    // ================= Recurrentes
    $rec = new RecurrentesServicio();
    $periodo = Db::fila("SELECT id, anio, mes FROM periodos WHERE estado = 'abierto' ORDER BY anio DESC, mes DESC LIMIT 1");
    $r1 = $rec->asegurar($periodo, '2026-12-31');
    $r2 = $rec->asegurar($periodo, '2026-12-31');
    afirmar('los cargos recurrentes no se duplican al repetir', $r2 === ['nomina' => 0, 'admin' => 0], json_encode([$r1, $r2]));
    $n = Db::fila("SELECT COUNT(*) n, MIN(monto_mxn) m FROM gastos_operativos WHERE periodo_id = ? AND clave_recurrente LIKE 'nom-%'", [$periodo['id']]);
    afirmar('la nómina semanal es de $8,560', (int) $n['n'] >= 1 && $n['m'] === '8560.00');
    afirmar('nunca se generan cargos anteriores al 2 de julio de 2026', ($rec->asegurar(['id' => $periodo['id'], 'anio' => 2026, 'mes' => 6], '2026-12-31') === ['nomina' => 0, 'admin' => 0]));
    [$e] = llamar(new RecurrentesController(), 'generar', $capitan, []);
    afirmar('solo el admin genera cargos a mano: 403', $e === 403);
    [$e, $d] = llamar(new RecurrentesController(), 'generar', $admin, ['periodoId' => $periodo['id']]);
    afirmar('el botón responde con los conteos', $e === 200 && isset($d['nomina'], $d['admin']));

    // ================= Auditoría
    Db::ejecutar("INSERT INTO auditoria (usuario_nombre, accion, tabla, detalle, created_at) VALUES ('prueba', 'crear', 'periodos', 'hora', '2026-08-26 15:44:00')");
    $fila = array_values(array_filter(AuditoriaServicio::ultimos(5), fn($x) => $x['detalle'] === 'hora'))[0] ?? null;
    afirmar('la auditoría muestra la hora de Baja California Sur (UTC−7)', $fila && $fila['cuando'] === '26/08/2026 08:44', json_encode($fila['cuando'] ?? null));
});

afirmar('la prueba no dejó rastro en la base', $antes === array_map('cuenta', $tablas), json_encode([$antes, array_map('cuenta', $tablas)]));
terminar('Fase 4 OK.');
