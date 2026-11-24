<?php
namespace Lebleu\Modulos\Reportes;

use Lebleu\Compartido\Dinero;
use Lebleu\Compartido\Pdf\Fusionador;
use Lebleu\Compartido\Pdf\Lienzo;
use Lebleu\Compartido\Pdf\Recibo;

// Reporte de caja chica: carátula horizontal con el balance y firma, más los soportes de cada movimiento.
final class CajaPdf
{
    private const PW = Lienzo::ALTO;   // página horizontal
    private const PH = Lienzo::ANCHO;
    private const M = 46;
    private const ROJO = [0.72, 0.11, 0.11];

    public function __construct(private ReportesRepositorio $repo = new ReportesRepositorio()) {}

    /** @param int|null $caja número de caja, o null para todas */
    public function generar(?int $caja, string $responsable, ?string $hoy = null): ?string
    {
        $todos = $this->repo->cajaMovimientos();
        $bal = 0;
        foreach ($todos as &$m) {
            $bal += Dinero::aCentavos($m['abono']) - Dinero::aCentavos($m['monto']);
            $m['balance'] = Dinero::aFloat($bal);
        }
        unset($m);
        $movs = $caja === null ? $todos : array_values(array_filter($todos, fn($m) => (int) $m['caja_numero'] === $caja && $m['caja_numero'] !== null));
        if (!$movs) {
            return null;
        }
        $porMov = [];
        foreach ($this->repo->cajaAdjuntos() as $a) {
            $porMov[(int) $a['caja_id']][] = $a;
        }

        $l = new Lienzo();
        $this->caratula($l, $movs, $caja, $responsable, $hoy ?? date('Y-m-d'));
        $f = new Fusionador();
        $f->agregar($l->salida());
        $medidor = new Lienzo(); // el lienzo de la carátula ya se cerró: otro para medir el texto de los sellos

        foreach ($movs as $m) {
            $sello = 'Soporte de caja chica · ' . Recibo::cortaFecha((string) $m['fecha']) . ($m['caja_numero'] !== null ? ' · Caja ' . $m['caja_numero'] : '')
                . ' · ' . ($m['proveedor'] ?: $m['concepto'] ?: ($m['tipo'] === 'abono' ? 'Abono' : ''))
                . ' · ' . Recibo::mxn($m['tipo'] === 'abono' ? $m['abono'] : $m['monto']);
            foreach ($porMov[(int) $m['id']] ?? [] as $a) {
                try {
                    $f->agregar((string) base64_decode($a['datos'], false), function (float $pw) use ($sello, $medidor) {
                        return self::ajustar($medidor, $sello, 8, $pw - 20, true);
                    });
                } catch (\Throwable) {
                    $e = new Lienzo();
                    $e->nuevaPagina(true);
                    $e->texto('El soporte no se pudo incrustar', self::M, self::PH - 110, 14, true, Recibo::NAVY);
                    $e->texto($a['nombre'] . ' · ' . $sello, self::M, self::PH - 130, 10, false, Recibo::GRAY);
                    $f->agregar($e->salida());
                }
            }
        }
        return $f->generar();
    }

    private function caratula(Lienzo $l, array $movs, ?int $caja, string $responsable, string $hoy): void
    {
        [$PW, $PH, $M] = [self::PW, self::PH, self::M];
        $esTodas = $caja === null;
        $cw = $PW - 2 * $M;
        $cajaTxt = $esTodas ? 'Todas las cajas' : "Caja $caja";
        $num = fn($v) => (float) ($v ?? 0);
        $xFecha = $M + 6;
        $xCaja = $M + 52;
        $xFactura = $M + 92;
        $xProv = $M + 178;
        $xConc = $M + 300;
        $xMonto = $esTodas ? $M + 520 : $M + 545;
        $xAbono = $M + 588;
        $xBal = $M + 652;
        $xObs = $esTodas ? $M + 664 : $M + 560;
        $concMax = $xMonto - 62 - $xConc;
        $obsMax = $M + $cw - $xObs - 4;
        $der = fn(string $s, float $xr, float $y, float $tam, bool $neg = false, array $c = Recibo::NAVY) => $l->texto($s, $xr - $l->ancho($s, $tam, $neg), $y, $tam, $neg, $c);
        $cabecera = function (float $y) use ($l, $cw, $M, $esTodas, $xFecha, $xCaja, $xFactura, $xProv, $xConc, $xMonto, $xAbono, $xBal, $xObs, $der) {
            $l->rect($M, $y - 22, $cw, 22, Recibo::LIGHT);
            $hy = $y - 14.5;
            foreach ([['FECHA', $xFecha], ['CAJA', $xCaja], ['FACTURA', $xFactura], ['PROVEEDOR', $xProv], ['CONCEPTO', $xConc]] as [$t, $x]) {
                $l->texto($t, $x, $hy, 8, true, Recibo::NAVY);
            }
            $der('MONTO', $xMonto, $hy, 8, true);
            if ($esTodas) {
                $der('ABONO', $xAbono, $hy, 8, true);
                $der('BALANCE', $xBal, $hy, 8, true);
            }
            $l->texto('OBSERVACIONES', $xObs, $hy, 8, true, Recibo::NAVY);
            return $y - 22;
        };
        $continuacion = function () use ($l, $cajaTxt, $M, $PH) {
            $l->nuevaPagina(true);
            $l->texto("Balance de caja chica — $cajaTxt (continuación)", $M, $PH - 46, 11, true, Recibo::NAVY);
        };

        $l->nuevaPagina(true);
        $topY = $PH - 52;
        $logo = Recibo::logo('lebleu-navy');
        [$iw, $ih] = Lienzo::medidas($logo);
        $lh = 42;
        $l->imagen($logo, $M, $topY - $lh + 10, $iw / $ih * $lh, $lh);
        $xInfo = $M + 115;
        $l->texto("Balance de caja chica — $cajaTxt", $xInfo, $topY, 16, true, Recibo::NAVY);
        $l->texto('Arrendadora Acma S de RL de CV · Embarcación Le Bleu', $xInfo, $topY - 17, 9.5, false, Recibo::GRAY);
        $l->texto('Fecha: ' . Recibo::largaFecha($hoy), $xInfo, $topY - 31, 9, false, Recibo::GRAY);
        $l->texto('Responsable de caja: ' . ($responsable ?: '—'), $xInfo + 200, $topY - 31, 9, false, Recibo::GRAY);
        $l->linea($M, $topY - 42, $PW - $M, $topY - 42, 1.5, Recibo::NAVY);

        $y = $cabecera($topY - 54);
        $rh = 22;
        foreach ($movs as $m) {
            if ($y - $rh < 78) {
                $continuacion();
                $y = $cabecera($PH - 58);
            }
            $ty = $y - 14.5;
            $l->texto(Recibo::cortaFecha((string) $m['fecha']), $xFecha, $ty, 9, false, Recibo::NAVY);
            $l->texto($m['caja_numero'] !== null ? (string) $m['caja_numero'] : '—', $xCaja, $ty, 9, false, Recibo::NAVY);
            $l->texto(self::ajustar($l, $m['factura'] ?: '—', 9, $xProv - $xFactura - 8), $xFactura, $ty, 9, false, Recibo::NAVY);
            $l->texto(self::ajustar($l, $m['proveedor'] ?: '—', 9, $xConc - $xProv - 8), $xProv, $ty, 9, false, Recibo::NAVY);
            $l->texto(self::ajustar($l, $m['concepto'] ?: ($m['tipo'] === 'abono' ? 'Abono a caja' : '—'), 9, $concMax), $xConc, $ty, 9, false, Recibo::NAVY);
            $der($num($m['monto']) ? Recibo::mxn($num($m['monto'])) : '—', $xMonto, $ty, 9);
            if ($esTodas) {
                $der($num($m['abono']) ? Recibo::mxn($num($m['abono'])) : '—', $xAbono, $ty, 9);
                $der(Recibo::mxn($m['balance']), $xBal, $ty, 9, false, $m['balance'] < 0 ? self::ROJO : Recibo::NAVY);
            }
            $l->texto(self::ajustar($l, $m['observaciones'] ?: '—', 8.5, $obsMax), $xObs, $ty, 8.5, false, Recibo::GRAY);
            $l->linea($M, $y - $rh, $M + $cw, $y - $rh, 0.5, Recibo::BORDER);
            $y -= $rh;
        }

        $totalGastos = array_sum(array_map(fn($m) => Dinero::aCentavos($m['monto']), $movs));
        $totalAbonos = array_sum(array_map(fn($m) => Dinero::aCentavos($m['abono']), $movs));
        $balanceActual = $movs[count($movs) - 1]['balance'];
        if ($y - 26 < 78) {
            $continuacion();
            $y = $PH - 58;
        }
        $l->rect($M, $y - 26, $cw, 26, [0.955, 0.965, 0.98]);
        $tly = $y - 17;
        $der('Totales', $xConc + 120, $tly, 10, true);
        $der(Recibo::mxn(Dinero::aFloat($totalGastos)), $xMonto, $tly, 10, true);
        if ($esTodas) {
            $der(Recibo::mxn(Dinero::aFloat($totalAbonos)), $xAbono, $tly, 10, true);
            $der(Recibo::mxn($balanceActual), $xBal, $tly, 10, true, $balanceActual < 0 ? self::ROJO : Recibo::NAVY);
        } else {
            $aPagar = Recibo::mxn(Dinero::aFloat($totalGastos - $totalAbonos));
            $l->texto($totalAbonos > 0 ? 'Abonado: ' . Recibo::mxn(Dinero::aFloat($totalAbonos)) . " · A pagar: $aPagar" : "A pagar: $aPagar", $xObs, $tly, 9, true, Recibo::NAVY);
        }
        $y -= 26;

        if ($y < 130) {
            $l->nuevaPagina(true);
            $y = $PH - 120;
        }
        $sigY = max(64, $y - 92);
        $sigW = 210;
        $sx = ($PW - $sigW) / 2;
        $l->linea($sx, $sigY, $sx + $sigW, $sigY, 1, Recibo::NAVY);
        $l->texto('Kevin Flores', ($PW - $l->ancho('Kevin Flores', 10, true)) / 2, $sigY - 15, 10, true, Recibo::NAVY);
        $l->texto('Revisó', ($PW - $l->ancho('Revisó', 8.5)) / 2, $sigY - 28, 8.5, false, Recibo::GRAY);
    }

    private static function ajustar(Lienzo $l, string $s, float $tam, float $maxW, bool $neg = false): string
    {
        if ($l->ancho($s, $tam, $neg) <= $maxW) {
            return $s;
        }
        while (mb_strlen($s) > 1 && $l->ancho($s . '…', $tam, $neg) > $maxW) {
            $s = mb_substr($s, 0, -1);
        }
        return $s . '…';
    }
}
