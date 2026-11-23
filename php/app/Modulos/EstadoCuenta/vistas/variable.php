<?php
use Lebleu\Compartido\Formato as F;
$k = $d['kpis'];
$porSocio = fn(int $id) => array_values(array_filter($d['variable'], fn($f) => (int) $f['socio_id'] === $id))[0] ?? null;
?>
<div class="section-label">Resumen de gastos variables por socio</div>
<div class="table-card">
  <table class="resp-table">
    <thead><tr><th>Socio</th><th class="tc">Viajes</th><th class="tr">Combustible</th><th class="tr">Marinero</th><th class="tr">Consumibles</th><th class="tr">Total</th></tr></thead>
    <tbody>
      <?php foreach ($d['balances'] as $b): $v = $porSocio((int) $b['socio_id']); ?>
      <tr>
        <td data-label="Socio"><?= F::e($b['nombre']) ?></td>
        <td data-label="Viajes" class="tc"><?= (int) ($v['num_viajes'] ?? 0) ?></td>
        <td data-label="Combustible" class="tr mono"><?= F::mxn($v['combustible'] ?? null) ?></td>
        <td data-label="Marinero" class="tr mono"><?= F::mxn($v['marinero'] ?? null) ?></td>
        <td data-label="Consumibles" class="tr mono"><?= F::mxn($v['consumibles'] ?? null) ?></td>
        <td data-label="Total" class="tr mono"><?= F::mxn($v['total_variable'] ?? null) ?></td>
      </tr>
      <?php endforeach; ?>
      <tr class="row-total">
        <td class="tr" colspan="2">TOTAL</td>
        <td class="tr mono"><?= F::mxn($k['combTotal']) ?></td>
        <td class="tr mono"><?= F::mxn($k['marTotal']) ?></td>
        <td class="tr mono"><?= F::mxn($k['consTotal']) ?></td>
        <td class="tr mono"><?= F::mxn($k['varTotal']) ?></td>
      </tr>
    </tbody>
  </table>
</div>
