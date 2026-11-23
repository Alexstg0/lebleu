<?php
namespace Lebleu\Modulos\Auth;

use Lebleu\Core\Peticion;
use Lebleu\Core\Respuesta;

final class AuthController
{
    public function login(Peticion $p, array $args): Respuesta
    {
        try {
            $d = $p->json();
            $email = trim((string) ($d['email'] ?? ''));
            $clave = (string) ($d['password'] ?? '');
            $r = (new AuthServicio())->iniciarSesion($email, $clave, $p->ip());
        } catch (\Throwable $e) {
            error_log('login error: ' . $e->getMessage());
            return Respuesta::error('No se pudo iniciar sesión.', 400);
        }
        if (!$r['ok']) {
            return Respuesta::error($r['error'], $r['estado']);
        }
        self::ponerCookie($r['token'], time() + AuthServicio::DIAS_SESION * 86400, $p->esHttps());
        return Respuesta::json(['ok' => true, 'rol' => $r['rol']]);
    }

    public function logout(Peticion $p, array $args): Respuesta
    {
        (new AuthServicio())->cerrarSesion($p->cookie(AuthServicio::COOKIE));
        self::ponerCookie('', time() - 3600, $p->esHttps());
        return Respuesta::json(['ok' => true]);
    }

    private static function ponerCookie(string $valor, int $expira, bool $https): void
    {
        setcookie(AuthServicio::COOKIE, $valor, [
            'expires'  => $expira,
            'path'     => '/',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
