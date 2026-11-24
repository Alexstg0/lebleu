<?php
namespace Lebleu\Modulos\Usuarios;

use Lebleu\Core\Db;

final class UsuariosRepositorio
{
    public function listar(): array
    {
        return Db::filas(
            "SELECT u.id, u.nombre, u.email, u.rol, u.socio_id, u.activo, so.nombre AS socio_nombre
               FROM usuarios u LEFT JOIN socios so ON so.id = u.socio_id
              ORDER BY CASE u.rol WHEN 'admin' THEN 0 WHEN 'capitan' THEN 1 ELSE 2 END, u.id"
        );
    }

    public function insertar(string $nombre, string $email, string $hash, string $rol, ?int $socioId): int
    {
        Db::ejecutar('INSERT INTO usuarios (nombre, email, password_hash, rol, socio_id) VALUES (?, ?, ?, ?, ?)', [$nombre, $email, $hash, $rol, $socioId]);
        return Db::ultimoId();
    }

    public function existe(int $id): bool
    {
        return (bool) Db::fila('SELECT 1 FROM usuarios WHERE id = ?', [$id]);
    }

    public function esAdminActivo(int $id): bool
    {
        return (bool) Db::fila("SELECT 1 FROM usuarios WHERE id = ? AND rol = 'admin' AND activo = 1", [$id]);
    }

    /** Administradores activos distintos de $exceptoId. */
    public function otrosAdminsActivos(int $exceptoId): int
    {
        return (int) Db::fila("SELECT COUNT(*) n FROM usuarios WHERE rol = 'admin' AND activo = 1 AND id <> ?", [$exceptoId])['n'];
    }

    public function fijarClave(int $id, string $hash): void
    {
        Db::ejecutar('UPDATE usuarios SET password_hash = ? WHERE id = ?', [$hash, $id]);
    }

    public function fijarActivo(int $id, bool $activo): void
    {
        Db::ejecutar('UPDATE usuarios SET activo = ? WHERE id = ?', [$activo ? 1 : 0, $id]);
    }

    public function cerrarSesiones(int $id): void
    {
        Db::ejecutar('DELETE FROM sesiones WHERE usuario_id = ?', [$id]);
    }

    public function borrar(int $id): void
    {
        Db::ejecutar('DELETE FROM usuarios WHERE id = ?', [$id]);
    }
}
