<?php
namespace Lebleu\Compartido;

// Aritmética de dinero en centavos enteros: exacta, sin errores de punto flotante.
// Redondeo "mitad hacia afuera de cero", igual que ROUND() de las vistas SQL.
final class Dinero
{
    public static function aCentavos(string|int|float|null $v): int
    {
        if ($v === null || $v === '') {
            return 0;
        }
        if (is_float($v) || is_int($v)) {
            return (int) round($v * 100);
        }
        $v = trim($v);
        $neg = str_starts_with($v, '-');
        $v = ltrim($v, '+-');
        [$ent, $dec] = array_pad(explode('.', $v, 2), 2, '');
        $dec = substr(str_pad($dec, 3, '0'), 0, 3);
        $c = (int) $ent * 100 + (int) substr($dec, 0, 2);
        if ((int) $dec[2] >= 5) {
            $c++;
        }
        return $neg ? -$c : $c;
    }

    public static function aTexto(int $centavos): string
    {
        $signo = $centavos < 0 ? '-' : '';
        $a = abs($centavos);
        return sprintf('%s%d.%02d', $signo, intdiv($a, 100), $a % 100);
    }

    public static function aFloat(int $centavos): float
    {
        return $centavos / 100;
    }

    /** total × porcentaje/100, redondeado a centavos. $porcentaje es texto tipo "50.00". */
    public static function porcentaje(int $centavos, string $porcentaje): int
    {
        return self::dividirRedondeando($centavos * self::aCentavos($porcentaje), 10000);
    }

    /** Reparto "sin centavo fantasma": el último socio recibe el resto del total. */
    public static function parte(int $total, string $porcentaje, bool $esUltimo): int
    {
        if (!$esUltimo) {
            return self::porcentaje($total, $porcentaje);
        }
        $resto = self::aTexto(10000 - self::aCentavos($porcentaje));
        return $total - self::porcentaje($total, $resto);
    }

    public static function mitad(int $centavos): int
    {
        return self::dividirRedondeando($centavos, 2);
    }

    private static function dividirRedondeando(int $numerador, int $divisor): int
    {
        $q = intdiv(abs($numerador) * 2 + $divisor, $divisor * 2);
        return $numerador < 0 ? -$q : $q;
    }
}
