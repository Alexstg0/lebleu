<?php
use Lebleu\Compartido\Formato as F;
/** @var array $filas */
$secciones = [
    'gastos_operativos' => 'Gasto operativo', 'insumos' => 'Insumo', 'viajes' => 'Viaje', 'aportaciones' => 'Aportación',
    'extraordinarios' => 'Extraordinario', 'abonos_extraordinarios' => 'Abono extraordinario', 'ingresos_renta' => 'Renta',
    'caja_chica' => 'Caja chica', 'reservas' => 'Reserva', 'periodos' => 'Periodo', 'marineros' => 'Marinero',
    'adjuntos' => 'Archivo', 'usuarios' => 'Usuario',
];
$clases = ['crear' => 'g', 'editar' => 'b', 'borrar' => 'a', 'cerrar' => 'a', 'reabrir' => 'b', 'subir' => 'g', 'generar' => 'b'];
?>
<div class="wrap">
  <h1 style="margin:4px 0 2px;font-size:22px">Auditoría</h1>
  <div class="hint">Registro de quién hizo qué y cuándo (últimos 300 movimientos, hora de BCS)</div>
  <div style="height:18px"></div>
  <?php if (!$filas): ?>
  <div class="empty">Aún no hay actividad registrada. A partir de ahora, cada cambio quedará anotado aquí.</div>
  <?php else: ?>
  <div class="table-card">
    <table class="resp-table">
      <thead><tr><th>Fecha y hora</th><th>Usuario</th><th class="tc">Acción</th><th>Sección</th><th>Detalle</th></tr></thead>
      <tbody>
        <?php foreach ($filas as $r): ?>
        <tr>
          <td data-label="Fecha" style="white-space:nowrap"><?= F::e($r['cuando']) ?></td>
          <td data-label="Usuario"><?= F::e($r['usuario_nombre'] ?: 'sistema') ?></td>
          <td data-label="Acción" class="tc"><span class="tag <?= $clases[$r['accion']] ?? '' ?>"><?= F::e($r['accion']) ?></span></td>
          <td data-label="Sección"><?= F::e($secciones[$r['tabla']] ?? $r['tabla']) ?></td>
          <td data-label="Detalle"><?= F::e($r['detalle']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
