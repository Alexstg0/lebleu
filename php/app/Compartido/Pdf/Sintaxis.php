<?php
namespace Lebleu\Compartido\Pdf;

// Analizador léxico/sintáctico de objetos PDF (sin flujos: de esos se encarga LectorPdf).
final class Sintaxis
{
    private const ESPACIOS = "\0\t\n\f\r ";
    private const DELIMITADORES = "()<>[]{}/%";

    /** @return array{0: mixed, 1: int} valor y posición siguiente */
    public static function leer(string $s, int $p): array
    {
        $p = self::saltar($s, $p);
        if ($p >= strlen($s)) {
            throw new \RuntimeException('Fin inesperado del PDF.');
        }
        $c = $s[$p];
        if ($c === '/') {
            [$n, $p] = self::token($s, $p + 1);
            return [Valor::nombre(preg_replace_callback('/#([0-9A-Fa-f]{2})/', fn($m) => chr(hexdec($m[1])), $n)), $p];
        }
        if ($c === '(') {
            return self::cadena($s, $p);
        }
        if ($c === '<') {
            return ($s[$p + 1] ?? '') === '<' ? self::diccionario($s, $p + 2) : self::hexadecimal($s, $p);
        }
        if ($c === '[') {
            $lista = [];
            $p++;
            while (true) {
                $p = self::saltar($s, $p);
                if ($p >= strlen($s)) {
                    throw new \RuntimeException('Arreglo sin cerrar.');
                }
                if ($s[$p] === ']') {
                    return [$lista, $p + 1];
                }
                [$lista[], $p] = self::leer($s, $p);
            }
        }
        [$t, $sig] = self::token($s, $p);
        if ($t === '') {
            throw new \RuntimeException("Carácter inesperado en la posición $p.");
        }
        if ($t === 'true' || $t === 'false') {
            return [$t === 'true', $sig];
        }
        if ($t === 'null') {
            return [null, $sig];
        }
        if (preg_match('/^[+-]?\d+$/', $t)) {
            if (preg_match('/\G\s+(\d+)\s+R(?![^\s\/<>\[\]()%])/A', $s, $m, 0, $sig)) {
                return [Valor::ref((int) $t, (int) $m[1]), $sig + strlen($m[0])];
            }
            return [(int) $t, $sig];
        }
        if (is_numeric($t)) {
            return [(float) $t, $sig];
        }
        return [Valor::nombre('@' . $t), $sig]; // palabra clave (obj, endobj, stream…)
    }

    public static function saltar(string $s, int $p): int
    {
        $n = strlen($s);
        while ($p < $n) {
            if (strpos(self::ESPACIOS, $s[$p]) !== false) {
                $p++;
            } elseif ($s[$p] === '%') {
                while ($p < $n && $s[$p] !== "\n" && $s[$p] !== "\r") {
                    $p++;
                }
            } else {
                break;
            }
        }
        return $p;
    }

    private static function token(string $s, int $p): array
    {
        $ini = $p;
        $n = strlen($s);
        while ($p < $n && strpos(self::ESPACIOS . self::DELIMITADORES, $s[$p]) === false) {
            $p++;
        }
        return [substr($s, $ini, $p - $ini), $p];
    }

    private static function diccionario(string $s, int $p): array
    {
        $d = [];
        while (true) {
            $p = self::saltar($s, $p);
            if (substr($s, $p, 2) === '>>') {
                return [Valor::dic($d), $p + 2];
            }
            [$k, $p] = self::leer($s, $p);
            if (!$k instanceof Valor || !$k->es(Valor::NOMBRE)) {
                throw new \RuntimeException('Clave de diccionario no válida.');
            }
            [$d[$k->v], $p] = self::leer($s, $p);
        }
    }

    private static function hexadecimal(string $s, int $p): array
    {
        $fin = strpos($s, '>', $p);
        if ($fin === false) {
            throw new \RuntimeException('Cadena hexadecimal sin cerrar.');
        }
        $h = preg_replace('/[^0-9A-Fa-f]/', '', substr($s, $p + 1, $fin - $p - 1));
        if (strlen($h) % 2) {
            $h .= '0';
        }
        return [new Valor(Valor::CADENA, (string) hex2bin($h)), $fin + 1];
    }

    private static function cadena(string $s, int $p): array
    {
        $out = '';
        $nivel = 1;
        $n = strlen($s);
        $p++;
        while ($p < $n) {
            $c = $s[$p++];
            if ($c === '\\') {
                $e = $s[$p++] ?? '';
                $mapa = ['n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\f", '(' => '(', ')' => ')', '\\' => '\\'];
                if (isset($mapa[$e])) {
                    $out .= $mapa[$e];
                } elseif ($e >= '0' && $e <= '7') {
                    $o = $e;
                    for ($i = 0; $i < 2 && ($s[$p] ?? '') >= '0' && ($s[$p] ?? '') <= '7'; $i++) {
                        $o .= $s[$p++];
                    }
                    $out .= chr(octdec($o) & 255);
                } elseif ($e === "\r") {
                    if (($s[$p] ?? '') === "\n") {
                        $p++;
                    }
                } elseif ($e !== "\n") {
                    $out .= $e;
                }
            } elseif ($c === '(') {
                $nivel++;
                $out .= $c;
            } elseif ($c === ')') {
                if (--$nivel === 0) {
                    return [new Valor(Valor::CADENA, $out), $p];
                }
                $out .= $c;
            } else {
                $out .= $c;
            }
        }
        throw new \RuntimeException('Cadena sin cerrar.');
    }
}
