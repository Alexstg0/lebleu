<?php
namespace Lebleu\Compartido\Movimientos;

use Lebleu\Compartido\Politicas;
use Lebleu\Core\Db;
use Lebleu\Core\Peticion;
use Lebleu\Core\Respuesta;
use Lebleu\Modulos\Auditoria\AuditoriaServicio;
use Lebleu\Modulos\Periodos\PeriodosServicio;

// Flujo común de alta/edición/baja de un movimiento:
//   permiso por rol -> periodo no cerrado -> validar -> guardar (en transacción) -> auditar.
// Cada módulo solo dice qué campos acepta (valores) y qué texto deja en la auditoría (detalle).
abstract class MovimientoControlador
{
    abstract protected function repo(): MovimientoRepositorio;

    abstract protected function tabla(): string;

    /** Normaliza y valida el cuerpo; devuelve [columna => valor]. Lanza InvalidArgumentException si algo es inválido. */
    abstract protected function valores(array $b, bool $esAlta): array;

    /** Texto de la bitácora para $accion = crear | editar | borrar. */
    abstract protected function detalle(string $accion, array $b, ?array $previo): string;

    /** @return string[] */
    protected function roles(): array
    {
        return ['admin'];
    }

    /** @return string[] */
    protected function rolesBorrar(): array
    {
        return ['admin'];
    }

    /** false para los módulos que no dejan rastro en la bitácora (reservas). */
    protected function auditar(): bool
    {
        return true;
    }

    /** Periodo al que se mueve el movimiento al editar (solo si el módulo permite cambiarlo); se valida igual que el actual. */
    protected function periodoDestino(array $b): ?int
    {
        return null;
    }

    /** Trabajo extra dentro de la misma transacción (ej. sincronizar el costo de un viaje). */
    /** Reglas propias de un módulo sobre los valores ya validados; lanza InvalidArgumentException para rechazar. $previo: la fila actual si es edición. */
    protected function validar(array $valores, ?array $previo): void {}

    protected function alGuardar(int $id, array $b, ?array $previo): void {}

    protected function alBorrar(array $previo): void {}

    protected function respuestaAlta(int $id): array
    {
        return ['ok' => true, 'id' => $id];
    }

    public function crear(Peticion $p, array $args): Respuesta
    {
        $usuario = Politicas::guardiaApi($p, $this->roles());
        if (!$usuario) {
            return Respuesta::error('No autorizado.', 403);
        }
        try {
            $b = $p->json();
            $cerrado = (new PeriodosServicio())->mensajeSiCerrado(isset($b['periodo_id']) ? (int) $b['periodo_id'] : null);
            if ($cerrado) {
                return Respuesta::error($cerrado, 400);
            }
            $id = Db::transaccion(function () use ($b) {
                $valores = $this->valores($b, true);
                $this->validar($valores, null);
                $id = $this->repo()->insertar($valores);
                $this->alGuardar($id, $b, null);
                return $id;
            });
            $this->auditarSi($usuario, 'crear', $id, $this->detalle('crear', $b, null));
            return Respuesta::json($this->respuestaAlta($id));
        } catch (\InvalidArgumentException $e) {
            return Respuesta::error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            return $this->falla($e);
        }
    }

    public function editar(Peticion $p, array $args): Respuesta
    {
        $usuario = Politicas::guardiaApi($p, $this->roles());
        if (!$usuario) {
            return Respuesta::error('No autorizado.', 403);
        }
        try {
            $b = $p->json();
            $id = (int) ($b['id'] ?? 0);
            $previo = $id ? $this->repo()->anterior($id) : null;
            if (!$previo) {
                return Respuesta::error('No encontrado.', 404);
            }
            $cerrado = (new PeriodosServicio())->mensajeSiCerrado(isset($previo['periodo_id']) ? (int) $previo['periodo_id'] : null);
            if ($cerrado) {
                return Respuesta::error($cerrado, 400);
            }
            $destino = $this->periodoDestino($b);
            if ($destino && $destino !== (int) ($previo['periodo_id'] ?? 0)) {
                $cerradoDestino = (new PeriodosServicio())->mensajeSiCerrado($destino);
                if ($cerradoDestino) {
                    return Respuesta::error($cerradoDestino, 400);
                }
            }
            Db::transaccion(function () use ($id, $b, $previo) {
                $valores = $this->valores($b, false);
                $this->validar($valores, $previo);
                $this->repo()->actualizar($id, $valores);
                $this->alGuardar($id, $b, $previo);
            });
            $this->auditarSi($usuario, 'editar', $id, $this->detalle('editar', $b, $previo));
            return Respuesta::json(['ok' => true]);
        } catch (\InvalidArgumentException $e) {
            return Respuesta::error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            return $this->falla($e);
        }
    }

    public function borrar(Peticion $p, array $args): Respuesta
    {
        $usuario = Politicas::guardiaApi($p, $this->rolesBorrar());
        if (!$usuario) {
            return Respuesta::error('No autorizado.', 403);
        }
        try {
            $id = (int) $p->consulta('id', 0);
            $previo = $id ? $this->repo()->anterior($id) : null;
            if ($previo) {
                $cerrado = (new PeriodosServicio())->mensajeSiCerrado(isset($previo['periodo_id']) ? (int) $previo['periodo_id'] : null);
                if ($cerrado) {
                    return Respuesta::error($cerrado, 400);
                }
                Db::transaccion(function () use ($id, $previo) {
                    $this->repo()->borrar($id);
                    $this->alBorrar($previo);
                });
                $this->auditarSi($usuario, 'borrar', $id, $this->detalle('borrar', [], $previo));
            }
            return Respuesta::json(['ok' => true]);
        } catch (\Throwable $e) {
            return $this->falla($e);
        }
    }

    private function auditarSi(array $usuario, string $accion, int $id, string $detalle): void
    {
        if ($this->auditar()) {
            AuditoriaServicio::registrar($usuario, $accion, $this->tabla(), $id, $detalle);
        }
    }

    private function falla(\Throwable $e): Respuesta
    {
        error_log('api ' . $this->tabla() . ': ' . $e->getMessage());
        return Respuesta::error('Ocurrió un error al procesar la solicitud.', 400);
    }
}
