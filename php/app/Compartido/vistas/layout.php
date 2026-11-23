<?php
use Lebleu\Compartido\Formato as F;
/** @var string $titulo @var string $contenido @var string[] $scripts */
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= F::e($titulo) ?></title>
<meta name="description" content="Control financiero y operativo de la embarcación Le Bleu">
<meta name="theme-color" content="#1a3a5c">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" href="/icon-192.png">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Le Bleu">
<link rel="stylesheet" href="/recursos/css/globals.css">
<link rel="stylesheet" href="/recursos/css/extras.css">
</head>
<body>
<?= $contenido ?>
<script src="/recursos/js/comun.js" defer></script>
<?php foreach ($scripts as $js): ?>
<script src="/recursos/js/<?= F::e($js) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
