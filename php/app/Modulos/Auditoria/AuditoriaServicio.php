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

    /** Últimos movimientos con la hora ya en la zona de la embarcación (Baja California Sur). */
    public static function ultimos(int $limite = 300): array
    {
        $zona = new \DateTimeZone('America/Mazatlan');
        return array_map(function ($f) use ($zona) {
            $cuando = (new \DateTimeImmutable($f['created_at'], new \DateTimeZone('UTC')))->setTimezone($zona)->format('d/m/Y H:i');
            return $f + ['cuando' => $cuando];
        }, (new AuditoriaRepositorio())->ultimos($limite));
    }
}
