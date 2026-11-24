<?php
namespace Lebleu\Modulos\CajaChica;

use Lebleu\Compartido\Encabezado;
use Lebleu\Compartido\Formato;
use Lebleu\Compartido\Politicas;
use Lebleu\Core\Peticion;
use Lebleu\Core\Respuesta;
use Lebleu\Core\Vista;
use Lebleu\Modulos\Periodos\PeriodosServicio;

// Pantalla Caja chica (administrador y capitán).
final class CajaChicaPaginaController
{
    public function pagina(Peticion $p, array $args): Respuesta
    {
        [$usuario, $redireccion] = Politicas::requerirUsuario($p, ['admin', 'capitan']);
        if ($redireccion) {
            return $redireccion;
        }
        $periodos = (new PeriodosServicio())->listar();
        $html = Encabezado::topbar($periodos, (int) ($periodos[0]['id'] ?? 0), 'caja', $usuario)
              . Vista::parcial(__DIR__ . '/vistas/caja_chica.php', [
                  'movimientos' => (new CajaChicaServicio())->movimientos(),
                  'hoyTexto' => (int) date('j') . ' de ' . mb_strtolower(Formato::mesNombre((int) date('n'))) . ' del ' . date('Y'),
                  'embarcacion' => $periodos[0]['embarcacion'] ?? 'Le Bleu',
                  'razonSocial' => $periodos[0]['razon_social'] ?? 'Arrendadora Acma S de RL de CV',
              ]);
        return Respuesta::html(Vista::pagina('Le Bleu — Caja Chica', $html, ['topbar.js', 'imprimir.js', 'visor-pdf.js', 'adjuntos.js', 'caja-chica.js']));
    }
}
