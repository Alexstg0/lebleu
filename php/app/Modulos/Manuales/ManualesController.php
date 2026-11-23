<?php
namespace Lebleu\Modulos\Manuales;

use Lebleu\Compartido\Politicas;
use Lebleu\Core\Peticion;
use Lebleu\Core\Respuesta;

final class ManualesController
{
    private const ROLES = ['admin', 'capitan', 'socio'];

    // Manual de uso del rol del usuario (el admin puede pedir cualquiera con ?rol=).
    public function ver(Peticion $p, array $args): Respuesta
    {
        $usuario = Politicas::usuarioActual($p);
        if (!$usuario) {
            return Respuesta::error('No autorizado.', 403);
        }
        $pedido = (string) $p->consulta('rol', '');
        $rol = $usuario['rol'] === 'admin' && in_array($pedido, self::ROLES, true) ? $pedido : $usuario['rol'];
        try {
            $fila = (new ManualesRepositorio())->porRol($rol);
        } catch (\Throwable $e) {
            error_log('api manual: ' . $e->getMessage());
            return Respuesta::error('No se pudo cargar el manual.', 500);
        }
        if (!$fila) {
            return Respuesta::error('El manual aún no está cargado.', 404);
        }
        // El encabezado HTTP no acepta acentos: se usa el nombre sin diacríticos ni caracteres raros.
        $ascii = preg_replace('/[^\x20-\x7E]/', '', strtr($fila['nombre'], 'áéíóúÁÉÍÓÚñÑüÜ', 'aeiouAEIOUnNuU')) ?: 'manual.pdf';
        return Respuesta::binario((string) base64_decode($fila['datos']), 'application/pdf', [
            'Content-Disposition' => 'inline; filename="' . str_replace('"', '', $ascii) . '"',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
