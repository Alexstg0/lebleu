<?php
namespace Lebleu\Modulos\Extraordinarios;

use Lebleu\Compartido\Entrada;
use Lebleu\Compartido\Movimientos\MovimientoControlador;
use Lebleu\Compartido\Movimientos\MovimientoRepositorio;
use Lebleu\Compartido\Politicas;
use Lebleu\Core\Peticion;
use Lebleu\Core\Respuesta;

final class ExtraordinariosController extends MovimientoControlador
{
    private const CATEGORIAS = ['muelle', 'refaccion', 'varada', 'otro'];

    protected function repo(): MovimientoRepositorio
    {
        return new ExtraordinariosRepositorio();
    }

    protected function tabla(): string
    {
        return 'extraordinarios';
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
            'crear' => "Extraordinario \"{$b['concepto']}\" por \${$b['monto_original']} $moneda",
            'editar' => "Editó el extraordinario \"{$b['concepto']}\"",
            default => "Borró el extraordinario \"{$previo['concepto']}\" de \${$previo['monto_mxn']}",
        };
    }

    // Marca un gasto extraordinario como pagado (liquidado) o pendiente. Solo admin.
    public function pagado(Peticion $p, array $args): Respuesta
    {
        if (!Politicas::guardiaApi($p, ['admin'])) {
            return Respuesta::error('No autorizado.', 403);
        }
        try {
            $d = $p->json();
            $id = (int) ($d['id'] ?? 0);
            if (!$id) {
                return Respuesta::error('Falta el gasto.', 400);
            }
            (new ExtraordinariosRepositorio())->marcarLiquidado($id, !empty($d['liquidado']));
            return Respuesta::json(['ok' => true]);
        } catch (\Throwable $e) {
            error_log('api extraordinarios/pagado: ' . $e->getMessage());
            return Respuesta::error('No se pudo actualizar.', 400);
        }
    }
}
