<?php
// Prueba de humo contra un sitio YA publicado (IIS o servidor de desarrollo). No cambia datos: solo abre y cierra sesión.
// Uso:  php scripts/prueba_humo.php https://app.lebleu.mx correo@admin clave [--http=http://app.lebleu.mx] [--capitan=correo:clave]
// Comprueba: salud, archivos estáticos y su tipo MIME, cabeceras de seguridad, cookie de sesión, cada pantalla y cada PDF por rol,
// que sin sesión no se entra a nada y que cerrar sesión invalida la cookie. Con --http verifica la redirección a HTTPS.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$base = rtrim($argv[1] ?? '', '/');
if ($base === '' || !isset($argv[3])) {
    fwrite(STDERR, "Uso: php scripts/prueba_humo.php URL_BASE correo clave [--http=URL_HTTP] [--capitan=correo:clave]\n");
    exit(2);
}
[$correo, $clave] = [$argv[2], $argv[3]];
$opc = [];
foreach (array_slice($argv, 4) as $a) {
    if (preg_match('/^--(\w+)=(.*)$/', $a, $m)) {
        $opc[$m[1]] = $m[2];
    }
}
$esHttps = str_starts_with($base, 'https://');
$fallos = 0;
function afirmar(string $n, bool $c, string $extra = ''): void
{
    global $fallos;
    echo ($c ? 'ok      ' : 'FALLA   ') . $n . (!$c && $extra !== '' ? "  ($extra)" : '') . "\n";
    $fallos += $c ? 0 : 1;
}

/** @return array{0: int, 1: array<string,string>, 2: string, 3: string[]} estado, cabeceras (minúsculas), cuerpo, cookies Set-Cookie */
function pedir(string $url, string $metodo = 'GET', ?array $json = null, string $cookie = ''): array
{
    $cab = "Accept: */*\r\n" . ($cookie !== '' ? "Cookie: $cookie\r\n" : '') . ($json !== null ? "Content-Type: application/json\r\n" : '');
    $ctx = stream_context_create(['http' => ['method' => $metodo, 'header' => $cab, 'content' => $json !== null ? json_encode($json) : '',
        'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 90]]);
    $cuerpo = (string) @file_get_contents($url, false, $ctx);
    $estado = 0;
    $h = [];
    $cookies = [];
    foreach ($http_response_header ?? [] as $l) {
        if (preg_match('#^HTTP/\S+ (\d+)#', $l, $m)) {
            $estado = (int) $m[1];
        } elseif (str_contains($l, ':')) {
            [$k, $v] = explode(':', $l, 2);
            $h[strtolower(trim($k))] = trim($v);
            if (strtolower(trim($k)) === 'set-cookie') {
                $cookies[] = trim($v);
            }
        }
    }
    return [$estado, $h, $cuerpo, $cookies];
}
function iniciar(string $base, string $correo, string $clave): array
{
    [$e, , $c, $cookies] = pedir("$base/api/login", 'POST', ['email' => $correo, 'password' => $clave]);
    $sid = '';
    foreach ($cookies as $ck) {
        if (str_starts_with($ck, 'sid=')) {
            $sid = explode(';', $ck)[0];
        }
    }
    return [$e, json_decode($c, true), $sid, $cookies];
}

echo "== Sitio y archivos estáticos\n";
[$e, , $c] = pedir("$base/api/salud");
afirmar('/api/salud responde y la base está conectada', $e === 200 && (json_decode($c, true)['bd'] ?? false) === true, "estado $e");
[$e, $h, $c] = pedir("$base/login");
afirmar('/login abre y es HTML en UTF-8', $e === 200 && str_contains($h['content-type'] ?? '', 'text/html') && str_contains($c, 'Le Bleu'));
afirmar('cabeceras de seguridad presentes', ($h['x-frame-options'] ?? '') === 'SAMEORIGIN' && ($h['x-content-type-options'] ?? '') === 'nosniff' && isset($h['referrer-policy']) && isset($h['permissions-policy']));
afirmar('no se anuncia la versión de PHP/IIS', !isset($h['x-powered-by']) && !preg_match('#IIS/\d#', $h['server'] ?? ''), ($h['x-powered-by'] ?? '') . ' ' . ($h['server'] ?? ''));
if ($esHttps) {
    afirmar('HSTS activo bajo HTTPS', str_contains($h['strict-transport-security'] ?? '', 'max-age='));
}
foreach (['/recursos/css/globals.css' => 'text/css', '/recursos/js/comun.js' => 'javascript', '/manifest.webmanifest' => 'manifest+json', '/sw.js' => 'javascript', '/icon-192.png' => 'image/png', '/logo-lebleu.png' => 'image/png'] as $ruta => $mime) {
    [$e, $h] = pedir($base . $ruta);
    afirmar("$ruta se sirve con tipo $mime", $e === 200 && str_contains($h['content-type'] ?? '', $mime), "estado $e, " . ($h['content-type'] ?? 'sin tipo'));
}
foreach (['/config/config.php', '/app/rutas.php', '/vendor/fpdf/fpdf.php', '/db/migraciones/001_esquema.sql', '/scripts/migrar.php', '/pruebas/roles.php', '/.git/config', '/web.config'] as $ruta) {
    [$e, , $c] = pedir($base . $ruta);
    afirmar("$ruta NO es accesible desde la web", $e !== 200 || !preg_match('/<\?php|CREATE TABLE|<configuration|\[core\]/', $c), "estado $e");
}
if (isset($opc['http'])) {
    [$e, $h] = pedir($opc['http'] . '/login');
    afirmar('HTTP redirige a HTTPS', in_array($e, [301, 302, 307, 308], true) && str_starts_with($h['location'] ?? '', 'https://'), "estado $e " . ($h['location'] ?? ''));
}

echo "\n== Sin sesión\n";
foreach (['/', '/capturar', '/movimientos', '/usuarios', '/bitacora', '/reportes'] as $ruta) {
    [$e, $h] = pedir($base . $ruta);
    afirmar("$ruta sin sesión redirige a /login", $e === 302 && str_ends_with($h['location'] ?? '', '/login'), "estado $e");
}
foreach (['/api/reportes/completo?periodo=1', '/api/viajes/recibo?id=1', '/api/adjuntos/1', '/api/manual'] as $ruta) {
    [$e] = pedir($base . $ruta);
    afirmar("$ruta sin sesión responde 401/403", in_array($e, [401, 403], true), "estado $e");
}
[$e, $j] = iniciar($base, $correo, 'clave-equivocada-xyz');
afirmar('una contraseña equivocada se rechaza', in_array($e, [400, 401, 429], true) && !($j['ok'] ?? false), "estado $e");

echo "\n== Administrador\n";
[$e, $j, $sid, $cookies] = iniciar($base, $correo, $clave);
afirmar('el inicio de sesión funciona', $e === 200 && ($j['ok'] ?? false) && $sid !== '', "estado $e");
if ($sid === '') {
    echo "\nNo se pudo iniciar sesión: se omite el resto.\n";
    exit(1);
}
$ck = implode(' ', $cookies);
afirmar('la cookie es HttpOnly y SameSite' . ($esHttps ? ' y Secure' : ''), stripos($ck, 'httponly') !== false && stripos($ck, 'samesite') !== false && (!$esHttps || stripos($ck, 'secure') !== false), $ck);
foreach (['/', '/capturar', '/movimientos', '/periodos', '/calendario', '/usuarios', '/auditoria', '/reportes', '/analisis', '/caja-chica', '/bitacora'] as $ruta) {
    [$e, , $c] = pedir($base . $ruta, 'GET', null, $sid);
    afirmar("$ruta abre", $e === 200 && str_contains($c, '</html>') && !str_contains($c, 'Error interno'), "estado $e");
}
[$e, , $c] = pedir("$base/reportes", 'GET', null, $sid);
preg_match('/data-visor-pdf="\/api\/reportes\/soportes\?periodo=(\d+)"/', $c, $m);
$periodo = $m[1] ?? '';
afirmar('Reportes ofrece un periodo', $periodo !== '');
foreach (['soportes', 'marineros', 'completo'] as $r) {
    $t = microtime(true);
    [$e, $h, $c] = pedir("$base/api/reportes/$r?periodo=$periodo", 'GET', null, $sid);
    afirmar("reporte $r: PDF válido en " . round(microtime(true) - $t, 1) . ' s', $e === 200 && str_starts_with($c, '%PDF-') && str_contains($h['content-type'] ?? '', 'application/pdf'), "estado $e");
}
[$e, , $c] = pedir("$base/api/reportes/caja-chica?caja=todas", 'GET', null, $sid);
afirmar('reporte de caja chica: PDF válido', $e === 200 && str_starts_with($c, '%PDF-'), "estado $e");
[$e, , $c] = pedir("$base/api/manual", 'GET', null, $sid);
afirmar('el manual del rol se descarga', $e === 200 && str_starts_with($c, '%PDF-'), "estado $e");

if (isset($opc['capitan'])) {
    echo "\n== Capitán\n";
    [$cc, $cp] = explode(':', $opc['capitan'], 2);
    [, , $sidc] = iniciar($base, $cc, $cp);
    afirmar('el capitán inicia sesión', $sidc !== '');
    foreach (['/calendario' => 200, '/bitacora' => 200, '/caja-chica' => 200, '/' => 302, '/usuarios' => 302, '/reportes' => 302] as $ruta => $esperado) {
        [$e] = pedir($base . $ruta, 'GET', null, $sidc);
        afirmar("capitán en $ruta → $esperado", $e === $esperado, "estado $e");
    }
    [$e] = pedir("$base/api/reportes/completo?periodo=$periodo", 'GET', null, $sidc);
    afirmar('el capitán no descarga el reporte completo', $e === 403, "estado $e");
}

echo "\n== Cerrar sesión\n";
[$e] = pedir("$base/api/logout", 'POST', [], $sid);
afirmar('cerrar sesión responde ok', $e === 200);
[$e] = pedir("$base/usuarios", 'GET', null, $sid);
afirmar('la cookie anterior ya no sirve', $e === 302, "estado $e");

echo "\n" . ($fallos ? "$fallos prueba(s) fallaron." : 'PRUEBA DE HUMO OK') . "\n";
exit($fallos ? 1 : 0);
