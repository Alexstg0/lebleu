<?php
use Lebleu\Compartido\Formato as F;
use Lebleu\Compartido\Iconos;
/** @var array $periodos @var int $periodoId @var string $activo @var string $rol @var ?string $nombre */
$principales = [];
if ($rol === 'admin' || $rol === 'socio') $principales[] = ["/?periodo=$periodoId", 'Estado de cuenta', 'dashboard'];
$principales[] = ['/calendario', 'Calendario', 'calendario'];
if ($rol === 'admin' || $rol === 'capitan') {
    $principales[] = ['/capturar', $rol === 'capitan' ? 'Registrar viaje' : 'Capturar', 'capturar'];
    $principales[] = ['/bitacora', 'Bitácora', 'bitacora'];
    $principales[] = ['/caja-chica', 'Caja chica', 'caja'];
}
$grupos = [];
if ($rol === 'admin' || $rol === 'socio') {
    $grupos['Reportes'] = [['/reportes', 'Reportes', 'reportes'], ['/analisis', 'Análisis', 'analisis']];
}
if ($rol === 'admin') {
    $grupos['Administración'] = [
        ["/movimientos?periodo=$periodoId", 'Movimientos', 'movimientos'], ['/periodos', 'Periodos', 'periodos'],
        ['/usuarios', 'Usuarios', 'usuarios'], ['/auditoria', 'Auditoría', 'auditoria'],
    ];
}
$mostrarPeriodo = ($rol === 'admin' || $rol === 'socio') && count($periodos) > 0;
$selector = function (string $cls = '') use ($periodos, $periodoId): string {
    $h = '<select' . ($cls ? ' class="' . $cls . '"' : '') . ' data-periodo-select aria-label="Periodo">';
    foreach ($periodos as $p) {
        $h .= '<option value="' . (int) $p['id'] . '"' . ((int) $p['id'] === $periodoId ? ' selected' : '') . '>'
            . F::e(F::mesNombre((int) $p['mes']) . ' ' . $p['anio']) . '</option>';
    }
    return $h . '</select>';
};
$enlace = fn(array $it, string $extra = '') => '<a href="' . F::e($it[0]) . '" class="' . ($activo === $it[2] ? 'active' : '') . '"' . $extra . '>' . F::e($it[1]) . '</a>';
?>
<header class="topbar">
  <div class="topbar-left">
    <button class="topbar-burger" data-abrir-menu aria-label="Abrir menú">
      <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
    </button>
    <div class="brand">Le Bleu<small>Control de embarcación</small></div>
  </div>

  <nav class="topbar-nav" data-nav>
    <?php if ($mostrarPeriodo) echo $selector(); ?>
    <?php foreach ($principales as $it) echo $enlace($it); ?>
    <?php foreach ($grupos as $nombreGrupo => $items):
        $grupoActivo = in_array($activo, array_column($items, 2), true); ?>
    <div class="nav-group">
      <button class="nav-group-btn <?= $grupoActivo ? 'active' : '' ?>" data-menu aria-expanded="false"><?= F::e($nombreGrupo) ?> <?= Iconos::chevron() ?></button>
      <div class="nav-menu" hidden>
        <?php foreach ($items as $it) echo $enlace($it); ?>
      </div>
    </div>
    <?php endforeach; ?>
    <div class="nav-group">
      <button class="nav-group-btn nav-user" data-menu aria-expanded="false">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>
        <?= F::e($nombre ? explode(' ', $nombre)[0] : 'Cuenta') ?> <?= Iconos::chevron() ?>
      </button>
      <div class="nav-menu" style="right:0;left:auto" hidden>
        <?php if ($nombre): ?><div class="nav-menu-nombre"><?= F::e($nombre) ?></div><?php endif; ?>
        <a href="/api/manual" target="_blank" rel="noopener">Manual de uso</a>
        <button data-salir>Cerrar sesión</button>
      </div>
    </div>
  </nav>
</header>

<div class="drawer-overlay" data-cerrar-menu></div>
<aside class="drawer" data-drawer aria-hidden="true">
  <div class="drawer-head">
    <div class="brand">Le Bleu<small>Control de embarcación</small></div>
    <button class="drawer-close" data-cerrar-menu aria-label="Cerrar menú">
      <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
    </button>
  </div>
  <?php if ($mostrarPeriodo): ?>
  <div class="drawer-periodo"><label>Periodo</label><?= $selector('drawer-select') ?></div>
  <?php endif; ?>
  <nav class="drawer-nav">
    <?php foreach ($principales as $it) echo $enlace($it, ' data-cerrar-menu'); ?>
    <?php foreach ($grupos as $nombreGrupo => $items): ?>
    <div>
      <div class="drawer-seccion"><?= F::e($nombreGrupo) ?></div>
      <?php foreach ($items as $it) echo $enlace($it, ' data-cerrar-menu'); ?>
    </div>
    <?php endforeach; ?>
  </nav>
  <a class="drawer-manual" href="/api/manual" target="_blank" rel="noopener" data-cerrar-menu>Manual de uso</a>
  <button class="drawer-salir" data-salir><?= F::e($nombre ? "Salir · $nombre" : 'Salir') ?></button>
</aside>
