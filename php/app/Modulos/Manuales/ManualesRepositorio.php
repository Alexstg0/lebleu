<?php
namespace Lebleu\Modulos\Manuales;

use Lebleu\Core\Db;

final class ManualesRepositorio
{
    public function porRol(string $rol): ?array
    {
        return Db::fila('SELECT nombre, datos FROM manuales WHERE rol = ?', [$rol]);
    }
}
