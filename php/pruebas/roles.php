<?php
// Fase 6: matriz de permisos. Los roles permitidos de cada página y de cada método de API se LEEN del código original
// (requireUser / apiGuard) y se comprueba que PHP responde igual para sin sesión, admin, capitán y socio.
// Todo dentro de una transacción que se revierte. Uso: php pruebas/roles.php
require __DIR__ . '/ayuda.php';

use Lebleu\Core\Db;
use Lebleu\Core\Peticion;
use Lebleu\Core\Ruteador;

$raiz = dirname(__DIR__, 2) . '/app';
$todos = ['admin', 'capitan', 'socio'];
// Rutas públicas por diseño (no llevan guardia en el original).
$publicas = ['GET /login', 'POST /api/login', 'POST /api/logout', 'GET /api/logout', 'GET /api/cron/recurrentes'];

/** @return array<string, array{0: ?array, 1: string}> "METODO /ruta" => [roles|null (cualquier sesión), archivo] */
function leerOriginal(string $raiz): array
{
    $mapa = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($raiz)));
        $src = (string) file_get_contents($f->getPathname());
        $constantes = preg_match('/const\s+ROLES\s*=\s*\[([^\]]*)\]/', $src, $c) ? $c[1] : null;
        $roles = function (string $texto, string $fn) use ($constantes): ?array {
            if (!preg_match('/' . $fn . '\(\s*(\[([^\]]*)\])?\s*\)/', $texto, $m)) {
                return ['?'];
            }
            $lista = $m[2] ?? '';
            if (preg_match('/^\s*\.\.\.ROLES\s*$/', $lista) && $constantes !== null) {
                $lista = $constantes;
            }
            return trim($lista) !== '' ? array_map(fn($r) => trim($r, " \"'"), explode(',', $lista)) : null;
        };
        $guardia = fn(string $texto) => $roles($texto, 'apiGuard') !== ['?'] ? $roles($texto, 'apiGuard') : $roles($texto, 'requireUser');
        if (basename($rel) === 'page.tsx') {
            $ruta = preg_replace('#/page\.tsx$#', '', $rel) ?: '/';
            $mapa['GET ' . ($ruta === '' ? '/' : $ruta)] = [$roles($src, 'requireUser'), $rel];
        } elseif (basename($rel) === 'route.ts') {
            $ruta = preg_replace(['#/route\.ts$#', '#\[(\w+)\]#'], ['', '{$1}'], $rel);
            $partes = preg_split('/(?=export\s+async\s+function\s+(?:GET|POST|PATCH|PUT|DELETE)\b)/', $src);
            foreach ($partes as $parte) {
                if (preg_match('/^export\s+async\s+function\s+(GET|POST|PATCH|PUT|DELETE)\b/', $parte, $m)) {
                    $mapa["$m[1] $ruta"] = [$guardia($parte), $rel];
                }
            }
        }
    }
    // Dos rutas protegen con getSession (cualquier sesión) y reglas propias dentro del manejador.
    foreach (['GET /api/adjuntos/{id}', 'GET /api/manual'] as $k) {
        $mapa[$k][0] = null;
    }
    $mapa['DELETE /api/usuarios'][0] = ['admin']; // getSession + "rol !== admin → 403"; no usa apiGuard
    ksort($mapa);
    return $mapa;
}

$original = leerOriginal($raiz);
$ruteador = new Ruteador();
(require __DIR__ . '/../app/rutas.php')($ruteador);

Db::simular(function () use ($original, $ruteador, $todos, $publicas) {
    $tokens = ['admin' => usuario('admin', 'r6'), 'capitan' => usuario('capitan', 'r6'), 'socio' => usuario('socio', 'r6'), 'nadie' => null];
    $sinGuardia = [];
    $revisadas = 0;
    foreach ($original as $clave => [$roles, $archivo]) {
        if (in_array($clave, $publicas, true)) {
            continue;
        }
        [$metodo, $ruta] = explode(' ', $clave, 2);
        if ($roles === ['?']) {
            $sinGuardia[] = "$clave ($archivo)";
            continue;
        }
        $ruta = str_replace('{id}', '1', $ruta);
        $esApi = str_starts_with($ruta, '/api/');
        foreach ($tokens as $quien => $token) {
            $permitido = $quien !== 'nadie' && ($roles === null || in_array($quien, $roles, true));
            $r = $ruteador->despachar(new Peticion($metodo, $ruta, [], $token ? ['sid' => $token] : [], []));
            $e = $r->estado();
            if ($esApi) {
                $ok = $permitido ? !in_array($e, [401, 403, 405], true) : in_array($e, [401, 403], true);
            } else {
                $ok = $permitido ? $e === 200 : ($e === 302 && $r->cabecera('Location') !== null);
            }
            $revisadas++;
            if (!$ok) {
                afirmar("$clave como $quien " . ($permitido ? 'debe poder' : 'no debe poder'), false, "estado $e");
            }
        }
    }
    afirmar("matriz completa: $revisadas combinaciones ruta × rol coinciden con las guardias del original", $GLOBALS['fallos'] === 0);
    afirmar('toda ruta del original con guardia pudo leerse (sin casos sin resolver)', !$sinGuardia, implode('; ', $sinGuardia));

    // Reglas de negocio de la tabla de roles, expresadas directamente
    $ve = fn($rol, $ruta) => $ruteador->despachar(new Peticion('GET', $ruta, [], ['sid' => $tokens[$rol]], []))->estado() === 200;
    afirmar('capitán: sí calendario, bitácora, caja chica; no estado de cuenta, reportes, usuarios, auditoría, movimientos',
        $ve('capitan', '/calendario') && $ve('capitan', '/bitacora') && $ve('capitan', '/caja-chica')
        && !$ve('capitan', '/') && !$ve('capitan', '/reportes') && !$ve('capitan', '/usuarios') && !$ve('capitan', '/auditoria') && !$ve('capitan', '/movimientos'));
    afirmar('socio: sí estado de cuenta, análisis, reportes, calendario; no bitácora, caja chica, usuarios, capturar',
        $ve('socio', '/') && $ve('socio', '/analisis') && $ve('socio', '/reportes') && $ve('socio', '/calendario')
        && !$ve('socio', '/bitacora') && !$ve('socio', '/caja-chica') && !$ve('socio', '/usuarios') && !$ve('socio', '/capturar'));
    afirmar('admin: entra a todas las pantallas', array_reduce(['/', '/capturar', '/movimientos', '/periodos', '/calendario', '/usuarios', '/auditoria', '/reportes', '/analisis', '/caja-chica', '/bitacora'], fn($a, $p) => $a && $ve('admin', $p), true));
    $inicio = fn($rol) => $ruteador->despachar(new Peticion('GET', '/usuarios', [], ['sid' => $tokens[$rol]], []))->cabecera('Location');
    afirmar('quien no puede ver una pantalla vuelve a su propio inicio (capitán → calendario)', $inicio('capitan') === '/calendario');

    // Sesión: caducada, desconocida y usuario desactivado
    $sesion = fn($t) => $ruteador->despachar(new Peticion('GET', '/calendario', [], $t ? ['sid' => $t] : [], []))->estado();
    afirmar('una cookie inventada no abre sesión', $sesion(str_repeat('a', 64)) === 302);
    Db::ejecutar("UPDATE sesiones SET expires_at = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE token = ?", [$tokens['socio']]);
    afirmar('una sesión vencida no abre sesión', $sesion($tokens['socio']) === 302);
    Db::ejecutar("UPDATE usuarios SET activo = 0 WHERE email = ?", ['admin' . 'r6.p4@prueba.test']);
    afirmar('un usuario desactivado pierde el acceso aunque su sesión siga vigente', $sesion($tokens['admin']) === 302);
});

terminar('FASE 6 (roles) OK');
