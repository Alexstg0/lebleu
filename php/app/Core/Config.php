<?php
namespace Lebleu\Core;

final class Config
{
    private static ?array $datos = null;

    public static function obtener(string $ruta, mixed $defecto = null): mixed
    {
        if (self::$datos === null) {
            $archivo = dirname(__DIR__, 2) . '/config/config.php';
            self::$datos = is_file($archivo) ? (require $archivo) : [];
        }
        $valor = self::$datos;
        foreach (explode('.', $ruta) as $parte) {
            if (!is_array($valor) || !array_key_exists($parte, $valor)) {
                return $defecto;
            }
            $valor = $valor[$parte];
        }
        return $valor;
    }

    public static function fijar(array $datos): void
    {
        self::$datos = $datos;
    }
}
