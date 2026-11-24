<?php
namespace Lebleu\Modulos\Aportaciones;

use Lebleu\Compartido\Entrada;
use Lebleu\Compartido\Movimientos\MovimientoControlador;
use Lebleu\Compartido\Movimientos\MovimientoRepositorio;

final class AportacionesController extends MovimientoControlador
{
    protected function repo(): MovimientoRepositorio
    {
        return new AportacionesRepositorio();
    }

    protected function tabla(): string
    {
        return 'aportaciones';
    }

    protected function valores(array $b, bool $esAlta): array
    {
        $v = [
            'socio_id' => Entrada::entero($b, 'socio_id', 'El socio'),
            'fecha' => Entrada::fecha($b, 'fecha'),
            'monto' => Entrada::numero($b, 'monto', 'El monto'),
            'metodo' => Entrada::textoOpcional($b, 'metodo', 'El método', 80),
            'referencia' => Entrada::textoOpcional($b, 'referencia', 'La referencia', 160),
        ];
        return $esAlta ? ['periodo_id' => Entrada::entero($b, 'periodo_id', 'El periodo')] + $v : $v;
    }

    protected function detalle(string $accion, array $b, ?array $previo): string
    {
        return match ($accion) {
            'crear' => "Aportación de \${$b['monto']} (socio {$b['socio_id']})",
            'editar' => "Editó la aportación → \${$b['monto']}",
            default => "Borró la aportación de \${$previo['monto']}",
        };
    }
}
