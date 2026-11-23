<?php
// Regla anti-monolito: ningún archivo PHP de app/, public/ o scripts/ pasa de 400 líneas (aviso: 300).
// Uso:  php pruebas/tamano_de_archivos.php   (sale con código 1 si alguno falla)
const AVISO = 300;
const TOPE = 400;

$raiz = dirname(__DIR__);
$fallos = 0;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $ruta = str_replace('\\', '/', $f->getPathname());
    if ($f->getExtension() !== 'php' || str_contains($ruta, '/vendor/') || str_contains($ruta, '/config/')) {
        continue;
    }
    $lineas = count(file($ruta));
    $rel = substr($ruta, strlen($raiz) + 1);
    if ($lineas > TOPE) {
        echo "FALLA  $rel: $lineas líneas (tope " . TOPE . ")\n";
        $fallos++;
    } elseif ($lineas > AVISO) {
        echo "aviso  $rel: $lineas líneas (conviene dividirlo)\n";
    }
}
echo $fallos ? "\n$fallos archivo(s) pasan del tope.\n" : "Tamaños de archivo OK.\n";
exit($fallos ? 1 : 0);
