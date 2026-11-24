<?php
namespace Lebleu\Compartido\Pdf;

use Lebleu\Compartido\Formato;

// Recibo de una página (marineros, nómina, notas de gasto) y encabezado común de los reportes.
// Es el equivalente de lib/recibo.ts del sistema original, con las mismas medidas y colores.
final class Recibo
{
    public const BAND = [0.102, 0.227, 0.361];
    public const NAVY = [0.086, 0.153, 0.28];
    public const GRAY = [0.5, 0.55, 0.62];
    public const LIGHT = [0.94, 0.95, 0.97];
    public const BORDER = [0.8, 0.83, 0.87];
    public const WHITE = [1, 1, 1];
    public const W = Lienzo::ANCHO;
    public const H = Lienzo::ALTO;
    public const M = 46;

    private const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    public static function logo(string $nombre): string
    {
        return __DIR__ . '/logos/' . $nombre . '.png';
    }

    public static function capMes(int $m): string
    {
        return ucfirst(self::MESES[$m - 1]);
    }

    public static function cortaFecha(string $iso): string
    {
        $s = explode('-', substr($iso, 0, 10));
        return count($s) === 3 ? "{$s[2]}/{$s[1]}" : $iso;
    }

    public static function largaFecha(string $iso): string
    {
        $s = explode('-', substr($iso, 0, 10));
        return count($s) === 3 ? ((int) $s[2]) . ' de ' . self::MESES[(int) $s[1] - 1] . ' de ' . $s[0] : $iso;
    }

    /** Número como lo escribe JavaScript al interpolarlo en un texto (4 → "4", 4.5 → "4.5"). */
    public static function numJs(float|string|null $v): string
    {
        return (string) json_encode(round((float) ($v ?? 0), 10));
    }

    public static function mxn(float|string|null $v): string
    {
        return Formato::mxn($v ?? 0);
    }

    /** Banda azul superior con logo a la izquierda y título/periodo a la derecha. */
    public static function encabezado(Lienzo $l, string $logo, string $titulo, string $periodoTxt): void
    {
        [$W, $H, $M] = [self::W, self::H, self::M];
        $l->rect(0, $H - 78, $W, 78, self::BAND);
        [$iw, $ih] = Lienzo::medidas($logo);
        $lw = 120;
        $lh = $ih / $iw * $lw;
        $l->imagen($logo, $M, $H - 39 - $lh / 2, $lw, $lh);
        $t = mb_strtoupper($titulo);
        $l->texto($t, $W - $M - $l->ancho($t, 13, true), $H - 40, 13, true, self::WHITE);
        $l->texto($periodoTxt, $W - $M - $l->ancho($periodoTxt, 9.5), $H - 56, 9.5, false, [0.82, 0.86, 0.92]);
    }

    /**
     * @param array $d titulo, periodoTxt, etiqueta?, nombre, sub, pairs [[[lbl,val],[lbl,val]],…], total, totalLabel,
     *                 observaciones, firmaIzq {nombre,rol}, firmaDer {nombre,rol}
     */
    public static function dibujar(Lienzo $l, string $logo, array $d): void
    {
        [$W, $H, $M] = [self::W, self::H, self::M];
        $T = fn(string $s, float $x, float $top, float $tam, bool $neg = false, array $c = self::NAVY) => $l->texto($s, $x, $H - $top, $tam, $neg, $c);
        self::encabezado($l, $logo, $d['titulo'], $d['periodoTxt']);

        if (!empty($d['etiqueta'])) {
            $T($d['etiqueta'], $M, 118, 11, true);
        }
        $T($d['nombre'], $M, 146, 22, true);
        if (!empty($d['sub'])) {
            $T($d['sub'], $M, 166, 10.5, false, self::GRAY);
        }

        $tableTop = 196;
        $rowH = 28;
        $cw = $W - 2 * $M;
        $colw = $cw / 2;
        foreach ($d['pairs'] as $i => $fila) {
            $rowTop = $tableTop + $i * $rowH;
            $ry = $H - $rowTop - $rowH;
            $l->rect($M, $ry, $cw, $rowH, null, self::BORDER, 1);
            foreach ($fila as $k => [$lbl, $val]) {
                $cx = $M + $k * $colw;
                $l->rect($cx, $ry, 112, $rowH, self::LIGHT);
                $T($lbl, $cx + 8, $rowTop + 18, 7.5, true);
                $T(mb_substr((string) $val, 0, 34), $cx + 120, $rowTop + 18, 9.5, false, [0.2, 0.25, 0.32]);
            }
        }

        $top = $tableTop + count($d['pairs']) * $rowH + 24;
        $l->rect($M, $H - $top - 32, $cw, 32, self::NAVY);
        $T($d['totalLabel'], $M + 14, $top + 21, 12, true, self::WHITE);
        $tp = self::mxn($d['total']) . ' MXN';
        $T($tp, $M + $cw - 14 - $l->ancho($tp, 14, true), $top + 21, 14, true, self::WHITE);
        $top += 32 + 32;

        $T('OBSERVACIONES', $M, $top, 10, true);
        $top += 16;
        $linea = '';
        foreach (explode(' ', (string) ($d['observaciones'] ?? '')) as $palabra) {
            $prueba = $linea !== '' ? "$linea $palabra" : $palabra;
            if ($l->ancho($prueba, 9) > $cw && $linea !== '') {
                $T($linea, $M, $top, 9, false, [0.3, 0.35, 0.42]);
                $top += 13;
                $linea = $palabra;
            } else {
                $linea = $prueba;
            }
        }
        if ($linea !== '') {
            $T($linea, $M, $top, 9, false, [0.3, 0.35, 0.42]);
        }

        $sigY = 120;
        $sigW = 200;
        $firma = function (float $x, array $s) use ($l, $sigY, $sigW) {
            $l->linea($x, $sigY, $x + $sigW, $sigY, 1, self::NAVY);
            $l->texto($s['nombre'], $x + max(6, ($sigW - $l->ancho($s['nombre'], 9, true)) / 2), $sigY - 14, 9, true, self::NAVY);
            $l->texto($s['rol'], $x + ($sigW - $l->ancho($s['rol'], 8)) / 2, $sigY - 26, 8, false, self::GRAY);
        };
        $firma($M + 20, $d['firmaIzq']);
        $firma($W - $M - 20 - $sigW, $d['firmaDer']);
    }

    /** PDF de una sola página con el recibo. */
    public static function unaPagina(string $logo, array $d): string
    {
        $l = new Lienzo();
        $l->nuevaPagina();
        self::dibujar($l, $logo, $d);
        return $l->salida();
    }
}
