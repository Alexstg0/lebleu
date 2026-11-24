<?php
namespace Lebleu\Modulos\Marineros;

use Lebleu\Core\Db;

final class MarinerosRepositorio
{
    public function porNombre(string $nombre): ?array
    {
        return Db::fila('SELECT id, nombre FROM marineros WHERE LOWER(TRIM(nombre)) = LOWER(?)', [$nombre]);
    }

    public function activar(int $id): void
    {
        Db::ejecutar('UPDATE marineros SET activo = 1 WHERE id = ?', [$id]);
    }

    public function insertar(string $nombre): int
    {
        Db::ejecutar('INSERT INTO marineros (nombre) VALUES (?)', [$nombre]);
        return Db::ultimoId();
    }
}
