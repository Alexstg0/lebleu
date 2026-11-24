<?php
// Regresión de la revisión independiente del código: cada hallazgo corregido tiene aquí su comprobación.
// Se revierte sola. Uso: php -d memory_limit=256M pruebas/revision.php
require __DIR__ . '/ayuda.php';

use Lebleu\Compartido\Pdf\Fusionador;
use Lebleu\Compartido\Pdf\ImagenAPdf;
use Lebleu\Core\Clave;
use Lebleu\Core\Db;
use Lebleu\Core\Peticion;
use Lebleu\Core\Ruteador;
use Lebleu\Modulos\Auth\AuthServicio;
use Lebleu\Modulos\GastosOperativos\GastosOperativosServicio;
use Lebleu\Modulos\Insumos\InsumosController;
use Lebleu\Modulos\Recurrentes\RecurrentesController;
use Lebleu\Modulos\Usuarios\UsuariosController;
use Lebleu\Servicios;

// ---------- PDF hostiles: se rechazan rápido y sin agotar la memoria
$rechaza = function (string $pdf): bool {
    try {
        (new Fusionador())->agregar($pdf);
        return false;
    } catch (\Throwable) {
        return true;
    }
};
$antes = memory_get_peak_usage();
$t = microtime(true);
// a) árbol de páginas con hijos repetidos (crecimiento exponencial si se recorre sin memoria de lo visitado)
$o = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n";
$niveles = 40;
for ($i = 0; $i < $niveles; $i++) {
    $hijo = $i + 3;
    $o .= (2 + $i) . " 0 obj<</Type/Pages/Kids[" . str_repeat("$hijo 0 R ", 8) . "]/Count 8>>endobj\n";
}
$o .= (2 + $niveles) . " 0 obj<</Type/Page/MediaBox[0 0 10 10]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";
$rechaza($o);
afirmar('un árbol de páginas con hijos repetidos no se expande sin fin', microtime(true) - $t < 5 && memory_get_peak_usage() - $antes < 64 << 20);
// b) xref con anchos en cero e /Index enorme
$x = "%PDF-1.5\n1 0 obj<</Type/XRef/W[0 0 0]/Index[0 100000000]/Size 100000000/Length 0>>\nstream\n\nendstream\nendobj\nstartxref\n9\n%%EOF";
afirmar('un xref con anchos en cero se rechaza sin agotar la memoria', $rechaza($x) && memory_get_peak_usage() - $antes < 64 << 20);
// c) bomba de descompresión: 100 MB de ceros comprimidos en ~100 KB, como flujo xref
$bomba = gzcompress(str_repeat("\0", 100 << 20), 9);
$b = "%PDF-1.5\n1 0 obj<</Type/XRef/W[1 1 1]/Size 5/Filter/FlateDecode/Length " . strlen($bomba) . ">>\nstream\n$bomba\nendstream\nendobj\nstartxref\n9\n%%EOF";
memory_reset_peak_usage();
$base = memory_get_usage();
$rechazada = $rechaza($b);
afirmar('una bomba de descompresión (100 MB) se rechaza sin consumir esa memoria', $rechazada && memory_get_peak_usage() - $base < 80 << 20, round((memory_get_peak_usage() - $base) / 1048576) . ' MB');
unset($bomba, $b);

// ---------- Foto con dimensiones hostiles (PNG de 9000×9000 declarado, sin datos reales)
$ihdr = pack('NN', 9000, 9000) . "\x08\x06\x00\x00\x00";
$png = "\x89PNG\r\n\x1a\n" . pack('N', 13) . 'IHDR' . $ihdr . pack('N', crc32('IHDR' . $ihdr)) . pack('N', 0) . 'IEND' . pack('N', crc32('IEND'));
$fallo = '';
try {
    ImagenAPdf::convertir($png);
} catch (\InvalidArgumentException $e) {
    $fallo = $e->getMessage();
}
afirmar('una foto de 9000×9000 se rechaza con un mensaje claro', str_contains($fallo, 'demasiado grande'), $fallo);

// ---------- Contraseñas: el señuelo no cambia entre peticiones (misma hora para usuarios que existen y que no)
$a = trim((string) shell_exec('php -r \'require "' . __DIR__ . '/../app/arranque.php"; echo Lebleu\Core\Clave::senuelo();\''));
$b = trim((string) shell_exec('php -r \'require "' . __DIR__ . '/../app/arranque.php"; echo Lebleu\Core\Clave::senuelo();\''));
afirmar('el hash señuelo del login es el mismo en procesos distintos (no se recalcula en cada petición)', $a !== '' && $a === $b);

Db::simular(function () {
    $admin = usuario('admin', 'rv');
    $socio = usuario('socio', 'rv');

    // ---------- Login: acentos y mayúsculas cuentan como la misma cuenta
    Db::ejecutar('INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES (?, ?, ?, ?)', ['Cuenta límite', 'limite.rv@prueba.test', Clave::crear('buena'), 'socio']);
    $auth = new AuthServicio();
    $estados = [];
    foreach (['limite.rv@prueba.test', 'LIMITE.rv@prueba.test', 'límite.rv@prueba.test', 'limite.rv@prúeba.test', 'Límite.rv@prueba.test', 'limite.rv@prueba.tést', 'LIMITE.RV@prueba.test', 'límite.rv@prúeba.test', 'limíte.rv@prueba.test', 'limite.rv@prueba.test'] as $variante) {
        $estados[] = $auth->iniciarSesion($variante, 'mala', '203.0.113.9')['estado'];
    }
    afirmar('variantes con acentos/mayúsculas del mismo correo comparten el límite de intentos', in_array(429, $estados, true) && array_search(429, $estados, true) <= 8, implode(',', $estados));
    afirmar('con el límite alcanzado, ni la clave buena entra', $auth->iniciarSesion('limite.rv@prueba.test', 'buena', '203.0.113.9')['estado'] === 429);
    afirmar('el límite es por IP: otra IP sí puede entrar', $auth->iniciarSesion('limite.rv@prueba.test', 'buena', '203.0.113.10')['ok'] === true);
    Db::ejecutar("INSERT INTO login_intentos (clave, intentos, updated_at) VALUES ('viejo', 5, DATE_SUB(UTC_TIMESTAMP(), INTERVAL 3 DAY))");
    Db::ejecutar("INSERT INTO sesiones (token, usuario_id, expires_at) VALUES (?, 1, DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY))", [str_repeat('f', 64)]);
    (new \Lebleu\Modulos\Auth\AuthRepositorio())->purgar();
    afirmar('las sesiones vencidas y los intentos viejos se purgan', !Db::fila("SELECT 1 FROM login_intentos WHERE clave = 'viejo'") && !Db::fila('SELECT 1 FROM sesiones WHERE token = ?', [str_repeat('f', 64)]));

    // ---------- Usuarios: nunca sin administrador activo
    Db::ejecutar("UPDATE usuarios SET activo = 0 WHERE rol = 'admin' AND email NOT LIKE 'admin%rv.p4@prueba.test'");
    $yo = (int) Db::fila("SELECT id FROM usuarios WHERE email = 'adminrv.p4@prueba.test'")['id'];
    [$e, $d] = llamar(new UsuariosController(), 'editar', $admin, ['id' => $yo, 'activo' => false]);
    afirmar('no se puede desactivar al único administrador activo', $e === 400 && str_contains($d['error'], 'único administrador') && (int) Db::fila('SELECT activo FROM usuarios WHERE id = ?', [$yo])['activo'] === 1, json_encode($d));

    // ---------- Recurrentes: el botón se comporta como el original (también en periodos cerrados)
    Db::ejecutar("INSERT INTO periodos (embarcacion_id, anio, mes, tipo_cambio, precio_litro, cargo_administracion, estado) VALUES (1, 2097, 5, 1, 1, 0, 'cerrado')");
    $cerrado = (int) Db::ultimoId();
    [$e] = llamar(new RecurrentesController(), 'generar', $admin, ['periodoId' => $cerrado]);
    afirmar('generar cargos en un periodo cerrado funciona como en el original', $e === 200);

    // ---------- Recurrentes: clave única y recibo que se recupera
    Db::ejecutar("INSERT INTO periodos (embarcacion_id, anio, mes, tipo_cambio, precio_litro, cargo_administracion, estado) VALUES (1, 2096, 2, 1, 1, 0, 'abierto')");
    $abierto = (int) Db::ultimoId();
    $g = new GastosOperativosServicio();
    $id1 = $g->crearRecurrente($abierto, '2096-02-01', 'SOLMEX', 'Admin', 'admin', 6000, 'ADM-2096-02', 'adm-2096-02');
    afirmar('un cargo recurrente se crea una vez', $id1 > 0);
    afirmar('crearlo otra vez no duplica ni lanza error', $g->crearRecurrente($abierto, '2096-02-01', 'SOLMEX', 'Admin', 'admin', 6000, 'ADM-2096-02', 'adm-2096-02') === null && (int) Db::fila("SELECT COUNT(*) n FROM gastos_operativos WHERE clave_recurrente = 'adm-2096-02'")['n'] === 1);
    $choque = false;
    try {
        Db::ejecutar('INSERT INTO gastos_operativos (periodo_id, fecha, proveedor, concepto, categoria, moneda, monto_original, tipo_cambio, comprobante_folio, clave_recurrente)
                      SELECT periodo_id, fecha, proveedor, concepto, categoria, moneda, monto_original, tipo_cambio, comprobante_folio, clave_recurrente FROM gastos_operativos WHERE id = ?', [$id1]);
    } catch (\PDOException $e) {
        $choque = ($e->errorInfo[1] ?? 0) === 1062;
    }
    afirmar('la base misma impide dos cargos con la misma clave (índice único)', $choque);
    $pr = Db::fila('SELECT p.id, p.anio, p.mes, e.nombre AS embarcacion, e.razon_social FROM periodos p JOIN embarcaciones e ON e.id = p.embarcacion_id WHERE p.id = ?', [$abierto]);
    Servicios::recurrentes()->asegurar($pr, '2096-02-28', '2096-02-01');
    afirmar('si el cargo existía sin recibo, el siguiente intento lo genera', (bool) Db::fila('SELECT 1 FROM adjuntos WHERE gasto_id = ? AND generado = 1', [$id1]));

    // ---------- Insumos: el viaje debe ser del mismo periodo
    $viaje = Db::fila('SELECT id, periodo_id FROM viajes ORDER BY id LIMIT 1');
    $otro = (int) Db::fila('SELECT id FROM periodos WHERE id <> ? AND estado = ? ORDER BY id LIMIT 1', [$viaje['periodo_id'], 'abierto'])['id'];
    [$e, $d] = llamar(new InsumosController(), 'crear', $admin, ['periodo_id' => $otro, 'viaje_id' => $viaje['id'], 'fecha' => date('Y-m-d'), 'concepto' => 'x', 'monto_original' => 10]);
    afirmar('un insumo no se puede asignar a un viaje de otro periodo', $e === 400 && str_contains($d['error'], 'no pertenece'), json_encode($d));

    // ---------- Origen cruzado y URL original de IIS
    $r = new Ruteador();
    (require __DIR__ . '/../app/rutas.php')($r);
    $consulta = fn(string $sitio) => (function () use ($r, $sitio, $admin) {
        $_SERVER['HTTP_SEC_FETCH_SITE'] = $sitio;
        $res = $r->despachar(new Peticion('POST', '/api/reservas', [], ['sid' => $admin], []));
        unset($_SERVER['HTTP_SEC_FETCH_SITE']);
        return $res->estado();
    })();
    afirmar('una escritura de la API enviada desde otro sitio (cross-site o same-site) se rechaza', $consulta('cross-site') === 403 && $consulta('same-site') === 403);
    afirmar('desde el propio sitio (same-origin) y sin cabecera (scripts) sí pasa', $consulta('same-origin') !== 403 && $consulta('none') !== 403);
    $_SERVER['HTTP_X_ORIGINAL_URL'] = '/calendario?y=2026&m=8';
    $ruta = (new Peticion(null, null, []))->ruta;
    unset($_SERVER['HTTP_X_ORIGINAL_URL']);
    afirmar('bajo IIS la ruta se toma de X-Original-URL', $ruta === '/calendario', $ruta);
    $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
    afirmar('X-Forwarded-Proto no se cree si el servidor no está declarado tras un proxy', !(new Peticion('GET', '/', []))->esHttps());
    unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
});

terminar('REVISIÓN OK');
