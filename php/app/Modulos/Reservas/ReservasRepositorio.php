<?php
namespace Lebleu\Modulos\Reservas;

use Lebleu\Compartido\Movimientos\MovimientoRepositorio;
use Lebleu\Core\Db;

final class ReservasRepositorio extends MovimientoRepositorio
{
    private const COLUMNAS = ['fecha', 'hora', 'cliente', 'socio_id', 'num_personas', 'duracion_horas', 'notas'];

    protected function tabla(): string
    {
        return 'reservas';
    }

    protected function columnasAlta(): array
    {
        return self::COLUMNAS;
    }

    protected function columnasEdicion(): array
    {
        return self::COLUMNAS;
    }

    /** Reservas de un mes (rango de fechas, para aprovechar el índice). Las que no tienen hora van al final del día. */
    public function delMes(string $desde, string $hasta): array
    {
        return Db::filas(
            'SELECT r.id, r.fecha, r.hora, r.cliente, r.socio_id, r.num_personas, r.duracion_horas, r.notas, so.nombre AS socio_nombre
               FROM reservas r LEFT JOIN socios so ON so.id = r.socio_id
              WHERE r.fecha >= ? AND r.fecha < ? ORDER BY r.fecha, r.hora IS NULL, r.hora',
            [$desde, $hasta]
        );
    }
}
