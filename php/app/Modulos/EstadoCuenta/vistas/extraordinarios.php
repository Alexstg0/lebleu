<?php
use Lebleu\Compartido\Formato as F;
$naranja = 'border-left-color:#c05621;color:#c05621';
$cuenta = $d['extraCuenta'];
$hayExtra = (bool) array_filter($cuenta, fn($c) => $c['saldoAnterior'] != 0 || $c['cargoMes'] != 0 || $c['abonosMes'] != 0 || $c['saldoPendiente'] != 0);
$esAdmin = $usuario['rol'] === 'admin';
?>
<?php if ($hayExtra): ?>
<div class="section-label" style="<?= $naranja ?>">Estado de extraordinarios por socio</div>
<div class="socios-grid">
  <?php foreach ($cuenta as $i => $c): ?>
  <div class="socio-card <?= $i === 0 ? '' : 's-green' ?>" style="border-top-color:#c05621">
    <div class="socio-name"><?= F::e($c['nombre']) ?></div>
    <div class="flow-item"><span class="flow-desc">Saldo anterior pendiente</span><span class="amt-out mono"><?= F::mxn($c['saldoAnterior']) ?></span></div>
    <div class="flow-item"><span class="flow-desc">Cargo del mes (50%)</span><span class="amt-out mono">+ <?= F::mxn($c['cargoMes']) ?></span></div>
    <div class="flow-item"><span class="flow-desc">Abonos del mes</span><span class="<?= $c['abonosMes'] > 0 ? 'amt-in' : 'amt-zero' ?> mono"><?= $c['abonosMes'] > 0 ? '− ' : '' ?><?= F::mxn($c['abonosMes']) ?></span></div>
    <div class="socio-balance">
      <div><div class="balance-label">SALDO PENDIENTE</div><div class="hint">Gastos extraordinarios</div></div>
      <div class="balance-amt mono" style="color:<?= $c['saldoPendiente'] > 0 ? '#c0392b' : 'var(--green)' ?>"><?= F::mxn($c['saldoPendiente']) ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="section-label" style="<?= $naranja ?>">Gastos extraordinarios</div>
<?php if (!$d['extraordinarios']): ?>
<div class="empty">Sin gastos extraordinarios registrados este mes.</div>
<?php else: ?>
<div class="table-card" style="border:1px solid #f5b7a0">
  <table class="resp-table">
    <thead><tr><th>#</th><th>Fecha</th><th>Proveedor</th><th>Concepto</th><th class="tr">USD</th><th class="tr">MXN</th><th class="tc">Pagado</th><th class="tc">Comprobante</th></tr></thead>
    <tbody>
      <?php foreach ($d['extraordinarios'] as $i => $e): $adj = $e['adjunto_id']; $pagado = (bool) $e['liquidado']; ?>
      <tr<?= $adj ? ' class="row-pdf" data-pdf="/api/adjuntos/' . (int) $adj . '" title="Ver PDF de respaldo"' : '' ?>>
        <td data-label="#">E-<?= $i + 1 ?></td>
        <td data-label="Fecha"><?= F::e(F::fechaCorta($e['fecha'])) ?></td>
        <td data-label="Proveedor"><?= F::e($e['proveedor']) ?></td>
        <td data-label="Concepto"><?= F::e($e['concepto']) ?></td>
        <td data-label="USD" class="tr mono"><?= $e['moneda'] === 'USD' ? '$' . F::num($e['monto_original']) : '—' ?></td>
        <td data-label="MXN" class="tr mono"><?= F::mxn($e['monto_mxn']) ?></td>
        <td data-label="Pagado" class="tc">
          <?php if ($esAdmin): ?>
          <button class="pago-badge <?= $pagado ? 'si' : 'no' ?>" data-pagado-id="<?= (int) $e['id'] ?>" data-pagado="<?= $pagado ? 1 : 0 ?>" title="Clic para cambiar el estado de pago"><?= $pagado ? 'Pagado' : 'Pendiente' ?></button>
          <?php else: ?>
          <span class="pago-badge <?= $pagado ? 'si' : 'no' ?>"><?= $pagado ? 'Pagado' : 'Pendiente' ?></span>
          <?php endif; ?>
        </td>
        <td data-label="Comprobante" class="tc"><span class="tag <?= $adj ? 'pdf-doc' : 'a' ?>"><?= F::e($e['comprobante_folio'] ?: ($adj ? 'Ver PDF' : '—')) ?></span></td>
      </tr>
      <?php endforeach; ?>
      <tr class="row-total">
        <td colspan="5" class="tr">TOTAL EXTRAORDINARIOS</td>
        <td class="tr mono"><?= F::mxn($d['kpis']['extraTotal']) ?></td>
        <td class="tc mono" colspan="2">50% c/u: <?= F::mxn($d['kpis']['extraMitad']) ?></td>
      </tr>
    </tbody>
  </table>
</div>
<?php endif; ?>
