<?php
namespace Lebleu\Modulos\Auditoria;

use Lebleu\Core\Db;

final class AuditoriaRepositorio
{
    public function insertar(?int $usuarioId, string $usuario, string $accion, string $tabla, ?int $registroId, string $detalle): void
    {
        Db::ejecutar(
            'INSERT INTO auditoria (usuario_id, usuario_nombre, accion, tabla, registro_id, detalle) VALUES (?, ?, ?, ?, ?, ?)',
            [$usuarioId, $usuario, $accion, $tabla, $registroId, $detalle]
        );
    }
}
