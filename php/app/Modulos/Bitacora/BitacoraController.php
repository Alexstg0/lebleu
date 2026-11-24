<?php
namespace Lebleu\Modulos\Bitacora;

use Lebleu\Compartido\Encabezado;
use Lebleu\Compartido\Politicas;
use Lebleu\Core\Peticion;
use Lebleu\Core\Respuesta;
use Lebleu\Core\Vista;
use Lebleu\Modulos\Periodos\PeriodosServicio;

// Pantalla Bitácora de viajes: administrador (con editar/borrar) y capitán (solo lectura).
final class BitacoraController
{
    public function pagina(Peticion $p, array $args): Respuesta
    {
        [$usuario, $redireccion] = Politicas::requerirUsuario($p, ['admin', 'capitan']);
        if ($redireccion) {
            return $redireccion;
        }
        $servicio = new BitacoraServicio();
        $periodos = (new PeriodosServicio())->listar();
        $meses = $servicio->meses();
        $def = $meses[0] ?? ['anio' => (int) date('Y'), 'mes' => (int) date('n')];
        $anio = (int) $p->consulta('y', 0) ?: $def['anio'];
        $mes = (int) $p->consulta('m', 0) ?: $def['mes'];
        if ($mes < 1 || $mes > 12 || $anio < 2000 || $anio > 2100) {
            [$anio, $mes] = [$def['anio'], $def['mes']];
        }
        $html = Encabezado::topbar($periodos, (int) ($periodos[0]['id'] ?? 0), 'bitacora', $usuario)
              . Vista::parcial(__DIR__ . '/vistas/bitacora.php', [
                  'meses' => $meses, 'anio' => $anio, 'mes' => $mes,
                  'viajes' => $servicio->delMes($anio, $mes), 'esAdmin' => $usuario['rol'] === 'admin',
              ]);
        return Respuesta::html(Vista::pagina('Le Bleu', $html, ['topbar.js', 'selector-periodo.js', 'formulario.js', 'marinero.js', 'viaje.js', 'bitacora.js']));
    }
}
