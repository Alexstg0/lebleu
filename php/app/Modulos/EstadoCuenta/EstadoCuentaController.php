<?php
namespace Lebleu\Modulos\EstadoCuenta;

use Lebleu\Compartido\Encabezado;
use Lebleu\Compartido\Formato;
use Lebleu\Compartido\Politicas;
use Lebleu\Core\Peticion;
use Lebleu\Core\Respuesta;
use Lebleu\Core\Vista;
use Lebleu\Modulos\Periodos\PeriodosServicio;

final class EstadoCuentaController
{
    private const SCRIPTS = ['topbar.js', 'visor-pdf.js', 'imprimir.js', 'pagado.js'];

    public function pagina(Peticion $p, array $args): Respuesta
    {
        [$usuario, $redireccion] = Politicas::requerirUsuario($p, ['admin', 'socio']);
        if ($redireccion) {
            return $redireccion;
        }
        $periodos = (new PeriodosServicio())->listar();
        if (!$periodos) {
            return Respuesta::html(Vista::pagina('Le Bleu', '<div class="empty">No hay periodos cargados.</div>'));
        }
        $periodoId = (int) $p->consulta('periodo', 0) ?: (int) $periodos[0]['id'];
        $datos = (new EstadoCuentaServicio())->obtener($periodoId);
        if (!$datos) {
            return Respuesta::html(Vista::pagina('Le Bleu', '<div class="empty">Periodo no encontrado.</div>'));
        }

        $sel = array_values(array_filter($periodos, fn($x) => (int) $x['id'] === $periodoId))[0] ?? null;
        $titulo = $sel ? 'Le Bleu — Estado de Cuenta ' . Formato::mesNombre((int) $sel['mes']) . ' ' . $sel['anio'] : 'Le Bleu';
        $html = Encabezado::topbar($periodos, $periodoId, 'dashboard', $usuario)
              . Vista::parcial(__DIR__ . '/vistas/estado_cuenta.php', ['d' => $datos, 'usuario' => $usuario]);
        return Respuesta::html(Vista::pagina($titulo, $html, self::SCRIPTS));
    }
}
