<?php
namespace Lebleu\Compartido;

use Lebleu\Core\Db;

final class SaludRepositorio
{
    public function responde(): bool
    {
        return Db::fila('SELECT 1 AS ok') !== null;
    }
}
