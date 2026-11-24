<?php
namespace Lebleu\Modulos\Movimientos;

use Lebleu\Compartido\Encabezado;
use Lebleu\Compartido\Politicas;
use Lebleu\Core\Peticion;
use Lebleu\Core\Respuesta;
use Lebleu\Core\Vista;
use Lebleu\Modulos\Captura\Formularios;
use Lebleu\Modulos\Catalogos\CatalogosServicio;
use Lebleu\Modulos\Periodos\PeriodosServicio;

// Pantalla Movimientos (administrador): lo capturado del periodo, con editar y borrar.
final class MovimientosController
{
    private const SCRIPTS = ['topbar.js', 'selector-periodo.js', 'visor-pdf.js', 'formulario.js', 'marinero.js', 'viaje.js', 'insumos.js', 'renta.js', 'movimientos.js'];
    private const FORMULARIO = ['op' => 'gasto', 'in' => 'insumo', 'vi' => 'viaje', 'ap' => 'aportacion', 'ex' => 'extraordinario', 'ae' => 'abono', 're' => 'renta'];

    public function pagina(Peticion $p, array $args): Respuesta
    {
        [$usuario, $redireccion] = Politicas::requerirUsuario($p, ['admin']);
        if ($redireccion) {
            return $redireccion;
        }
        $periodos = (new PeriodosServicio())->listar();
        if (!$periodos) {
            return Respuesta::html(Vista::pagina('Le Bleu', '<div class="empty">No hay periodos.</div>'));
        }
        $periodo = $this->elegirPeriodo($periodos, (int) $p->consulta('periodo', 0));
        $mov = (new MovimientosServicio())->delPeriodo((int) $periodo['id']);

        $html = Encabezado::topbar($periodos, (int) $periodo['id'], 'movimientos', $usuario)
              . Vista::parcial(__DIR__ . '/vistas/movimientos.php', ['periodos' => $periodos, 'periodo' => $periodo, 'mov' => $mov]);
        return Respuesta::html(Vista::pagina('Le Bleu — Control de Embarcación', $html, self::SCRIPTS));
    }

    /** Formulario de edición de una fila (fragmento HTML que inserta movimientos.js). ?k=op:5&periodo=3 */
    public function formulario(Peticion $p, array $args): Respuesta
    {
        if (!Politicas::guardiaApi($p, ['admin'])) {
            return Respuesta::error('No autorizado.', 403);
        }
        [$prefijo, $id] = array_pad(explode(':', (string) $p->consulta('k', ''), 2), 2, '');
        if (!isset(self::FORMULARIO[$prefijo]) || !ctype_digit($id)) {
            return Respuesta::error('Movimiento no válido.', 400);
        }
        $servicio = new MovimientosServicio();
        $fila = $servicio->fila($prefijo, (int) $id);
        if (!$fila) {
            return Respuesta::error('No encontrado.', 404);
        }
        $periodoId = (int) ($fila['periodo_id'] ?? 0);
        $periodo = $this->elegirPeriodo((new PeriodosServicio())->listar(), $periodoId);
        $mov = $servicio->delPeriodo((int) $periodo['id']);
        $ctx = (new CatalogosServicio())->todos() + [
            'periodoId' => (int) $periodo['id'],
            'tipoCambio' => (float) $periodo['tipo_cambio'],
            'precioLitro' => (float) $periodo['precio_litro'],
            'viajes' => $servicio->viajesParaSelector($mov),
        ];
        return Respuesta::html(Formularios::renderizar(self::FORMULARIO[$prefijo], $fila, $ctx));
    }

    private function elegirPeriodo(array $periodos, int $pedido): array
    {
        return array_values(array_filter($periodos, fn($x) => (int) $x['id'] === $pedido))[0] ?? $periodos[0];
    }
}
