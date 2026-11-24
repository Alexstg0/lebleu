<?php
namespace Lebleu\Modulos\Extraordinarios;

use Lebleu\Compartido\Movimientos\MovimientoRepositorio;

final class AbonosRepositorio extends MovimientoRepositorio
{
    private const EDITABLES = ['socio_id', 'fecha', 'monto', 'metodo', 'referencia'];

    protected function tabla(): string
    {
        return 'abonos_extraordinarios';
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
