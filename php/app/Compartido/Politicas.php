<?php
namespace Lebleu\Compartido;

use Lebleu\Core\Peticion;
use Lebleu\Core\Respuesta;
use Lebleu\Modulos\Auth\AuthServicio;

// Autorización de dos capas: guardia de API (apiGuard) y guardia de página (requerirUsuario).
// Cada Controller valida su propio rol; no se asume que la protección de la página basta.
final class Politicas
{
    public static function usuarioActual(Peticion $p): ?array
    {
        return (new AuthServicio())->usuarioDeToken($p->cookie(AuthServicio::COOKIE));
    }

    /** Devuelve el usuario si su rol está permitido; si no, null (el handler responde 401/403). */
    public static function guardiaApi(Peticion $p, array $roles): ?array
    {
        $u = self::usuarioActual($p);
        return ($u && in_array($u['rol'], $roles, true)) ? $u : null;
    }

    public static function respuestaDenegada(Peticion $p): Respuesta
    {
        return self::usuarioActual($p)
            ? Respuesta::error('No tienes permiso para esto.', 403)
            : Respuesta::error('Sesión requerida.', 401);
    }

    /** Para páginas: usuario o respuesta de redirección. @return array{0: ?array, 1: ?Respuesta} */
    public static function requerirUsuario(Peticion $p, ?array $roles = null): array
    {
        $u = self::usuarioActual($p);
        if (!$u) {
            return [null, Respuesta::redirigir('/login')];
        }
        if ($roles !== null && !in_array($u['rol'], $roles, true)) {
            return [null, Respuesta::redirigir(AuthServicio::inicioDe($u['rol']))];
        }
        return [$u, null];
    }
}
