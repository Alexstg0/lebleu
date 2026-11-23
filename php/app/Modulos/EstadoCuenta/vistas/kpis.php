<?php
use Lebleu\Compartido\Formato as F;
$k = $d['kpis'];
$periodo = $d['periodo'];
?>
<div class="kpi-grid" style="margin-top:18px">
  <div class="kpi-card k-navy">
    <div class="kpi-label">Gastos operativos</div>
    <div class="kpi-value mono"><?= F::mxn($k['opTotal']) ?></div>
    <div class="kpi-note">50% c/u: <?= F::mxn($k['opMitad']) ?><br>Extraordinarios: <?= F::mxn($k['extraTotal']) ?></div>
  </div>
  <div class="kpi-card k-teal">
    <div class="kpi-label">Gasto variable</div>
    <div class="kpi-value mono"><?= F::mxn($k['varTotal']) ?></div>
    <div class="kpi-note">Comb. <?= F::mxn($k['combTotal']) ?> · Mar. <?= F::mxn($k['marTotal']) ?> · Cons. <?= F::mxn($k['consTotal']) ?></div>
  </div>
  <div class="kpi-card k-gold">
    <div class="kpi-label">Ingresos por renta</div>
    <div class="kpi-value mono"><?= F::mxn($k['ingresosRenta']) ?></div>
    <div class="kpi-note"><?= $k['ingresosRenta'] > 0 ? 'Renta del mes' : 'Sin renta este mes' ?></div>
  </div>
  <div class="kpi-card k-navy">
    <div class="kpi-label">Viajes realizados</div>
    <div class="kpi-value mono"><?= (int) $k['numViajes'] ?> viajes</div>
    <div class="kpi-note"><?= F::num($k['litrosTotal'], 2) ?> Lts consumo total<br>PU: $<?= F::num($periodo['precio_litro'], 2) ?> / Lt</div>
  </div>
</div>
