<?php
namespace Lebleu\Modulos\Auth;

use Lebleu\Core\Db;

// Único lugar del módulo Auth que escribe SQL.
final class AuthRepositorio
{
    public function usuarioActivoPorEmail(string $email): ?array
    {
        return Db::fila(
            'SELECT id, nombre, email, password_hash, rol, socio_id
               FROM usuarios WHERE LOWER(email) = LOWER(?) AND activo = 1',
            [$email]
        );
    }

    public function intentosVigentes(string $clave, int $ventanaMin): int
    {
        $f = Db::fila(
            'SELECT intentos FROM login_intentos
              WHERE clave = ? AND updated_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? MINUTE)',
            [$clave, $ventanaMin]
        );
        return (int) ($f['intentos'] ?? 0);
    }

    public function registrarFallo(string $clave, int $ventanaMin): void
    {
        Db::ejecutar(
            'INSERT INTO login_intentos (clave, intentos, updated_at) VALUES (?, 1, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE
               intentos = IF(updated_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? MINUTE), intentos + 1, 1),
               updated_at = UTC_TIMESTAMP()',
            [$clave, $ventanaMin]
        );
    }

    public function limpiarIntentos(string $clave): void
    {
        Db::ejecutar('DELETE FROM login_intentos WHERE clave = ?', [$clave]);
    }

    public function crearSesion(string $token, int $usuarioId, int $dias): void
    {
        Db::ejecutar(
            'INSERT INTO sesiones (token, usuario_id, expires_at)
             VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? DAY))',
            [$token, $usuarioId, $dias]
        );
    }

    public function usuarioPorToken(string $token): ?array
    {
        return Db::fila(
            'SELECT u.id, u.nombre, u.email, u.rol, u.socio_id
               FROM sesiones s JOIN usuarios u ON u.id = s.usuario_id
              WHERE s.token = ? AND s.expires_at > UTC_TIMESTAMP() AND u.activo = 1',
            [$token]
        );
    }

    public function borrarSesion(string $token): void
    {
        Db::ejecutar('DELETE FROM sesiones WHERE token = ?', [$token]);
    }
}
