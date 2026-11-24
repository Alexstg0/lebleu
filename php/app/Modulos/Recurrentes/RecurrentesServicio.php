<?php
namespace Lebleu\Modulos\Recurrentes;

use DateTimeImmutable;
use Lebleu\Compartido\Formato;
use Lebleu\Modulos\GastosOperativos\GastosOperativosServicio;

// Cargos que se generan solos: nómina semanal del capitán (cada viernes) y cargo administrativo mensual.
final class RecurrentesServicio
{
    public const NOMINA_CAPITAN = 8560;
    public const CARGO_ADMIN = 6000;
    /** "De hoy en adelante": nunca se generan cargos con fecha anterior a esta. */
    public const DESDE = '2026-07-02';

    private GeneradorRecibo $recibos;

    public function __construct(?GeneradorRecibo $recibos = null)
    {
        $this->recibos = $recibos ?? new SinRecibo();
    }

    /**
     * Genera los cargos que falten de un periodo, hasta la fecha $hoy (YYYY-MM-DD, hora de la embarcación).
     * @param array $periodo id, anio, mes
     * @return array{nomina: int, admin: int}
     */
    public function asegurar(array $periodo, string $hoy, string $desde = self::DESDE): array
    {
        $gastos = new GastosOperativosServicio();
        $id = (int) $periodo['id'];
        $anio = (int) $periodo['anio'];
        $mes = (int) $periodo['mes'];
        $nomina = $admin = 0;

        foreach ($this->viernesDelMes($anio, $mes) as $f) {
            if ($f < $desde || $f > $hoy) {
                continue;
            }
            $gid = $gastos->crearRecurrente($id, $f, 'Gabriel Preciado', 'Nómina del capitán — semana del ' . Formato::fechaLarga($f),
                'nomina', self::NOMINA_CAPITAN, "NOM-$f", "nom-$f");
            if ($gid) {
                $this->recibos->paraGasto($gid);
                $nomina++;
            } elseif ($sin = $gastos->idSinRecibo("nom-$f")) {
                $this->recibos->paraGasto($sin); // el cargo ya existía pero su recibo no se alcanzó a generar
            }
        }

        $mesClave = sprintf('%04d-%02d', $anio, $mes);
        $primero = "$mesClave-01";
        $fechaAdmin = $primero < $desde ? $desde : $primero;
        if ($fechaAdmin <= $hoy && $mesClave >= substr($desde, 0, 7)) {
            $gid = $gastos->crearRecurrente($id, $fechaAdmin, 'SOLMEX Administración',
                'Gasto administrativo mensual — ' . Formato::mesNombre($mes) . " $anio", 'admin', self::CARGO_ADMIN, "ADM-$mesClave", "adm-$mesClave");
            if ($gid) {
                $this->recibos->paraGasto($gid);
                $admin++;
            } elseif ($sin = $gastos->idSinRecibo("adm-$mesClave")) {
                $this->recibos->paraGasto($sin);
            }
        }
        return ['nomina' => $nomina, 'admin' => $admin];
    }

    /** @return string[] fechas ISO de los viernes del mes */
    private function viernesDelMes(int $anio, int $mes): array
    {
        $salida = [];
        $dia = new DateTimeImmutable(sprintf('%04d-%02d-01', $anio, $mes));
        $fin = $dia->modify('last day of this month');
        for (; $dia <= $fin; $dia = $dia->modify('+1 day')) {
            if ($dia->format('N') === '5') {
                $salida[] = $dia->format('Y-m-d');
            }
        }
        return $salida;
    }
}
