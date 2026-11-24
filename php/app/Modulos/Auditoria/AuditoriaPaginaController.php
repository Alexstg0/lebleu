<?php
namespace Lebleu\Modulos\Auditoria;

use Lebleu\Compartido\Encabezado;
use Lebleu\Compartido\Politicas;
use Lebleu\Core\Peticion;
use Lebleu\Core\Respuesta;
use Lebleu\Core\Vista;
use Lebleu\Modulos\Periodos\PeriodosServicio;

final class AuditoriaPaginaController
{
    public function pagina(Peticion $p, array $args): Respuesta
    {
        [$usuario, $redireccion] = Politicas::requerirUsuario($p, ['admin']);
        if ($redireccion) {
            return $redireccion;
        }
        $periodos = (new PeriodosServicio())->listar();
        $html = Encabezado::topbar($periodos, (int) ($periodos[0]['id'] ?? 0), 'auditoria', $usuario)
              . Vista::parcial(__DIR__ . '/vistas/auditoria.php', ['filas' => AuditoriaServicio::ultimos(300)]);
        return Respuesta::html(Vista::pagina('Le Bleu — Auditoría', $html, ['topbar.js']));
    }
}
