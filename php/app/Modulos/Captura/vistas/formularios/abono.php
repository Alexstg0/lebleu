<?php
use Lebleu\Compartido\Campos as C;
use Lebleu\Modulos\Captura\Formularios as F;
/** @var ?array $d @var array $ctx @var bool $editando */
$socios = array_column($ctx['socios'], 'nombre', 'id');
?>
<form class="form-card" <?= F::atributosForm('/api/abonos-extraordinarios', $editando, 'monto,referencia') ?>>
  <?= F::ocultos($d, $ctx['periodoId']) ?>
  <?php if (!$editando): ?><h2>Nuevo abono a extraordinarios</h2><?php endif; ?>
  <p class="hint" style="margin:-6px 0 12px">Pago de un socio a su cuenta de gastos extraordinarios (se liquidan por separado).</p>
  <div class="grid">
    <?= C::campo('Fecha', C::input('fecha', $d['fecha'] ?? date('Y-m-d'), ['type' => 'date', 'required' => true])) ?>
    <?= C::campo('Socio', C::select('socio_id', $socios, (string) ($d['socio_id'] ?? array_key_first($socios)))) ?>
    <?= C::campo('Monto (MXN)', C::input('monto', isset($d['monto']) ? (string) $d['monto'] : '', ['type' => 'number', 'step' => '0.01', 'required' => true])) ?>
    <?= C::campo('Método', C::select('metodo', ['transferencia' => 'Transferencia', 'efectivo' => 'Efectivo', 'cheque' => 'Cheque', 'otro' => 'Otro'], $d['metodo'] ?? 'transferencia')) ?>
    <?= C::campo('Referencia', C::input('referencia', $d['referencia'] ?? '', ['placeholder' => 'Folio / nota']), 'full') ?>
  </div>
  <?= F::pie($editando, 'Guardar abono') ?>
</form>
