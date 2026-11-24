<?php
namespace Lebleu\Modulos\Adjuntos;

use Lebleu\Core\Db;

final class AdjuntosRepositorio
{
    public const COLUMNAS = ['gasto_id', 'insumo_id', 'extraordinario_id', 'caja_id'];

    public function porId(int $id): ?array
    {
        return Db::fila('SELECT nombre, datos, caja_id FROM adjuntos WHERE id = ?', [$id]);
    }

    public function insertar(string $columna, int $registroId, string $nombre, string $mime, string $base64): int
    {
        if (!in_array($columna, self::COLUMNAS, true)) {
            throw new \LogicException("Columna de adjunto no permitida: $columna");
        }
        Db::ejecutar(
            "INSERT INTO adjuntos (`$columna`, nombre, mime, datos, generado) VALUES (?, ?, ?, ?, 0)",
            [$registroId, $nombre, $mime, $base64]
        );
        return Db::ultimoId();
    }

    public function borrar(int $id): void
    {
        Db::ejecutar('DELETE FROM adjuntos WHERE id = ?', [$id]);
    }

    /**
     * Adjuntos (sin su contenido) de los movimientos de un periodo, agrupados por movimiento.
     * @param string $tabla gastos_operativos | insumos | extraordinarios
     * @return array<int, array<int, array{id: int, nombre: string, generado: bool}>>
     */
    public function delPeriodo(string $tabla, int $periodoId): array
    {
        $col = ['gastos_operativos' => 'gasto_id', 'insumos' => 'insumo_id', 'extraordinarios' => 'extraordinario_id'][$tabla]
            ?? throw new \LogicException("Tabla sin adjuntos: $tabla");
        $filas = Db::filas(
            "SELECT a.id, a.`$col` AS movimiento, a.nombre, a.generado FROM adjuntos a JOIN `$tabla` t ON t.id = a.`$col`
              WHERE t.periodo_id = ? ORDER BY a.id",
            [$periodoId]
        );
        return self::agrupar($filas);
    }

    /** Adjuntos de todos los movimientos de caja chica, agrupados por movimiento. */
    public function deCajaChica(): array
    {
        return self::agrupar(Db::filas('SELECT id, caja_id AS movimiento, nombre, generado FROM adjuntos WHERE caja_id IS NOT NULL ORDER BY id'));
    }

    private static function agrupar(array $filas): array
    {
        $salida = [];
        foreach ($filas as $f) {
            $salida[(int) $f['movimiento']][] = ['id' => (int) $f['id'], 'nombre' => $f['nombre'], 'generado' => (bool) $f['generado']];
        }
        return $salida;
    }
}
