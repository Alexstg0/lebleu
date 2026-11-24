<?php
namespace Lebleu\Compartido\Movimientos;

use Lebleu\Core\Db;

// SQL común de los movimientos (gastos, aportaciones, rentas...). Cada módulo declara su tabla y las
// columnas permitidas; los NOMBRES de columna salen siempre de esas listas, los VALORES siempre van enlazados.
abstract class MovimientoRepositorio
{
    abstract protected function tabla(): string;

    /** @return string[] columnas que se pueden escribir al dar de alta */
    abstract protected function columnasAlta(): array;

    /** @return string[] columnas que se pueden escribir al editar */
    abstract protected function columnasEdicion(): array;

    public function anterior(int $id): ?array
    {
        return Db::fila('SELECT * FROM `' . $this->tabla() . '` WHERE id = ?', [$id]);
    }

    public function insertar(array $valores): int
    {
        $this->exigirColumnas($valores, $this->columnasAlta());
        $cols = implode(', ', array_map(fn($c) => "`$c`", array_keys($valores)));
        $marcas = implode(', ', array_fill(0, count($valores), '?'));
        Db::ejecutar('INSERT INTO `' . $this->tabla() . "` ($cols) VALUES ($marcas)", array_values($valores));
        return Db::ultimoId();
    }

    public function actualizar(int $id, array $valores): void
    {
        $this->exigirColumnas($valores, $this->columnasEdicion());
        $set = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($valores)));
        Db::ejecutar('UPDATE `' . $this->tabla() . "` SET $set WHERE id = ?", [...array_values($valores), $id]);
    }

    public function borrar(int $id): void
    {
        Db::ejecutar('DELETE FROM `' . $this->tabla() . '` WHERE id = ?', [$id]);
    }

    private function exigirColumnas(array $valores, array $permitidas): void
    {
        $extra = array_diff(array_keys($valores), $permitidas);
        if ($extra) {
            throw new \LogicException('Columnas no permitidas en ' . $this->tabla() . ': ' . implode(', ', $extra));
        }
    }
}
