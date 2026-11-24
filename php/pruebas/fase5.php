<?php
// Fase 5: reportes en PDF, fusionador de PDF, recibos, archivo al cerrar un periodo y recibos de cargos recurrentes.
// Todo sobre la base real, dentro de una transacción que se revierte. Uso: php pruebas/fase5.php
require __DIR__ . '/ayuda.php';

use Lebleu\Compartido\Pdf\Fusionador;
use Lebleu\Compartido\Pdf\LectorPdf;
use Lebleu\Compartido\Pdf\Lienzo;
use Lebleu\Core\Db;
use Lebleu\Modulos\Reportes\{ReportesPdfController, ReciboGastoPdf};
use Lebleu\Servicios;

$esPdf = fn($r) => str_starts_with($r->cuerpo(), '%PDF-') && $r->cabecera('Content-Type') === 'application/pdf';
$paginas = fn(string $pdf) => count((new LectorPdf($pdf))->paginas());
$texto = function (string $pdf): ?string {
    if (!trim((string) shell_exec('command -v pdftotext'))) {
        return null;
    }
    $t = tempnam(sys_get_temp_dir(), 'f5');
    file_put_contents($t, $pdf);
    $s = (string) shell_exec('pdftotext ' . escapeshellarg($t) . ' - 2>/dev/null');
    unlink($t);
    return $s;
};

// ---------- Medida de texto y fusionador (sin base de datos)
$medidor = new Lienzo();
afirmar('el ancho del texto incluye el kerning como pdf-lib', abs($medidor->ancho('PPT Performance T…', 8) - 77.848) < 0.001 && abs($medidor->ancho('Refacciones Mercury: Se…', 8) - 96.96) < 0.001);

$una = function (string $t): string {
    $l = new Lienzo();
    $l->nuevaPagina();
    $l->texto($t, 50, 700, 14, true, [0, 0, 0]);
    return $l->salida();
};
$f = new Fusionador();
$f->agregar($una('uno'));
$f->agregar($una('dos'));
$unido = $f->generar();
afirmar('el fusionador une documentos y conserva el número de páginas', $f->total() === 2 && $paginas($unido) === 2);
afirmar('el fusionador cabe en un PDF que vuelve a leerse y fusionarse', $paginas((function () use ($unido, $una) { $g = new Fusionador(); $g->agregar($unido); $g->agregar($una('tres')); return $g->generar(); })()) === 3);
$antes = $f->total();
$fallo = false;
try {
    $f->agregar('esto no es un PDF');
} catch (\Throwable) {
    $fallo = true;
}
afirmar('un archivo que no es PDF se rechaza sin dejar páginas a medias', $fallo && $f->total() === $antes);
$cifrado = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 10 10]>>endobj\ntrailer<</Root 1 0 R/Encrypt 4 0 R>>\n%%EOF";
$fallo = false;
try {
    (new Fusionador())->agregar($cifrado);
} catch (\Throwable) {
    $fallo = true;
}
afirmar('un PDF cifrado se rechaza', $fallo);
$sinXref = preg_replace('/xref.*$/s', '', $una('daño')); // PDF sin tabla de referencias: se reconstruye
afirmar('un PDF con la tabla de referencias dañada se reconstruye', $paginas($sinXref) === 1);

// Todos los comprobantes reales de la base se pueden fusionar y conservan sus páginas
$adjuntos = Db::filas('SELECT id, datos FROM adjuntos');
$g = new Fusionador();
$esperadas = 0;
$malos = [];
foreach ($adjuntos as $a) {
    try {
        $pdf = (string) base64_decode($a['datos']);
        $n = $paginas($pdf);
        $g->agregar($pdf);
        $esperadas += $n;
    } catch (\Throwable $e) {
        $malos[] = $a['id'];
    }
}
afirmar('los ' . count($adjuntos) . ' comprobantes de la base se fusionan sin error', !$malos, implode(',', $malos));
afirmar('el PDF fusionado conserva todas las páginas', $g->total() === $esperadas && $paginas($g->generar()) === $esperadas);

Db::simular(function () use ($esPdf, $paginas, $texto) {
    $admin = usuario('admin', 'f5');
    $socio = usuario('socio', 'f5');
    $capitan = usuario('capitan', 'f5');
    $c = new ReportesPdfController();
    $periodo = Db::fila("SELECT p.id, p.anio, p.mes FROM periodos p WHERE EXISTS (SELECT 1 FROM viajes v WHERE v.periodo_id = p.id) AND EXISTS (SELECT 1 FROM gastos_operativos g WHERE g.periodo_id = p.id) ORDER BY p.id DESC LIMIT 1");
    $pid = (int) $periodo['id'];

    // ---------- Permisos y validaciones de los reportes por periodo
    foreach (['soportes', 'marineros', 'completo'] as $accion) {
        afirmar("$accion: administrador y socio reciben un PDF", $esPdf(llamar($c, $accion, $admin, [], ['periodo' => $pid])[2]) && $esPdf(llamar($c, $accion, $socio, [], ['periodo' => $pid])[2]));
        afirmar("$accion: el capitán y quien no inició sesión reciben 403", llamar($c, $accion, $capitan, [], ['periodo' => $pid])[0] === 403 && llamar($c, $accion, null, [], ['periodo' => $pid])[0] === 403);
        afirmar("$accion: sin periodo responde 400", llamar($c, $accion, $admin)[0] === 400);
        $r = llamar($c, $accion, $admin, [], ['periodo' => $pid])[2];
        afirmar("$accion: no se indexa ni se adivina el tipo (nosniff, sin caché)", $r->cabecera('X-Content-Type-Options') === 'nosniff' && str_contains((string) $r->cabecera('Cache-Control'), 'no-store'));
    }
    Db::ejecutar("INSERT INTO periodos (embarcacion_id, anio, mes, tipo_cambio, precio_litro, cargo_administracion, estado) VALUES (1, 2099, 12, 1, 1, 0, 'abierto')");
    $vacio = (int) Db::ultimoId();
    afirmar('soportes sin comprobantes responde 404', llamar($c, 'soportes', $admin, [], ['periodo' => $vacio])[0] === 404);
    afirmar('marineros sin viajes responde 404', llamar($c, 'marineros', $admin, [], ['periodo' => $vacio])[0] === 404);
    afirmar('periodo inexistente responde 404', llamar($c, 'completo', $admin, [], ['periodo' => 987654])[0] === 404);

    // El reporte completo = estado de cuenta + soportes (sin recibos de marinero) + marineros
    $est = (new \Lebleu\Modulos\Reportes\EstadoCuentaPdf())->generar($pid);
    $sop = (new \Lebleu\Modulos\Reportes\SoportesPdf())->generar($pid, false);
    $mar = (new \Lebleu\Modulos\Reportes\MarinerosPdf())->reporte($pid);
    $completo = llamar($c, 'completo', $admin, [], ['periodo' => $pid])[2]->cuerpo();
    afirmar('el reporte completo suma estado de cuenta, soportes y marineros', $paginas($completo) === $paginas($est) + $paginas($sop) + $paginas($mar));

    // Un comprobante dañado no tumba el reporte: se sustituye por una hoja que lo avisa
    Db::ejecutar("INSERT INTO adjuntos (gasto_id, nombre, mime, datos, generado) VALUES ((SELECT id FROM gastos_operativos WHERE periodo_id = ? ORDER BY fecha, id LIMIT 1), 'roto.pdf', 'application/pdf', ?, 0)", [$pid, base64_encode('basura')]);
    $conRoto = llamar($c, 'soportes', $admin, [], ['periodo' => $pid])[2]->cuerpo();
    $t = $texto($conRoto);
    afirmar('un comprobante dañado se reemplaza por una hoja de aviso', $t === null || str_contains($t, 'Comprobante no se pudo incrustar'));

    // ---------- Contenido del PDF (texto extraído)
    $te = $texto($est);
    if ($te !== null) {
        afirmar('el estado de cuenta trae el título del periodo y los socios', str_contains($te, 'Estado de cuenta') && str_contains($te, 'BALANCE OPERATIVO'));
        afirmar('el reporte de marineros trae el resumen y los recibos', str_contains((string) $texto($mar), 'Relación de viajes y pagos a marineros') && str_contains((string) $texto($mar), 'TOTAL A PAGAR'));
    }

    // ---------- Caja chica
    afirmar('caja chica: administrador y capitán reciben un PDF', $esPdf(llamar($c, 'cajaChica', $admin, [], ['caja' => 'todas'])[2]) && $esPdf(llamar($c, 'cajaChica', $capitan, [], ['caja' => 'todas'])[2]));
    afirmar('caja chica: el socio recibe 403', llamar($c, 'cajaChica', $socio, [], ['caja' => 'todas'])[0] === 403);
    afirmar('caja chica: caja inválida responde 400', llamar($c, 'cajaChica', $admin, [], ['caja' => 'abc'])[0] === 400);
    afirmar('caja chica: caja sin movimientos responde 404', llamar($c, 'cajaChica', $admin, [], ['caja' => '987'])[0] === 404);
    $n = (int) Db::fila('SELECT caja_numero n FROM caja_chica WHERE caja_numero IS NOT NULL ORDER BY id LIMIT 1')['n'];
    afirmar('caja chica: una caja concreta da un PDF', $esPdf(llamar($c, 'cajaChica', $admin, [], ['caja' => (string) $n, 'responsable' => 'Prueba'])[2]));
    $ancho = fn(string $pdf) => (float) (preg_match('/MediaBox\s*\[\s*0 0 ([\d.]+) /', $pdf, $m) ? $m[1] : 0);
    afirmar('la carátula de caja chica es horizontal', $ancho(llamar($c, 'cajaChica', $admin, [], ['caja' => 'todas'])[2]->cuerpo()) > 800);

    // ---------- Recibo de viaje
    $viaje = (int) Db::fila('SELECT id FROM viajes WHERE periodo_id = ? ORDER BY id LIMIT 1', [$pid])['id'];
    afirmar('recibo de viaje: el administrador recibe un PDF de una página', $esPdf($r = llamar($c, 'reciboViaje', $admin, [], ['id' => $viaje])[2]) && $paginas($r->cuerpo()) === 1);
    afirmar('recibo de viaje: socio y capitán reciben 403', llamar($c, 'reciboViaje', $socio, [], ['id' => $viaje])[0] === 403 && llamar($c, 'reciboViaje', $capitan, [], ['id' => $viaje])[0] === 403);
    afirmar('recibo de viaje: sin id 400 y viaje inexistente 404', llamar($c, 'reciboViaje', $admin)[0] === 400 && llamar($c, 'reciboViaje', $admin, [], ['id' => 987654])[0] === 404);

    // ---------- Recibo de gasto
    $gasto = Db::fila("SELECT id, comprobante_folio FROM gastos_operativos WHERE categoria = 'nomina' ORDER BY id LIMIT 1");
    $gid = (int) $gasto['id'];
    $generados = fn() => (int) Db::fila('SELECT COUNT(*) n FROM adjuntos WHERE gasto_id = ? AND generado = 1', [$gid])['n'];
    [$estado, $datos] = llamar($c, 'reciboGasto', $admin, ['gasto_id' => $gid]);
    afirmar('recibo de gasto: se genera y se adjunta', $estado === 200 && ($datos['ok'] ?? false) && $generados() === 1);
    $nombre = Db::fila('SELECT nombre FROM adjuntos WHERE gasto_id = ? AND generado = 1', [$gid])['nombre'];
    afirmar('recibo de gasto: se llama "Recibo {folio}.pdf"', $nombre === 'Recibo ' . $gasto['comprobante_folio'] . '.pdf', $nombre);
    llamar($c, 'reciboGasto', $admin, ['gasto_id' => $gid]);
    afirmar('recibo de gasto: al regenerarlo reemplaza el anterior', $generados() === 1);
    $guardado = (string) base64_decode(Db::fila('SELECT datos FROM adjuntos WHERE gasto_id = ? AND generado = 1', [$gid])['datos']);
    afirmar('recibo de gasto: el adjunto es un PDF de una página', str_starts_with($guardado, '%PDF-') && $paginas($guardado) === 1);
    afirmar('recibo de gasto: solo el administrador', llamar($c, 'reciboGasto', $socio, ['gasto_id' => $gid])[0] === 403 && llamar($c, 'reciboGasto', null, ['gasto_id' => $gid])[0] === 403);
    afirmar('recibo de gasto: sin gasto 400 y gasto inexistente 404', llamar($c, 'reciboGasto', $admin)[0] === 400 && llamar($c, 'reciboGasto', $admin, ['gasto_id' => 987654])[0] === 404);

    // ---------- Cierre de periodo: archiva el estado de cuenta
    Db::ejecutar('DELETE FROM adjuntos WHERE periodo_id = ? AND generado = 1', [$pid]);
    $s = Servicios::periodos();
    $s->reabrir($pid);
    afirmar('cerrar un periodo archiva su estado de cuenta en PDF', $s->cerrar($pid) === true);
    $arch = Db::filas('SELECT nombre, datos FROM adjuntos WHERE periodo_id = ? AND generado = 1', [$pid]);
    afirmar('el archivo es único y se llama "Estado de cuenta AAAA-MM.pdf"', count($arch) === 1 && $arch[0]['nombre'] === sprintf('Estado de cuenta %d-%02d.pdf', $periodo['anio'], $periodo['mes']), (string) ($arch[0]['nombre'] ?? ''));
    afirmar('el archivo guardado es un PDF', str_starts_with((string) base64_decode($arch[0]['datos'] ?? ''), '%PDF-'));
    $s->reabrir($pid);
    $s->cerrar($pid);
    afirmar('volver a cerrar reemplaza el archivo anterior', (int) Db::fila('SELECT COUNT(*) n FROM adjuntos WHERE periodo_id = ? AND generado = 1', [$pid])['n'] === 1);

    // ---------- Cargos recurrentes: cada uno nace con su recibo
    Db::ejecutar("INSERT INTO periodos (embarcacion_id, anio, mes, tipo_cambio, precio_litro, cargo_administracion, estado) VALUES (1, 2098, 3, 1, 1, 0, 'abierto')");
    $pr = Db::fila('SELECT p.id, p.anio, p.mes, e.nombre AS embarcacion, e.razon_social FROM periodos p JOIN embarcaciones e ON e.id = p.embarcacion_id WHERE p.id = ?', [Db::ultimoId()]);
    $r = Servicios::recurrentes()->asegurar($pr, '2098-03-31', '2098-03-01');
    $conRecibo = (int) Db::fila('SELECT COUNT(*) n FROM gastos_operativos g JOIN adjuntos a ON a.gasto_id = g.id AND a.generado = 1 WHERE g.periodo_id = ? AND g.clave_recurrente IS NOT NULL', [$pr['id']])['n'];
    afirmar('cada cargo recurrente se genera con su recibo en PDF', ($r['nomina'] + $r['admin']) > 0 && $conRecibo === $r['nomina'] + $r['admin'], "cargos=" . ($r['nomina'] + $r['admin']) . " recibos=$conRecibo");
    $folio = Db::fila("SELECT g.comprobante_folio, a.nombre FROM gastos_operativos g JOIN adjuntos a ON a.gasto_id = g.id WHERE g.periodo_id = ? AND g.categoria = 'admin'", [$pr['id']]);
    afirmar('el recibo del cargo administrativo lleva el folio ADM-AAAA-MM', $folio && $folio['nombre'] === 'Recibo ' . $folio['comprobante_folio'] . '.pdf' && str_starts_with($folio['comprobante_folio'], 'ADM-2098-03'));
});

terminar('FASE 5 OK');
