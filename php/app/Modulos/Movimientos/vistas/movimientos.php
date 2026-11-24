<?php
use Lebleu\Compartido\Encabezado;
use Lebleu\Compartido\Formato as F;
use Lebleu\Core\Vista;
/** @var array $periodos @var array $periodo @var array $mov */
$fecha = fn($r) => F::e(F::fechaCorta($r['fecha']));
$acciones = fn(string $clave, string $url, string $etiqueta, int $id) =>
    '<td class="tc" style="white-space:nowrap"><button class="btn-link" data-editar="' . $clave . ':' . $id . '">Editar</button>'
    . '<button class="btn-link danger" data-borrar data-url="' . $url . '" data-id="' . $id . '" data-etiqueta="' . F::e($etiqueta) . '">Borrar</button></td>';
$archivos = fn(string $columna, array $fila) => '<td>' . Vista::parcial(dirname(__DIR__, 2) . '/Adjuntos/vistas/adjuntos.php', ['adjuntos' => $fila['adjuntos'], 'columna' => $columna, 'id' => (int) $fila['id']]) . '</td>';
$vacio = fn(int $cols, string $texto) => '<tr><td colspan="' . $cols . '" class="tc hint">' . $texto . '</td></tr>';
$anomalo = '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="var(--amber)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px" aria-label="anómalo"><path d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>';
$naranja = 'border-left-color:#c05621;color:#c05621';
?>
<div class="wrap" data-periodo-id="<?= (int) $periodo['id'] ?>">
  <div class="rep-head">
    <div>
      <h1 style="margin:4px 0 2px;font-size:22px">Movimientos</h1>
      <div class="hint"><?= F::e(F::mesNombre((int) $periodo['mes']) . ' ' . $periodo['anio']) ?> · editar o borrar lo capturado
        <?php if ($periodo['estado'] === 'cerrado'): ?><span class="tag a" style="margin-left:8px">cerrado</span><?php endif; ?></div>
    </div>
    <?php if (count($periodos) > 1) echo Encabezado::selectorPeriodo($periodos, (int) $periodo['id'], '/movimientos'); ?>
  </div>
  <div style="height:18px"></div>

  <div class="gen-rec">
    <button class="btn" data-generar-recurrentes>Generar nómina y administrativo</button>
    <span class="hint">Agrega la nómina del capitán (viernes) y el cargo administrativo mensual que falten.</span>
    <span class="msg-ok" style="margin-left:4px" data-msg-recurrentes hidden></span>
  </div>

  <div data-refrescable="movimientos">
  <div class="section-label">Gastos operativos</div>
  <div class="table-card"><table>
    <thead><tr><th>Fecha</th><th>Proveedor</th><th>Concepto</th><th class="tr">Monto</th><th>Respaldo (PDF)</th><th class="tc">Acciones</th></tr></thead>
    <tbody>
      <?php if (!$mov['operativos']) echo $vacio(6, 'Sin gastos operativos.'); ?>
      <?php foreach ($mov['operativos'] as $g): ?>
      <tr><td><?= $fecha($g) ?></td><td><?= F::e($g['proveedor']) ?></td><td><?= F::e($g['concepto']) ?></td><td class="tr mono"><?= F::mxn($g['monto_mxn']) ?></td><?= $archivos('gasto_id', $g) ?><?= $acciones('op', '/api/gastos-operativos', 'el gasto', (int) $g['id']) ?></tr>
      <?php endforeach; ?>
    </tbody></table></div>

  <div class="section-label">Insumos</div>
  <div class="table-card"><table>
    <thead><tr><th>Fecha</th><th>Viaje</th><th>Proveedor</th><th>Concepto</th><th class="tr">Monto</th><th>Respaldo (PDF)</th><th class="tc">Acciones</th></tr></thead>
    <tbody>
      <?php if (!$mov['insumos']) echo $vacio(7, 'Sin insumos.'); ?>
      <?php foreach ($mov['insumos'] as $i): ?>
      <tr><td><?= $fecha($i) ?></td><td><?= $i['viaje_id'] ? F::e($i['viaje_cliente'] . ' · ' . F::fechaCorta($i['viaje_fecha'])) : '—' ?></td>
        <td><?= F::e($i['proveedor'] ?: '—') ?></td><td><?= F::e($i['concepto'] ?: '—') ?></td><td class="tr mono"><?= F::mxn($i['monto_mxn']) ?></td><?= $archivos('insumo_id', $i) ?><?= $acciones('in', '/api/insumos', 'el insumo', (int) $i['id']) ?></tr>
      <?php endforeach; ?>
    </tbody></table></div>

  <div class="section-label">Viajes</div>
  <div class="table-card"><table>
    <thead><tr><th>Fecha</th><th>Socio / cliente</th><th class="tr">Total</th><th class="tc">Acciones</th></tr></thead>
    <tbody>
      <?php if (!$mov['viajes']) echo $vacio(4, 'Sin viajes.'); ?>
      <?php foreach ($mov['viajes'] as $v): ?>
      <tr><td><?= $fecha($v) ?> <?= $v['bandera'] ? $anomalo : '' ?></td><td><?= F::e($v['cliente_disp']) ?><?= $v['es_renta'] ? ' · Renta' : '' ?></td><td class="tr mono"><?= F::mxn($v['total']) ?></td><?= $acciones('vi', '/api/viajes', 'el viaje', (int) $v['id']) ?></tr>
      <?php endforeach; ?>
    </tbody></table></div>

  <div class="section-label">Marineros</div>
  <div class="table-card"><table>
    <thead><tr><th>Fecha</th><th>Marinero</th><th>Cliente / viaje</th><th class="tr">Pago</th></tr></thead>
    <tbody>
      <?php if (!$mov['viajes']) echo $vacio(4, 'Sin viajes.'); ?>
      <?php foreach ($mov['viajes'] as $v): ?>
      <tr><td><?= $fecha($v) ?></td><td><?= trim((string) $v['marinero']) !== '' ? F::e($v['marinero']) : '<span class="hint">Sin asignar</span>' ?></td>
        <td><?= F::e($v['cliente_disp']) ?><?= $v['es_renta'] ? ' · Renta' : '' ?></td><td class="tr mono"><?= F::mxn($v['costo_marinero']) ?></td></tr>
      <?php endforeach; ?>
      <?php if ($mov['viajes']): ?>
      <tr class="row-total"><td colspan="3" class="tr">TOTAL MARINEROS</td><td class="tr mono"><?= F::mxn(array_sum(array_map(fn($v) => \Lebleu\Compartido\Dinero::aCentavos($v['costo_marinero']), $mov['viajes'])) / 100) ?></td></tr>
      <?php endif; ?>
    </tbody></table></div>
  <div class="hint" style="margin:-8px 0 4px">El marinero y su pago se editan dentro de cada viaje (sección Viajes).</div>

  <div class="section-label">Aportaciones</div>
  <div class="table-card"><table>
    <thead><tr><th>Fecha</th><th>Socio</th><th class="tr">Monto</th><th class="tc">Acciones</th></tr></thead>
    <tbody>
      <?php if (!$mov['aportaciones']) echo $vacio(4, 'Sin aportaciones.'); ?>
      <?php foreach ($mov['aportaciones'] as $a): ?>
      <tr><td><?= $fecha($a) ?></td><td><?= F::e($a['socio_nombre']) ?></td><td class="tr mono"><?= F::mxn($a['monto']) ?></td><?= $acciones('ap', '/api/aportaciones', 'la aportación', (int) $a['id']) ?></tr>
      <?php endforeach; ?>
    </tbody></table></div>

  <div class="section-label" style="<?= $naranja ?>">Extraordinarios</div>
  <div class="table-card" style="border:1px solid #f5b7a0"><table>
    <thead><tr><th>Fecha</th><th>Proveedor</th><th>Concepto</th><th class="tr">Monto</th><th>Respaldo (PDF)</th><th class="tc">Acciones</th></tr></thead>
    <tbody>
      <?php if (!$mov['extraordinarios']) echo $vacio(6, 'Sin extraordinarios.'); ?>
      <?php foreach ($mov['extraordinarios'] as $e): ?>
      <tr><td><?= $fecha($e) ?></td><td><?= F::e($e['proveedor']) ?></td><td><?= F::e($e['concepto']) ?></td><td class="tr mono"><?= F::mxn($e['monto_mxn']) ?></td><?= $archivos('extraordinario_id', $e) ?><?= $acciones('ex', '/api/extraordinarios', 'el extraordinario', (int) $e['id']) ?></tr>
      <?php endforeach; ?>
    </tbody></table></div>

  <div class="section-label">Abonos a extraordinarios</div>
  <div class="table-card"><table>
    <thead><tr><th>Fecha</th><th>Socio</th><th>Método</th><th>Referencia</th><th class="tr">Monto</th><th class="tc">Acciones</th></tr></thead>
    <tbody>
      <?php if (!$mov['abonosExtra']) echo $vacio(6, 'Sin abonos a extraordinarios.'); ?>
      <?php foreach ($mov['abonosExtra'] as $a): ?>
      <tr><td><?= $fecha($a) ?></td><td><?= F::e($a['socio_nombre']) ?></td><td><?= F::e($a['metodo'] ?: '—') ?></td><td><?= F::e($a['referencia'] ?: '—') ?></td>
        <td class="tr mono"><?= F::mxn($a['monto']) ?></td><?= $acciones('ae', '/api/abonos-extraordinarios', 'el abono', (int) $a['id']) ?></tr>
      <?php endforeach; ?>
    </tbody></table></div>

  <div class="section-label">Ingresos por renta</div>
  <div class="table-card"><table>
    <thead><tr><th>Fecha</th><th>Cliente</th><th class="tr">Ingreso</th><th class="tr">Utilidad</th><th class="tc">Acciones</th></tr></thead>
    <tbody>
      <?php if (!$mov['renta']) echo $vacio(5, 'Sin ingresos por renta.'); ?>
      <?php foreach ($mov['renta'] as $r): ?>
      <tr><td><?= $fecha($r) ?></td><td><?= F::e($r['cliente']) ?></td><td class="tr mono"><?= F::mxn($r['monto']) ?></td><td class="tr mono"><?= F::mxn($r['utilidad']) ?></td><?= $acciones('re', '/api/ingresos-renta', 'el ingreso', (int) $r['id']) ?></tr>
      <?php endforeach; ?>
    </tbody></table></div>
  </div>
</div>
