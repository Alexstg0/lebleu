<?php
namespace Lebleu\Modulos\Recurrentes;

final class SinRecibo implements GeneradorRecibo
{
    public function paraGasto(int $gastoId): bool
    {
        return false;
    }
}
