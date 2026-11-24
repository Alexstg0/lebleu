<?php
namespace Lebleu\Modulos\GastosOperativos;

use Lebleu\Compartido\Movimientos\MovimientoRepositorio;

use Lebleu\Core\Db;

final class GastosOperativosRepositorio extends MovimientoRepositorio
{
    private const EDITABLES = ['fecha', 'proveedor', 'concepto', 'categoria', 'moneda', 'monto_original', 'tipo_cambio', 'comprobante_folio'];

    protected function tabla(): string
    {
        return 'gastos_operativos';
    }

    protected function columnasAlta(): array
    {
        return ['periodo_id', 'clave_recurrente', ...self::EDITABLES];
    }

    protected function columnasEdicion(): array
    {
        return self::EDITABLES;
    }

    public function existeClaveRecurrente(string $clave): bool
    {
        return (bool) Db::fila('SELECT id FROM gastos_operativos WHERE clave_recurrente = ?', [$clave]);
    }
}
