<?php
use Lebleu\Compartido\Campos as C;
use Lebleu\Compartido\Formato;
use Lebleu\Modulos\Captura\Formularios as F;
/** @var ?array $d @var array $ctx @var bool $editando */
$clientes = $ctx['clientes'];
$quien = [];
foreach ($clientes as $c) {
    $quien['cli:' . $c['id']] = $c['nombre'];
}
$quien['renta'] = 'Renta (cliente externo)';
$mB = $ctx['motores'][0] ?? null;
$mE = $ctx['motores'][1] ?? null;
$etB = $mB['etiqueta'] ?? 'Motor B';
$etE = $mE['etiqueta'] ?? 'Motor E';
$inputNum = fn(string $n, string $v, array $a = []) => C::input($n, $v, ['type' => 'number', 'inputmode' => 'decimal', 'step' => '0.01'] + $a);
?>
<form class="form-card cap-form" <?= F::atributosForm('/api/viajes', false, 'rentaNombre,marinero,num_personas,duracion_horas,galIni,galFin') ?> data-calculo="viaje-capitan"
      data-precio="<?= Formato::e((string) (float) $ctx['precioLitro']) ?>"
      data-clientes-socio="<?= Formato::e(json_encode(array_column($clientes, 'socio_id', 'id'))) ?>"
      data-motor-b="<?= (int) ($mB['id'] ?? 0) ?>" data-motor-e="<?= (int) ($mE['id'] ?? 0) ?>"
      data-etiqueta-b="<?= Formato::e($etB) ?>" data-etiqueta-e="<?= Formato::e($etE) ?>">
  <input type="hidden" name="periodo_id" value="<?= (int) $ctx['periodoId'] ?>">
  <h2>Registrar viaje</h2>

  <div class="cap-section">Datos del viaje</div>
  <div class="grid">
    <?= C::campo('Fecha', C::input('fecha', date('Y-m-d'), ['type' => 'date', 'required' => true])) ?>
    <?= C::campo('Familia / cliente', C::select('quien', $quien, array_key_first($quien))) ?>
    <?= C::campo('Nombre del cliente (renta)', C::input('rentaNombre', '', ['placeholder' => 'Ej. Sra. Gardenia']), 'full', 'data-solo-renta hidden') ?>
    <?= C::campo('No. de personas', C::input('num_personas', '', ['type' => 'number', 'inputmode' => 'numeric', 'placeholder' => 'Ej. 8'])) ?>
    <?= C::campo('Duración del viaje (horas)', C::input('duracion_horas', '', ['type' => 'number', 'inputmode' => 'decimal', 'step' => '0.5', 'placeholder' => 'Ej. 4'])) ?>
    <?= C::campo('Marinero', F::marineroSelect($ctx['marineros'], null), 'full') ?>
  </div>

  <div class="cap-section">Horómetros</div>
  <div class="grid">
    <?= C::campo($etB . ' — inicial', $inputNum('bIni', (string) ($mB['horometro_actual'] ?? ''))) ?>
    <?= C::campo($etB . ' — final', $inputNum('bFin', '') . '<span class="cap-hint" data-horas="b" hidden></span>') ?>
    <?= C::campo($etE . ' — inicial', $inputNum('eIni', (string) ($mE['horometro_actual'] ?? ''))) ?>
    <?= C::campo($etE . ' — final', $inputNum('eFin', '') . '<span class="cap-hint" data-horas="e" hidden></span>') ?>
  </div>

  <div class="cap-section">Combustible <span class="cap-section-note">en galones — se convierte a litros</span></div>
  <div class="grid">
    <?= C::campo('Combustible inicio (galones)', $inputNum('galIni', '', ['placeholder' => 'Lectura del tanque']) . '<span class="cap-hint" data-hint="ini" hidden></span>') ?>
    <?= C::campo('Combustible fin (galones)', $inputNum('galFin', '', ['placeholder' => 'Lectura del tanque']) . '<span class="cap-hint" data-hint="fin" hidden></span>') ?>
  </div>

  <div class="cap-resumen"><span>Consumo del viaje</span><strong class="mono" data-consumo>0 L</strong></div>

  <?= F::pie(false, 'Guardar viaje', 'btn cap-btn', '', ';margin-top:18px') ?>
</form>
