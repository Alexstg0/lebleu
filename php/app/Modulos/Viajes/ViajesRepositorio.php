<?php
namespace Lebleu\Modulos\Viajes;

use Lebleu\Compartido\Movimientos\MovimientoRepositorio;
use Lebleu\Core\Db;

final class ViajesRepositorio extends MovimientoRepositorio
{
    private const EDITABLES = [
        'socio_id', 'cliente_id', 'cliente_nombre', 'marinero', 'fecha', 'duracion_horas', 'num_personas',
        'litros', 'precio_litro', 'costo_combustible', 'costo_marinero', 'costo_consumibles',
        'consumibles_comprobante', 'bandera', 'combustible_inicio', 'combustible_fin',
    ];

    protected function tabla(): string
    {
        return 'viajes';
    }

    protected function columnasAlta(): array
    {
        return ['periodo_id', 'embarcacion_id', 'es_renta', ...self::EDITABLES];
    }

    protected function columnasEdicion(): array
    {
        return self::EDITABLES;
    }

    /** El viaje con el nombre a mostrar del cliente (para la bitácora de auditoría). */
    public function anterior(int $id): ?array
    {
        return Db::fila(
            "SELECT v.*, COALESCE(v.cliente_nombre, c.nombre, so.nombre, 'Renta') AS cliente
               FROM viajes v LEFT JOIN clientes c ON c.id = v.cliente_id LEFT JOIN socios so ON so.id = v.socio_id
              WHERE v.id = ?",
            [$id]
        );
    }

    /**
     * Reemplaza las lecturas de horómetro del viaje y deja la última lectura como horómetro actual del motor.
     * @param array<int, array{motor_id: int, inicio: int|float|string, fin: int|float|string}> $lecturas
     */
    public function reemplazarHorometros(int $viajeId, array $lecturas): void
    {
        Db::ejecutar('DELETE FROM viaje_horometros WHERE viaje_id = ?', [$viajeId]);
        foreach ($lecturas as $l) {
            Db::ejecutar(
                'INSERT INTO viaje_horometros (viaje_id, motor_id, lectura_inicio, lectura_fin) VALUES (?, ?, ?, ?)',
                [$viajeId, $l['motor_id'], $l['inicio'], $l['fin']]
            );
            Db::ejecutar('UPDATE motores SET horometro_actual = ? WHERE id = ?', [$l['fin'], $l['motor_id']]);
        }
    }
}
