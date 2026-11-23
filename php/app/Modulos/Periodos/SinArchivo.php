<?php
namespace Lebleu\Modulos\Periodos;

final class SinArchivo implements ArchivadorEstado
{
    public function archivar(int $periodoId): bool
    {
        return false;
    }
}
