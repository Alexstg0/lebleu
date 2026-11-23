<?php
namespace Lebleu\Modulos\Periodos;

use Lebleu\Compartido\Encabezado;
use Lebleu\Compartido\Politicas;
use Lebleu\Core\Peticion;
use Lebleu\Core\Respuesta;
use Lebleu\Core\Vista;

final class PeriodosPaginaController
{
    public function pagina(Peticion $p, array $args): Respuesta
    {
        [$usuario, $redireccion] = Politicas::requerirUsuario($p, ['admin']);
        if ($redireccion) {
            return $redireccion;
        }
        $resumen = (new PeriodosServicio())->resumen();
        $html = Encabezado::topbar($resumen['periodos'], (int) ($resumen['ultimo']['id'] ?? 0), 'periodos', $usuario)
              . Vista::parcial(__DIR__ . '/vistas/periodos.php', $resumen);
        return Respuesta::html(Vista::pagina('Le Bleu — Control de Embarcación', $html, ['topbar.js', 'visor-pdf.js', 'periodos.js']));
    }
}
