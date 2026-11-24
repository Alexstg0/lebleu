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
        if (self::$senuelo !== null) {
            return self::$senuelo;
        }
        // Bajo FastCGI cada petición es un proceso nuevo: calcular un hash cada vez haría tardar más a los usuarios que no existen
        // (y delataría cuáles existen). Se calcula una vez y se guarda en la carpeta temporal, por algoritmo.
        $archivo = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lebleu_senuelo_' . md5((string) self::algoritmo()) . '.txt';
        $guardado = is_file($archivo) ? trim((string) @file_get_contents($archivo)) : '';
        if ($guardado !== '' && password_get_info($guardado)['algo'] !== null) {
            return self::$senuelo = $guardado;
        }
        self::$senuelo = self::crear(bin2hex(random_bytes(16)));
        @file_put_contents($archivo, self::$senuelo, LOCK_EX);
        return self::$senuelo;
    }
}
