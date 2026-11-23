<?php
namespace Lebleu\Compartido;

use Lebleu\Core\Db;
use Lebleu\Core\Peticion;
use Lebleu\Core\Respuesta;

final class SaludController
{
    public function estado(Peticion $p, array $args): Respuesta
    {
        try {
            Db::fila('SELECT 1 AS ok');
            return Respuesta::json(['ok' => true, 'bd' => true]);
        } catch (\Throwable $e) {
            error_log('salud: ' . $e->getMessage());
            return Respuesta::json(['ok' => false, 'bd' => false], 503);
        }
    }
}
