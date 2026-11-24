<?php
namespace Lebleu\Compartido\Pdf;

// Descompresión de los flujos internos de un PDF (tabla xref comprimida y flujos de objetos): solo FlateDecode, con predictores PNG.
final class Flujos
{
    /** Tope de lo que se descomprime en estructuras internas (un PDF hostil no debe agotar la memoria). */
    private const MAX_BYTES = 64 << 20;
    private const MAX_ANCHO = 1 << 20;

    /** @param callable(mixed): mixed $resolver sigue referencias del documento */
    public static function decodificar(Valor $f, callable $resolver): string
    {
        $datos = $f->v[1];
        $filtros = $resolver($f->clave('Filter'));
        $parametros = $resolver($f->clave('DecodeParms'));
        $filtros = $filtros === null ? [] : (is_array($filtros) ? $filtros : [$filtros]);
        $parametros = is_array($parametros) && array_is_list($parametros) ? $parametros : [$parametros];
        foreach ($filtros as $i => $filtro) {
            $filtro = $resolver($filtro);
            if (!$filtro instanceof Valor || !in_array($filtro->v, ['FlateDecode', 'Fl'], true)) {
                throw new \RuntimeException('Filtro no soportado en estructuras internas.');
            }
            $raw = @gzuncompress($datos, self::MAX_BYTES);
            if ($raw === false) {
                $raw = @gzinflate(substr($datos, 2), self::MAX_BYTES);
            }
            if ($raw === false) {
                throw new \RuntimeException('No se pudo descomprimir el flujo.');
            }
            $datos = self::predictor($raw, $resolver($parametros[$i] ?? null), $resolver);
        }
        return $datos;
    }

    private static function predictor(string $d, mixed $parm, callable $resolver): string
    {
        $pred = $parm instanceof Valor ? (int) $resolver($parm->clave('Predictor') ?? 1) : 1;
        if ($pred < 10) {
            return $d;
        }
        $cols = (int) $resolver($parm->clave('Columns') ?? 1);
        $bpp = max(1, (int) ceil((int) $resolver($parm->clave('Colors') ?? 1) * (int) $resolver($parm->clave('BitsPerComponent') ?? 8) / 8));
        $ancho = (int) ceil($cols * (int) $resolver($parm->clave('Colors') ?? 1) * (int) $resolver($parm->clave('BitsPerComponent') ?? 8) / 8);
        if ($ancho < 1 || $ancho > self::MAX_ANCHO) {
            throw new \RuntimeException('Predictor con ancho de fila no válido.');
        }
        $previa = array_fill(0, $ancho, 0);
        $salida = '';
        for ($p = 0; $p + $ancho + 1 <= strlen($d); $p += $ancho + 1) {
            $tipo = ord($d[$p]);
            $fila = array_values(unpack('C*', substr($d, $p + 1, $ancho)));
            for ($i = 0; $i < $ancho; $i++) {
                $izq = $i >= $bpp ? $fila[$i - $bpp] : 0;
                $arr = $previa[$i];
                $diag = $i >= $bpp ? $previa[$i - $bpp] : 0;
                $fila[$i] = ($fila[$i] + match ($tipo) {
                    1 => $izq,
                    2 => $arr,
                    3 => intdiv($izq + $arr, 2),
                    4 => self::paeth($izq, $arr, $diag),
                    default => 0,
                }) & 255;
            }
            $salida .= pack('C*', ...$fila);
            $previa = $fila;
        }
        return $salida;
    }

    private static function paeth(int $a, int $b, int $c): int
    {
        $p = $a + $b - $c;
        $pa = abs($p - $a);
        $pb = abs($p - $b);
        $pc = abs($p - $c);
        return ($pa <= $pb && $pa <= $pc) ? $a : ($pb <= $pc ? $b : $c);
    }
}
