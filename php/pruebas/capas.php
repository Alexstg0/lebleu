<?php
// Regla de capas: solo los Repositorios (y Core/Herramientas) escriben SQL.
// Controllers, Servicios, vistas y Compartido no pueden llamar a Db:: ni traer sentencias SQL.
// (Db::transaccion y Db::simular sí: no son SQL.)  Uso:  php pruebas/capas.php
$raiz = dirname(__DIR__) . '/app';
$fallos = 0;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $ruta = str_replace('\\', '/', $f->getPathname());
    if ($f->getExtension() !== 'php' || str_contains($ruta, '/Core/') || str_contains($ruta, '/Herramientas/') || str_ends_with($ruta, 'Repositorio.php')) {
        continue;
    }
    $codigo = file_get_contents($ruta);
    $hallazgos = [];
    if (preg_match('/\bDb::(?!transaccion\b|simular\b)\w+/', $codigo, $m)) {
        $hallazgos[] = $m[0];
    }
    if (preg_match('/[\'"`]\s*(SELECT\s.+\sFROM\s|INSERT\s+INTO\s|UPDATE\s+\w+\s+SET\s|DELETE\s+FROM\s)/i', $codigo, $m)) {
        $hallazgos[] = 'SQL: ' . substr(trim($m[0]), 0, 40);
    }
    foreach ($hallazgos as $h) {
        echo 'FALLA  ' . substr($ruta, strlen($raiz) - 3) . "  usa $h fuera de un Repositorio\n";
        $fallos++;
    }
}
echo $fallos ? "\n$fallos violación(es) de la regla de capas.\n" : "Capas OK: el SQL vive solo en Repositorios.\n";
exit($fallos ? 1 : 0);
