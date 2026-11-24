<?php
namespace Lebleu\Modulos\Bitacora;

use Lebleu\Core\Db;

// Consultas de la Bitácora de viajes (solo lectura).
final class BitacoraRepositorio
{
    /** @return array<int, array{anio: int, mes: int}> meses con viajes, del más reciente al más antiguo */
    public function mesesConViajes(): array
    {
        return array_map(
            fn($r) => ['anio' => (int) $r['anio'], 'mes' => (int) $r['mes']],
            Db::filas('SELECT DISTINCT YEAR(fecha) AS anio, MONTH(fecha) AS mes FROM viajes ORDER BY anio DESC, mes DESC')
        );
    }

    /** Viajes de un mes, con el nombre a mostrar del cliente. */
    public function viajes(string $desde, string $hasta): array
    {
        return Db::filas(
            "SELECT v.id, v.fecha, v.num_personas, v.duracion_horas, v.litros, v.combustible_inicio, v.combustible_fin,
                    v.marinero, v.es_renta, v.costo_consumibles,
                    COALESCE(v.cliente_nombre, c.nombre, so.nombre, 'Renta') AS cliente
               FROM viajes v LEFT JOIN clientes c ON c.id = v.cliente_id LEFT JOIN socios so ON so.id = v.socio_id
              WHERE v.fecha >= ? AND v.fecha < ? ORDER BY v.fecha, v.id",
            [$desde, $hasta]
        );
    }

    public function horometros(string $desde, string $hasta): array
    {
        return Db::filas(
            'SELECT h.viaje_id, h.motor_id, h.lectura_inicio, h.lectura_fin
               FROM viaje_horometros h JOIN viajes v ON v.id = h.viaje_id
              WHERE v.fecha >= ? AND v.fecha < ? ORDER BY h.motor_id',
            [$desde, $hasta]
        );
    }
}
