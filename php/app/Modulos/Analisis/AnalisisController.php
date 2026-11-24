<?php
namespace Lebleu\Modulos\Analisis;

use Lebleu\Compartido\Encabezado;
use Lebleu\Compartido\Politicas;
use Lebleu\Core\Peticion;
use Lebleu\Core\Respuesta;
use Lebleu\Core\Vista;
use Lebleu\Modulos\Periodos\PeriodosServicio;

// Pantalla Análisis (administrador y socios): tendencias de gasto y operación.
final class AnalisisController
{
    public function pagina(Peticion $p, array $args): Respuesta
    {
        [$usuario, $redireccion] = Politicas::requerirUsuario($p, ['admin', 'socio']);
        if ($redireccion) {
            return $redireccion;
        }
        $periodos = (new PeriodosServicio())->listar();
        $repo = new AnalisisRepositorio();
        $html = Encabezado::topbar($periodos, (int) ($periodos[0]['id'] ?? 0), 'analisis', $usuario)
              . Vista::parcial(__DIR__ . '/vistas/analisis.php', ['porMes' => $repo->porMes(), 'viajes' => $repo->viajes()]);
        return Respuesta::html(Vista::pagina('Le Bleu — Análisis', $html, ['topbar.js', 'selector-periodo.js', 'imprimir.js']));
    }
}
