<?php
namespace Lebleu\Compartido\Pdf;

// Convierte una imagen JPG/PNG en un PDF de una página A4 con la imagen centrada
// (mismas medidas que el original: márgenes de 24 pt y ampliación máxima de 1.5x).
final class ImagenAPdf
{
    private const ANCHO = 595.28;
    private const ALTO = 841.89;
    private const MARGEN = 24;

    public static function convertir(string $binario): string
    {
        $info = @getimagesizefromstring($binario);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            throw new \InvalidArgumentException('La imagen no es un JPG o PNG válido.');
        }
        [$w, $h] = $info;
        if ($w < 1 || $h < 1 || $w * $h > 25_000_000) {
            throw new \InvalidArgumentException('La imagen es demasiado grande (máximo 25 megapíxeles). Tómala en tamaño reducido.');
        }
        $esc = min((self::ANCHO - 2 * self::MARGEN) / $w, (self::ALTO - 2 * self::MARGEN) / $h, 1.5);
        $pw = $w * $esc;
        $ph = $h * $esc;

        try {
            return self::armar($binario, $info[2] === IMAGETYPE_PNG ? 'PNG' : 'JPG', $pw, $ph);
        } catch (\Throwable $e) {
            // FPDF no abre algunos PNG (entrelazados, de 16 bits): se pasa por GD a JPG sin transparencia.
            if (!function_exists('imagecreatefromstring')) {
                throw new \InvalidArgumentException('No se pudo leer la imagen.');
            }
            $origen = @imagecreatefromstring($binario);
            if (!$origen) {
                throw new \InvalidArgumentException('No se pudo leer la imagen.');
            }
            $lienzo = imagecreatetruecolor($w, $h);
            imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));
            imagecopy($lienzo, $origen, 0, 0, 0, 0, $w, $h);
            ob_start();
            imagejpeg($lienzo, null, 90);
            return self::armar((string) ob_get_clean(), 'JPG', $pw, $ph);
        }
    }

    private static function armar(string $imagen, string $tipo, float $pw, float $ph): string
    {
        Cargador::fpdf();
        $tmp = tempnam(sys_get_temp_dir(), 'lbl') ?: throw new \RuntimeException('Sin carpeta temporal.');
        $ruta = $tmp . ($tipo === 'PNG' ? '.png' : '.jpg');
        rename($tmp, $ruta);
        try {
            file_put_contents($ruta, $imagen);
            $pdf = new \FPDF('P', 'pt', [self::ANCHO, self::ALTO]);
            $pdf->SetAutoPageBreak(false);
            $pdf->AddPage();
            $pdf->Image($ruta, (self::ANCHO - $pw) / 2, (self::ALTO - $ph) / 2, $pw, $ph, $tipo);
            return $pdf->Output('S');
        } finally {
            @unlink($ruta);
        }
    }
}
