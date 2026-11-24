<?php
namespace Lebleu\Modulos\Reportes;

use Lebleu\Compartido\Encabezado;
use Lebleu\Compartido\Formato;
use Lebleu\Compartido\Politicas;
use Lebleu\Core\Peticion;
use Lebleu\Core\Respuesta;
use Lebleu\Core\Vista;
use Lebleu\Modulos\Periodos\PeriodosServicio;

// Pantalla Reportes (administrador y socios): accesos a los PDF del periodo.
final class ReportesController
{
    public function pagina(Peticion $p, array $args): Respuesta
    {
        [$usuario, $redireccion] = Politicas::requerirUsuario($p, ['admin', 'socio']);
        if ($redireccion) {
            return $redireccion;
        }
        $periodos = (new PeriodosServicio())->listar();
        if (!$periodos) {
            return Respuesta::html(Vista::pagina('Le Bleu — Reportes', '<div class="empty">No hay periodos.</div>'));
        }
        $pedido = (int) $p->consulta('periodo', 0);
        $periodo = array_values(array_filter($periodos, fn($x) => (int) $x['id'] === $pedido))[0] ?? $periodos[0];
        $html = Encabezado::topbar($periodos, (int) $periodo['id'], 'reportes', $usuario)
              . Vista::parcial(__DIR__ . '/vistas/reportes.php', [
                  'periodos' => $periodos, 'periodoId' => (int) $periodo['id'],
                  'perTxt' => Formato::mesNombre((int) $periodo['mes']) . ' ' . $periodo['anio'],
              ]);
        return Respuesta::html(Vista::pagina('Le Bleu — Reportes', $html, ['topbar.js', 'selector-periodo.js', 'visor-pdf.js']));
    }
}
