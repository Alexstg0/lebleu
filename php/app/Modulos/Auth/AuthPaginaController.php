<?php
namespace Lebleu\Modulos\Auth;

use Lebleu\Compartido\Politicas;
use Lebleu\Core\Peticion;
use Lebleu\Core\Respuesta;
use Lebleu\Core\Vista;

final class AuthPaginaController
{
    public function login(Peticion $p, array $args): Respuesta
    {
        $usuario = Politicas::usuarioActual($p);
        if ($usuario) {
            return Respuesta::redirigir(AuthServicio::inicioDe($usuario['rol']));
        }
        $html = Vista::parcial(__DIR__ . '/vistas/login.php');
        return Respuesta::html(Vista::pagina('Le Bleu — Control de Embarcación', $html, ['login.js']));
    }
}
