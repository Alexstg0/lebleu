<?php
// Verificación previa del servidor (Windows/IIS o Linux): PHP, extensiones, php.ini, MySQL, esquema y archivos de la app.
// Correrla ANTES de abrir el sitio y después de cada cambio de PHP/MySQL.  Uso:  php scripts/verificar_servidor.php
// Salida: ok / AVISO (conviene corregir) / FALLA (no abrir el sitio). Código de salida 1 si hay alguna FALLA.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../app/arranque.php';

use Lebleu\Core\Config;
use Lebleu\Core\Db;

$fallas = $avisos = 0;
function ver(string $nivel, string $texto, string $detalle = ''): void
{
    global $fallas, $avisos;
    $etiqueta = ['ok' => 'ok     ', 'aviso' => 'AVISO  ', 'falla' => 'FALLA  '][$nivel];
    echo $etiqueta . $texto . ($detalle !== '' && $nivel !== 'ok' ? "  → $detalle" : '') . "\n";
    $fallas += $nivel === 'falla' ? 1 : 0;
    $avisos += $nivel === 'aviso' ? 1 : 0;
}
function bytes(string $v): int
{
    $n = (int) $v;
    return match (strtoupper(substr(trim($v), -1))) { 'G' => $n << 30, 'M' => $n << 20, 'K' => $n << 10, default => $n };
}
$ini = fn(string $k) => (string) ini_get($k);

echo "== PHP\n";
version_compare(PHP_VERSION, '8.3.0', '>=') ? ver('ok', 'PHP ' . PHP_VERSION) : ver('falla', 'PHP ' . PHP_VERSION, 'se requiere 8.3 o superior');
ver(PHP_ZTS ? 'aviso' : 'ok', 'Compilación ' . (PHP_ZTS ? 'TS' : 'NTS'), 'con IIS + FastCGI se recomienda la compilación NTS (Non Thread Safe)');
foreach (['pdo_mysql', 'mbstring', 'zlib', 'iconv', 'ctype', 'json', 'openssl'] as $e) {
    extension_loaded($e) ? ver('ok', "extensión $e") : ver('falla', "extensión $e", "habilitar extension=$e en php.ini");
}
extension_loaded('gd') ? ver('ok', 'extensión gd (fotos a PDF)') : ver('aviso', 'extensión gd', 'sin ella algunas fotos PNG (entrelazadas) no se convierten a PDF; habilitar extension=gd');
extension_loaded('sodium') || defined('PASSWORD_ARGON2ID') ? ver('ok', 'hash de contraseñas disponible') : ver('aviso', 'Argon2id no disponible', 'se usará bcrypt, que también es seguro');

echo "\n== php.ini\n";
bytes($ini('memory_limit')) >= (256 << 20) || $ini('memory_limit') === '-1' ? ver('ok', 'memory_limit ' . $ini('memory_limit')) : ver('aviso', 'memory_limit ' . $ini('memory_limit'), 'los reportes unen muchos PDF: usar 256M o más');
bytes($ini('post_max_size')) >= (8 << 20) ? ver('ok', 'post_max_size ' . $ini('post_max_size')) : ver('falla', 'post_max_size ' . $ini('post_max_size'), 'los respaldos se envían en base64 (hasta ~4.5 MB): usar 8M o más');
bytes($ini('max_execution_time')) === 0 || (int) $ini('max_execution_time') >= 60 ? ver('ok', 'max_execution_time ' . $ini('max_execution_time')) : ver('aviso', 'max_execution_time ' . $ini('max_execution_time'), 'el reporte completo puede tardar: usar 60 o más');
in_array(strtolower($ini('display_errors')), ['0', '', 'off', 'false'], true) ? ver('ok', 'display_errors apagado') : ver('falla', 'display_errors encendido', 'en producción debe ser Off (los errores no se muestran al usuario)');
$ini('error_log') !== '' ? ver('ok', 'error_log ' . $ini('error_log')) : ver('aviso', 'error_log sin definir', 'definir una ruta para conservar los errores');
ini_get('opcache.enable') ? ver('ok', 'OPcache activo') : ver('aviso', 'OPcache apagado', 'activarlo acelera la app (zend_extension=opcache y opcache.enable=1)');
$tmp = sys_get_temp_dir();
is_writable($tmp) ? ver('ok', "carpeta temporal escribible ($tmp)") : ver('falla', "carpeta temporal sin permiso de escritura ($tmp)", 'la identidad del grupo de aplicaciones de IIS debe poder escribir ahí');
ver(date_default_timezone_get() === (string) Config::obtener('zona_horaria', 'America/Mazatlan') ? 'ok' : 'aviso', 'zona horaria ' . date_default_timezone_get());

echo "\n== Archivos de la aplicación\n";
$raiz = dirname(__DIR__);
foreach (['config/config.php' => 'copiar config/config.ejemplo.php y completarlo', 'vendor/fpdf/fpdf.php' => 'falta FPDF', 'vendor/fpdf/font/helvetica.json' => 'faltan las fuentes de FPDF',
    'app/Compartido/Pdf/kerning.php' => 'falta la tabla de kerning', 'app/Compartido/Pdf/logos/lebleu-hi.png' => 'faltan los logos de los PDF',
    'public/index.php' => 'falta el punto de entrada', 'public/web.config' => 'falta web.config de IIS'] as $a => $ayuda) {
    is_file("$raiz/$a") ? ver('ok', $a) : ver('falla', $a, $ayuda);
}
$mt = fileperms("$raiz/config/config.php");
if (DIRECTORY_SEPARATOR === '/' && is_file("$raiz/config/config.php") && ($mt & 0o004)) {
    ver('aviso', 'config/config.php es legible por todos', 'chmod 640: contiene la contraseña de la base');
}
if (Config::obtener('db.clave') === 'cambiar' || Config::obtener('db.clave') === null) {
    ver('falla', 'config.php conserva la contraseña de ejemplo', 'poner la contraseña real del usuario de MySQL');
}

echo "\n== MySQL\n";
try {
    $v = Db::fila('SELECT VERSION() v, @@max_allowed_packet p, @@character_set_database cs, @@collation_database co, @@lower_case_table_names l, @@innodb_strict_mode s');
    $esMaria = stripos($v['v'], 'mariadb') !== false;
    $num = (string) preg_replace('/^(\d+\.\d+\.\d+).*/', '$1', $v['v']);
    if ($esMaria) {
        ver('aviso', "MariaDB {$v['v']}", 'el destino definido es MySQL 8.0.16+; MariaDB solo se usó para desarrollar');
    } elseif (version_compare($num, '8.0.16', '>=')) {
        ver('ok', "MySQL {$v['v']}");
    } else {
        ver('falla', "MySQL {$v['v']}", 'se requiere 8.0.16 o superior (restricciones CHECK)');
    }
    $v['p'] >= (16 << 20) ? ver('ok', 'max_allowed_packet ' . round($v['p'] / 1048576) . ' MB') : ver('falla', 'max_allowed_packet ' . round($v['p'] / 1048576) . ' MB', 'subir a 16M o más en my.ini ([mysqld]) y reiniciar MySQL: los PDF se guardan en base64');
    str_starts_with($v['cs'], 'utf8mb4') ? ver('ok', "juego de caracteres {$v['cs']} / {$v['co']}") : ver('falla', "juego de caracteres {$v['cs']}", 'crear la base con CHARACTER SET utf8mb4');
    $tablas = (int) Db::fila('SELECT COUNT(*) n FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = "BASE TABLE"')['n'];
    $tablas >= 20 ? ver('ok', "$tablas tablas") : ver('falla', "$tablas tablas", 'correr php scripts/migrar.php');
    $vistas = (int) Db::fila('SELECT COUNT(*) n FROM information_schema.views WHERE table_schema = DATABASE()')['n'];
    $vistas >= 3 ? ver('ok', "$vistas vistas") : ver('falla', "$vistas vistas", 'correr php scripts/migrar.php');
    $m = Db::fila('SELECT COUNT(*) n FROM migracion')['n'] ?? 0;
    ver('ok', "migraciones aplicadas: $m");
    Db::fila('SELECT 1 FROM periodos LIMIT 1') ? ver('ok', 'hay periodos cargados') : ver('aviso', 'la base no tiene periodos', 'importar el respaldo (php scripts/importar_respaldo.php) o crear el primero');
    (int) Db::fila("SELECT COUNT(*) n FROM usuarios WHERE rol = 'admin' AND activo = 1")['n'] > 0 ? ver('ok', 'hay un administrador activo') : ver('falla', 'no hay administrador activo', 'php scripts/crear_admin.php');
} catch (Throwable $e) {
    ver('falla', 'conexión a MySQL', $e->getMessage());
}

echo "\n" . ($fallas ? "$fallas FALLA(S): no abrir el sitio hasta corregirlas." : ($avisos ? "Sin fallas; $avisos aviso(s)." : 'Servidor listo.')) . "\n";
exit($fallas ? 1 : 0);
