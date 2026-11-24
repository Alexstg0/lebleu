<?php
use Lebleu\Compartido\Formato as F;
/** @var array $periodos @var int $periodoId @var string $perTxt */
$tarjetas = [
    ['📄', 'Estado de cuenta', 'Reporte principal por socio: balances, gastos, viajes y extraordinarios.', null],
    ['📎', 'Reporte general de soportes', 'Un solo PDF con una portada por partida (gastos operativos, insumos) seguida de todos los comprobantes cargados del periodo.',
        ['btn gold', "/api/reportes/soportes?periodo=$periodoId", 'Reporte general de soportes', 'Incluye solo los gastos que tengan PDF de respaldo.']],
    ['⚓', 'Reporte de marineros', 'Resumen de pagos por marinero y un recibo de pago por cada uno con el detalle de los viajes en los que estuvo.',
        ['btn', "/api/reportes/marineros?periodo=$periodoId", 'Reporte de marineros', 'Toma el marinero y el costo capturado en cada viaje.']],
    ['📦', 'Reporte completo', 'Un solo PDF con todo el periodo, en orden: estado de cuenta, reporte general de soportes y reporte de marineros. Listo para enviar.',
        ['btn gold', "/api/reportes/completo?periodo=$periodoId", "Reporte completo — $perTxt", 'Junta los 3 reportes de arriba con todos los comprobantes.']],
];
?>
<div class="wrap">
  <div class="rep-head">
    <div>
      <h1 style="margin:4px 0 2px;font-size:22px">Reportes</h1>
      <div class="hint">Genera y consulta los reportes de <?= F::e($perTxt) ?></div>
    </div>
    <div class="rep-periodo"><label>Periodo</label><select data-ir-periodo="/reportes">
      <?php foreach ($periodos as $p): ?>
        <option value="<?= (int) $p['id'] ?>"<?= (int) $p['id'] === $periodoId ? ' selected' : '' ?>><?= F::e(F::mesNombre((int) $p['mes']) . ' ' . $p['anio']) ?></option>
      <?php endforeach; ?>
    </select></div>
  </div>
  <div class="rep-grid">
    <?php foreach ($tarjetas as [$icono, $titulo, $texto, $accion]): ?>
    <div class="rep-card">
      <div class="rep-icon"><?= $icono ?></div>
      <h3><?= F::e($titulo) ?></h3>
      <p><?= F::e($texto) ?></p>
      <div class="rep-actions">
        <?php if ($accion): ?>
          <button type="button" class="<?= $accion[0] ?>" data-visor-pdf="<?= F::e($accion[1]) ?>" data-titulo="<?= F::e($accion[2]) ?>">Ver</button>
          <span class="hint"><?= F::e($accion[3]) ?></span>
        <?php else: ?>
          <a class="btn" href="/?periodo=<?= $periodoId ?>">Abrir</a>
          <span class="hint">Usa el botón PDF dentro del reporte para descargarlo.</span>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
