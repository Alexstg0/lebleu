<?php
namespace Lebleu\Modulos\Extraordinarios;

use Lebleu\Compartido\Politicas;
use Lebleu\Core\Peticion;
use Lebleu\Core\Respuesta;

final class ExtraordinariosController
{
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
