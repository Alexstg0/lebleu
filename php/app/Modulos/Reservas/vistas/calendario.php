<?php
use Lebleu\Compartido\Formato as F;
/** @var int $anio @var int $mes @var ?array $ultimo @var array $reservas @var array $socios @var string $hoy */
$mesTxt = F::mesNombre($mes);
$primerDia = (int) date('w', mktime(0, 0, 0, $mes, 1, $anio));
$diasEnMes = (int) date('t', mktime(0, 0, 0, $mes, 1, $anio));
$celdas = array_merge(array_fill(0, $primerDia, null), $diasEnMes ? range(1, $diasEnMes) : []);
$porDia = [];
foreach ($reservas as $r) {
    $porDia[(int) substr($r['fecha'], 8, 2)][] = $r;
}
$fam = fn($r) => $r['socio_id'] === 1 ? 'fam-acosta' : ($r['socio_id'] === 2 ? 'fam-garcia' : 'fam-ext');
$color = fn($r) => $r['socio_id'] === 1 ? 'var(--ocean)' : ($r['socio_id'] === 2 ? 'var(--teal)' : 'var(--muted)');
$num = fn($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
$meta = function ($r) use ($num) {
    $partes = array_filter([
        $r['hora'] ? F::hora12($r['hora']) : null,
        $r['num_personas'] ? $r['num_personas'] . ' pers' : null,
        $r['duracion_horas'] !== null ? $num($r['duracion_horas']) . ' h' : null,
    ]);
    return $partes ? implode(' · ', $partes) : '—';
};
$flecha = fn($d) => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="' . $d . '"/></svg>';
$prevMes = $mes === 1 ? 12 : $mes - 1;
$prevAnio = $mes === 1 ? $anio - 1 : $anio;
$nextMes = $mes === 12 ? 1 : $mes + 1;
$nextAnio = $mes === 12 ? $anio + 1 : $anio;
$n = count($reservas);
$datos = ['anio' => $anio, 'mes' => $mes, 'hoy' => $hoy, 'reservas' => $reservas, 'socios' => $socios];
?>
<button class="pdf-btn" data-imprimir aria-label="Exportar a PDF" title="Exportar a PDF">
  <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M12 16l-4-4h3V4h2v8h3l-4 4z" fill="currentColor"/><path d="M20 18H4v2h16v-2z" fill="currentColor"/></svg>
  PDF
</button>
<div class="wrap cal-page">
  <div class="cal-print-head">
    <img src="/logo-lebleu.png" alt="Le Bleu">
    <div class="cal-print-info">
      <h2>Calendario de reservaciones — <?= F::e($mesTxt) ?> <?= $anio ?></h2>
      <div>Embarcación <?= F::e($ultimo['embarcacion'] ?? 'Le Bleu') ?><?= !empty($ultimo['razon_social']) ? ' · ' . F::e($ultimo['razon_social']) : '' ?></div>
    </div>
  </div>
  <div class="cal-toolbar">
    <div class="cal-title"><h1><?= F::e($mesTxt) ?></h1><span class="yr"><?= $anio ?></span></div>
    <div class="cal-nav">
      <span class="cal-count"><?= $n ?> reserva<?= $n === 1 ? '' : 's' ?></span>
      <a class="ico" href="/calendario?y=<?= $prevAnio ?>&amp;m=<?= $prevMes ?>" aria-label="Mes anterior"><?= $flecha('M15 18l-6-6 6-6') ?></a>
      <a href="/calendario?y=<?= (int) substr($hoy, 0, 4) ?>&amp;m=<?= (int) substr($hoy, 5, 2) ?>">Hoy</a>
      <a class="ico" href="/calendario?y=<?= $nextAnio ?>&amp;m=<?= $nextMes ?>" aria-label="Mes siguiente"><?= $flecha('M9 18l6-6-6-6') ?></a>
    </div>
  </div>

  <div class="cal-legend">
    <span><i style="background:var(--ocean)"></i> Familia Acosta</span>
    <span><i style="background:var(--teal)"></i> Familia García</span>
    <span><i style="background:var(--muted)"></i> Externo / renta</span>
  </div>

  <div class="cal-desktop">
    <div class="cal-card">
      <div class="cal-head"><?php foreach (['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'] as $d) echo "<div>$d</div>"; ?></div>
      <div class="cal-body">
        <?php foreach ($celdas as $i => $d):
            $col = $i % 7;
            $fecha = $d ? sprintf('%04d-%02d-%02d', $anio, $mes, $d) : '';
            $clases = ['cal-cell'];
            if (!$d) { $clases[] = 'empty'; } else {
                if ($col === 0 || $col === 6) $clases[] = 'wknd';
                if ($fecha === $hoy) $clases[] = 'today';
            } ?>
        <div class="<?= implode(' ', $clases) ?>">
          <?php if ($d): ?>
          <span class="cal-daynum"><?= $d ?></span>
          <button class="cal-add" data-nueva="<?= $fecha ?>" aria-label="Agregar reserva el <?= $d ?>"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg></button>
          <?php foreach ($porDia[$d] ?? [] as $r): $m = $meta($r); ?>
          <button class="cal-chip <?= $fam($r) ?> <?= $r['fecha'] < $hoy ? 'past' : '' ?>" data-reserva="<?= $r['id'] ?>">
            <span class="dot"></span>
            <span class="info">
              <span class="name"><?= F::e($r['cliente'] ?: ($r['socio_nombre'] ?: 'Reserva')) ?></span>
              <?php if ($m !== '—'): ?><span class="meta"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><?= F::e($m) ?></span><?php endif; ?>
              <?php if ($r['notas'] && !preg_match('/^s[áa]bado reservado/iu', $r['notas'])): ?><span class="cal-chip-notas"><?= F::e($r['notas']) ?></span><?php endif; ?>
            </span>
          </button>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="cal-mobile">
    <div class="ical-card">
      <div class="ical-grid ical-dow-row"><?php foreach (['D', 'L', 'M', 'M', 'J', 'V', 'S'] as $d) echo "<div class=\"ical-dow\">$d</div>"; ?></div>
      <div class="ical-grid">
        <?php foreach ($celdas as $d):
            if (!$d) { echo '<div class="ical-day empty"></div>'; continue; }
            $fecha = sprintf('%04d-%02d-%02d', $anio, $mes, $d); ?>
        <button class="ical-day <?= $fecha === $hoy ? 'today' : '' ?>" data-dia="<?= $d ?>">
          <span class="ical-num"><?= $d ?></span>
          <span class="ical-dots"><?php foreach (array_slice($porDia[$d] ?? [], 0, 3) as $r) echo '<i style="background:' . $color($r) . '"></i>'; ?></span>
        </button>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="ical-detail" data-detalle></div>
  </div>
</div>
<script type="application/json" id="datos-calendario"><?= json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_PRESERVE_ZERO_FRACTION) ?></script>
