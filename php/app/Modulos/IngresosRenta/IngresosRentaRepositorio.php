<?php
namespace Lebleu\Modulos\IngresosRenta;

use Lebleu\Compartido\Movimientos\MovimientoRepositorio;

final class IngresosRentaRepositorio extends MovimientoRepositorio
{
    private const EDITABLES = ['cliente', 'fecha', 'monto', 'costos_asociados'];

    protected function tabla(): string
    {
        return 'ingresos_renta';
    }

    protected function columnasAlta(): array
    {
        return ['periodo_id', ...self::EDITABLES];
    }

    protected function columnasEdicion(): array
    {
        return self::EDITABLES;
    }
}
