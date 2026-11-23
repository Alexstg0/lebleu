<?php
namespace Lebleu\Compartido;

// Íconos SVG en línea (los mismos del original).
final class Iconos
{
    private const ATTR = 'class="ci" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';

    public static function combustible(): string
    {
        return '<svg ' . self::ATTR . '><path d="M12 3s5 5.5 5 9a5 5 0 0 1-10 0c0-3.5 5-9 5-9z"/></svg>';
    }

    public static function marinero(): string
    {
        return '<svg ' . self::ATTR . '><circle cx="12" cy="5" r="2"/><path d="M12 7v13M5 13a7 7 0 0 0 14 0M4 13H2m20 0h-2"/></svg>';
    }

    public static function insumos(): string
    {
        return '<svg ' . self::ATTR . '><path d="M6 8h12l-1 12H7L6 8zM9 8V6a3 3 0 0 1 6 0v2"/></svg>';
    }

    public static function chevron(): string
    {
        return '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>';
    }
}
