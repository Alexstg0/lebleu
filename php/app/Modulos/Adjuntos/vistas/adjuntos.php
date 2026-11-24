<?php
use Lebleu\Compartido\Formato as F;
/** @var array $adjuntos lista de {id, nombre, generado} @var string $columna gasto_id|insumo_id|extraordinario_id|caja_id @var int $id */
?>
<div class="adj-wrap" data-adjuntos data-columna="<?= F::e($columna) ?>" data-registro="<?= (int) $id ?>">
  <?php foreach ($adjuntos as $a): ?>
  <span class="adj-chip <?= $a['generado'] ? 'gen' : '' ?>">
    <button class="adj-name" data-visor-pdf="/api/adjuntos/<?= (int) $a['id'] ?>" data-titulo="<?= F::e($a['nombre']) ?>" data-solo-documento title="Ver PDF">
      <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
      <?= $a['generado'] ? 'Recibo' : 'PDF' ?>
    </button>
    <button class="adj-del" data-borrar-adjunto="<?= (int) $a['id'] ?>" title="Eliminar" aria-label="Eliminar">×</button>
  </span>
  <?php endforeach; ?>
  <button class="adj-add" data-subir-adjunto>+ PDF/Foto</button>
  <input type="file" accept="application/pdf,image/jpeg,image/png" hidden>
  <span class="adj-err" hidden></span>
</div>
