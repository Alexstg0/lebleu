<?php
use Lebleu\Compartido\Campos as C;
use Lebleu\Compartido\Formato;
use Lebleu\Modulos\Captura\Formularios as F;
/** @var ?array $d @var array $ctx @var bool $editando */
$moneda = $d['moneda'] ?? 'MXN';
$tc = isset($d['tipo_cambio']) ? (string) $d['tipo_cambio'] : (string) (float) $ctx['tipoCambio'];
$viajes = $ctx['viajes'];
$opcionesViaje = ['' => '— Selecciona un viaje —'];
foreach ($viajes as $v) {
    $opcionesViaje[$v['id']] = Formato::fechaCorta($v['fecha']) . ' · ' . $v['cliente'];
}
$viajeInicial = (string) ($d['viaje_id'] ?? ($viajes[0]['id'] ?? ''));
?>
<form class="form-card" <?= F::atributosForm('/api/insumos', $editando, 'proveedor,concepto,monto_original,comprobante_folio') ?> data-calculo="insumo">
  <?= F::ocultos($d, $ctx['periodoId']) ?>
  <?php if (!$editando): ?><h2>Nuevos insumos del viaje</h2><?php endif; ?>
  <div class="msg-err" style="margin-bottom:12px" data-sin-viajes<?= $viajes ? ' hidden' : '' ?>>No hay viajes en este periodo. Registra primero el viaje para poder asignarle insumos.</div>
  <div class="grid">
    <?= C::campo('Fecha', C::input('fecha', $d['fecha'] ?? date('Y-m-d'), ['type' => 'date', 'required' => true])) ?>
    <?= C::campo('Viaje', C::select('viaje_id', $opcionesViaje, $viajeInicial, ['data-viajes' => true])) ?>
    <?= C::campo('Categoría', C::select('categoria', ['alimentos' => 'Alimentos', 'bebidas' => 'Bebidas', 'hielo' => 'Hielo', 'limpieza' => 'Limpieza', 'otro' => 'Otro'], $d['categoria'] ?? 'alimentos')) ?>
    <?= C::campo('Proveedor', C::input('proveedor', $d['proveedor'] ?? '', ['placeholder' => 'ej. Chedraui'])) ?>
    <?= C::campo('Concepto', C::input('concepto', $d['concepto'] ?? '', ['placeholder' => 'ej. Despensa, hielo, agua'])) ?>
    <?= C::campo('Moneda', C::select('moneda', ['MXN' => 'MXN', 'USD' => 'USD'], $moneda, ['data-moneda' => true])) ?>
    <div class="field"><label data-etiqueta-monto><?= $moneda === 'USD' ? 'Monto (USD)' : 'Monto (MXN)' ?></label><?= C::input('monto_original', isset($d['monto_original']) ? (string) $d['monto_original'] : '', ['type' => 'number', 'step' => '0.01', 'required' => true]) ?></div>
    <?= C::campo('Tipo de cambio', C::input('tipo_cambio', $tc, ['type' => 'number', 'step' => '0.0001']), '', 'data-solo-usd' . ($moneda === 'USD' ? '' : ' hidden')) ?>
    <?= C::campo('Comprobante / folio', C::input('comprobante_folio', $d['comprobante_folio'] ?? '', ['placeholder' => 'Factura, ticket…'])) ?>
  </div>
  <?= str_replace('data-boton', 'data-boton' . ($viajes ? '' : ' disabled'), F::pie($editando, 'Guardar insumos')) ?>
</form>
