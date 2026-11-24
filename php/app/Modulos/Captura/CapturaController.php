<?php
namespace Lebleu\Modulos\Captura;

use Lebleu\Compartido\Encabezado;
use Lebleu\Compartido\Politicas;
use Lebleu\Core\Peticion;
use Lebleu\Core\Respuesta;
use Lebleu\Core\Vista;
use Lebleu\Modulos\Catalogos\CatalogosServicio;
use Lebleu\Modulos\Periodos\PeriodosServicio;

// Pantalla Capturar: el administrador ve todos los formularios en pestañas; el capitán solo el de viaje.
final class CapturaController
{
    private const SCRIPTS_COMUNES = ['topbar.js', 'selector-periodo.js', 'formulario.js', 'marinero.js', 'viaje-capitan.js', 'viaje.js', 'insumos.js', 'renta.js', 'capturar.js'];

    public function pagina(Peticion $p, array $args): Respuesta
    {
        [$usuario, $redireccion] = Politicas::requerirUsuario($p, ['admin', 'capitan']);
        if ($redireccion) {
            return $redireccion;
        }
        $periodos = (new PeriodosServicio())->listar();
        if (!$periodos) {
            return Respuesta::html(Vista::pagina('Le Bleu', '<div class="empty">No hay periodos cargados.</div>'));
        }
        $pedido = (int) $p->consulta('periodo', 0);
        $periodo = array_values(array_filter($periodos, fn($x) => (int) $x['id'] === $pedido))[0] ?? $periodos[0];
        $periodoId = (int) $periodo['id'];

        $catalogos = new CatalogosServicio();
        $ctx = $catalogos->todos() + [
            'periodoId' => $periodoId,
            'tipoCambio' => (float) $periodo['tipo_cambio'],
            'precioLitro' => (float) $periodo['precio_litro'],
            'viajes' => $catalogos->viajesDelPeriodo($periodoId),
        ];

        $html = Encabezado::topbar($periodos, $periodoId, 'capturar', $usuario)
              . Vista::parcial(__DIR__ . '/vistas/capturar.php', [
                  'usuario' => $usuario, 'periodos' => $periodos, 'periodo' => $periodo, 'ctx' => $ctx,
              ]);
        return Respuesta::html(Vista::pagina('Le Bleu — Control de Embarcación', $html, self::SCRIPTS_COMUNES));
    }
}
