<?php
namespace Lebleu\Herramientas;

// Lee un respaldo con INSERT estilo PostgreSQL ("tabla" ("col", ...) VALUES (...);) y entrega
// cada sentencia como [tabla, columnas, valores]. Un INSERT puede ocupar varias líneas.
final class LectorInserts
{
    /** @return \Generator<int, array{0: string, 1: string[], 2: array}> */
    public static function leer(string $archivo): \Generator
    {
        $fh = fopen($archivo, 'rb');
        if (!$fh) {
            throw new \RuntimeException("No se pudo abrir $archivo");
        }
        $buf = '';
        while (($linea = fgets($fh)) !== false) {
            if ($buf === '' && !str_starts_with($linea, 'INSERT INTO')) {
                continue;
            }
            $buf .= $linea;
            if (substr_count($buf, "'") % 2 === 0 && str_ends_with(rtrim($buf), ');')) {
                yield self::analizar(rtrim($buf));
                $buf = '';
            }
        }
        fclose($fh);
        if ($buf !== '') {
            throw new \RuntimeException('El respaldo termina con una sentencia incompleta.');
        }
    }

    private static function analizar(string $sql): array
    {
        if (!preg_match('/^INSERT INTO "(\w+)" \(([^)]*)\) VALUES \((.*)\);$/s', $sql, $m)) {
            throw new \RuntimeException('INSERT con formato desconocido: ' . substr($sql, 0, 80));
        }
        $cols = array_map(fn($c) => trim($c, ' "'), explode(',', $m[2]));
        return [$m[1], $cols, self::valores($m[3])];
    }

    /** @return array<int, ?string> valores en texto; NULL como null, TRUE/FALSE como '1'/'0'. */
    private static function valores(string $s): array
    {
        $salida = [];
        $i = 0;
        $n = strlen($s);
        while ($i < $n) {
            while ($i < $n && ($s[$i] === ' ' || $s[$i] === ',')) {
                $i++;
            }
            if ($i >= $n) {
                break;
            }
            if ($s[$i] === "'") {
                $j = $i + 1;
                $txt = '';
                while ($j < $n) {
                    if ($s[$j] === "'") {
                        if (($s[$j + 1] ?? '') === "'") {
                            $txt .= "'";
                            $j += 2;
                            continue;
                        }
                        break;
                    }
                    $txt .= $s[$j++];
                }
                $salida[] = $txt;
                $i = $j + 1;
                continue;
            }
            $j = $i;
            while ($j < $n && $s[$j] !== ',') {
                $j++;
            }
            $crudo = trim(substr($s, $i, $j - $i));
            $salida[] = match (strtoupper($crudo)) {
                'NULL' => null, 'TRUE' => '1', 'FALSE' => '0', default => $crudo,
            };
            $i = $j;
        }
        return $salida;
    }
}
