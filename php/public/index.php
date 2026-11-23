<?php
// Único punto de entrada. IIS (web.config) y `php -S` mandan aquí toda ruta que no sea un archivo.
require __DIR__ . '/../app/arranque.php';

use Lebleu\Core\Peticion;
use Lebleu\Core\Respuesta;
use Lebleu\Core\Ruteador;

$peticion = new Peticion();
try {
    $ruteador = new Ruteador();
    (require __DIR__ . '/../app/rutas.php')($ruteador);
    $respuesta = $ruteador->despachar($peticion);
} catch (\Throwable $e) {
    error_log('error no controlado: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    $respuesta = $peticion->esApi() ? Respuesta::error('Error interno.', 500)
                                    : Respuesta::html('<h1>Error interno</h1>', 500);
}
$respuesta->enviar($peticion->esHttps());
