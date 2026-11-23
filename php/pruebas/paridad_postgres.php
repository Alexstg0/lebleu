<?php
// Prueba de cuadre: compara la base MySQL nueva contra la base Postgres de referencia (misma data).
// Compara conteos, fechas (id:fecha), sumas de columnas calculadas y las 3 vistas del estado de cuenta.
// Uso:  PG_DSN="pgsql:host=127.0.0.1;dbname=lebleu_ref" PG_USER=... PG_PASS=... php pruebas/paridad_postgres.php
require __DIR__ . '/../app/arranque.php';

use Lebleu\Core\Db;

$pg = new PDO(getenv('PG_DSN') ?: 'pgsql:host=127.0.0.1;dbname=lebleu_ref', getenv('PG_USER') ?: 'lebleu',
    getenv('PG_PASS') ?: 'lebleu_test', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

$fallos = 0;
$verificadas = 0;
function comparar(string $nombre, mixed $mysql, mixed $postgres): void
{
    global $fallos, $verificadas;
    $verificadas++;
    if ($mysql != $postgres) {
        $fallos++;
        echo "DIFIERE  $nombre\n  mysql:    " . json_encode($mysql) . "\n  postgres: " . json_encode($postgres) . "\n";
    }
}
function normalizar(array $filas): array
{
    return array_map(fn($f) => array_map(fn($v) => is_numeric($v) ? round((float) $v, 2) : $v, $f), $filas);
}

$tablas = array_column($pg->query("SELECT tablename t FROM pg_tables WHERE schemaname='public' ORDER BY 1")->fetchAll(), 't');
foreach ($tablas as $t) {
    if (in_array($t, ['sesiones', 'login_intentos'], true)) {
        continue;
    }
    comparar("filas $t", (int) Db::fila("SELECT COUNT(*) n FROM `$t`")['n'], (int) $pg->query("SELECT COUNT(*) n FROM \"$t\"")->fetch()['n']);
    $cols = array_column(Db::filas('SELECT COLUMN_NAME c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$t]), 'c');
    if (in_array('fecha', $cols, true) && in_array('id', $cols, true)) {
        comparar("fechas $t", Db::filas("SELECT id, CAST(fecha AS CHAR) f FROM `$t` ORDER BY id"),
            $pg->query("SELECT id, fecha::text f FROM \"$t\" ORDER BY id")->fetchAll());
    }
}

$sumas = [
    'gastos_operativos' => 'monto_mxn', 'extraordinarios' => 'monto_mxn', 'insumos' => 'monto_mxn',
    'viajes' => 'total', 'viaje_horometros' => 'horas', 'ingresos_renta' => 'utilidad',
];
foreach ($sumas as $t => $c) {
    $grupo = $t === 'viaje_horometros' ? '' : 'periodo_id, ';
    $agrupar = $t === 'viaje_horometros' ? '' : ' GROUP BY periodo_id ORDER BY periodo_id';
    $m = Db::filas("SELECT {$grupo}ROUND(COALESCE(SUM(`$c`),0),2) s FROM `$t`$agrupar");
    $p = $pg->query("SELECT {$grupo}ROUND(COALESCE(SUM($c),0),2) s FROM $t$agrupar")->fetchAll();
    comparar("suma $t.$c", normalizar($m), normalizar($p));
}

foreach (['v_variable_por_socio' => 'periodo_id, COALESCE(socio_id, 0)', 'v_balance_operativo' => 'periodo_id, COALESCE(socio_id, 0)',
          'v_extraordinarios_por_socio' => 'periodo_id, COALESCE(socio_id, 0)'] as $vista => $orden) {
    comparar("vista $vista", normalizar(Db::filas("SELECT * FROM $vista ORDER BY $orden")),
        normalizar($pg->query("SELECT * FROM $vista ORDER BY $orden")->fetchAll()));
}

echo $fallos ? "\n$fallos de $verificadas comparaciones DIFIEREN.\n" : "Cuadre perfecto: $verificadas comparaciones iguales.\n";
exit($fallos ? 1 : 0);
