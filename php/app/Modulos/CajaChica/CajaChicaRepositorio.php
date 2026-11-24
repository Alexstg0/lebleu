<?php
namespace Lebleu\Modulos\CajaChica;

use Lebleu\Compartido\Movimientos\MovimientoRepositorio;
use Lebleu\Core\Db;

final class CajaChicaRepositorio extends MovimientoRepositorio
{
    private const COLUMNAS = ['fecha', 'tipo', 'caja_numero', 'factura', 'proveedor', 'concepto', 'monto', 'abono', 'observaciones'];

    protected function tabla(): string
    {
        return 'caja_chica';
    }

    protected function columnasAlta(): array
    {
        return self::COLUMNAS;
    }

    protected function columnasEdicion(): array
    {
        return self::COLUMNAS;
    }

    public function todos(): array
    {
        return Db::filas(
            'SELECT id, fecha, tipo, caja_numero, factura, proveedor, concepto, monto, abono, observaciones FROM caja_chica ORDER BY fecha, id'
        );
    }
}
