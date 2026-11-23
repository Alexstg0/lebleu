<?php
// Importa el respaldo de la versión Next.js (db/datos_20260826.sql) a MySQL.
// Uso:  php scripts/importar_respaldo.php ruta/al/respaldo.sql
// Exige base vacía (sin periodos). Las contraseñas NO se migran: quedan por restablecer.
require __DIR__ . '/../app/arranque.php';

use Lebleu\Core\Db;
use Lebleu\Herramientas\ImportadorRespaldo;

$archivo = $argv[1] ?? '';
if (!is_file($archivo)) {
    fwrite(STDERR, "Uso: php scripts/importar_respaldo.php ruta/al/respaldo.sql\n");
    exit(1);
}
if (Db::fila('SELECT 1 FROM periodos LIMIT 1')) {
    fwrite(STDERR, "La base ya tiene datos. Para repetir la carga, vacíela primero (herramientas/vaciar_base).\n");
    exit(1);
}
$imp = new ImportadorRespaldo();
$imp->importar($archivo);
ksort($imp->conteo);
foreach ($imp->conteo as $tabla => $n) {
    printf("%-24s %5d\n", $tabla, $n);
}
echo "Respaldo importado. Siguiente paso: php scripts/restablecer_claves.php\n";
