<?php
use Lebleu\Compartido\Dinero;
use Lebleu\Compartido\Formato as F;
use Lebleu\Core\Vista;
/** @var array $movimientos @var string $hoyTexto @var string $embarcacion @var string $razonSocial */
$cajas = array_unique(array_filter(array_column($movimientos, 'caja_numero'), fn($c) => $c !== null));
rsort($cajas);
$totalGastos = array_sum(array_map(fn($m) => Dinero::aCentavos($m['monto']), $movimientos));
$totalAbonado = array_sum(array_map(fn($m) => Dinero::aCentavos($m['abono']), $movimientos));
$balanceActual = $movimientos ? end($movimientos)['balance'] : 0;
$ver = fn($v) => (float) $v ? F::mxn($v) : '—';
$datos = ['movimientos' => $movimientos, 'ultimaCaja' => $cajas ? max($cajas) : 13];
?>
<button class="pdf-btn" data-imprimir aria-label="Exportar a PDF" title="Exportar a PDF">
  <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M12 16l-4-4h3V4h2v8h3l-4 4z" fill="currentColor"/><path d="M20 18H4v2h16v-2z" fill="currentColor"/></svg>
  PDF
</button>
<div class="wrap cal-page" data-caja-chica>
  <h1 style="margin:4px 0 14px;font-size:22px" class="cc-screen-title">Caja chica</h1>

  <div class="caja-print-head">
    <img src="/logo-lebleu.png" alt="Le Bleu">
    <div class="caja-print-info">
      <h2 data-titulo-impresion>Balance de caja chica</h2>
      <div><?= F::e($razonSocial) ?> · Embarcación <?= F::e($embarcacion) ?></div>
      <div class="caja-print-meta"><span>Fecha: <?= F::e($hoyTexto) ?></span><span>Responsable de caja: <span data-responsable-impresion>Gabriel Preciado</span></span></div>
    </div>
  </div>

  <div class="cc-toolbar">
    <div class="cc-filtros">
      <button class="cc-pill on" data-filtro="todas">Todas</button>
      <?php foreach ($cajas as $c): ?><button class="cc-pill" data-filtro="<?= (int) $c ?>">Caja <?= (int) $c ?></button><?php endforeach; ?>
    </div>
    <div class="cc-tools-right">
      <label class="cc-resp">Responsable<input value="Gabriel Preciado" data-responsable></label>
      <button type="button" class="btn-ghost" data-reporte-pdf data-visor-pdf="/api/reportes/caja-chica?caja=todas&amp;responsable=Gabriel%20Preciado" data-titulo="Reporte de caja chica">Reporte PDF</button>
      <button class="btn" data-nuevo-mov>+ Movimiento</button>
    </div>
  </div>

  <div class="cc-cards" data-tarjetas>
    <div class="cc-card"><span data-et="1">Gastos</span><b data-v="1"><?= F::mxn($totalGastos / 100) ?></b></div>
    <div class="cc-card"><span data-et="2">Abonos</span><b data-v="2"><?= F::mxn($totalAbonado / 100) ?></b></div>
    <div class="cc-card <?= $balanceActual < 0 ? 'neg' : 'pos' ?>" data-tarjeta-final><span data-et="3">Balance actual</span><b data-v="3"><?= F::mxn($balanceActual) ?></b></div>
  </div>

  <div class="cc-empty" data-vacio<?= $movimientos ? ' hidden' : '' ?>>Sin movimientos<span data-vacio-caja></span>. Usa “+ Movimiento” para registrar gastos o abonos.</div>
  <div class="cc-table-wrap" data-tabla<?= $movimientos ? '' : ' hidden' ?>>
    <table class="resp-table cc-table">
      <thead><tr><th>Fecha</th><th>Caja</th><th>Factura</th><th>Proveedor</th><th>Concepto</th><th class="r">Monto</th><th class="r cc-abono-col">Abono</th><th class="r cc-bal">Balance</th><th>Observaciones</th><th class="cc-soporte-col">Soporte</th></tr></thead>
      <tbody>
        <?php foreach ($movimientos as $m): ?>
        <tr class="cc-row" data-mov="<?= $m['id'] ?>" data-caja="<?= $m['caja_numero'] ?? '' ?>" data-monto="<?= Dinero::aCentavos($m['monto']) ?>" data-abono="<?= Dinero::aCentavos($m['abono']) ?>">
          <td data-label="Fecha"><?= F::e(F::fechaCorta($m['fecha'])) ?></td>
          <td data-label="Caja"><?= $m['caja_numero'] ?? '—' ?></td>
          <td data-label="Factura"><?= F::e($m['factura'] ?: '—') ?></td>
          <td data-label="Proveedor"><?= F::e($m['proveedor'] ?: '—') ?></td>
          <td data-label="Concepto"><?= F::e($m['concepto'] ?: '—') ?></td>
          <td data-label="Monto" class="r"><?= $ver($m['monto']) ?></td>
          <td data-label="Abono" class="r cc-abono-col"><?= $ver($m['abono']) ?></td>
          <td data-label="Balance" class="r cc-bal <?= $m['balance'] < 0 ? 'neg' : '' ?>"><?= F::mxn($m['balance']) ?></td>
          <td data-label="Observaciones"><?= F::e($m['observaciones'] ?: '—') ?></td>
          <td data-label="Soporte" class="cc-soporte-col" data-sin-fila><?= Vista::parcial(dirname(__DIR__, 2) . '/Adjuntos/vistas/adjuntos.php', ['adjuntos' => $m['adjuntos'], 'columna' => 'caja_id', 'id' => $m['id']]) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="5" class="r"><b>Totales</b></td>
          <td class="r"><b data-t-monto><?= F::mxn($totalGastos / 100) ?></b></td>
          <td class="r cc-abono-col"><b data-t-abono><?= F::mxn($totalAbonado / 100) ?></b></td>
          <td class="r cc-bal"><b data-t-balance><?= F::mxn($balanceActual) ?></b></td>
          <td class="cc-apagar" data-t-apagar></td>
          <td class="cc-soporte-col"></td>
        </tr>
      </tfoot>
    </table>
  </div>

  <div class="caja-sign"><div class="caja-sign-box"><span class="line"></span><span>Kevin Flores</span><small>Revisó</small></div></div>
</div>
<script type="application/json" id="datos-caja"><?= json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
