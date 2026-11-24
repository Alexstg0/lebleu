<?php
use Lebleu\Compartido\Campos as C;
use Lebleu\Compartido\Formato;
use Lebleu\Modulos\Captura\Formularios as F;
/** @var ?array $d @var array $ctx @var bool $editando */
defined('GAL') || define('GAL', 3.78541);
$aGal = fn($litros) => $litros === null || $litros === '' ? '' : (string) round((float) $litros / GAL, 2);
$socios = array_column($ctx['socios'], 'nombre', 'id');
$clientes = ['' => '—'] + array_column($ctx['clientes'], 'nombre', 'id');
$precio = (float) ($d['precio_litro'] ?? $ctx['precioLitro']);
$horos = [];
foreach ($d['horometros'] ?? [] as $h) {
    $horos[(int) $h['motor_id']] = $h;
}
$texto = fn(string $k, string $def = '') => isset($d[$k]) && $d[$k] !== null ? (string) $d[$k] : $def;
?>
<form class="form-card" <?= F::atributosForm('/api/viajes', $editando, 'duracion_horas,num_personas,gal_inicio,gal_fin,litros,costo_consumibles,consumibles_comprobante') ?> data-calculo="viaje" data-precio="<?= Formato::e((string) $precio) ?>" data-editando="<?= $editando ? 1 : 0 ?>">
  <?= F::ocultos($d, $ctx['periodoId']) ?>
  <?php if (!$editando): ?><h2>Nuevo viaje (gasto variable)</h2><?php endif; ?>
  <div class="grid">
    <?= C::campo('Fecha', C::input('fecha', $d['fecha'] ?? date('Y-m-d'), ['type' => 'date', 'required' => true])) ?>
    <?= C::campo('Socio responsable', C::select('socio_id', $socios, (string) ($d['socio_id'] ?? array_key_first($socios)))) ?>
    <?= C::campo('Cliente / familia', C::select('cliente_id', $clientes, (string) ($d['cliente_id'] ?? array_key_first($ctx['clientes'] ? array_column($ctx['clientes'], 'nombre', 'id') : [])))) ?>
    <?= C::campo('Duración (horas)', C::input('duracion_horas', $texto('duracion_horas'), ['type' => 'number', 'step' => '0.5'])) ?>
    <?= C::campo('Personas', C::input('num_personas', $texto('num_personas'), ['type' => 'number'])) ?>
    <?= C::campo('Combustible inicio (galones)', C::input('gal_inicio', $aGal($d['combustible_inicio'] ?? null), ['type' => 'number', 'step' => '0.01', 'inputmode' => 'decimal', 'placeholder' => 'Lectura del tanque']) . '<span class="cap-hint" data-hint="ini" hidden></span>') ?>
    <?= C::campo('Combustible fin (galones)', C::input('gal_fin', $aGal($d['combustible_fin'] ?? null), ['type' => 'number', 'step' => '0.01', 'inputmode' => 'decimal', 'placeholder' => 'Lectura del tanque']) . '<span class="cap-hint" data-hint="fin" hidden></span>') ?>
    <?= C::campo('Consumo (litros) — se calcula solo',
        C::input('consumo_auto', '', ['type' => 'number', 'readonly' => true, 'hidden' => true, 'style' => 'background:var(--bg2);cursor:not-allowed', 'title' => 'Se calcula con las lecturas de combustible'])
        . C::input('litros', $editando ? $texto('litros') : '', ['type' => 'number', 'step' => '0.01', 'placeholder' => 'Captura inicio y fin arriba'])
        . '<span class="cap-hint">× $' . number_format($precio, 2) . '/L (precio del periodo) = <strong data-costo-combustible>$0.00</strong></span>') ?>
    <?= C::campo('Marinero', F::marineroSelect($ctx['marineros'], $d['marinero'] ?? null)) ?>
    <?= C::campo('Costo marinero', C::input('costo_marinero', $texto('costo_marinero', '800'), ['type' => 'number', 'step' => '0.01'])) ?>
    <?= C::campo('Costo consumibles', C::input('costo_consumibles', $editando ? $texto('costo_consumibles') : '', ['type' => 'number', 'step' => '0.01'])) ?>
    <?= C::campo('Comprobante consumibles', C::input('consumibles_comprobante', $texto('consumibles_comprobante'), ['placeholder' => 'ej. Chedraui SM-91363']), 'full') ?>
  </div>

  <div class="section-label" style="margin:18px 0 10px">Horómetros</div>
  <div class="grid">
    <?php foreach ($ctx['motores'] as $m): $h = $horos[(int) $m['id']] ?? null; ?>
    <div class="field" data-motor-id="<?= (int) $m['id'] ?>" data-etiqueta="<?= Formato::e($m['etiqueta']) ?>">
      <label><?= Formato::e($m['etiqueta']) ?></label>
      <div style="display:flex;gap:8px">
        <input type="number" step="0.01" placeholder="inicio" data-h="inicio" value="<?= Formato::e($h ? (string) $h['lectura_inicio'] : (string) $m['horometro_actual']) ?>">
        <input type="number" step="0.01" placeholder="fin" data-h="fin" value="<?= Formato::e($h ? (string) $h['lectura_fin'] : '') ?>">
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div style="margin-top:16px;display:flex;align-items:center;gap:14px;flex-wrap:wrap">
    <label style="display:flex;align-items:center;gap:6px;text-transform:none"><input type="checkbox" name="bandera" style="width:auto"<?= !empty($d['bandera']) ? ' checked' : '' ?>> Marcar como viaje anómalo</label>
    <span class="hint">Total viaje: <strong class="mono" data-total-viaje>$0.00</strong></span>
  </div>
  <?= F::pie($editando, 'Guardar viaje', 'btn gold', '', ';margin-top:14px') ?>
</form>
