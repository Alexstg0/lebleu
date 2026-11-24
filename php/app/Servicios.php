<?php
namespace Lebleu;

use Lebleu\Modulos\Periodos\PeriodosServicio;
use Lebleu\Modulos\Recurrentes\RecurrentesServicio;
use Lebleu\Modulos\Reportes\ArchivadorPdf;
use Lebleu\Modulos\Reportes\ReciboGastoPdf;

// Raíz de composición: une los módulos que se conocen solo por interfaz. Periodos no depende de Reportes
// (recibe un ArchivadorEstado) ni Recurrentes de los recibos (recibe un GeneradorRecibo); aquí se les entrega el real.
final class Servicios
{
    public static function periodos(): PeriodosServicio
    {
        return new PeriodosServicio(null, new ArchivadorPdf());
    }

    public static function recurrentes(): RecurrentesServicio
    {
        return new RecurrentesServicio(new ReciboGastoPdf());
    }
}
