<?php
namespace Lebleu\Modulos\Adjuntos;

use Lebleu\Compartido\Politicas;
use Lebleu\Core\Peticion;
use Lebleu\Core\Respuesta;

final class AdjuntosController
{
    // Sube un PDF o foto de respaldo. Administrador y capitán (el capitán solo en caja chica).
    public function subir(Peticion $p, array $args): Respuesta
    {
        $usuario = Politicas::guardiaApi($p, ['admin', 'capitan']);
        if (!$usuario) {
            return Respuesta::error('No autorizado.', 403);
        }
        try {
            (new AdjuntosServicio())->subir($usuario, $p->json());
            return Respuesta::json(['ok' => true]);
        } catch (\DomainException $e) {
            return Respuesta::error($e->getMessage(), 403);
        } catch (\InvalidArgumentException $e) {
            return Respuesta::error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            error_log('api adjuntos: ' . $e->getMessage());
            return Respuesta::error('No se pudo subir el archivo. Verifica que sea PDF o JPG/PNG válido.', 400);
        }
    }

    public function borrar(Peticion $p, array $args): Respuesta
    {
        $usuario = Politicas::guardiaApi($p, ['admin', 'capitan']);
        if (!$usuario) {
            return Respuesta::error('No autorizado.', 403);
        }
        try {
            (new AdjuntosServicio())->borrar($usuario, (int) $p->consulta('id', 0));
            return Respuesta::json(['ok' => true]);
        } catch (\DomainException $e) {
            return Respuesta::error($e->getMessage(), 403);
        } catch (\Throwable $e) {
            error_log('api adjuntos: ' . $e->getMessage());
            return Respuesta::error('No se pudo eliminar.', 400);
        }
    }

    // Sirve el PDF para el visor en línea. Requiere sesión iniciada.
    public function ver(Peticion $p, array $args): Respuesta
    {
        $usuario = Politicas::usuarioActual($p);
        if (!$usuario) {
            return Respuesta::error('No autorizado.', 403);
        }
        $fila = (new AdjuntosRepositorio())->porId((int) $args['id']);
        if (!$fila) {
            return Respuesta::error('No encontrado.', 404);
        }
        // El capitán solo puede ver soportes de caja chica (mismo criterio que la subida y el borrado).
        if ($usuario['rol'] === 'capitan' && !$fila['caja_id']) {
            return Respuesta::error('No autorizado.', 403);
        }
        // Todo adjunto se guarda como PDF: se fuerza el tipo y se impide el sniffing para que un MIME
        // manipulado nunca se interprete como HTML (evita XSS almacenado).
        return Respuesta::binario((string) base64_decode($fila['datos']), 'application/pdf', [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline; filename="' . rawurlencode($fila['nombre'] ?: 'documento.pdf') . '"',
            'Cache-Control' => 'private, max-age=0, no-store',
        ]);
    }
}
