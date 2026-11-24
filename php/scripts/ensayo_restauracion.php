<?php
// Ensayo de restauración: respalda la base, la restaura en una base TEMPORAL y compara filas de cada tabla y el resultado de las vistas.
// Un respaldo que nunca se restauró no está probado: correrlo una vez al mes y después de cada cambio de servidor.
// Uso:  php scripts/ensayo_restauracion.php --admin=C:\lebleu_respaldos\admin.cnf [--mysqldump=ruta] [--mysql=ruta]
//   El respaldo se hace con el usuario de la app (config/config.php). Crear, restaurar y borrar la base temporal exige un usuario con
//   permisos de administrador: va en un archivo de opciones ([client] user=... password=...), nunca en la línea de comandos.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../app/arranque.php';

use Lebleu\Core\Config;
use Lebleu\Core\Db;

$opc = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--(\w+)=(.*)$/', $a, $m)) {
        $opc[$m[1]] = $m[2];
    }
}
$dump = $opc['mysqldump'] ?? 'mysqldump';
$cli = $opc['mysql'] ?? 'mysql';
$admin = $opc['admin'] ?? '';
if (!is_file($admin)) {
    fwrite(STDERR, "Falta --admin=archivo.cnf (usuario con permiso para crear bases). Ver el encabezado de este script.\n");
    exit(2);
}
$origen = (string) Config::obtener('db.nombre', 'lebleu');
$temporal = $origen . '_ensayo_' . date('His');
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lebleu_ensayo_' . getmypid();
@mkdir($tmp, 0700, true);
$cnf = "$tmp/cliente.cnf";
file_put_contents($cnf, sprintf("[client]\nuser=%s\npassword=\"%s\"\nhost=%s\nport=%d\ndefault-character-set=utf8mb4\n",
    Config::obtener('db.usuario'), addcslashes((string) Config::obtener('db.clave'), '"\\'), Config::obtener('db.host', '127.0.0.1'), (int) Config::obtener('db.puerto', 3306)));
$sql = "$tmp/respaldo.sql";
$fallos = 0;
$ok = function (string $n, bool $c, string $x = '') use (&$fallos) {
    echo ($c ? 'ok      ' : 'FALLA   ') . $n . (!$c && $x !== '' ? "  ($x)" : '') . "\n";
    $fallos += $c ? 0 : 1;
};
$consulta = function (string $sentencia) use ($cli, $admin): string {
    exec(sprintf('%s --defaults-extra-file=%s -N -B -e %s 2>&1', escapeshellarg($cli), escapeshellarg($admin), escapeshellarg($sentencia)), $salida, $codigo);
    if ($codigo !== 0) {
        throw new RuntimeException(implode("\n", $salida));
    }
    return implode("\n", $salida);
};
$ejecutar = function (string $cmd) {
    exec($cmd . ' 2>&1', $salida, $codigo);
    return [$codigo, implode("\n", $salida)];
};
$limpiar = function () use ($tmp, $sql, $cnf, $temporal, $consulta) {
    try {
        $consulta("DROP DATABASE IF EXISTS `$temporal`");
    } catch (Throwable) {
    }
    @unlink($sql);
    @unlink($cnf);
    @rmdir($tmp);
};

[$c, $m] = $ejecutar(sprintf('%s --defaults-extra-file=%s --single-transaction --routines --triggers --default-character-set=utf8mb4 --result-file=%s %s',
    escapeshellarg($dump), escapeshellarg($cnf), escapeshellarg($sql), escapeshellarg($origen)));
$ok('respaldo con mysqldump', $c === 0 && is_file($sql) && filesize($sql) > 1000, $m);
if ($c !== 0) {
    $limpiar();
    exit(1);
}
printf("        (%.1f MB)\n", filesize($sql) / 1048576);
try {
    $consulta("CREATE DATABASE `$temporal` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $ok("base temporal $temporal", true);
} catch (Throwable $e) {
    $ok("base temporal $temporal", false, $e->getMessage());
}
[$c, $m] = $ejecutar(sprintf('%s --defaults-extra-file=%s %s < %s', escapeshellarg($cli), escapeshellarg($admin), escapeshellarg($temporal), escapeshellarg($sql)));
$ok('restauración del respaldo', $c === 0, $m);

if ($c === 0) {
    $tablas = array_filter(explode("\n", $consulta("SELECT table_name FROM information_schema.tables WHERE table_schema = '$origen' AND table_type = 'BASE TABLE' ORDER BY 1")));
    $distintas = [];
    foreach ($tablas as $t) {
        $a = $consulta("SELECT COUNT(*) FROM `$origen`.`$t`");
        $b = $consulta("SELECT COUNT(*) FROM `$temporal`.`$t`");
        if ($a !== $b) {
            $distintas[] = "$t ($a ≠ $b)";
        }
    }
    $ok('las ' . count($tablas) . ' tablas tienen las mismas filas', !$distintas, implode(', ', $distintas));
    $malas = [];
    foreach (array_filter(explode("\n", $consulta("SELECT table_name FROM information_schema.views WHERE table_schema = '$origen'"))) as $v) {
        if (md5($consulta("SELECT * FROM `$origen`.`$v` ORDER BY 1, 2")) !== md5($consulta("SELECT * FROM `$temporal`.`$v` ORDER BY 1, 2"))) {
            $malas[] = $v;
        }
    }
    $ok('las vistas dan el mismo resultado', !$malas, implode(', ', $malas));
    $a = $consulta("SELECT COALESCE(SUM(LENGTH(datos)), 0) FROM `$origen`.adjuntos");
    $b = $consulta("SELECT COALESCE(SUM(LENGTH(datos)), 0) FROM `$temporal`.adjuntos");
    $ok('los PDF adjuntos se restauran completos (bytes iguales)', $a === $b, "$a ≠ $b");
}
$limpiar();
echo $fallos ? "\nEL RESPALDO NO SE PUDO RESTAURAR BIEN.\n" : "\nRESTAURACIÓN OK: el respaldo de hoy sirve.\n";
exit($fallos ? 1 : 0);
