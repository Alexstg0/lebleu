<?php
namespace Lebleu\Modulos\Marineros;

use Lebleu\Compartido\Politicas;
use Lebleu\Core\Peticion;
use Lebleu\Core\Respuesta;
use Lebleu\Modulos\Auditoria\AuditoriaServicio;

final class MarinerosController
{
    // Alta de marinero (capitán y admin). Devuelve el nombre normalizado; si ya existe lo reutiliza.
    public function crear(Peticion $p, array $args): Respuesta
    {
        $usuario = Politicas::guardiaApi($p, ['admin', 'capitan']);
        if (!$usuario) {
            return Respuesta::error('No autorizado.', 403);
        }
        try {
            $nombre = trim(preg_replace('/\s+/u', ' ', (string) ($p->json()['nombre'] ?? '')));
            if (mb_strlen($nombre) < 3) {
                return Respuesta::error('Escribe el nombre completo del marinero.', 400);
            }
            if (mb_strlen($nombre) > 160) {
                return Respuesta::error('El nombre es demasiado largo.', 400);
            }
            $repo = new MarinerosRepositorio();
            $existe = $repo->porNombre($nombre);
            if ($existe) {
                $repo->activar((int) $existe['id']);
                return Respuesta::json(['ok' => true, 'id' => (int) $existe['id'], 'nombre' => $existe['nombre']]);
            }
            $id = $repo->insertar($nombre);
            AuditoriaServicio::registrar($usuario, 'crear', 'marineros', $id, "Agregó al marinero $nombre");
            return Respuesta::json(['ok' => true, 'id' => $id, 'nombre' => $nombre]);
        } catch (\Throwable $e) {
            error_log('api marineros: ' . $e->getMessage());
            return Respuesta::error('No se pudo agregar el marinero.', 400);
        }
    }
}
