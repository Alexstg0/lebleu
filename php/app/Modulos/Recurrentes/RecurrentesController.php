<?php
namespace Lebleu\Modulos\Recurrentes;

use Lebleu\Servicios;
use Lebleu\Compartido\Politicas;
use Lebleu\Core\Peticion;
use Lebleu\Core\Respuesta;
use Lebleu\Modulos\Periodos\PeriodosServicio;

final class RecurrentesController
{
    // Botón "Generar nómina y administrativo" (solo administrador): un periodo, o todos si no se indica.
    public function generar(Peticion $p, array $args): Respuesta
    {
        if (!Politicas::guardiaApi($p, ['admin'])) {
            return Respuesta::error('No autorizado.', 403);
        }
        try {
            $pedido = (int) ($p->json()['periodoId'] ?? 0);
            $periodos = (new PeriodosServicio())->listar();
            if ($pedido) {
                $periodos = array_values(array_filter($periodos, fn($x) => (int) $x['id'] === $pedido));
                if ($periodos && $periodos[0]['estado'] === 'cerrado') {
                    return Respuesta::error('El periodo está cerrado: ya no se generan cargos en él. Reábrelo en la pestaña Periodos.', 400);
                }
            }
            $periodos = array_values(array_filter($periodos, fn($x) => $x['estado'] !== 'cerrado'));
            $servicio = Servicios::recurrentes();
            $nomina = $admin = 0;
            foreach ($periodos as $periodo) {
                $r = $servicio->asegurar($periodo, date('Y-m-d'));
                $nomina += $r['nomina'];
                $admin += $r['admin'];
            }
            return Respuesta::json(['ok' => true, 'nomina' => $nomina, 'admin' => $admin]);
        } catch (\Throwable $e) {
            error_log('api recurrentes: ' . $e->getMessage());
            return Respuesta::error('No se pudieron generar los cargos.', 400);
        }
    }
}
