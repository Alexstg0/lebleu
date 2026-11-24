<?php
namespace Lebleu\Modulos\Usuarios;

use Lebleu\Compartido\Encabezado;
use Lebleu\Compartido\Politicas;
use Lebleu\Core\Peticion;
use Lebleu\Core\Respuesta;
use Lebleu\Core\Vista;
use Lebleu\Modulos\Catalogos\CatalogosServicio;
use Lebleu\Modulos\Periodos\PeriodosServicio;

final class UsuariosPaginaController
{
    public function pagina(Peticion $p, array $args): Respuesta
    {
        [$usuario, $redireccion] = Politicas::requerirUsuario($p, ['admin']);
        if ($redireccion) {
            return $redireccion;
        }
        $periodos = (new PeriodosServicio())->listar();
        $html = Encabezado::topbar($periodos, (int) ($periodos[0]['id'] ?? 0), 'usuarios', $usuario)
              . Vista::parcial(__DIR__ . '/vistas/usuarios.php', [
                  'usuarios' => (new UsuariosServicio())->listar(),
                  'socios' => (new CatalogosServicio())->todos()['socios'],
                  'yoId' => (int) $usuario['id'],
              ]);
        return Respuesta::html(Vista::pagina('Le Bleu — Control de Embarcación', $html, ['topbar.js', 'formulario.js', 'usuarios.js']));
    }
}
