<?php
// Compara dos JSON (referencia Next.js/Postgres vs PHP/MySQL) normalizando lo que solo cambia de forma:
// números en texto, booleanos, fechas ISO y orden de llaves.
// Uso:  php pruebas/comparar_json.php referencia.json php.json
function normalizar(mixed $v): mixed
{
    if (is_bool($v)) {
        return (int) $v;
    }
    if (is_int($v) || is_float($v)) {
        return round((float) $v, 4);
    }
    if (is_string($v)) {
        if (preg_match('/^-?\d+(\.\d+)?$/', $v)) {
            return round((float) $v, 4);
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})(\.\d+)?Z$/', $v, $m)) {
            return $m[2] === '00:00:00' ? $m[1] : "$m[1] $m[2]";
        }
        return $v;
    }
    if (is_array($v)) {
        $n = array_map('normalizar', $v);
        if (!array_is_list($n)) {
            ksort($n);
        }
        return $n;
    }
    return $v;
}

function diferencias(mixed $a, mixed $b, string $ruta, array &$out): void
{
    if (is_array($a) && is_array($b)) {
        foreach (array_unique(array_merge(array_keys($a), array_keys($b))) as $k) {
            if (!array_key_exists($k, $a)) {
                $out[] = "$ruta.$k  solo en PHP: " . json_encode($b[$k], JSON_UNESCAPED_UNICODE);
            } elseif (!array_key_exists($k, $b)) {
                $out[] = "$ruta.$k  solo en referencia: " . json_encode($a[$k], JSON_UNESCAPED_UNICODE);
            } else {
                diferencias($a[$k], $b[$k], "$ruta.$k", $out);
            }
        }
    } elseif ($a !== $b && !(is_numeric($a) && is_numeric($b) && abs($a - $b) < 0.0001)) {
        $out[] = "$ruta  referencia=" . json_encode($a, JSON_UNESCAPED_UNICODE) . "  php=" . json_encode($b, JSON_UNESCAPED_UNICODE);
    }
}

// La consulta original de 'variable' no tiene ORDER BY (orden indefinido): se compara ordenada por socio.
function ordenarVariable(array $d): array
{
    foreach ($d['estados'] ?? [] as $id => $e) {
        if (isset($e['variable'])) {
            usort($e['variable'], fn($x, $y) => ($x['socio_id'] ?? 0) <=> ($y['socio_id'] ?? 0));
            $d['estados'][$id] = $e;
        }
    }
    return $d;
}

// Desviación conocida: el original calcula "la mitad" con Math.round sobre flotantes y en totales de
// centavos nones queda un centavo abajo (ej. 33754.46 en vez de 33754.47, que es lo que paga el socio 1).
const DESVIACION_CONOCIDA = '/^\.estados\.\d+\.kpis\.(opMitad|extraMitad)$/';

[$ref, $php] = array_map(fn($f) => normalizar(json_decode(file_get_contents($f), true)), array_slice($argv, 1, 2));
$dif = [];
diferencias(ordenarVariable($ref), ordenarVariable($php), '', $dif);
$conocidas = array_values(array_filter($dif, fn($d) => preg_match(DESVIACION_CONOCIDA, strtok($d, ' '))));
$dif = array_values(array_filter($dif, fn($d) => !preg_match(DESVIACION_CONOCIDA, strtok($d, ' '))));
foreach ($conocidas as $c) {
    echo "NOTA (error de flotante del original, la versión PHP es la exacta) $c\n";
}
foreach (array_slice($dif, 0, 40) as $d) {
    echo "DIFIERE $d\n";
}
$periodos = count($ref['estados'] ?? []);
echo $dif ? "\n" . count($dif) . " diferencia(s) en $periodos periodos.\n" : "Idéntico: $periodos estados de cuenta completos coinciden con la versión original.\n";
exit($dif ? 1 : 0);
