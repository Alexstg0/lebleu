<?php
namespace Lebleu\Compartido\Pdf;

// Carga FPDF (copiado en vendor/fpdf, sin Composer). Los PDF se escriben con tipografía Helvetica estándar.
final class Cargador
{
    public static function fpdf(): void
    {
        if (!class_exists('FPDF', false)) {
            if (!defined('FPDF_FONTPATH')) {
                define('FPDF_FONTPATH', dirname(__DIR__, 3) . '/vendor/fpdf/font/');
            }
            require_once dirname(__DIR__, 3) . '/vendor/fpdf/fpdf.php';
        }
    }

    /** FPDF escribe en Windows-1252: convierte el texto UTF-8 (acentos, ñ, ¿, —) sin perder caracteres. */
    public static function texto(?string $utf8): string
    {
        $t = strtr((string) $utf8, ['—' => '-', '–' => '-', '“' => '"', '”' => '"', '‘' => "'", '’' => "'", '…' => '...', '→' => '->', '✕' => 'x']);
        $c = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $t);
        return $c === false ? preg_replace('/[^\x20-\x7E]/', '?', $t) : $c;
    }
}
