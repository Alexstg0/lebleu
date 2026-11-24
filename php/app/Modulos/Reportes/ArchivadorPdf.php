<?php
namespace Lebleu\Modulos\Reportes;

use Lebleu\Modulos\Periodos\ArchivadorEstado;

// Al cerrar un periodo guarda su estado de cuenta en PDF como adjunto generado (reemplaza el anterior).
final class ArchivadorPdf implements ArchivadorEstado
{
    public function __construct(private ReportesRepositorio $repo = new ReportesRepositorio()) {}

    public function archivar(int $periodoId): bool
    {
        try {
            $pdf = (new EstadoCuentaPdf())->generar($periodoId);
            $p = $this->repo->periodoConEmbarcacion($periodoId);
            if (!$pdf || !$p) {
                return true;
            }
            $this->repo->guardarGenerado('periodo_id', $periodoId, sprintf('Estado de cuenta %d-%02d.pdf', $p['anio'], $p['mes']), $pdf);
            return true;
        } catch (\Throwable $e) {
            error_log('archivar estado de cuenta: ' . $e->getMessage());
            return false;
        }
    }
}
