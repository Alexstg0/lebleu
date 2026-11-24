<?php
namespace Lebleu\Compartido\Pdf;

// Serializa valores PDF.
final class Escritor
{
    public static function valor(mixed $v): string
    {
        if ($v === null) {
            return 'null';
        }
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        if (is_int($v)) {
            return (string) $v;
        }
        if (is_float($v)) {
            return rtrim(rtrim(number_format($v, 6, '.', ''), '0'), '.') ?: '0';
        }
        if (is_array($v)) {
            return '[' . implode(' ', array_map(fn($x) => self::valor($x), $v)) . ']';
        }
        return match ($v->t) {
            Valor::NOMBRE => self::nombre($v->v),
            Valor::CADENA => '<' . bin2hex($v->v) . '>',
            Valor::REF => $v->v[0] . ' 0 R',
            Valor::DIC => self::diccionario($v->v),
            Valor::FLUJO => self::flujo($v),
        };
    }

    private static function nombre(string $n): string
    {
        return '/' . preg_replace_callback('/[^\x21-\x7e]|[#\/()<>\[\]{}%]/', fn($m) => sprintf('#%02X', ord($m[0])), $n);
    }

    private static function diccionario(array $d): string
    {
        $s = '<<';
        foreach ($d as $k => $x) {
            $s .= self::nombre((string) $k) . ' ' . self::valor($x) . ' ';
        }
        return $s . '>>';
    }

    private static function flujo(Valor $f): string
    {
        $d = $f->v[0]->v;
        $d['Length'] = strlen($f->v[1]);
        return self::diccionario($d) . "\nstream\n" . $f->v[1] . "\nendstream";
    }
}
