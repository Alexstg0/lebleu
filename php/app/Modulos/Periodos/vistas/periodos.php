<?php
use Lebleu\Compartido\Formato as F;
/** @var array $periodos @var array $sugerido @var array|null $ultimo */
$meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
?>
<div class="wrap">
  <h1 style="margin:4px 0 14px;font-size:22px">Periodos</h1>

  <div class="periodo-list">
    <?php foreach ($periodos as $p): $etiqueta = F::mesNombre((int) $p['mes']) . ' ' . $p['anio']; ?>
    <div class="periodo-row">
      <div>
        <strong><?= F::e($etiqueta) ?></strong>
        <span class="est <?= $p['estado'] === 'abierto' ? 'est-abierto' : 'est-cerrado' ?>"><?= F::e($p['estado']) ?></span>
        <div class="hint">T/C $<?= number_format((float) $p['tipo_cambio'], 2) ?> · $<?= number_format((float) $p['precio_litro'], 2) ?>/Lt</div>
      </div>
      <div style="text-align:right;display:flex;flex-direction:column;align-items:flex-end;gap:4px">
        <div class="hint">Balance operativo (ambos socios)</div>
        <div class="mono" style="font-weight:700;font-size:16px"><?= F::mxn($p['balance']) ?></div>
        <a class="hint" href="/?periodo=<?= (int) $p['id'] ?>">Ver estado de cuenta →</a>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap" data-periodo-id="<?= (int) $p['id'] ?>" data-etiqueta="<?= F::e($etiqueta) ?>">
          <?php if ($p['archivo_id']): ?>
          <button type="button" class="btn-link" data-visor-pdf="/api/adjuntos/<?= (int) $p['archivo_id'] ?>" data-titulo="Estado de cuenta archivado — <?= F::e($etiqueta) ?>">📄 Archivo</button>
          <?php endif; ?>
          <?php if ($p['estado'] === 'abierto'): ?>
          <button class="btn-ghost" style="padding:6px 14px;font-size:12px" data-accion-periodo="cerrar">Cerrar periodo</button>
          <?php else: ?>
          <button class="btn-ghost" style="padding:6px 14px;font-size:12px" data-accion-periodo="reabrir">Reabrir</button>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <form class="form-card" data-nuevo-periodo>
    <h2>Abrir nuevo periodo</h2>
    <div class="hint" style="margin-bottom:14px">
      El saldo de inicio de cada socio se arrastra automáticamente del balance del mes anterior, que queda cerrado.
    </div>
    <div class="grid">
      <div class="field"><label>Mes</label>
        <select name="mes">
          <?php foreach ($meses as $i => $m): ?>
          <option value="<?= $i + 1 ?>"<?= $i + 1 === (int) $sugerido['mes'] ? ' selected' : '' ?>><?= F::e($m) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>Año</label><input type="number" name="anio" required value="<?= (int) $sugerido['anio'] ?>"></div>
      <div class="field"><label>Tipo de cambio</label><input type="number" name="tipo_cambio" step="0.0001" value="<?= F::e((string) (float) ($ultimo['tipo_cambio'] ?? 1)) ?>"></div>
      <div class="field"><label>Precio combustible / litro</label><input type="number" name="precio_litro" step="0.0001" value="<?= F::e((string) (float) ($ultimo['precio_litro'] ?? 0)) ?>"></div>
      <div class="field"><label>Cargo por administración</label><input type="number" name="cargo_administracion" step="0.01" value="6000"></div>
    </div>
    <div style="margin-top:16px;display:flex;align-items:center;gap:14px">
      <button class="btn" data-boton>Abrir periodo</button>
      <span data-mensaje hidden></span>
    </div>
  </form>
</div>
