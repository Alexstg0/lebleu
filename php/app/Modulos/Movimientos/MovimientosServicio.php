<?php
namespace Lebleu\Modulos\Movimientos;

// Todo lo capturado en un periodo, para editarlo o borrarlo.
final class MovimientosServicio
{
    private MovimientosRepositorio $repo;

    public function __construct(?MovimientosRepositorio $repo = null)
    {
        $this->repo = $repo ?? new MovimientosRepositorio();
    }

    public function delPeriodo(int $periodoId): array
    {
        $horos = $this->repo->horometros($periodoId);
        $viajes = array_map(
            fn($v) => $v + ['horometros' => array_values(array_filter($horos, fn($h) => $h['viaje_id'] === $v['id']))],
            $this->repo->viajes($periodoId)
        );
        return [
            'operativos' => $this->repo->operativos($periodoId),
            'insumos' => $this->repo->insumos($periodoId),
            'viajes' => $viajes,
            'aportaciones' => $this->repo->aportaciones($periodoId),
            'extraordinarios' => $this->repo->extraordinarios($periodoId),
            'abonosExtra' => $this->repo->abonosExtra($periodoId),
            'renta' => $this->repo->renta($periodoId),
        ];
    }

    /** Lista ligera de viajes del periodo para el selector de insumos. */
    public function viajesParaSelector(array $mov): array
    {
        return array_map(fn($v) => ['id' => $v['id'], 'fecha' => $v['fecha'], 'cliente' => $v['cliente_disp']], $mov['viajes']);
    }

    public function fila(string $prefijo, int $id): ?array
    {
        return $this->repo->fila($prefijo, $id);
    }
}
