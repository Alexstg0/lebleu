<?php
namespace Lebleu\Compartido;

// Piezas de formulario con el mismo HTML que producía la versión React (clases .field, .grid, etc.).
final class Campos
{
    /** @param string $extra clase adicional del contenedor (ej. "full") */
    public static function campo(string $etiqueta, string $control, string $extra = '', string $atributos = ''): string
    {
        $clase = trim('field ' . $extra);
        return '<div class="' . $clase . '"' . ($atributos ? " $atributos" : '') . '><label>' . Formato::e($etiqueta) . '</label>' . $control . '</div>';
    }

    /** @param array<string, string|bool|null> $attrs */
    public static function input(string $nombre, ?string $valor, array $attrs = []): string
    {
        return '<input name="' . Formato::e($nombre) . '" value="' . Formato::e($valor) . '"' . self::atributos($attrs) . '>';
    }

    /** @param array<string|int, string> $opciones valor => etiqueta @param array<string, string|bool|null> $attrs */
    public static function select(string $nombre, array $opciones, ?string $seleccionado, array $attrs = []): string
    {
        $h = '<select name="' . Formato::e($nombre) . '"' . self::atributos($attrs) . '>';
        foreach ($opciones as $valor => $etiqueta) {
            $h .= '<option value="' . Formato::e((string) $valor) . '"' . ((string) $valor === (string) $seleccionado ? ' selected' : '') . '>' . Formato::e($etiqueta) . '</option>';
        }
        return $h . '</select>';
    }

    /** @param array<string, string|bool|null> $attrs */
    public static function atributos(array $attrs): string
    {
        $h = '';
        foreach ($attrs as $k => $v) {
            if ($v === false || $v === null) {
                continue;
            }
            $h .= $v === true ? " $k" : " $k=\"" . Formato::e((string) $v) . '"';
        }
        return $h;
    }
}
