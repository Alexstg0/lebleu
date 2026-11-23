<?php
namespace Lebleu\Core;

// Contraseñas: password_hash de PHP (Argon2id si el servidor lo trae, si no bcrypt).
final class Clave
{
    private static ?string $senuelo = null;

    private static function algoritmo(): string
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    public static function crear(string $clave): string
    {
        return password_hash($clave, self::algoritmo());
    }

    public static function verificar(string $clave, string $hash): bool
    {
        return password_verify($clave, $hash);
    }

    // Hash falso con el mismo costo, para gastar el mismo tiempo cuando el usuario no existe.
    public static function senuelo(): string
    {
        return self::$senuelo ??= self::crear(bin2hex(random_bytes(16)));
    }
}
