<?php
use Lebleu\Compartido\Formato as F;
use Lebleu\Core\Vista;
/** @var array $d datos de EstadoCuentaServicio::obtener @var array $usuario */
$periodo = $d['periodo'];
$dir = __DIR__;
$datos = ['d' => $d, 'usuario' => $usuario];
?>
<button class="pdf-btn" data-imprimir aria-label="Exportar a PDF" title="Exportar a PDF">
  <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M12 16l-4-4h3V4h2v8h3l-4 4z" fill="currentColor"/><path d="M20 18H4v2h16v-2z" fill="currentColor"/></svg>
  PDF
</button>
<div class="wrap">
  <div class="ec-header">
    <img src="/logo-lebleu.png" alt="Le Bleu" class="ec-logo">
    <div>
      <h1 style="margin:0 0 2px;font-size:22px">Estado de cuenta — <?= F::e(F::mesNombre((int) $periodo['mes'])) ?> <?= (int) $periodo['anio'] ?></h1>
      <div class="hint"><?= F::e($periodo['embarcacion']) ?> · <?= F::e($periodo['razon_social']) ?> · T/C $<?= F::num($periodo['tipo_cambio'], 2) ?> · Combustible $<?= F::num($periodo['precio_litro'], 2) ?>/Lt · Cifras en MXN</div>
    </div>
  </div>
  <?= Vista::parcial("$dir/kpis.php", $datos) ?>
  <?= Vista::parcial("$dir/socios.php", $datos) ?>
  <?= Vista::parcial("$dir/operativos.php", $datos) ?>
  <?= Vista::parcial("$dir/insumos.php", $datos) ?>
  <?= Vista::parcial("$dir/variable.php", $datos) ?>
  <?= Vista::parcial("$dir/viajes.php", $datos) ?>
  <?= Vista::parcial("$dir/extraordinarios.php", $datos) ?>
  <div style="text-align:center;color:var(--muted);font-size:11px;margin:30px 0">
    <?= F::e($periodo['razon_social']) ?> · Embarcación <?= F::e($periodo['embarcacion']) ?> · <?= F::e(F::mesNombre((int) $periodo['mes'])) ?> <?= (int) $periodo['anio'] ?>
  </div>
</div>
