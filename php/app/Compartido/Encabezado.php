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

    /** Selector "Periodo a registrar" que se queda en la misma pantalla ($base = ruta, ej. /capturar). */
    public static function selectorPeriodo(array $periodos, int $periodoId, string $base): string
    {
        $h = '<div class="rep-periodo"><label>Periodo a registrar</label><select data-ir-periodo="' . Formato::e($base) . '">';
        foreach ($periodos as $p) {
            $h .= '<option value="' . (int) $p['id'] . '"' . ((int) $p['id'] === $periodoId ? ' selected' : '') . '>'
                . Formato::e(Formato::mesNombre((int) $p['mes']) . ' ' . $p['anio'] . (!empty($p['estado']) ? ' · ' . $p['estado'] : '')) . '</option>';
        }
        return $h . '</select></div>';
    }
}
