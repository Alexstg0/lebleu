<?php
namespace Lebleu\Modulos\Reservas;

// Reservas (salidas agendadas) de un mes, con la forma que usa el calendario.
final class ReservasServicio
{
    /** @return array<int, array<string, mixed>> */
    public function delMes(int $anio, int $mes): array
    {
        $desde = sprintf('%04d-%02d-01', $anio, $mes);
        $hasta = $mes === 12 ? sprintf('%04d-01-01', $anio + 1) : sprintf('%04d-%02d-01', $anio, $mes + 1);
        return array_map(fn($r) => [
            'id' => (int) $r['id'],
            'fecha' => $r['fecha'],
            'hora' => $r['hora'] ? substr($r['hora'], 0, 5) : '',
            'cliente' => $r['cliente'] ?? '',
            'socio_id' => $r['socio_id'] !== null ? (int) $r['socio_id'] : null,
            'socio_nombre' => $r['socio_nombre'] ?? '',
            'num_personas' => $r['num_personas'] !== null ? (int) $r['num_personas'] : null,
            'duracion_horas' => $r['duracion_horas'] !== null ? (float) $r['duracion_horas'] : null,
            'notas' => $r['notas'] ?? '',
        ], (new ReservasRepositorio())->delMes($desde, $hasta));
    }
}
