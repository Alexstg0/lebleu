<?php
namespace Lebleu\Compartido;

use Lebleu\Core\Vista;

// Barra superior común de las pantallas con sesión.
final class Encabezado
{
    public static function topbar(array $periodos, int $periodoId, string $activo, array $usuario): string
    {
        return Vista::parcial(__DIR__ . '/vistas/topbar.php', [
            'periodos' => $periodos, 'periodoId' => $periodoId, 'activo' => $activo,
            'rol' => $usuario['rol'], 'nombre' => $usuario['nombre'] ?? null,
        ]);
    }
}
