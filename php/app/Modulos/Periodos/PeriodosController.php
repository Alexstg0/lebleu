<?php
namespace Lebleu\Modulos\Periodos;

use Lebleu\Servicios;
use Lebleu\Compartido\Politicas;
use Lebleu\Modulos\Auditoria\AuditoriaServicio;
use Lebleu\Core\Peticion;
use Lebleu\Core\Respuesta;

final class PeriodosController
{
    public function crear(Peticion $p, array $args): Respuesta
    {
        $usuario = Politicas::guardiaApi($p, ['admin']);
        if (!$usuario) {
            return Respuesta::error('No autorizado.', 403);
        }
        try {
            $d = $p->json();
            $id = Servicios::periodos()->crear($d);
            AuditoriaServicio::registrar($usuario, 'crear', 'periodos', $id, "Creó el periodo {$d['mes']}/{$d['anio']}");
            return Respuesta::json(['ok' => true, 'id' => $id]);
        } catch (\InvalidArgumentException $e) {
            return Respuesta::error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            error_log('api periodos: ' . $e->getMessage());
            return Respuesta::error('Ocurrió un error al procesar la solicitud.', 400);
        }
    }

    // Cerrar (con archivo PDF) o reabrir un periodo.
    public function estado(Peticion $p, array $args): Respuesta
    {
        $usuario = Politicas::guardiaApi($p, ['admin']);
        if (!$usuario) {
            return Respuesta::error('No autorizado.', 403);
        }
        try {
            $d = $p->json();
            $id = (int) ($d['id'] ?? 0);
            if (!$id) {
                return Respuesta::error('Falta el periodo.', 400);
            }
            $servicio = Servicios::periodos();
            $etiqueta = $servicio->etiqueta($id);
            if ($etiqueta === null) {
                return Respuesta::error('Periodo no encontrado.', 404);
            }
            if (($d['accion'] ?? '') === 'reabrir') {
                $servicio->reabrir($id);
                AuditoriaServicio::registrar($usuario, 'reabrir', 'periodos', $id, "Reabrió el periodo $etiqueta");
                return Respuesta::json(['ok' => true]);
            }
            $archivo = $servicio->cerrar($id);
            AuditoriaServicio::registrar($usuario, 'cerrar', 'periodos', $id,
                "Cerró el periodo $etiqueta (archivo " . ($archivo ? 'generado' : 'falló') . ')');
            return Respuesta::json(['ok' => true, 'archivo' => $archivo]);
        } catch (\Throwable $e) {
            error_log('api periodos/estado: ' . $e->getMessage());
            return Respuesta::error('No se pudo cambiar el estado del periodo.', 400);
        }
    }
}
