<?php
use Lebleu\Compartido\Campos as C;
use Lebleu\Modulos\Captura\Formularios as F;
/** @var ?array $d @var array $ctx @var bool $editando */
$moneda = $d['moneda'] ?? 'MXN';
$tc = isset($d['tipo_cambio']) ? (string) $d['tipo_cambio'] : (string) (float) $ctx['tipoCambio'];
?>
<form class="form-card" <?= F::atributosForm('/api/extraordinarios', $editando, 'proveedor,concepto,monto_original,comprobante_folio') ?>>
  <?= F::ocultos($d, $ctx['periodoId']) ?>
  <?php if (!$editando): ?>
  <h2>Nuevo gasto extraordinario (liquidación separada)</h2>
  <div class="hint" style="margin-bottom:14px">No entra al balance operativo. Se reparte 50/50 y se liquida por separado.</div>
  <?php endif; ?>
  <div class="grid">
    <?= C::campo('Fecha', C::input('fecha', $d['fecha'] ?? date('Y-m-d'), ['type' => 'date', 'required' => true])) ?>
    <?= C::campo('Categoría', C::select('categoria', ['muelle' => 'Muelle', 'refaccion' => 'Refacción', 'varada' => 'Varada', 'otro' => 'Otro'], $d['categoria'] ?? 'refaccion')) ?>
    <?= C::campo('Proveedor', C::input('proveedor', $d['proveedor'] ?? '', ['required' => true])) ?>
    <?= C::campo('Concepto', C::input('concepto', $d['concepto'] ?? '', ['required' => true])) ?>
    <?= C::campo('Moneda', C::select('moneda', ['MXN' => 'MXN', 'USD' => 'USD'], $moneda, ['data-moneda' => true])) ?>
    <div class="field"><label data-etiqueta-monto><?= $moneda === 'USD' ? 'Monto (USD)' : 'Monto (MXN)' ?></label><?= C::input('monto_original', isset($d['monto_original']) ? (string) $d['monto_original'] : '', ['type' => 'number', 'step' => '0.01', 'required' => true]) ?></div>
    <?= C::campo('Tipo de cambio', C::input('tipo_cambio', $tc, ['type' => 'number', 'step' => '0.0001']), '', 'data-solo-usd' . ($moneda === 'USD' ? '' : ' hidden')) ?>
    <?= C::campo('Comprobante / folio', C::input('comprobante_folio', $d['comprobante_folio'] ?? '', ['placeholder' => 'Invoice, orden…'])) ?>
    <?php if (!$editando): ?>
    <?= C::campo('Soporte (PDF o foto, opcional)', C::input('soporte', '', ['type' => 'file', 'accept' => 'application/pdf,image/jpeg,image/png', 'data-soporte' => 'extraordinario_id']), 'full') ?>
    <?php endif; ?>
  </div>
  <?php if (!$editando): ?><div class="hint" style="margin-top:8px">Puedes arrastrar el archivo hasta aquí. Si no lo subes ahora, lo puedes agregar después en Administración / Movimientos.</div><?php endif; ?>
  <?= F::pie($editando, 'Guardar extraordinario', 'btn gold') ?>
</form>
