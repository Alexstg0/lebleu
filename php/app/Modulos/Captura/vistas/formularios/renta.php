<?php
use Lebleu\Compartido\Campos as C;
use Lebleu\Compartido\Formato;
use Lebleu\Modulos\Captura\Formularios as F;
/** @var ?array $d @var array $ctx @var bool $editando */
$utilidad = (float) ($d['monto'] ?? 0) - (float) ($d['costos_asociados'] ?? 0);
$extra = '<span class="hint">Utilidad: <strong class="mono" data-utilidad>$' . Formato::num($utilidad, 2) . '</strong></span>';
?>
<form class="form-card" <?= F::atributosForm('/api/ingresos-renta', $editando, 'cliente,monto,costos_asociados') ?> data-calculo="renta">
  <?= F::ocultos($d, $ctx['periodoId']) ?>
  <?php if (!$editando): ?>
  <h2>Nuevo ingreso por renta</h2>
  <div class="hint" style="margin-bottom:14px">La utilidad (ingreso − costos) se reparte 50/50 y suma al balance operativo.</div>
  <?php endif; ?>
  <div class="grid">
    <?= C::campo('Fecha', C::input('fecha', $d['fecha'] ?? date('Y-m-d'), ['type' => 'date', 'required' => true])) ?>
    <?= C::campo('Cliente', C::input('cliente', $d['cliente'] ?? '', ['required' => true])) ?>
    <?= C::campo('Ingreso (MXN)', C::input('monto', isset($d['monto']) ? (string) $d['monto'] : '', ['type' => 'number', 'step' => '0.01', 'required' => true])) ?>
    <?= C::campo('Costos asociados (MXN)', C::input('costos_asociados', isset($d['costos_asociados']) ? (string) $d['costos_asociados'] : '', ['type' => 'number', 'step' => '0.01'])) ?>
  </div>
  <?= F::pie($editando, 'Guardar renta', 'btn', $extra, ';flex-wrap:wrap') ?>
</form>
