<?php
use Lebleu\Compartido\Encabezado;
use Lebleu\Compartido\Formato as F;
use Lebleu\Modulos\Captura\Formularios;
/** @var array $usuario @var array $periodos @var array $periodo @var array $ctx */
$esCapitan = $usuario['rol'] === 'capitan';
$pestanas = [
    'viaje' => ['Viaje', 'viaje'], 'gasto' => ['Gasto operativo', 'gasto'], 'insumos' => ['Insumos', 'insumo'],
    'aportacion' => ['Aportación', 'aportacion'], 'extraordinario' => ['Extraordinario', 'extraordinario'],
    'abono_extra' => ['Abono extraord.', 'abono'], 'renta' => ['Renta', 'renta'],
];
?>
<div class="wrap">
  <div class="rep-head">
    <div>
      <h1 style="margin:4px 0 2px;font-size:22px"><?= $esCapitan ? 'Registrar viaje' : 'Capturar movimientos' ?></h1>
      <div class="hint">Registrando en el periodo: <strong><?= F::e(F::mesNombre((int) $periodo['mes']) . ' ' . $periodo['anio']) ?></strong>
        <?php if ($periodo['estado'] === 'cerrado'): ?><span class="tag a" style="margin-left:8px">cerrado</span><?php endif; ?></div>
    </div>
    <?php if ($usuario['rol'] === 'admin' && count($periodos) > 1) echo Encabezado::selectorPeriodo($periodos, (int) $periodo['id'], '/capturar'); ?>
  </div>
  <div style="height:18px"></div>

  <?php if ($esCapitan): ?>
    <?= Formularios::renderizar('viaje_capitan', null, $ctx) ?>
  <?php else: ?>
    <div class="tabs">
      <?php foreach ($pestanas as $clave => [$etiqueta]): ?>
      <button class="tab" data-tab="<?= $clave ?>" type="button"><?= F::e($etiqueta) ?></button>
      <?php endforeach; ?>
    </div>
    <?php foreach ($pestanas as $clave => [, $tipo]): ?>
    <div data-panel="<?= $clave ?>" hidden><?= Formularios::renderizar($tipo, null, $ctx) ?></div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<script type="application/json" id="datos-viajes"><?= json_encode($ctx['viajes'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
