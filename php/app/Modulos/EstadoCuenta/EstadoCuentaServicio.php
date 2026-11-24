<?php
namespace Lebleu\Modulos\EstadoCuenta;

use Lebleu\Compartido\Dinero;

// Arma el estado de cuenta de un periodo con la misma forma de datos que la versión Next.js.
final class EstadoCuentaServicio
{
    private EstadoCuentaRepositorio $repo;

    public function __construct(?EstadoCuentaRepositorio $repo = null)
    {
        $this->repo = $repo ?? new EstadoCuentaRepositorio();
    }

    public function obtener(int $periodoId): ?array
    {
        $periodo = $this->repo->periodo($periodoId);
        if (!$periodo) {
            return null;
        }
        $embId = (int) $periodo['embarcacion_id'];
        unset($periodo['embarcacion_id']);

        $operativos = $this->repo->operativos($periodoId);
        $extraordinarios = $this->repo->extraordinarios($periodoId);
        $insumos = $this->repo->insumos($periodoId);
        $viajes = $this->repo->viajes($periodoId);
        $horos = $this->repo->horometros($periodoId);
        $renta = $this->repo->renta($periodoId);

        $adjO = $this->repo->adjuntoPreferido('gastos_operativos', $periodoId);
        $adjI = $this->repo->adjuntoPreferido('insumos', $periodoId);
        $adjE = $this->repo->adjuntoPreferido('extraordinarios', $periodoId);

        $extraPrev = $this->repo->extraMesAnterior($periodoId, (int) $periodo['anio'], (int) $periodo['mes']);

        return [
            'periodo' => $periodo,
            'balances' => $this->repo->balances($periodoId),
            'variable' => $this->repo->variable($periodoId),
            'extraSocio' => $this->repo->extraPorSocio($periodoId),
            'operativos' => array_map(fn($o) => $o + ['adjunto_id' => $adjO[$o['id']] ?? null], $operativos),
            'insumos' => array_map(fn($i) => $i + ['adjunto_id' => $adjI[$i['id']] ?? null], $insumos),
            'extraordinarios' => array_map(fn($e) => $e + ['adjunto_id' => $adjE[$e['id']] ?? null], $extraordinarios),
            'extraPrev' => $extraPrev ? [
                'anio' => (int) $extraPrev['anio'], 'mes' => (int) $extraPrev['mes'],
                'total' => (float) $extraPrev['total'], 'pendiente' => (float) $extraPrev['pendiente'],
            ] : null,
            'extraCuenta' => $this->extraCuenta($periodo, $embId, $periodoId, $extraordinarios),
            'viajes' => array_map(
                fn($v) => $v + ['horometros' => array_values(array_filter($horos, fn($h) => $h['viaje_id'] === $v['id']))],
                $viajes
            ),
            'renta' => $renta,
            'kpis' => $this->kpis($operativos, $extraordinarios, $viajes, $insumos, $renta),
        ];
    }

    /** Cuenta de extraordinarios por socio: cargo acumulado menos abonos acumulados. */
    private function extraCuenta(array $periodo, int $embId, int $periodoId, array $extraordinarios): array
    {
        $claveActual = (int) $periodo['anio'] * 12 + (int) $periodo['mes'];
        $totalMes = $this->sumaCentavos($extraordinarios, 'monto_mxn');
        $totalPrevAcum = Dinero::aCentavos($this->repo->extraTotalAnterior($embId, $claveActual));
        $socios = $this->repo->sociosActivos();
        $abonos = $this->repo->abonosExtra($embId);
        $ultimoId = $socios ? max(array_map(fn($s) => (int) $s['id'], $socios)) : 0;

        return array_map(function ($s) use ($totalMes, $totalPrevAcum, $abonos, $periodoId, $claveActual, $ultimoId) {
            $esUltimo = (int) $s['id'] === $ultimoId;
            $cargoMes = Dinero::parte($totalMes, $s['porcentaje'], $esUltimo);
            $cargoPrev = Dinero::parte($totalPrevAcum, $s['porcentaje'], $esUltimo);
            $abonosMes = $abonosPrev = 0;
            foreach ($abonos as $a) {
                if ((int) $a['socio_id'] !== (int) $s['id']) {
                    continue;
                }
                $abonosMes += (int) $a['periodo_id'] === $periodoId ? Dinero::aCentavos($a['monto']) : 0;
                $abonosPrev += (int) $a['clave'] < $claveActual ? Dinero::aCentavos($a['monto']) : 0;
            }
            $saldoAnterior = $cargoPrev - $abonosPrev;
            return [
                'socio_id' => (int) $s['id'], 'nombre' => $s['nombre'],
                'saldoAnterior' => Dinero::aFloat($saldoAnterior), 'cargoMes' => Dinero::aFloat($cargoMes),
                'abonosMes' => Dinero::aFloat($abonosMes),
                'saldoPendiente' => Dinero::aFloat($saldoAnterior + $cargoMes - $abonosMes),
            ];
        }, $socios);
    }

    private function kpis(array $operativos, array $extraordinarios, array $viajes, array $insumos, array $renta): array
    {
        $op = $this->sumaCentavos($operativos, 'monto_mxn');
        $extra = $this->sumaCentavos($extraordinarios, 'monto_mxn');
        return [
            'opTotal' => Dinero::aFloat($op), 'opMitad' => Dinero::aFloat(Dinero::mitad($op)),
            'extraTotal' => Dinero::aFloat($extra), 'extraMitad' => Dinero::aFloat(Dinero::mitad($extra)),
            'varTotal' => Dinero::aFloat($this->sumaCentavos($viajes, 'total')),
            'combTotal' => Dinero::aFloat($this->sumaCentavos($viajes, 'costo_combustible')),
            'marTotal' => Dinero::aFloat($this->sumaCentavos($viajes, 'costo_marinero')),
            'consTotal' => Dinero::aFloat($this->sumaCentavos($viajes, 'costo_consumibles')),
            'litrosTotal' => round(array_sum(array_map(fn($v) => (float) $v['litros'], $viajes)), 2),
            'numViajes' => count($viajes),
            'ingresosRenta' => (float) $renta['ingresos'],
            'insumosTotal' => Dinero::aFloat($this->sumaCentavos($insumos, 'monto_mxn')),
        ];
    }

    private function sumaCentavos(array $filas, string $columna): int
    {
        return array_sum(array_map(fn($f) => Dinero::aCentavos($f[$columna]), $filas));
    }
}
