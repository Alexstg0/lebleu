<?php
// Aplica db/migraciones/*.sql en orden y anota cada una en la tabla `migracion`.
// Uso:  php scripts/migrar.php        (re-ejecutable: no repite las ya aplicadas)
require __DIR__ . '/../app/arranque.php';

use Lebleu\Core\Db;

$dir = __DIR__ . '/../db/migraciones';
Db::ejecutar('CREATE TABLE IF NOT EXISTS migracion (
  nombre VARCHAR(120) NOT NULL PRIMARY KEY,
  aplicada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');

$hechas = array_column(Db::filas('SELECT nombre FROM migracion'), 'nombre');
$archivos = glob($dir . '/*.sql');
sort($archivos);
$nuevas = 0;

foreach ($archivos as $ruta) {
    $nombre = basename($ruta);
    if (in_array($nombre, $hechas, true)) {
        echo "= $nombre (ya aplicada)\n";
        continue;
    }
    $limpio = preg_replace('/^\s*--.*$/m', '', file_get_contents($ruta));
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $limpio) as $sentencia) {
        if (trim($sentencia) !== '') {
            Db::ejecutar($sentencia);
        }
    }
    Db::ejecutar('INSERT INTO migracion (nombre) VALUES (?)', [$nombre]);
    echo "+ $nombre aplicada\n";
    $nuevas++;
}
echo $nuevas ? "Listo: $nuevas migración(es).\n" : "La base ya está al día.\n";
