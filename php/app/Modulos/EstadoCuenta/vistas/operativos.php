<?php
use Lebleu\Compartido\Formato as F;
?>
<div class="section-label">Gastos operativos del mes</div>
<div class="table-card">
  <table class="resp-table">
    <thead><tr><th>#</th><th>Fecha</th><th>Proveedor</th><th>Concepto</th><th class="tr">Cargo</th><th class="tc">Comprobante</th></tr></thead>
    <tbody>
      <?php foreach ($d['operativos'] as $i => $g): $adj = $g['adjunto_id']; ?>
      <tr<?= $adj ? ' class="row-pdf" data-pdf="/api/adjuntos/' . (int) $adj . '" title="Ver PDF de respaldo"' : '' ?>>
        <td data-label="#"><?= $i + 1 ?></td>
        <td data-label="Fecha"><?= F::e(F::fechaCorta($g['fecha'])) ?></td>
        <td data-label="Proveedor"><?= F::e($g['proveedor']) ?></td>
        <td data-label="Concepto"><?= F::e($g['concepto']) ?><?php if ($g['moneda'] === 'USD'): ?><span class="costo-sub">USD $<?= F::num($g['monto_original']) ?> × T/C <?= F::num($g['tipo_cambio']) ?></span><?php endif; ?></td>
        <td data-label="Cargo" class="tr mono"><?= F::mxn($g['monto_mxn']) ?></td>
        <td data-label="Comprobante" class="tc"><span class="tag <?= $adj ? 'pdf-doc' : '' ?>"><?= F::e($g['comprobante_folio'] ?: ($adj ? 'Ver PDF' : '—')) ?></span></td>
      </tr>
      <?php endforeach; ?>
      <tr class="row-total"><td colspan="4" class="tr">SUBTOTAL OPERATIVO</td><td class="tr mono"><?= F::mxn($d['kpis']['opTotal']) ?></td><td></td></tr>
      <tr class="row-total"><td colspan="4" class="tr">50% por socio</td><td class="tr mono"><?= F::mxn($d['kpis']['opMitad']) ?></td><td></td></tr>
    </tbody>
  </table>
</div>
