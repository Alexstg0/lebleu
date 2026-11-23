<?php
// Escribe en JSON el estado de cuenta de TODOS los periodos según la versión PHP (para compararlo con la original).
require __DIR__ . '/../app/arranque.php';

use Lebleu\Modulos\EstadoCuenta\EstadoCuentaServicio;
use Lebleu\Modulos\Periodos\PeriodosRepositorio;

$periodos = (new PeriodosRepositorio())->listar();
$salida = ['periodos' => $periodos, 'estados' => []];
foreach ($periodos as $p) {
    $salida['estados'][$p['id']] = (new EstadoCuentaServicio())->obtener((int) $p['id']);
}
echo json_encode($salida, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
