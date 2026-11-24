<?php
namespace Lebleu\Modulos\Reportes;

use Lebleu\Compartido\Pdf\Fusionador;
use Lebleu\Compartido\Politicas;
use Lebleu\Core\Peticion;
use Lebleu\Core\Respuesta;

// Endpoints GET de los reportes en PDF (se abren dentro de la app, en el visor).
final class ReportesPdfController
{
    public function soportes(Peticion $p, array $args): Respuesta
    {
        return $this->periodo($p, ['admin', 'socio'], 'reporte-soportes', 'No hay soportes (PDF) cargados en este periodo.', fn($id) => (new SoportesPdf())->generar($id));
    }

    public function marineros(Peticion $p, array $args): Respuesta
    {
        return $this->periodo($p, ['admin', 'socio'], 'reporte-marineros', 'No hay viajes con marinero en este periodo.', fn($id) => (new MarinerosPdf())->reporte($id));
    }

    /** Estado de cuenta + soportes (sin recibos de marinero) + marineros, en un solo PDF. */
    public function completo(Peticion $p, array $args): Respuesta
    {
        return $this->periodo($p, ['admin', 'socio'], 'reporte-completo', 'No hay información en este periodo.', function ($id) {
            $partes = array_filter([
                (new EstadoCuentaPdf())->generar($id),
                (new SoportesPdf())->generar($id, false),
                (new MarinerosPdf())->reporte($id),
            ]);
            if (!$partes) {
                return null;
            }
            $f = new Fusionador();
            foreach ($partes as $parte) {
                $f->agregar($parte);
            }
            return $f->generar();
        });
    }

    public function cajaChica(Peticion $p, array $args): Respuesta
    {
        if (!Politicas::guardiaApi($p, ['admin', 'capitan'])) {
            return Respuesta::error('No autorizado.', 403);
        }
        $param = (string) $p->consulta('caja', 'todas');
        if ($param !== 'todas' && !preg_match('/^-?\d+$/', $param)) {
            return Respuesta::error('Caja inválida.', 400);
        }
        $responsable = mb_substr((string) ($p->consulta('responsable', '') ?: 'Gabriel Preciado'), 0, 80);
        try {
            $pdf = (new CajaPdf())->generar($param === 'todas' ? null : (int) $param, $responsable);
        } catch (\Throwable $e) {
            error_log('reporte caja chica: ' . $e->getMessage());
            return Respuesta::error('No se pudo generar el reporte.', 500);
        }
        return $pdf ? self::pdf($pdf, 'reporte-caja-chica-' . $param . '.pdf') : Respuesta::error('No hay movimientos para este filtro.', 404);
    }

    /** Recibo de pago al marinero de un viaje (administrador). */
    public function reciboViaje(Peticion $p, array $args): Respuesta
    {
        if (!Politicas::guardiaApi($p, ['admin'])) {
            return Respuesta::error('No autorizado.', 403);
        }
        $id = (int) $p->consulta('id', 0);
        if (!$id) {
            return Respuesta::error('Falta el viaje.', 400);
        }
        try {
            $pdf = (new MarinerosPdf())->reciboViaje($id);
        } catch (\Throwable $e) {
            error_log('recibo de viaje: ' . $e->getMessage());
            return Respuesta::error('No se pudo generar el recibo.', 500);
        }
        return $pdf ? self::pdf($pdf, "recibo-marinero-viaje-$id.pdf") : Respuesta::error('Viaje no encontrado.', 404);
    }

    /** Genera (o regenera) el recibo PDF de un gasto operativo y lo adjunta. */
    public function reciboGasto(Peticion $p, array $args): Respuesta
    {
        if (!Politicas::guardiaApi($p, ['admin'])) {
            return Respuesta::error('No autorizado.', 403);
        }
        $id = (int) ($p->json()['gasto_id'] ?? 0);
        if (!$id) {
            return Respuesta::error('Falta el gasto.', 400);
        }
        try {
            return (new ReciboGastoPdf())->paraGasto($id) ? Respuesta::json(['ok' => true]) : Respuesta::error('Gasto no encontrado.', 404);
        } catch (\Throwable $e) {
            error_log('recibo de gasto: ' . $e->getMessage());
            return Respuesta::error('No se pudo generar el recibo.', 400);
        }
    }

    private function periodo(Peticion $p, array $roles, string $nombre, string $vacio, callable $generar): Respuesta
    {
        if (!Politicas::guardiaApi($p, $roles)) {
            return Respuesta::error('No autorizado.', 403);
        }
        $id = (int) $p->consulta('periodo', 0);
        if (!$id) {
            return Respuesta::error('Falta el periodo.', 400);
        }
        try {
            $pdf = $generar($id);
        } catch (\Throwable $e) {
            error_log("reporte $nombre: " . $e->getMessage());
            return Respuesta::error('No se pudo generar el reporte.', 500);
        }
        return $pdf ? self::pdf($pdf, "$nombre-$id.pdf") : Respuesta::error($vacio, 404);
    }

    private static function pdf(string $bytes, string $archivo): Respuesta
    {
        return Respuesta::binario($bytes, 'application/pdf', [
            'Content-Disposition' => 'inline; filename="' . $archivo . '"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
