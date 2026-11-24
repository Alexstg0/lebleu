<?php
namespace Lebleu\Modulos\Insumos;

use Lebleu\Compartido\Movimientos\MovimientoRepositorio;
use Lebleu\Core\Db;

final class InsumosRepositorio extends MovimientoRepositorio
{
    private const COLUMNAS = ['periodo_id', 'viaje_id', 'fecha', 'categoria', 'proveedor', 'concepto', 'moneda', 'monto_original', 'tipo_cambio', 'comprobante_folio'];

    protected function tabla(): string
    {
        return 'insumos';
    }

    protected function columnasAlta(): array
    {
        return self::COLUMNAS;
    }

    protected function columnasEdicion(): array
    {
        return self::COLUMNAS;
    }

    /** viajes.costo_consumibles = suma de los insumos de ese viaje (alimenta el gasto variable del socio). */
    public function sincronizarViaje(?int $viajeId): void
    {
        if ($viajeId) {
            Db::ejecutar(
                'UPDATE viajes SET costo_consumibles = (SELECT COALESCE(SUM(monto_mxn), 0) FROM insumos WHERE viaje_id = ?) WHERE id = ?',
                [$viajeId, $viajeId]
            );
        }
    }

    public function periodoDeViaje(int $viajeId): ?int
    {
        $f = Db::fila('SELECT periodo_id FROM viajes WHERE id = ?', [$viajeId]);
        return $f ? (int) $f['periodo_id'] : null;
    }
}
