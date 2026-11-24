<?php
namespace Lebleu\Modulos\Catalogos;

use Lebleu\Core\Db;

final class CatalogosRepositorio
{
    public function socios(): array
    {
        return Db::filas('SELECT id, nombre FROM socios WHERE activo = 1 ORDER BY id');
    }

    public function clientes(): array
    {
        return Db::filas('SELECT id, nombre, socio_id FROM clientes ORDER BY id');
    }

    public function motores(): array
    {
        return Db::filas('SELECT id, etiqueta, horometro_actual FROM motores ORDER BY id');
    }

    public function marineros(): array
    {
        return Db::filas('SELECT id, nombre FROM marineros WHERE activo = 1 ORDER BY nombre');
    }

    /** Lista ligera de viajes de un periodo (selector de insumos y aviso de viaje repetido). */
    public function viajesDelPeriodo(int $periodoId): array
    {
        return Db::filas(
            "SELECT v.id, v.fecha, COALESCE(v.cliente_nombre, c.nombre, so.nombre, 'Renta') AS cliente
               FROM viajes v LEFT JOIN clientes c ON c.id = v.cliente_id LEFT JOIN socios so ON so.id = v.socio_id
              WHERE v.periodo_id = ? ORDER BY v.fecha, v.id",
            [$periodoId]
        );
    }
}
