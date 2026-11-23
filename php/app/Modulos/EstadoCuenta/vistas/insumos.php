<?php
use Lebleu\Compartido\Formato as F;
$insumos = $d['insumos'];
?>
<div class="section-label">Insumos de viajes</div>
<div class="table-card">
  <table class="resp-table">
    <thead><tr><th>#</th><th>Fecha</th><th>Viaje</th><th>Proveedor</th><th>Concepto</th><th class="tr">Cargo</th><th class="tc">Comprobante</th></tr></thead>
    <tbody>
      <?php if (!$insumos): ?>
      <tr><td colspan="7" class="tc hint">Sin insumos registrados este mes. Captúralos en Capturar → Insumos.</td></tr>
      <?php endif; ?>
      <?php foreach ($insumos as $i => $it): $adj = $it['adjunto_id']; ?>
      <tr<?= $adj ? ' class="row-pdf" data-pdf="/api/adjuntos/' . (int) $adj . '" title="Ver PDF de respaldo"' : '' ?>>
        <td data-label="#"><?= $i + 1 ?></td>
        <td data-label="Fecha"><?= F::e(F::fechaCorta($it['fecha'])) ?></td>
        <td data-label="Viaje"><?= $it['viaje_id'] ? F::e($it['viaje_cliente'] . ' · ' . F::fechaCorta($it['viaje_fecha'])) : '—' ?></td>
        <td data-label="Proveedor"><?= F::e($it['proveedor'] ?: '—') ?></td>
        <td data-label="Concepto"><?= F::e($it['concepto'] ?: '—') ?></td>
        <td data-label="Cargo" class="tr mono"><?= F::mxn($it['monto_mxn']) ?></td>
        <td data-label="Comprobante" class="tc"><span class="tag <?= $adj ? 'pdf-doc' : '' ?>"><?= F::e($it['comprobante_folio'] ?: ($adj ? 'Ver PDF' : '—')) ?></span></td>
      </tr>
      <?php endforeach; ?>
      <?php if ($insumos): ?>
      <tr class="row-total"><td colspan="5" class="tr">TOTAL INSUMOS</td><td class="tr mono"><?= F::mxn($d['kpis']['insumosTotal']) ?></td><td></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
