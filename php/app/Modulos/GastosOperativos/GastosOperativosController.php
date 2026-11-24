<?php
namespace Lebleu\Modulos\GastosOperativos;

use Lebleu\Compartido\Entrada;
use Lebleu\Compartido\Movimientos\MovimientoControlador;
use Lebleu\Compartido\Movimientos\MovimientoRepositorio;

final class GastosOperativosController extends MovimientoControlador
{
    private const CATEGORIAS = ['nomina', 'impuesto', 'servicio', 'refaccion', 'consumible', 'admin', 'otro'];

    protected function repo(): MovimientoRepositorio
    {
        return new GastosOperativosRepositorio();
    }

    protected function tabla(): string
    {
        return 'gastos_operativos';
    }

    protected function valores(array $b, bool $esAlta): array
    {
        $moneda = Entrada::opcion($b, 'moneda', 'La moneda', ['MXN', 'USD'], 'MXN');
        $v = [
            'fecha' => Entrada::fecha($b, 'fecha'),
            'proveedor' => Entrada::texto($b, 'proveedor', 'El proveedor', 200),
            'concepto' => Entrada::texto($b, 'concepto', 'El concepto', 400),
            'categoria' => Entrada::opcion($b, 'categoria', 'La categoría', self::CATEGORIAS, 'otro'),
            'moneda' => $moneda,
            'monto_original' => Entrada::numero($b, 'monto_original', 'El monto'),
            'tipo_cambio' => Entrada::tipoCambio($b, $moneda),
            'comprobante_folio' => Entrada::textoOpcional($b, 'comprobante_folio', 'El folio', 120),
        ];
        return $esAlta ? ['periodo_id' => Entrada::entero($b, 'periodo_id', 'El periodo')] + $v : $v;
    }

    protected function detalle(string $accion, array $b, ?array $previo): string
    {
        $moneda = ($b['moneda'] ?? '') ?: 'MXN';
        return match ($accion) {
            'crear' => "Gasto \"{$b['concepto']}\" ({$b['proveedor']}) por \${$b['monto_original']} $moneda",
            'editar' => "Editó el gasto \"{$b['concepto']}\" → \${$b['monto_original']} $moneda",
            default => "Borró el gasto \"{$previo['concepto']}\" de \${$previo['monto_mxn']}",
        };
    }
}
