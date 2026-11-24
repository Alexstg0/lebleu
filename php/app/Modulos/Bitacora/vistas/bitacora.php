<?php
use Lebleu\Compartido\Formato as F;
/** @var array $meses @var int $anio @var int $mes @var array $viajes @var bool $esAdmin */
$n = fn($v) => (float) ($v ?? 0);
$h = fn(array $v, int $i) => $v['horometros'][$i] ?? null;
$rango = function (?array $x) {
    return $x ? F::num($x['lectura_inicio']) . ' → ' . F::num($x['lectura_fin']) : '—';
};
$total = count($viajes);
?>
<div class="wrap">
  <div class="cal-toolbar">
    <div class="cal-title"><h1>Bitácora</h1><span class="yr">de viajes</span></div>
    <?php if ($meses): ?>
    <select class="bita-filter" data-filtro-mes aria-label="Filtrar por mes">
      <?php foreach ($meses as $x): $v = $x['anio'] . '-' . $x['mes']; ?>
        <option value="<?= F::e($v) ?>"<?= $x['anio'] === $anio && $x['mes'] === $mes ? ' selected' : '' ?>><?= F::e(F::mesNombre($x['mes']) . ' ' . $x['anio']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>
  </div>

  <?php if (!$viajes): ?>
    <div class="empty">Sin viajes registrados en <?= F::e(F::mesNombre($mes) . ' ' . $anio) ?>.</div>
  <?php else: ?>
  <div class="table-card" style="overflow-x:auto">
    <table class="bita-table resp-table">
      <thead><tr>
        <?php if ($esAdmin): ?><th class="tc"></th><?php endif; ?>
        <th>Fecha</th><th>Cliente</th><th class="tr">Insumos</th><th class="tc">Pers.</th><th>Marinero</th>
        <th>Motor B</th><th>Motor E</th><th class="tr">Comb. inicio</th><th class="tr">Comb. fin</th><th class="tr">Consumo</th><th class="tc">Duración</th>
      </tr></thead>
      <tbody>
      <?php foreach ($viajes as $v): $etiqueta = $v['cliente'] . ' (' . F::fechaCorta($v['fecha']) . ')'; ?>
        <tr>
          <?php if ($esAdmin): ?>
          <td class="tc bita-acc" data-label="Acciones" style="white-space:nowrap">
            <button class="bita-edit" data-editar-viaje="<?= (int) $v['id'] ?>" title="Editar viaje">Editar</button><button class="bita-del" data-borrar-viaje="<?= (int) $v['id'] ?>" data-etiqueta="<?= F::e($etiqueta) ?>" title="Eliminar viaje" aria-label="Eliminar viaje">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6"/></svg>
            </button>
          </td>
          <?php endif; ?>
          <td data-label="Fecha" style="white-space:nowrap"><?= F::e(F::fechaCorta($v['fecha'])) ?></td>
          <td data-label="Cliente"><?= F::e($v['cliente'] . ($v['es_renta'] ? ' · Renta' : '')) ?></td>
          <td data-label="Insumos" class="tr mono" style="white-space:nowrap"><?= $n($v['costo_consumibles']) ? F::e(F::mxn($v['costo_consumibles'])) : '—' ?></td>
          <td data-label="Personas" class="tc"><?= $v['num_personas'] ?? '—' ?></td>
          <td data-label="Marinero"><?= F::e($v['marinero'] ?? '—') ?></td>
          <td data-label="Motor B" class="mono" style="white-space:nowrap"><?= F::e($rango($h($v, 0))) ?></td>
          <td data-label="Motor E" class="mono" style="white-space:nowrap"><?= F::e($rango($h($v, 1))) ?></td>
          <td data-label="Comb. inicio" class="tr mono"><?= $v['combustible_inicio'] !== null ? F::e(F::num($v['combustible_inicio'])) . ' L' : '—' ?></td>
          <td data-label="Comb. fin" class="tr mono"><?= $v['combustible_fin'] !== null ? F::e(F::num($v['combustible_fin'])) . ' L' : '—' ?></td>
          <td data-label="Consumo" class="tr mono"><?= F::e(F::num($v['litros'])) ?> L</td>
          <td data-label="Duración" class="tc"><?= $v['duracion_horas'] !== null ? F::e(F::num($v['duracion_horas'], 0)) . ' h' : '—' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
  <div class="hint" style="margin-top:12px">
    <?= $total ?> viaje<?= $total === 1 ? '' : 's' ?> en <?= F::e(F::mesNombre($mes) . ' ' . $anio) ?>
    <?= $esAdmin && $total > 0 ? ' · Como administrador puedes Editar cualquier viaje para completar datos que falten (marinero, personas, horómetros, etc.).' : '' ?>
  </div>
</div>
