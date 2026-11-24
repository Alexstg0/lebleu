<?php
// Inventario de paridad: toda página (app/**/page.tsx) y todo método de API (app/api/**/route.ts) del sistema original
// debe existir en la tabla de rutas de PHP. Las únicas excepciones son las documentadas abajo.
// Uso: php pruebas/rutas_paridad.php   (desde php/; el original está en la raíz del repositorio)
require __DIR__ . '/../app/arranque.php';

use Lebleu\Core\Ruteador;

$raiz = dirname(__DIR__, 2) . '/app';
if (!is_dir($raiz . '/api')) {
    echo "No se encontró el código original en $raiz\n";
    exit(2);
}

// Excepciones deliberadas (ver docs/MIGRACION-PHP.md).
$excepciones = [
    'GET /api/logout' => 'Resto de depuración del original (responde "GET logout test"); no hace nada.',
    'GET /api/cron/recurrentes' => 'Vercel Cron: en el servidor propio lo reemplaza scripts/cron_recurrentes.php con el Programador de tareas.',
];

$original = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS));
foreach ($it as $archivo) {
    $rel = str_replace('\\', '/', substr($archivo->getPathname(), strlen($raiz)));
    if (basename($rel) === 'page.tsx') {
        $ruta = preg_replace('#/page\.tsx$#', '', $rel) ?: '/';
        $original[] = ['GET', $ruta === '' ? '/' : $ruta];
    } elseif (basename($rel) === 'route.ts') {
        $ruta = preg_replace('#/route\.ts$#', '', $rel);
        $fuente = (string) file_get_contents($archivo->getPathname());
        preg_match_all('/export\s+async\s+function\s+(GET|POST|PATCH|PUT|DELETE)\b/', $fuente, $m);
        foreach ($m[1] as $metodo) {
            $original[] = [$metodo, preg_replace('#\[(\w+)\]#', '{$1}', $ruta)];
        }
    }
}

$ruteador = new Ruteador();
(require __DIR__ . '/../app/rutas.php')($ruteador);
$php = array_map(fn($r) => $r[0] . ' ' . $r[1], $ruteador->rutas());

$faltan = $cubiertas = 0;
sort($original);
foreach ($original as [$metodo, $ruta]) {
    $clave = "$metodo $ruta";
    if (in_array($clave, $php, true)) {
        $cubiertas++;
        continue;
    }
    if (isset($excepciones[$clave])) {
        echo "excepción  $clave — {$excepciones[$clave]}\n";
        continue;
    }
    echo "FALTA      $clave\n";
    $faltan++;
}
$sobran = array_values(array_diff($php, array_map(fn($o) => "$o[0] $o[1]", $original)));
foreach ($sobran as $s) {
    echo "extra en PHP  $s\n";
}
printf("\nOriginal: %d rutas · cubiertas: %d · excepciones documentadas: %d · faltan: %d · extra en PHP: %d\n",
    count($original), $cubiertas, count($original) - $cubiertas - $faltan, $faltan, count($sobran));
exit($faltan ? 1 : 0);
