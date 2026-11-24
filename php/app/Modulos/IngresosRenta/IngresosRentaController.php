<?php
namespace Lebleu\Modulos\IngresosRenta;

use Lebleu\Compartido\Entrada;
use Lebleu\Compartido\Movimientos\MovimientoControlador;
use Lebleu\Compartido\Movimientos\MovimientoRepositorio;

final class IngresosRentaController extends MovimientoControlador
{
    protected function repo(): MovimientoRepositorio
    {
        return new IngresosRentaRepositorio();
    }

    protected function tabla(): string
    {
        return 'ingresos_renta';
    }

    protected function valores(array $b, bool $esAlta): array
    {
        $v = [
            'cliente' => Entrada::texto($b, 'cliente', 'El cliente', 160),
            'fecha' => Entrada::fecha($b, 'fecha'),
            'monto' => Entrada::numero($b, 'monto', 'El monto'),
            'costos_asociados' => Entrada::numero($b, 'costos_asociados', 'Los costos asociados', false, 0),
        ];
        return $esAlta ? ['periodo_id' => Entrada::entero($b, 'periodo_id', 'El periodo')] + $v : $v;
    }

    protected function detalle(string $accion, array $b, ?array $previo): string
    {
        return match ($accion) {
            'crear' => "Renta \"{$b['cliente']}\" por \${$b['monto']}",
            'editar' => "Editó la renta \"{$b['cliente']}\" → \${$b['monto']}",
            default => "Borró la renta \"{$previo['cliente']}\" de \${$previo['monto']}",
        };
    }
}
