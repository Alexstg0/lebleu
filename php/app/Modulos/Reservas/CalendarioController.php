<?php
namespace Lebleu\Modulos\Reservas;

use Lebleu\Compartido\Encabezado;
use Lebleu\Compartido\Formato;
use Lebleu\Compartido\Politicas;
use Lebleu\Core\Peticion;
use Lebleu\Core\Respuesta;
use Lebleu\Core\Vista;
use Lebleu\Modulos\Catalogos\CatalogosServicio;
use Lebleu\Modulos\Periodos\PeriodosServicio;

// Pantalla Calendario: reservas del mes. La ven y la editan administrador, capitán y socios.
final class CalendarioController
{
    public function pagina(Peticion $p, array $args): Respuesta
    {
        [$usuario, $redireccion] = Politicas::requerirUsuario($p, ['admin', 'capitan', 'socio']);
        if ($redireccion) {
            return $redireccion;
        }
        $periodos = (new PeriodosServicio())->listar();
        $ultimo = $periodos[0] ?? null;
        $anio = (int) $p->consulta('y', 0) ?: (int) ($ultimo['anio'] ?? date('Y'));
        $mes = (int) $p->consulta('m', 0) ?: (int) ($ultimo['mes'] ?? date('n'));
        if ($mes < 1 || $mes > 12 || $anio < 2000 || $anio > 2100) {
            [$anio, $mes] = [(int) ($ultimo['anio'] ?? date('Y')), (int) ($ultimo['mes'] ?? date('n'))];
        }

        $html = Encabezado::topbar($periodos, (int) ($ultimo['id'] ?? 0), 'calendario', $usuario)
              . Vista::parcial(__DIR__ . '/vistas/calendario.php', [
                  'anio' => $anio, 'mes' => $mes, 'ultimo' => $ultimo,
                  'reservas' => (new ReservasServicio())->delMes($anio, $mes),
                  'socios' => (new CatalogosServicio())->todos()['socios'],
                  'hoy' => date('Y-m-d'),
              ]);
        return Respuesta::html(Vista::pagina(
            'Le Bleu — Calendario ' . Formato::mesNombre($mes) . ' ' . $anio, $html, ['topbar.js', 'imprimir.js', 'calendario.js']
        ));
    }
}
