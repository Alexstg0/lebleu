<?php
namespace Lebleu\Compartido;

// Validación de los datos que llegan del navegador. Lanza InvalidArgumentException con un mensaje
// en español; los controladores lo devuelven tal cual como error 400.
final class Entrada
{
    public static function fecha(array $b, string $k, string $nombre = 'fecha'): string
    {
        $v = $b[$k] ?? null;
        if (!is_string($v) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', substr($v, 0, 10), $m)
            || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            throw new \InvalidArgumentException("La $nombre no es válida.");
        }
        return substr($v, 0, 10);
    }

    public static function fechaOpcional(array $b, string $k): ?string
    {
        return ($b[$k] ?? '') === '' || $b[$k] === null ? null : self::fecha($b, $k);
    }

    /** Número decimal (acepta número o texto numérico). */
    public static function numero(array $b, string $k, string $nombre, bool $requerido = true, float $defecto = 0): int|float|string
    {
        $v = $b[$k] ?? null;
        if ($v === null || $v === '') {
            if ($requerido) {
                throw new \InvalidArgumentException("Falta $nombre.");
            }
            return $defecto;
        }
        if (!is_numeric($v) || abs((float) $v) >= 1e12) {
            throw new \InvalidArgumentException("$nombre no es un número válido.");
        }
        return $v;
    }

    public static function numeroOpcional(array $b, string $k, string $nombre): int|float|string|null
    {
        return ($b[$k] ?? '') === '' || $b[$k] === null ? null : self::numero($b, $k, $nombre);
    }

    public static function entero(array $b, string $k, string $nombre, bool $requerido = true): ?int
    {
        $v = $b[$k] ?? null;
        if ($v === null || $v === '' || $v === 0 || $v === '0') {
            if ($requerido) {
                throw new \InvalidArgumentException("Falta $nombre.");
            }
            return null;
        }
        if (!is_numeric($v) || (int) $v != $v || (int) $v < 0) {
            throw new \InvalidArgumentException("$nombre no es válido.");
        }
        return (int) $v;
    }

    public static function texto(array $b, string $k, string $nombre, int $max = 400): string
    {
        $v = trim((string) ($b[$k] ?? ''));
        if ($v === '') {
            throw new \InvalidArgumentException("Falta $nombre.");
        }
        return self::limitar($v, $max, $nombre);
    }

    public static function textoOpcional(array $b, string $k, string $nombre, int $max = 400): ?string
    {
        $v = trim((string) ($b[$k] ?? ''));
        return $v === '' ? null : self::limitar($v, $max, $nombre);
    }

    /** @param string[] $permitidos */
    public static function opcion(array $b, string $k, string $nombre, array $permitidos, string $defecto): string
    {
        $v = ($b[$k] ?? '') === '' ? $defecto : (string) $b[$k];
        if (!in_array($v, $permitidos, true)) {
            throw new \InvalidArgumentException("$nombre no es válida.");
        }
        return $v;
    }

    /** Tipo de cambio: solo aplica en dólares; en pesos siempre es 1. */
    public static function tipoCambio(array $b, string $moneda): int|float|string
    {
        return $moneda === 'USD' ? (self::numero($b, 'tipo_cambio', 'El tipo de cambio', false, 1) ?: 1) : 1;
    }

    private static function limitar(string $v, int $max, string $nombre): string
    {
        if (mb_strlen($v) > $max) {
            throw new \InvalidArgumentException("$nombre es demasiado largo (máximo $max caracteres).");
        }
        return $v;
    }
}
