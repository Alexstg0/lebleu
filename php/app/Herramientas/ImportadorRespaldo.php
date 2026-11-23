<?php
namespace Lebleu\Herramientas;

use DateTimeImmutable;
use DateTimeZone;
use Lebleu\Core\Db;

// Carga un respaldo de la versión Postgres en MySQL. Convierte según el tipo real de cada columna
// de MySQL (DATE, DATETIME, ...) y se detiene si columnas y valores no cuadran.
final class ImportadorRespaldo
{
    /** Tablas que NO se importan: sesiones viejas e intentos de login no tienen sentido en el servidor nuevo. */
    private const OMITIR = ['sesiones', 'login_intentos'];

    /** @var array<string, array<string, string>> tabla => columna => tipo MySQL */
    private array $tipos = [];
    public array $conteo = [];

    public function importar(string $archivo): void
    {
        Db::ejecutar('SET FOREIGN_KEY_CHECKS = 0');
        try {
            Db::transaccion(function () use ($archivo) {
                foreach (LectorInserts::leer($archivo) as [$tabla, $cols, $vals]) {
                    if (in_array($tabla, self::OMITIR, true)) {
                        continue;
                    }
                    $this->insertar($tabla, $cols, $vals);
                }
            });
        } finally {
            Db::ejecutar('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    private function insertar(string $tabla, array $cols, array $vals): void
    {
        if (count($cols) !== count($vals)) {
            throw new \RuntimeException(sprintf(
                'Respaldo desalineado en "%s": %d columnas y %d valores. Use db/datos_20260826.sql, no el _clean.',
                $tabla, count($cols), count($vals)
            ));
        }
        $tipos = $this->tiposDe($tabla);
        $fila = [];
        foreach ($cols as $i => $col) {
            if (!isset($tipos[$col])) {
                throw new \RuntimeException("La columna $tabla.$col no existe en MySQL.");
            }
            $fila[$col] = $this->convertir($vals[$i], $tipos[$col]);
        }
        if ($tabla === 'usuarios') {
            $fila['password_hash'] = '!restablecer';
        }
        $lista = implode(', ', array_map(fn($c) => "`$c`", array_keys($fila)));
        $marcas = implode(', ', array_fill(0, count($fila), '?'));
        Db::ejecutar("INSERT INTO `$tabla` ($lista) VALUES ($marcas)", array_values($fila));
        $this->conteo[$tabla] = ($this->conteo[$tabla] ?? 0) + 1;
    }

    private function convertir(?string $v, string $tipo): ?string
    {
        if ($v === null) {
            return null;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?Z$/', $v)) {
            return $v;
        }
        $utc = new DateTimeImmutable($v, new DateTimeZone('UTC'));
        if ($tipo === 'date') {
            return $utc->setTimezone(new DateTimeZone('America/Mazatlan'))->format('Y-m-d');
        }
        return $utc->format('Y-m-d H:i:s');
    }

    private function tiposDe(string $tabla): array
    {
        if (!isset($this->tipos[$tabla])) {
            $filas = Db::filas(
                'SELECT COLUMN_NAME AS c, DATA_TYPE AS t FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$tabla]
            );
            if (!$filas) {
                throw new \RuntimeException("La tabla $tabla no existe en MySQL (¿corrió migrar.php?).");
            }
            $this->tipos[$tabla] = array_column($filas, 't', 'c');
        }
        return $this->tipos[$tabla];
    }
}
