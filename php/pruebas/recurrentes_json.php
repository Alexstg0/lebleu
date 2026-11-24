<?php
// Corre los cargos recurrentes de PHP sobre la base real DENTRO de una transacción que se revierte,
// y escribe los cargos que quedan en el mismo formato que referencia/recurrentes.mjs.
// Uso: php pruebas/recurrentes_json.php 2026-11-24
require __DIR__ . '/../app/arranque.php';

use Lebleu\Core\Db;
use Lebleu\Modulos\Periodos\PeriodosRepositorio;
use Lebleu\Modulos\Recurrentes\RecurrentesServicio;

$hoy = $argv[1] ?? date('Y-m-d');
$salida = Db::simular(function () use ($hoy) {
    $resumen = [];
    $servicio = new RecurrentesServicio();
    foreach ((new PeriodosRepositorio())->listar() as $p) {
        $resumen[] = ['periodo' => (int) $p['id']] + $servicio->asegurar($p, $hoy);
    }
    usort($resumen, fn($a, $b) => $a['periodo'] <=> $b['periodo']);
    $gastos = Db::filas('SELECT periodo_id, fecha, proveedor, concepto, categoria, moneda, monto_original, tipo_cambio, monto_mxn, comprobante_folio, clave_recurrente
                           FROM gastos_operativos WHERE clave_recurrente IS NOT NULL ORDER BY clave_recurrente');
    return ['resumen' => $resumen, 'gastos' => $gastos];
});
echo json_encode($salida, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
