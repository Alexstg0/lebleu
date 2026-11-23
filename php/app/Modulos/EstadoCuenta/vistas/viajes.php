<?php
use Lebleu\Compartido\Formato as F;
use Lebleu\Compartido\Iconos;
$anio = (int) $d['periodo']['anio'];
$primerSocio = $d['balances'][0]['socio_id'] ?? null;
?>
<div class="section-label">Reporte de viajes — detalle por salida</div>
<div class="viajes-grid">
  <?php foreach ($d['viajes'] as $v): ?>
  <div class="viaje-card <?= $v['bandera'] ? 'warn' : '' ?>">
    <div class="viaje-header <?= (int) $v['socio_id'] === (int) $primerSocio ? 'h-acosta' : 'h-garcia' ?>">
      <div>
        <div class="viaje-date"><?= F::e(F::fechaCorta($v['fecha'])) ?> / <?= $anio ?></div>
        <div class="viaje-owner"><?= F::e($v['cliente'] ?? $v['socio']) ?></div>
      </div>
      <span class="dur-badge"><?= $v['duracion_horas'] !== null ? F::num($v['duracion_horas'], 0) . ' Hrs' : '—' ?> · <?= F::e((string) ($v['num_personas'] ?? 'NA')) ?> pers.</span>
    </div>
    <div class="viaje-body">
      <div class="horometros">
        <?php foreach ($v['horometros'] as $h): ?>
        <div class="horo-item">
          <div class="horo-label"><?= F::e($h['etiqueta']) ?></div>
          <div class="horo-vals mono"><?= F::num($h['lectura_inicio']) ?><span class="horo-arrow">→</span><?= F::num($h['lectura_fin']) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="costo-row"><span class="costo-label"><?= Iconos::combustible() ?> Combustible<span class="costo-sub"><?= F::num($v['litros']) ?> Lts × $<?= F::num($v['precio_litro']) ?></span></span><span class="mono"><?= F::mxn($v['costo_combustible']) ?></span></div>
      <div class="costo-row"><span class="costo-label"><?= Iconos::marinero() ?> Marinero</span><span class="mono"><?= F::mxn($v['costo_marinero']) ?></span></div>
      <div class="costo-row"><span class="costo-label"><?= Iconos::insumos() ?> Consumibles<?php if ($v['consumibles_comprobante']): ?><span class="costo-sub"><?= F::e($v['consumibles_comprobante']) ?></span><?php endif; ?></span><span class="mono"><?= (float) $v['costo_consumibles'] > 0 ? F::mxn($v['costo_consumibles']) : '—' ?></span></div>
      <div class="viaje-total"><span>Total viaje</span><span class="mono"><?= F::mxn($v['total']) ?></span></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
