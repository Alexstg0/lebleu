<?php
use Lebleu\Compartido\Formato as F;
use Lebleu\Modulos\Analisis\Graficas;
/** @var array $porMes @var array $viajes */
$NAVY = '#1a3a5c'; $TEAL = '#0e7c7b'; $GOLD = '#d99e2b';
$f = fn($v) => (float) ($v ?? 0);
$etq = fn($p) => mb_substr(F::mesNombre((int) $p['mes']), 0, 3) . ' ' . $p['anio'];
$grupos = array_map(fn($p) => ['etiqueta' => $etq($p), 'valores' => [$f($p['operativos']), $f($p['variables']), $f($p['extraordinarios'])]], $porMes);
$series = [['nombre' => 'Gastos operativos', 'color' => $NAVY], ['nombre' => 'Gasto variable (viajes)', 'color' => $TEAL], ['nombre' => 'Extraordinarios', 'color' => $GOLD]];
$conLitros = array_values(array_filter($viajes, fn($v) => $f($v['litros']) > 0));
$litrosViajes = array_slice($conLitros, -14);
$viajesMes = array_map(fn($p) => ['etiqueta' => $etq($p), 'valor' => $f($p['num_viajes'])], $porMes);
$totalGasto = array_sum(array_map(fn($p) => $f($p['operativos']) + $f($p['variables']), $porMes));
$totalLitros = array_sum(array_map(fn($p) => $f($p['litros']), $porMes));
$totalViajes = (int) array_sum(array_map(fn($p) => $f($p['num_viajes']), $porMes));
$primero = $porMes[0] ?? null;
?>
<div class="wrap">
  <h1 style="margin:4px 0 2px;font-size:22px">Análisis</h1>
  <div class="hint">Tendencias de gasto y operación de la embarcación · Cifras en MXN</div>
  <div style="height:16px"></div>

  <div class="kpi-grid">
    <div class="kpi-card k-navy"><div class="kpi-label">Gasto acumulado (op + variable)</div><div class="kpi-value mono"><?= F::e(F::mxn($totalGasto)) ?></div><div class="kpi-note"><?= count($porMes) ?> periodos</div></div>
    <div class="kpi-card k-teal"><div class="kpi-label">Viajes totales</div><div class="kpi-value mono"><?= $totalViajes ?></div><div class="kpi-note">desde <?= $primero ? F::e(F::mesNombre((int) $primero['mes']) . ' ' . $primero['anio']) : '—' ?></div></div>
    <div class="kpi-card k-gold"><div class="kpi-label">Combustible consumido</div><div class="kpi-value mono"><?= F::e(F::num($totalLitros, 0)) ?> L</div><div class="kpi-note"><?= $totalViajes ? F::e(F::num($totalLitros / $totalViajes, 1)) . ' L promedio por viaje' : '—' ?></div></div>
    <div class="kpi-card k-navy"><div class="kpi-label">Gasto promedio mensual</div><div class="kpi-value mono"><?= F::e(F::mxn($porMes ? $totalGasto / count($porMes) : 0)) ?></div><div class="kpi-note">operativo + variable</div></div>
  </div>

  <div class="section-label">Gastos por mes</div>
  <div class="chart-card">
    <div class="chart-legend"><?php foreach ($series as $s): ?><span><i style="background:<?= $s['color'] ?>"></i> <?= F::e($s['nombre']) ?></span><?php endforeach; ?></div>
    <?= Graficas::agrupadas($grupos, $series) ?>
  </div>

  <div class="section-label">Consumo de combustible por viaje (últimos <?= count($litrosViajes) ?>)</div>
  <div class="chart-card">
    <?= Graficas::simple(array_map(fn($v) => ['etiqueta' => substr($v['fecha'], 8, 2) . '/' . substr($v['fecha'], 5, 2), 'valor' => $f($v['litros']), 'sub' => $v['cliente']], $litrosViajes), $TEAL, ' L') ?>
  </div>

  <div class="section-label">Viajes por mes</div>
  <div class="chart-card"><?= Graficas::simple($viajesMes, $NAVY, '') ?></div>
</div>
