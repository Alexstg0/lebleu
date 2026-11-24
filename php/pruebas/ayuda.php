<?php
// Ayudas comunes de las pruebas de integración: afirmar, llamar a un controlador como el navegador, crear usuarios con sesión.
require_once __DIR__ . '/../app/arranque.php';

use Lebleu\Core\Clave;
use Lebleu\Core\Db;
use Lebleu\Core\Peticion;
use Lebleu\Modulos\Auth\AuthRepositorio;

$GLOBALS['fallos'] = 0;

function afirmar(string $nombre, bool $cond, string $extra = ''): void
{
    echo ($cond ? 'ok      ' : 'FALLA   ') . $nombre . ($cond || $extra === '' ? '' : "  ($extra)") . "\n";
    $GLOBALS['fallos'] += $cond ? 0 : 1;
}

/** Llama a un controlador como lo haría el navegador. @return array{0:int,1:?array,2:\Lebleu\Core\Respuesta} */
function llamar(object $ctrl, string $accion, ?string $token, array $json = [], array $consulta = [], array $args = []): array
{
    $r = $ctrl->$accion(new Peticion('POST', '/api/prueba', $json, $token ? ['sid' => $token] : [], $consulta), $args);
    return [$r->estado(), $r->datos(), $r];
}

function usuario(string $rol, string $sufijo = ''): string
{
    Db::ejecutar('INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES (?, ?, ?, ?)', ["Prueba $rol", "$rol$sufijo.p4@prueba.test", Clave::crear('x'), $rol]);
    $token = bin2hex(random_bytes(32));
    (new AuthRepositorio())->crearSesion($token, Db::ultimoId(), 1);
    return $token;
}

function ultimaAuditoria(): array
{
    return Db::fila('SELECT accion, tabla, registro_id, detalle, usuario_nombre FROM auditoria ORDER BY id DESC LIMIT 1') ?? [];
}

function cuenta(string $tabla): int
{
    return (int) Db::fila("SELECT COUNT(*) n FROM `$tabla`")['n'];
}

function terminar(string $ok): never
{
    echo $GLOBALS['fallos'] ? "\n{$GLOBALS['fallos']} prueba(s) fallaron.\n" : "\n$ok\n";
    exit($GLOBALS['fallos'] ? 1 : 0);
}
