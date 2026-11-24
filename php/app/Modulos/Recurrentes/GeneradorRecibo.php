<?php
namespace Lebleu\Modulos\Recurrentes;

// Cada cargo recurrente lleva su recibo en PDF. El generador real lo aporta el módulo de Reportes;
// mientras no exista, SinRecibo no hace nada y el cargo se crea igual.
interface GeneradorRecibo
{
    public function paraGasto(int $gastoId): bool;
}
