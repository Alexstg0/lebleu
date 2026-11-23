<?php
namespace Lebleu\Modulos\Extraordinarios;

use Lebleu\Core\Db;

final class ExtraordinariosRepositorio
{
    public function marcarLiquidado(int $id, bool $liquidado): void
    {
        Db::ejecutar('UPDATE extraordinarios SET liquidado = ? WHERE id = ?', [$liquidado ? 1 : 0, $id]);
    }
}
