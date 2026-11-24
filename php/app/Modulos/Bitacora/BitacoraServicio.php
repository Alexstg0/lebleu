<?php
namespace Lebleu\Modulos\Bitacora;

final class BitacoraServicio
{
    public function __construct(private BitacoraRepositorio $repo = new BitacoraRepositorio()) {}

    public function meses(): array
    {
        return $this->repo->mesesConViajes();
    }

    /** Viajes del mes con sus lecturas de horómetro (en orden de motor). */
    public function delMes(int $anio, int $mes): array
    {
        $desde = sprintf('%04d-%02d-01', $anio, $mes);
        $hasta = $mes === 12 ? sprintf('%04d-01-01', $anio + 1) : sprintf('%04d-%02d-01', $anio, $mes + 1);
        $horos = $this->repo->horometros($desde, $hasta);
        return array_map(
            fn($v) => $v + ['horometros' => array_values(array_filter($horos, fn($h) => $h['viaje_id'] === $v['id']))],
            $this->repo->viajes($desde, $hasta)
        );
    }
}
