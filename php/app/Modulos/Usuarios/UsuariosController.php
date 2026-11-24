<?php
namespace Lebleu\Modulos\Usuarios;

use Lebleu\Compartido\Politicas;
use Lebleu\Core\Peticion;
use Lebleu\Core\Respuesta;

final class UsuariosController
{
    public function crear(Peticion $p, array $args): Respuesta
    {
        return $this->ejecutar($p, fn($u) => (new UsuariosServicio())->crear($p->json()), 'No se pudo crear la cuenta.');
    }

    // PATCH: cambiar contraseña y/o activar o desactivar.
    public function editar(Peticion $p, array $args): Respuesta
    {
        return $this->ejecutar($p, fn($u) => (new UsuariosServicio())->actualizar($p->json()), 'No se pudo actualizar la cuenta.');
    }

    public function borrar(Peticion $p, array $args): Respuesta
    {
        return $this->ejecutar($p, fn($u) => (new UsuariosServicio())->borrar((int) $u['id'], (int) $p->consulta('id', 0)), 'No se pudo borrar la cuenta.');
    }

    private function ejecutar(Peticion $p, callable $accion, string $falla): Respuesta
    {
        $usuario = Politicas::guardiaApi($p, ['admin']);
        if (!$usuario) {
            return Respuesta::error('No autorizado.', 403);
        }
        try {
            $accion($usuario);
            return Respuesta::json(['ok' => true]);
        } catch (\InvalidArgumentException $e) {
            return Respuesta::error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            error_log('api usuarios: ' . $e->getMessage());
            return Respuesta::error($falla, 400);
        }
    }
}
