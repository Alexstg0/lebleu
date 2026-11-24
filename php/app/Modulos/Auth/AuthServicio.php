<?php
namespace Lebleu\Modulos\Auth;

use Lebleu\Core\Clave;

// Reglas de negocio del acceso: límite de intentos, verificación en tiempo constante y sesiones.
final class AuthServicio
{
    public const COOKIE = 'sid';
    public const MAX_INTENTOS = 8;
    public const MAX_INTENTOS_IP = 60;
    public const VENTANA_MIN = 10;
    public const DIAS_SESION = 30;

    public function __construct(private ?AuthRepositorio $repo = null)
    {
        $this->repo ??= new AuthRepositorio();
    }

    /** @return array{ok: bool, estado: int, error?: string, token?: string, rol?: string} */
    public function iniciarSesion(string $email, string $clave, string $ip): array
    {
        $u = $this->repo->usuarioActivoPorEmail($email);
        // El límite se cuenta por CUENTA (su id), no por el texto escrito: MySQL compara el correo sin acentos ni mayúsculas,
        // así que variantes del mismo correo entran a la misma cuenta y deben caer en el mismo contador. Además hay un tope por IP.
        $llave = $ip . ':' . ($u ? 'u' . $u['id'] : 'e' . mb_strtolower($email));
        $llaveIp = 'ip:' . $ip;
        if ($this->repo->intentosVigentes($llave, self::VENTANA_MIN) >= self::MAX_INTENTOS
            || $this->repo->intentosVigentes($llaveIp, self::VENTANA_MIN) >= self::MAX_INTENTOS_IP) {
            return ['ok' => false, 'estado' => 429,
                    'error' => 'Demasiados intentos. Espera unos minutos e inténtalo de nuevo.'];
        }
        // El intento se cuenta ANTES de verificar la contraseña: peticiones en paralelo no pueden saltarse el límite.
        $this->repo->registrarFallo($llave, self::VENTANA_MIN);
        // Se verifica SIEMPRE (contra el hash real o el señuelo) para que tarde lo mismo exista o no.
        $claveOk = Clave::verificar($clave, $u['password_hash'] ?? Clave::senuelo());
        if (!$u || !$claveOk) {
            $this->repo->registrarFallo($llaveIp, self::VENTANA_MIN); // el tope por IP cuenta solo los fallos
            return ['ok' => false, 'estado' => 401, 'error' => 'Correo o contraseña incorrectos.'];
        }

        $this->repo->limpiarIntentos($llave);
        $token = bin2hex(random_bytes(32));
        $this->repo->crearSesion($token, (int) $u['id'], self::DIAS_SESION);
        return ['ok' => true, 'estado' => 200, 'token' => $token, 'rol' => $u['rol']];
    }

    public function usuarioDeToken(?string $token): ?array
    {
        if (!$token || !preg_match('/^[0-9a-f]{64}$/', $token)) {
            return null;
        }
        return $this->repo->usuarioPorToken($token);
    }

    public function cerrarSesion(?string $token): void
    {
        if ($token && preg_match('/^[0-9a-f]{64}$/', $token)) {
            $this->repo->borrarSesion($token);
        }
    }

    public static function inicioDe(string $rol): string
    {
        return $rol === 'capitan' ? '/calendario' : '/';
    }
}
