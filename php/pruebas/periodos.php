<?php
// Prueba del módulo Periodos sobre la base real, sin dejar rastro (todo se revierte).
// Uso:  php pruebas/periodos.php
require __DIR__ . '/../app/arranque.php';

use Lebleu\Core\Db;
use Lebleu\Modulos\Periodos\PeriodosServicio;

$fallos = 0;
function afirmar(string $nombre, bool $cond, string $extra = ''): void
{
    global $fallos;
    echo ($cond ? 'ok      ' : 'FALLA   ') . $nombre . ($cond || $extra === '' ? '' : "  ($extra)") . "\n";
    $fallos += $cond ? 0 : 1;
}

$antes = [(int) Db::fila('SELECT COUNT(*) n FROM periodos')['n'], (int) Db::fila('SELECT COUNT(*) n FROM saldos_socio')['n']];
$ultimo = Db::fila('SELECT id, anio, mes FROM periodos ORDER BY anio DESC, mes DESC LIMIT 1');
$sig = ['anio' => $ultimo['mes'] == 12 ? $ultimo['anio'] + 1 : $ultimo['anio'], 'mes' => $ultimo['mes'] == 12 ? 1 : $ultimo['mes'] + 1];
$cerrado = Db::fila("SELECT id FROM periodos WHERE estado = 'cerrado' LIMIT 1")['id'];

Db::simular(function () use ($ultimo, $sig, $cerrado) {
    $s = new PeriodosServicio();
    $balPrev = array_column(Db::filas('SELECT socio_id, balance_operativo b FROM v_balance_operativo WHERE periodo_id = ?', [$ultimo['id']]), 'b', 'socio_id');

    $id = $s->crear($sig + ['tipo_cambio' => 17.6, 'precio_litro' => 25.5, 'cargo_administracion' => 6000]);
    afirmar('crear devuelve el id del periodo nuevo', $id > 0);
    $p = Db::fila('SELECT * FROM periodos WHERE id = ?', [$id]);
    afirmar('el periodo nuevo nace abierto con sus parámetros', $p['estado'] === 'abierto' && (float) $p['tipo_cambio'] === 17.6 && (float) $p['cargo_administracion'] === 6000.0);
    afirmar('el periodo anterior queda cerrado', Db::fila('SELECT estado FROM periodos WHERE id = ?', [$ultimo['id']])['estado'] === 'cerrado');

    $saldos = Db::filas('SELECT socio_id, saldo_inicio FROM saldos_socio WHERE periodo_id = ?', [$id]);
    afirmar('hay un saldo inicial por socio activo', count($saldos) === (int) Db::fila('SELECT COUNT(*) n FROM socios WHERE activo = 1')['n']);
    $arrastra = true;
    foreach ($saldos as $r) {
        $esperado = Db::fila('SELECT saldo_fin FROM saldos_socio WHERE periodo_id = ? AND socio_id = ?', [$ultimo['id'], $r['socio_id']])['saldo_fin'];
        $arrastra = $arrastra && abs((float) $r['saldo_inicio'] - (float) $esperado) < 0.005
                              && abs((float) $esperado - (float) $balPrev[$r['socio_id']]) < 0.005;
    }
    afirmar('el saldo inicial es el balance de cierre del periodo anterior', $arrastra);

    afirmar('periodo abierto no bloquea escrituras', $s->mensajeSiCerrado($id) === null);
    afirmar('periodo cerrado bloquea escrituras', str_contains((string) $s->mensajeSiCerrado($cerrado), 'cerrado'));
    afirmar('periodo inexistente se avisa', $s->mensajeSiCerrado(999999) === 'El periodo no existe.');
    afirmar('sin periodo no bloquea (caja chica, reservas)', $s->mensajeSiCerrado(null) === null);

    $s->reabrir($ultimo['id']);
    afirmar('reabrir vuelve el estado a abierto', Db::fila('SELECT estado FROM periodos WHERE id = ?', [$ultimo['id']])['estado'] === 'abierto');
    afirmar('cerrar sin archivador avisa que no hubo archivo', $s->cerrar($ultimo['id']) === false
        && Db::fila('SELECT estado FROM periodos WHERE id = ?', [$ultimo['id']])['estado'] === 'cerrado');

    afirmar('asegurarPeriodoActual no duplica un mes existente', $s->asegurarPeriodoActual(sprintf('%d-%02d-15', $sig['anio'], $sig['mes'])) === null);
    $otro = $sig['mes'] == 12 ? [$sig['anio'] + 1, 1] : [$sig['anio'], $sig['mes'] + 1];
    $nuevo = $s->asegurarPeriodoActual(sprintf('%d-%02d-02', $otro[0], $otro[1]));
    $copia = $nuevo ? Db::fila('SELECT tipo_cambio, cargo_administracion FROM periodos WHERE id = ?', [$nuevo]) : null;
    afirmar('asegurarPeriodoActual crea el mes nuevo copiando T/C y cargo', $copia && (float) $copia['tipo_cambio'] === 17.6 && (float) $copia['cargo_administracion'] === 6000.0);

    foreach ([['anio' => 2026, 'mes' => 13], ['anio' => 1999, 'mes' => 5], ['anio' => 2026, 'mes' => 0]] as $malo) {
        $lanza = false;
        try { $s->crear($malo); } catch (\InvalidArgumentException) { $lanza = true; }
        afirmar("rechaza año/mes inválido {$malo['anio']}-{$malo['mes']}", $lanza);
    }
    $dup = false;
    try { Db::transaccion(fn() => $s->crear($sig)); } catch (\PDOException) { $dup = true; }
    afirmar('un mes repetido falla sin dejar basura (SAVEPOINT)', $dup && (int) Db::fila('SELECT COUNT(*) n FROM periodos WHERE anio = ? AND mes = ?', [$sig['anio'], $sig['mes']])['n'] === 1);
});

$despues = [(int) Db::fila('SELECT COUNT(*) n FROM periodos')['n'], (int) Db::fila('SELECT COUNT(*) n FROM saldos_socio')['n']];
afirmar('la prueba no dejó rastro en la base', $antes === $despues, json_encode([$antes, $despues]));
echo $fallos ? "\n$fallos prueba(s) fallaron.\n" : "\nPeriodos OK.\n";
exit($fallos ? 1 : 0);
