<?php
namespace Lebleu\Modulos\Periodos;

// Al cerrar un periodo se archiva su estado de cuenta en PDF. La implementación real la aporta el
// módulo de Reportes; mientras no exista, SinArchivo responde "no se pudo" sin romper el cierre.
interface ArchivadorEstado
{
    public function archivar(int $periodoId): bool;
}
