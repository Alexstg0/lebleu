<?php
namespace Lebleu\Modulos\Usuarios;

use Lebleu\Compartido\Entrada;
use Lebleu\Core\Clave;
use Lebleu\Core\Db;

// Altas, cambio de contraseña, activación y baja de cuentas (solo administrador).
final class UsuariosServicio
{
    public const ROLES = ['admin', 'capitan', 'socio'];
    public const CLAVE_MINIMA = 6;

    private UsuariosRepositorio $repo;

    public function __construct(?UsuariosRepositorio $repo = null)
    {
        $this->repo = $repo ?? new UsuariosRepositorio();
    }

    public function listar(): array
    {
        return array_map(fn($u) => ['id' => (int) $u['id'], 'activo' => (bool) $u['activo'], 'socio_id' => $u['socio_id'] !== null ? (int) $u['socio_id'] : null] + $u,
            $this->repo->listar());
    }

    /** @throws \InvalidArgumentException con el mensaje que ve el administrador */
    public function crear(array $b): int
    {
        $nombre = Entrada::texto($b, 'nombre', 'El nombre', 160);
        $email = trim((string) ($b['email'] ?? ''));
        if ($email === '' || mb_strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Escribe un correo válido.');
        }
        $rol = Entrada::opcion($b, 'rol', 'El rol', self::ROLES, 'capitan');
        $socioId = $rol === 'socio' ? Entrada::entero($b, 'socio_id', 'El socio asociado') : null;
        $hash = Clave::crear($this->claveValida($b['password'] ?? ''));
        try {
            return $this->repo->insertar($nombre, $email, $hash, $rol, $socioId);
        } catch (\PDOException $e) {
            if (($e->errorInfo[1] ?? 0) === 1062) {
                throw new \InvalidArgumentException('Ya existe un usuario con ese correo.');
            }
            throw $e;
        }
    }

    /** Cambia la contraseña (y cierra las sesiones abiertas de esa cuenta) y/o activa o desactiva la cuenta. */
    public function actualizar(array $b): void
    {
        $id = (int) ($b['id'] ?? 0);
        if (!$id || !$this->repo->existe($id)) {
            throw new \InvalidArgumentException('Usuario no encontrado.');
        }
        Db::transaccion(function () use ($b, $id) {
            if (!empty($b['password'])) {
                $this->repo->fijarClave($id, Clave::crear($this->claveValida($b['password'])));
                $this->repo->cerrarSesiones($id);
            }
            if (isset($b['activo']) && is_bool($b['activo'])) {
                if (!$b['activo'] && $this->repo->otrosAdminsActivos($id) === 0 && $this->repo->esAdminActivo($id)) {
                    throw new \InvalidArgumentException('No se puede desactivar al único administrador activo.');
                }
                $this->repo->fijarActivo($id, $b['activo']);
                if (!$b['activo']) {
                    $this->repo->cerrarSesiones($id);
                }
            }
        });
    }

    public function borrar(int $yoId, int $id): void
    {
        if ($id === $yoId) {
            throw new \InvalidArgumentException('No puedes borrar tu propia cuenta.');
        }
        $this->repo->borrar($id);
    }

    private function claveValida(mixed $clave): string
    {
        $clave = (string) $clave;
        if (strlen($clave) < self::CLAVE_MINIMA) {
            throw new \InvalidArgumentException('La contraseña debe tener al menos ' . self::CLAVE_MINIMA . ' caracteres.');
        }
        return $clave;
    }
}
