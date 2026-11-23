<?php
// Único punto de entrada. IIS (web.config) y `php -S` mandan aquí toda ruta que no sea un archivo.
// Servidor de desarrollo de PHP (php -S): los archivos reales (css, js, imágenes) los sirve él mismo.
if (PHP_SAPI === 'cli-server') {
    $archivo = __DIR__ . rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    if (is_file($archivo) && realpath($archivo) !== __FILE__) {
        return false;
    }
}

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
