<?php
namespace Lebleu\Modulos\Reportes;

use Lebleu\Compartido\Formato;
use Lebleu\Compartido\Pdf\Lienzo;
use Lebleu\Compartido\Pdf\Recibo;

// Hoja de reporte con cursor vertical ($y = distancia desde el borde superior): texto, secciones y tablas
// que saltan de página solas. Es la base del estado de cuenta en PDF.
final class Hoja
{
    public const NAVY = [0.102, 0.227, 0.361];
    public const OCEAN = [0.118, 0.286, 0.463];
    public const TEAL = [0.055, 0.486, 0.482];
    public const GREEN = [0.102, 0.478, 0.29];
    public const RED = [0.753, 0.224, 0.169];
    public const AMBER = [0.753, 0.337, 0.129];
    public const TEXT = [0.118, 0.176, 0.239];
    public const MUTED = [0.42, 0.478, 0.553];
    public const BG2 = [0.933, 0.949, 0.969];
    public const BORDER = [0.859, 0.89, 0.925];
    public const WHITE = [1, 1, 1];
    public const ZEBRA = [0.975, 0.982, 0.99];

    public float $y = 0;
    public readonly float $cw;
    /** @var null|callable(): void */
    private $alNuevaPagina = null;

    public function __construct(public readonly Lienzo $l)
    {
        $this->cw = Recibo::W - 2 * Recibo::M;
    }

    public function alNuevaPagina(callable $f): void
    {
        $this->alNuevaPagina = $f;
    }

    public function nuevaPagina(bool $conEncabezado = false): void
    {
        $this->l->nuevaPagina();
        $this->y = 46;
        if ($conEncabezado && $this->alNuevaPagina) {
            ($this->alNuevaPagina)();
        }
    }

    /** Salta de página si no caben $h puntos más. */
    public function necesito(float $h): void
    {
        if (Recibo::H - $this->y - $h < 50) {
            $this->nuevaPagina();
        }
    }

    public function T(string $s, float $x, float $tam, bool $neg = false, array $c = self::TEXT): void
    {
        $this->l->texto($s, $x, Recibo::H - $this->y, $tam, $neg, $c);
    }

    /** Texto alineado a la derecha en $xr, con línea base $yy (coordenada del lienzo). */
    public function TR(string $s, float $xr, float $yy, float $tam, bool $neg = false, array $c = self::TEXT): void
    {
        $this->l->texto($s, $xr - $this->l->ancho($s, $tam, $neg), $yy, $tam, $neg, $c);
    }

    /** Recorta con "…" para que quepa en $maxW. */
    public function ajustar(string $s, float $tam, float $maxW, bool $neg = false): string
    {
        if ($this->l->ancho($s, $tam, $neg) <= $maxW) {
            return $s;
        }
        while (mb_strlen($s) > 1 && $this->l->ancho($s . '…', $tam, $neg) > $maxW) {
            $s = mb_substr($s, 0, -1);
        }
        return $s . '…';
    }

    public function seccion(string $titulo, array $color = self::NAVY): void
    {
        $this->necesito(34);
        $this->y += 16;
        $this->l->rect(Recibo::M, Recibo::H - $this->y - 3, 3, 12, $color);
        $this->T(mb_strtoupper($titulo), Recibo::M + 9, 9.5, true, $color);
        $this->y += 16;
    }

    /**
     * @param array<int, array{titulo: string, w: float, alinear?: string, get: callable, color?: callable}> $cols
     * @param array<int, array<int, string>> $totales
     */
    public function tabla(array $cols, array $filas, array $totales = []): void
    {
        $l = $this->l;
        $rowH = 18;
        $xs = [];
        $acc = Recibo::M;
        foreach ($cols as $c) {
            $xs[] = $acc;
            $acc += $c['w'];
        }
        $celda = function (string $v, int $k, bool $neg, array $color, float $yy) use ($cols, $xs, $l) {
            $col = $cols[$k];
            $vv = $this->ajustar($v, 8, $col['w'] - 10, $neg);
            $tx = $xs[$k] + 5;
            if (($col['alinear'] ?? '') === 'r') {
                $tx = $xs[$k] + $col['w'] - 5 - $l->ancho($vv, 8, $neg);
            } elseif (($col['alinear'] ?? '') === 'c') {
                $tx = $xs[$k] + ($col['w'] - $l->ancho($vv, 8, $neg)) / 2;
            }
            $l->texto($vv, $tx, $yy, 8, $neg, $color);
        };
        $cabecera = function () use ($cols, $xs, $rowH, $l) {
            $this->necesito($rowH + 40);
            $l->rect(Recibo::M, Recibo::H - $this->y - $rowH, $this->cw, $rowH, self::BG2);
            foreach ($cols as $k => $c) {
                $tx = $xs[$k] + 5;
                if (($c['alinear'] ?? '') === 'r') {
                    $tx = $xs[$k] + $c['w'] - 5 - $l->ancho($c['titulo'], 7.5, true);
                } elseif (($c['alinear'] ?? '') === 'c') {
                    $tx = $xs[$k] + ($c['w'] - $l->ancho($c['titulo'], 7.5, true)) / 2;
                }
                $l->texto($c['titulo'], $tx, Recibo::H - $this->y - 12.5, 7.5, true, self::NAVY);
            }
            $this->y += $rowH;
        };
        $cabecera();
        foreach (array_values($filas) as $i => $r) {
            if (Recibo::H - $this->y - $rowH < 50) {
                $this->nuevaPagina();
                $cabecera();
            }
            if ($i % 2 === 1) {
                $l->rect(Recibo::M, Recibo::H - $this->y - $rowH, $this->cw, $rowH, self::ZEBRA);
            }
            foreach ($cols as $k => $c) {
                $celda((string) ($c['get']($r, $i) ?? ''), $k, false, isset($c['color']) ? $c['color']($r) : self::TEXT, Recibo::H - $this->y - 12.5);
            }
            $l->linea(Recibo::M, Recibo::H - $this->y - $rowH, Recibo::M + $this->cw, Recibo::H - $this->y - $rowH, 0.4, self::BORDER);
            $this->y += $rowH;
        }
        foreach ($totales as $tr) {
            if (Recibo::H - $this->y - $rowH < 50) {
                $this->nuevaPagina();
            }
            $l->rect(Recibo::M, Recibo::H - $this->y - $rowH, $this->cw, $rowH, self::BG2);
            foreach ($tr as $k => $v) {
                if ($v !== '') {
                    $celda($v, $k, true, self::NAVY, Recibo::H - $this->y - 12.5);
                }
            }
            $this->y += $rowH;
        }
        $this->y += 4;
    }

    public static function nf(float|string|null $v, int $d = 2): string
    {
        return Formato::num($v ?? 0, $d);
    }

    public static function mxn(float|string|null $v): string
    {
        return Formato::mxn($v ?? 0);
    }
}
