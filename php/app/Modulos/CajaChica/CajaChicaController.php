<?php
namespace Lebleu\Modulos\CajaChica;

use Lebleu\Compartido\Entrada;
use Lebleu\Compartido\Movimientos\MovimientoControlador;
use Lebleu\Compartido\Movimientos\MovimientoRepositorio;

// Movimientos de caja chica (gastos y abonos): los capturan administrador y capitán.
final class CajaChicaController extends MovimientoControlador
{
    protected function repo(): MovimientoRepositorio
    {
        return new CajaChicaRepositorio();
    }

    protected function tabla(): string
    {
        return 'caja_chica';
    }

    protected function roles(): array
    {
        return ['admin', 'capitan'];
    }

    protected function rolesBorrar(): array
    {
        return ['admin', 'capitan'];
    }

    protected function valores(array $b, bool $esAlta): array
    {
        $abono = ($b['tipo'] ?? '') === 'abono';
        return [
            'fecha' => Entrada::fecha($b, 'fecha'),
            'tipo' => $abono ? 'abono' : 'gasto',
            'caja_numero' => Entrada::entero($b, 'caja_numero', 'El número de caja', false),
            'factura' => Entrada::textoOpcional($b, 'factura', 'La factura', 120),
            'proveedor' => Entrada::textoOpcional($b, 'proveedor', 'El proveedor', 200),
            'concepto' => Entrada::textoOpcional($b, 'concepto', 'El concepto', 400),
            'monto' => $abono ? 0 : Entrada::numero($b, 'monto', 'El monto', false, 0),
            'abono' => $abono ? Entrada::numero($b, 'abono', 'El abono', false, 0) : 0,
            'observaciones' => Entrada::textoOpcional($b, 'observaciones', 'Las observaciones', 1000),
        ];
    }

    protected function detalle(string $accion, array $b, ?array $previo): string
    {
        $caja = fn($v) => ($v ?? '') !== '' && $v !== null && $v != 0 ? $v : '—';
        return match ($accion) {
            'crear' => 'Caja ' . $caja($b['caja_numero'] ?? null) . ': ' . (($b['tipo'] ?? '') === 'abono' ? 'abono' : 'gasto')
                . " \"{$b['concepto']}\" por \$" . (($b['tipo'] ?? '') === 'abono' ? ($b['abono'] ?? 0) : ($b['monto'] ?? 0)),
            'editar' => "Editó el movimiento \"{$b['concepto']}\" de la caja " . $caja($b['caja_numero'] ?? null),
            default => "Borró \"{$previo['concepto']}\" de la caja " . $caja($previo['caja_numero'] ?? null),
        };
    }
}
