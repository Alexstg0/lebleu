<?php
namespace Lebleu\Compartido\Pdf;

// Pequeña capa sobre FPDF con la misma forma de dibujar que pdf-lib (la usa el sistema original):
// coordenadas en puntos con el origen ABAJO a la izquierda, colores 0..1 y texto con su línea base en y.
// Así los reportes se portan casi renglón por renglón y salen con las mismas medidas.
final class Lienzo
{
    public const ANCHO = 595.28;
    public const ALTO = 841.89;

    private \FPDF $pdf;
    private float $alto = self::ALTO;
    /** @var array<string, array<int, int>> */
    private static array $kerning = [];

    public function __construct()
    {
        Cargador::fpdf();
        $this->pdf = new \FPDF('P', 'pt', [self::ANCHO, self::ALTO]);
        $this->pdf->SetAutoPageBreak(false);
        $this->pdf->SetMargins(0, 0, 0);
        $this->pdf->SetCompression(true);
    }

    /** Página A4 vertical (u horizontal con $horizontal = true). */
    public function nuevaPagina(bool $horizontal = false): void
    {
        $this->alto = $horizontal ? self::ANCHO : self::ALTO;
        $this->pdf->AddPage($horizontal ? 'L' : 'P', [self::ANCHO, self::ALTO]);
    }

    public static function rgb(float $r, float $g, float $b): array
    {
        return [$r, $g, $b];
    }

    /** Mezcla de $frente sobre $fondo con opacidad $a (equivale a dibujar con transparencia sobre ese fondo). */
    public static function mezcla(array $frente, array $fondo, float $a): array
    {
        return [$frente[0] * $a + $fondo[0] * (1 - $a), $frente[1] * $a + $fondo[1] * (1 - $a), $frente[2] * $a + $fondo[2] * (1 - $a)];
    }

    /**
     * Ancho de un texto (puntos) con Helvetica normal o negrita. Incluye el kerning de las tablas AFM, igual que
     * pdf-lib al medir (al dibujar no lo aplica ninguno de los dos), para que centrados y recortes coincidan.
     */
    public function ancho(string $texto, float $tam, bool $negrita = false): float
    {
        $this->fuente($negrita, $tam);
        $c = self::codificar($texto);
        $ancho = $this->pdf->GetStringWidth($c);
        $pares = self::$kerning[$negrita ? 'B' : 'N'] ??= (require __DIR__ . '/kerning.php')[$negrita ? 'B' : 'N'];
        for ($i = 0, $n = strlen($c) - 1; $i < $n; $i++) {
            $ancho += ($pares[ord($c[$i]) * 256 + ord($c[$i + 1])] ?? 0) * $tam / 1000;
        }
        return $ancho;
    }

    public function texto(string $texto, float $x, float $y, float $tam, bool $negrita, array $color): void
    {
        $this->fuente($negrita, $tam);
        $this->pdf->SetTextColor(...self::c($color));
        $this->pdf->Text($x, $this->alto - $y, self::codificar($texto));
    }

    /** Rectángulo con esquina inferior izquierda en (x, y). Relleno y/o borde. */
    public function rect(float $x, float $y, float $w, float $h, ?array $relleno = null, ?array $borde = null, float $grosor = 1): void
    {
        $modo = '';
        if ($relleno) {
            $this->pdf->SetFillColor(...self::c($relleno));
            $modo .= 'F';
        }
        if ($borde && $grosor > 0) {
            $this->pdf->SetDrawColor(...self::c($borde));
            $this->pdf->SetLineWidth($grosor);
            $modo .= 'D';
        }
        if ($modo !== '') {
            $this->pdf->Rect($x, $this->alto - $y - $h, $w, $h, $modo);
        }
    }

    public function linea(float $x1, float $y1, float $x2, float $y2, float $grosor, array $color): void
    {
        $this->pdf->SetDrawColor(...self::c($color));
        $this->pdf->SetLineWidth($grosor);
        $this->pdf->Line($x1, $this->alto - $y1, $x2, $this->alto - $y2);
    }

    /** Elipse centrada en (cx, cy). */
    public function elipse(float $cx, float $cy, float $rx, float $ry, ?array $relleno, ?array $borde, float $grosor): void
    {
        $k = 0.5522847498;
        $p = $this->pdf;
        $pts = fn($x, $y) => sprintf('%.3F %.3F', $x, $y); // operadores PDF en bruto: el origen ya es abajo-izquierda
        $partes = [
            $pts($cx + $rx, $cy) . ' m',
            $pts($cx + $rx, $cy + $ry * $k) . ' ' . $pts($cx + $rx * $k, $cy + $ry) . ' ' . $pts($cx, $cy + $ry) . ' c',
            $pts($cx - $rx * $k, $cy + $ry) . ' ' . $pts($cx - $rx, $cy + $ry * $k) . ' ' . $pts($cx - $rx, $cy) . ' c',
            $pts($cx - $rx, $cy - $ry * $k) . ' ' . $pts($cx - $rx * $k, $cy - $ry) . ' ' . $pts($cx, $cy - $ry) . ' c',
            $pts($cx + $rx * $k, $cy - $ry) . ' ' . $pts($cx + $rx, $cy - $ry * $k) . ' ' . $pts($cx + $rx, $cy) . ' c h',
        ];
        $op = $relleno && $borde ? 'B' : ($relleno ? 'f' : 'S');
        if ($relleno) {
            $p->SetFillColor(...self::c($relleno));
        }
        if ($borde) {
            $p->SetDrawColor(...self::c($borde));
            $p->SetLineWidth($grosor);
        }
        $this->bruto(implode("\n", $partes) . "\n$op");
    }

    /** Imagen PNG/JPG con esquina inferior izquierda en (x, y). */
    public function imagen(string $ruta, float $x, float $y, float $w, float $h): void
    {
        $this->pdf->Image($ruta, $x, $this->alto - $y - $h, $w, $h);
    }

    /** Medidas de una imagen (para conservar su proporción). @return array{0: int, 1: int} */
    public static function medidas(string $ruta): array
    {
        $i = getimagesize($ruta);
        return [(int) $i[0], (int) $i[1]];
    }

    public function salida(): string
    {
        return $this->pdf->Output('S');
    }

    /** UTF-8 → Windows-1252 (la codificación de las fuentes estándar), sin perder —, …, • ni ›. */
    public static function codificar(string $utf8): string
    {
        $c = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $utf8);
        return $c === false ? preg_replace('/[^\x20-\x7E]/', '?', $utf8) : $c;
    }

    private function fuente(bool $negrita, float $tam): void
    {
        $this->pdf->SetFont('Helvetica', $negrita ? 'B' : '', $tam);
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function c(array $color): array
    {
        return [(int) round($color[0] * 255), (int) round($color[1] * 255), (int) round($color[2] * 255)];
    }

    private function bruto(string $operadores): void
    {
        (function (string $s) {
            $this->_out($s);
        })->call($this->pdf, $operadores);
    }
}
