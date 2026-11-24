<?php
namespace Lebleu\Modulos\Aportaciones;

use Lebleu\Compartido\Movimientos\MovimientoRepositorio;

final class AportacionesRepositorio extends MovimientoRepositorio
{
    private const EDITABLES = ['socio_id', 'fecha', 'monto', 'metodo', 'referencia'];

    protected function tabla(): string
    {
        return 'aportaciones';
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
