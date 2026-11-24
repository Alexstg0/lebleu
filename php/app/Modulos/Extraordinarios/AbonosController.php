<?php
namespace Lebleu\Modulos\Extraordinarios;

use Lebleu\Compartido\Entrada;
use Lebleu\Compartido\Movimientos\MovimientoControlador;
use Lebleu\Compartido\Movimientos\MovimientoRepositorio;

// Abonos de los socios a los gastos extraordinarios.
final class AbonosController extends MovimientoControlador
{
    protected function repo(): MovimientoRepositorio
    {
        return new AbonosRepositorio();
    }

    protected function tabla(): string
    {
        return 'abonos_extraordinarios';
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
            'crear' => "Abono a extraordinarios de \${$b['monto']} (socio {$b['socio_id']})",
            'editar' => "Editó el abono → \${$b['monto']}",
            default => "Borró el abono de \${$previo['monto']}",
        };
    }
}
