<?php
namespace Lebleu\Modulos\Catalogos;

// Listas de apoyo de los formularios de captura.
final class CatalogosServicio
{
    private CatalogosRepositorio $repo;

    public function __construct(?CatalogosRepositorio $repo = null)
    {
        $this->repo = $repo ?? new CatalogosRepositorio();
    }

    /** @return array{socios: array, clientes: array, motores: array, marineros: array} */
    public function todos(): array
    {
        return [
            'socios' => $this->repo->socios(),
            'clientes' => $this->repo->clientes(),
            'motores' => $this->repo->motores(),
            'marineros' => $this->repo->marineros(),
        ];
    }

    public function viajesDelPeriodo(int $periodoId): array
    {
        return $this->repo->viajesDelPeriodo($periodoId);
    }
}
