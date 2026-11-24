<?php
// Genera con la versión PHP los mismos PDF que pruebas/referencia/pdfs.mjs (mismos nombres) para compararlos.
// Escribe adjuntos (recibos de gasto): usar una BD desechable (LEBLEU_CONFIG). Uso: SALIDA=/ruta php pruebas/pdfs_php.php
require __DIR__ . '/../app/arranque.php';

use Lebleu\Compartido\Pdf\Fusionador;
use Lebleu\Core\Db;
use Lebleu\Modulos\Reportes\{CajaPdf, EstadoCuentaPdf, MarinerosPdf, ReciboGastoPdf, SoportesPdf};

$salida = getenv('SALIDA') ?: '/tmp/pdf_php';
@mkdir($salida, 0777, true);
$guardar = function (string $nombre, ?string $bytes) use ($salida) {
    $bytes === null ? print("sin contenido: $nombre\n") : file_put_contents("$salida/$nombre.pdf", $bytes);
};
foreach (Db::filas('SELECT id FROM periodos ORDER BY id') as $p) {
    $id = (int) $p['id'];
    $e = (new EstadoCuentaPdf())->generar($id);
    $s = (new SoportesPdf())->generar($id);
    $sSin = (new SoportesPdf())->generar($id, false);
    $m = (new MarinerosPdf())->reporte($id);
    $guardar("estado_$id", $e);
    $guardar("soportes_$id", $s);
    $guardar("soportes_sin_marineros_$id", $sSin);
    $guardar("marineros_$id", $m);
    $partes = array_filter([$e, $sSin, $m]);
    if ($partes) {
        $f = new Fusionador();
        foreach ($partes as $parte) {
            $f->agregar($parte);
        }
        $guardar("completo_$id", $f->generar());
    }
}
foreach (Db::filas('SELECT id FROM viajes ORDER BY id LIMIT 6') as $v) {
    $guardar('recibo_viaje_' . $v['id'], (new MarinerosPdf())->reciboViaje((int) $v['id']));
}
$guardar('caja_todas', (new CajaPdf())->generar(null, 'Gabriel Preciado'));
foreach (Db::filas('SELECT DISTINCT caja_numero FROM caja_chica WHERE caja_numero IS NOT NULL ORDER BY 1') as $c) {
    $guardar('caja_' . $c['caja_numero'], (new CajaPdf())->generar((int) $c['caja_numero'], 'Gabriel Preciado'));
}
foreach (Db::filas("SELECT id FROM gastos_operativos WHERE categoria IN ('nomina','admin','servicio') ORDER BY id LIMIT 4") as $g) {
    (new ReciboGastoPdf())->paraGasto((int) $g['id']);
    $a = Db::fila('SELECT datos FROM adjuntos WHERE gasto_id = ? AND generado = 1 ORDER BY id DESC LIMIT 1', [$g['id']]);
    $guardar('recibo_gasto_' . $g['id'], $a ? base64_decode($a['datos']) : null);
}
