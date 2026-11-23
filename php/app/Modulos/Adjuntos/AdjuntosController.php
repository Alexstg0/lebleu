<?php
namespace Lebleu\Modulos\Adjuntos;

use Lebleu\Compartido\Politicas;
use Lebleu\Core\Peticion;
use Lebleu\Core\Respuesta;

final class AdjuntosController
{
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
