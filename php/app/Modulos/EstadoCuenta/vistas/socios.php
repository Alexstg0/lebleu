<?php
use Lebleu\Compartido\Formato as F;
use Lebleu\Compartido\Iconos;
$porSocio = fn(array $filas, int $id) => array_values(array_filter($filas, fn($f) => (int) $f['socio_id'] === $id))[0] ?? null;
?>
<div class="section-label">Estado de cuenta por socio</div>
<div class="socios-grid">
  <?php foreach ($d['balances'] as $i => $b):
      $v = $porSocio($d['variable'], (int) $b['socio_id']);
      $x = $porSocio($d['extraSocio'], (int) $b['socio_id']);
      $renta = (float) $b['utilidad_renta'] > 0; ?>
  <div class="socio-card <?= $i === 0 ? '' : 's-green' ?>">
    <div class="socio-name"><?= F::e($b['nombre']) ?></div>
    <div class="flow-item"><span class="flow-desc">Saldo de inicio</span><span class="amt-in mono">+ <?= F::mxn($b['saldo_inicio']) ?></span></div>
    <div class="flow-item"><span class="flow-desc">Aportaciones este mes</span><span class="amt-in mono">+ <?= F::mxn($b['aportaciones']) ?></span></div>
    <div class="flow-item"><span class="flow-desc">Gastos generales (50%)</span><span class="amt-out mono">− <?= F::mxn($b['gastos_generales']) ?></span></div>
    <div class="flow-item" style="flex-direction:column;align-items:stretch;gap:0">
      <div style="display:flex;width:100%">
        <span class="flow-desc" style="font-weight:600;color:var(--text)">Gastos variables</span>
        <span class="amt-out mono" style="margin-left:auto">− <?= F::mxn($b['gastos_variables']) ?></span>
      </div>
      <div style="width:100%;margin-top:4px">
        <div class="sub-item"><span><?= Iconos::combustible() ?> Combustible · <?= (int) ($v['num_viajes'] ?? 0) ?> viajes</span><span class="mono">− <?= F::mxn($v['combustible'] ?? 0) ?></span></div>
        <div class="sub-item"><span><?= Iconos::marinero() ?> Marinero</span><span class="mono">− <?= F::mxn($v['marinero'] ?? 0) ?></span></div>
        <div class="sub-item"><span><?= Iconos::insumos() ?> Consumibles</span><span class="mono">− <?= F::mxn($v['consumibles'] ?? 0) ?></span></div>
      </div>
    </div>
    <div class="flow-item"><span class="flow-desc">Utilidad renta</span><span class="<?= $renta ? 'amt-in' : 'amt-zero' ?> mono"><?= $renta ? '+ ' : '' ?><?= F::mxn($b['utilidad_renta']) ?></span></div>
    <div class="socio-balance">
      <div><div class="balance-label">BALANCE OPERATIVO</div><div class="hint">Extraordinarios se liquidan por separado</div></div>
      <div class="balance-amt mono"><?= F::mxn($b['balance_operativo']) ?></div>
    </div>
    <?php if ($x && (float) $x['liquidacion_extraordinaria'] > 0): ?>
    <div class="extra-box">
      <div><div class="lbl">Pago extraordinario</div><div class="hint">Muelle + refacciones — liquidación separada</div></div>
      <div class="amt mono"><?= F::mxn($x['liquidacion_extraordinaria']) ?></div>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
