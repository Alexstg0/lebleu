<?php
namespace Lebleu\Compartido;

// Formato es-MX para montos, números y fechas (equivale a lib/format.ts, sin depender de la extensión intl).
final class Formato
{
    private const MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                           'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    public static function num(string|int|float|null $v, int $decimales = 2): string
    {
        return number_format((float) ($v ?? 0), $decimales, '.', ',');
    }

    public static function mxn(string|int|float|null $v): string
    {
        $c = Dinero::aCentavos($v);
        return ($c < 0 ? '-' : '') . '$' . number_format(abs($c) / 100, 2, '.', ',');
    }

    public static function mesNombre(int $mes): string
    {
        return self::MESES[$mes - 1] ?? (string) $mes;
    }

    /** "2026-05-10" -> "10/05" */
    public static function fechaCorta(?string $fecha): string
    {
        $p = explode('-', substr((string) $fecha, 0, 10));
        return count($p) === 3 ? "$p[2]/$p[1]" : (string) $fecha;
    }

    /** "14:30" -> "2:30 PM" */
    public static function hora12(?string $hhmm): string
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})/', (string) $hhmm, $m)) {
            return '';
        }
        $h = (int) $m[1];
        return sprintf('%d:%02d %s', $h % 12 === 0 ? 12 : $h % 12, (int) $m[2], $h >= 12 ? 'PM' : 'AM');
    }

    public static function e(?string $texto): string
    {
        return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
