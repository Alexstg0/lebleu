<?php
namespace Lebleu\Modulos\Insumos;

use Lebleu\Compartido\Entrada;
use Lebleu\Compartido\Movimientos\MovimientoControlador;
use Lebleu\Compartido\Movimientos\MovimientoRepositorio;

final class InsumosController extends MovimientoControlador
{
    protected function repo(): MovimientoRepositorio
    {
        return new InsumosRepositorio();
    }

    protected function tabla(): string
    {
        return 'insumos';
    }

    protected function valores(array $b, bool $esAlta): array
    {
        $moneda = Entrada::opcion($b, 'moneda', 'La moneda', ['MXN', 'USD'], 'MXN');
        return [
            'periodo_id' => Entrada::entero($b, 'periodo_id', 'El periodo'),
            'viaje_id' => Entrada::entero($b, 'viaje_id', 'El viaje', false),
            'fecha' => Entrada::fecha($b, 'fecha'),
            'categoria' => Entrada::textoOpcional($b, 'categoria', 'La categoría', 40),
            'proveedor' => Entrada::textoOpcional($b, 'proveedor', 'El proveedor', 200),
            'concepto' => Entrada::textoOpcional($b, 'concepto', 'El concepto', 400),
            'moneda' => $moneda,
            'monto_original' => Entrada::numero($b, 'monto_original', 'El monto', false, 0),
            'tipo_cambio' => Entrada::tipoCambio($b, $moneda),
            'comprobante_folio' => Entrada::textoOpcional($b, 'comprobante_folio', 'El folio', 120),
        ];
    }

    protected function periodoDestino(array $b): ?int
    {
        return Entrada::entero($b, 'periodo_id', 'El periodo', false);
    }

    /** El viaje al que se asigna un insumo debe ser del mismo periodo (si no, se movería el costo de un mes ajeno). */
    protected function validar(array $valores, ?array $previo): void
    {
        $viaje = (int) ($valores['viaje_id'] ?? 0);
        if ($viaje && (new InsumosRepositorio())->periodoDeViaje($viaje) !== (int) $valores['periodo_id']) {
            throw new \InvalidArgumentException('El viaje no pertenece al periodo del insumo.');
        }
    }

    protected function alGuardar(int $id, array $b, ?array $previo): void
    {
        $repo = new InsumosRepositorio();
        $viajeNuevo = Entrada::entero($b, 'viaje_id', 'El viaje', false);
        $viajePrevio = isset($previo['viaje_id']) ? (int) $previo['viaje_id'] : null;
        if ($viajePrevio && $viajePrevio !== $viajeNuevo) {
            $repo->sincronizarViaje($viajePrevio);
        }
        $repo->sincronizarViaje($viajeNuevo);
    }

    protected function alBorrar(array $previo): void
    {
        (new InsumosRepositorio())->sincronizarViaje(isset($previo['viaje_id']) ? (int) $previo['viaje_id'] : null);
    }

    protected function detalle(string $accion, array $b, ?array $previo): string
    {
        return match ($accion) {
            'crear' => "Insumo \"{$b['concepto']}\" por \${$b['monto_original']} (viaje " . (($b['viaje_id'] ?? null) ?: '—') . ')',
            'editar' => "Editó el insumo \"{$b['concepto']}\" → \${$b['monto_original']}",
            default => "Borró el insumo \"{$previo['concepto']}\" de \${$previo['monto_mxn']}",
        };
    }
}
