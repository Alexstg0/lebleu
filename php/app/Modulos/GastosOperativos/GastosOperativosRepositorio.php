<?php
namespace Lebleu\Modulos\GastosOperativos;

use Lebleu\Compartido\Movimientos\MovimientoRepositorio;

final class GastosOperativosRepositorio extends MovimientoRepositorio
{
    private const EDITABLES = ['fecha', 'proveedor', 'concepto', 'categoria', 'moneda', 'monto_original', 'tipo_cambio', 'comprobante_folio'];

    protected function tabla(): string
    {
        return 'gastos_operativos';
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
