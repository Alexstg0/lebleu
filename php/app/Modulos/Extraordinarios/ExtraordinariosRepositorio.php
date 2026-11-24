<?php
namespace Lebleu\Modulos\Extraordinarios;

use Lebleu\Compartido\Movimientos\MovimientoRepositorio;
use Lebleu\Core\Db;

final class ExtraordinariosRepositorio extends MovimientoRepositorio
{
    private const EDITABLES = ['fecha', 'proveedor', 'concepto', 'categoria', 'moneda', 'monto_original', 'tipo_cambio', 'comprobante_folio'];

    protected function tabla(): string
    {
        return 'extraordinarios';
    }

    protected function columnasAlta(): array
    {
        return ['periodo_id', ...self::EDITABLES];
    }

    protected function columnasEdicion(): array
    {
        return self::EDITABLES;
    }

    public function marcarLiquidado(int $id, bool $liquidado): void
    {
        Db::ejecutar('UPDATE extraordinarios SET liquidado = ? WHERE id = ?', [$liquidado ? 1 : 0, $id]);
    }
}
