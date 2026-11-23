<?php
namespace Lebleu\Modulos\Auditoria;

// Bitácora de quién hizo qué. Es "mejor esfuerzo": un fallo aquí NUNCA tumba la operación principal.
final class AuditoriaServicio
{
    public const ACCIONES = ['crear', 'editar', 'borrar', 'cerrar', 'reabrir', 'subir', 'generar'];

    public static function registrar(?array $usuario, string $accion, string $tabla, int|string|null $registroId, string $detalle): void
    {
        try {
            (new AuditoriaRepositorio())->insertar(
                isset($usuario['id']) ? (int) $usuario['id'] : null,
                (string) ($usuario['nombre'] ?? 'sistema'),
                $accion,
                $tabla,
                $registroId ? (int) $registroId : null,
                mb_substr($detalle, 0, 400)
            );
        } catch (\Throwable $e) {
            error_log('auditoria error: ' . $e->getMessage());
        }
    }
}
