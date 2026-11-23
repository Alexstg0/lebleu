<?php
namespace Lebleu\Modulos\Adjuntos;

use Lebleu\Core\Db;

final class AdjuntosRepositorio
{
    public function porId(int $id): ?array
    {
        return Db::fila('SELECT nombre, datos, caja_id FROM adjuntos WHERE id = ?', [$id]);
    }
}
