<?php
namespace Lebleu\Modulos\Analisis;

use Lebleu\Compartido\Formato as F;

// Gráficas de barras en SVG puro (mismas medidas y colores que la versión original).
final class Graficas
{
    private const NAVY = '#1a3a5c', GRAY = '#8a96a3';

    private static function n(float $v): string
    {
        return json_encode(round($v, 10));
    }

    private static function kFmt(float $v): string
    {
        return $v >= 1000 ? '$' . (int) round($v / 1000) . 'k' : '$' . (int) round($v);
    }

    /** @param array<int, array{etiqueta: string, valores: array<int, float>}> $grupos @param array<int, array{color: string}> $series */
    public static function agrupadas(array $grupos, array $series): string
    {
        [$W, $H, $padL, $padB, $padT] = [720, 260, 52, 34, 14];
        $max = max(1, ...array_merge([1], ...array_map(fn($g) => $g['valores'], $grupos)));
        $gw = ($W - $padL - 10) / max(1, count($grupos));
        $bw = min(34, ($gw - 14) / count($series));
        $y = fn($v) => $padT + ($H - $padT - $padB) * (1 - $v / $max);
        $s = '<svg viewBox="0 0 ' . $W . ' ' . $H . '" style="width:100%;height:auto" role="img">';
        foreach ([0, 0.25, 0.5, 0.75, 1] as $t) {
            $v = $t * $max;
            $s .= '<g><line x1="' . $padL . '" y1="' . self::n($y($v)) . '" x2="' . ($W - 6) . '" y2="' . self::n($y($v)) . '" stroke="#e6ebf1" stroke-width="1"/>'
                . '<text x="' . ($padL - 6) . '" y="' . self::n($y($v) + 3.5) . '" font-size="9.5" fill="' . self::GRAY . '" text-anchor="end">' . F::e(self::kFmt($v)) . '</text></g>';
        }
        foreach ($grupos as $gi => $g) {
            $s .= '<g>';
            foreach ($g['valores'] as $si => $v) {
                $x = $padL + $gi * $gw + ($gw - count($series) * $bw) / 2 + $si * $bw;
                $yy = $y($v);
                $s .= '<g><rect x="' . self::n($x) . '" y="' . self::n($yy) . '" width="' . self::n($bw - 3) . '" height="' . self::n($H - $padB - $yy) . '" rx="3" fill="' . $series[$si]['color'] . '"/>';
                if ($v > 0) {
                    $s .= '<text x="' . self::n($x + ($bw - 3) / 2) . '" y="' . self::n($yy - 4) . '" font-size="8.5" fill="' . self::NAVY . '" text-anchor="middle">' . F::e(self::kFmt($v)) . '</text>';
                }
                $s .= '</g>';
            }
            $s .= '<text x="' . self::n($padL + $gi * $gw + $gw / 2) . '" y="' . ($H - $padB + 16) . '" font-size="10.5" fill="' . self::NAVY . '" font-weight="600" text-anchor="middle">' . F::e($g['etiqueta']) . '</text></g>';
        }
        return $s . '</svg>';
    }

    /** @param array<int, array{etiqueta: string, valor: float, sub?: string}> $datos */
    public static function simple(array $datos, string $color, string $unidad): string
    {
        [$W, $H, $padL, $padB, $padT] = [720, 240, 46, 40, 14];
        $max = max(1, ...array_merge([1], array_column($datos, 'valor')));
        $gw = ($W - $padL - 10) / max(1, count($datos));
        $bw = min(44, $gw - 10);
        $y = fn($v) => $padT + ($H - $padT - $padB) * (1 - $v / $max);
        $s = '<svg viewBox="0 0 ' . $W . ' ' . $H . '" style="width:100%;height:auto" role="img">';
        foreach ([0, 0.5, 1] as $t) {
            $s .= '<g><line x1="' . $padL . '" y1="' . self::n($y($t * $max)) . '" x2="' . ($W - 6) . '" y2="' . self::n($y($t * $max)) . '" stroke="#e6ebf1" stroke-width="1"/>'
                . '<text x="' . ($padL - 6) . '" y="' . self::n($y($t * $max) + 3.5) . '" font-size="9.5" fill="' . self::GRAY . '" text-anchor="end">' . (int) round($t * $max) . '</text></g>';
        }
        foreach ($datos as $i => $d) {
            $x = $padL + $i * $gw + ($gw - $bw) / 2;
            $yy = $y($d['valor']);
            $s .= '<g><rect x="' . self::n($x) . '" y="' . self::n($yy) . '" width="' . self::n($bw) . '" height="' . self::n($H - $padB - $yy) . '" rx="3" fill="' . $color . '"/>'
                . '<text x="' . self::n($x + $bw / 2) . '" y="' . self::n($yy - 4) . '" font-size="8.5" fill="' . self::NAVY . '" text-anchor="middle">' . F::e(F::num($d['valor'], 0) . $unidad) . '</text>'
                . '<text x="' . self::n($x + $bw / 2) . '" y="' . ($H - $padB + 14) . '" font-size="9.5" fill="' . self::NAVY . '" font-weight="600" text-anchor="middle">' . F::e($d['etiqueta']) . '</text>';
            if (!empty($d['sub'])) {
                $s .= '<text x="' . self::n($x + $bw / 2) . '" y="' . ($H - $padB + 26) . '" font-size="8" fill="' . self::GRAY . '" text-anchor="middle">' . F::e(mb_substr($d['sub'], 0, 14)) . '</text>';
            }
            $s .= '</g>';
        }
        return $s . '</svg>';
    }
}
