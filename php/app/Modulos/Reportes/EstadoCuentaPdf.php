<?php
namespace Lebleu\Modulos\Reportes;

use Lebleu\Compartido\Pdf\Lienzo;
use Lebleu\Compartido\Pdf\Recibo;
use Lebleu\Modulos\EstadoCuenta\EstadoCuentaServicio;

// Estado de cuenta del periodo en PDF (mismo acomodo que la vista de impresión de la app).
final class EstadoCuentaPdf
{
    private const GOLD = [0.722, 0.525, 0.043];
    private const CREMA = [1, 0.973, 0.941];
    private const CREMA_B = [0.961, 0.718, 0.627];

    public function generar(int $periodoId): ?string
    {
        $data = (new EstadoCuentaServicio())->obtener($periodoId);
        if (!$data) {
            return null;
        }
        $p = $data['periodo'];
        $k = $data['kpis'];
        $periodoTxt = Recibo::capMes((int) $p['mes']) . ' ' . $p['anio'];
        $emb = $p['embarcacion'] ?: 'Le Bleu';
        $rs = $p['razon_social'] ?: 'Arrendadora Acma S de RL de CV';
        $logo = Recibo::logo('lebleu-navy');
        [$M, $W, $H] = [Recibo::M, Recibo::W, Recibo::H];

        $l = new Lienzo();
        $h = new Hoja($l);
        $h->alNuevaPagina(function () use ($h, $l, $logo, $periodoTxt, $emb, $rs, $p, $M, $W, $H) {
            [$iw, $ih] = Lienzo::medidas($logo);
            $lh = 36;
            $l->imagen($logo, $M, $H - $h->y - $lh + 6, $iw / $ih * $lh, $lh);
            $xT = $M + 104;
            $l->texto("Estado de cuenta — $periodoTxt", $xT, $H - $h->y - 12, 15.5, true, Hoja::NAVY);
            $l->texto("$emb · $rs · T/C $" . Hoja::nf($p['tipo_cambio']) . ' · Combustible $' . Hoja::nf($p['precio_litro']) . '/Lt · Cifras en MXN',
                $xT, $H - $h->y - 27, 8.5, false, Hoja::MUTED);
            $l->linea($M, $H - $h->y - 40, $W - $M, $H - $h->y - 40, 2, Hoja::NAVY);
            $h->y += 54;
        });
        $h->nuevaPagina(true);

        $this->kpis($h, $k, $p);
        $this->socios($h, $data);
        $this->tablas($h, $data, $k);
        (new EstadoCuentaViajesPdf())->dibujar($h, $data, $p);
        $this->extraordinarios($h, $data, $k);

        $h->necesito(28);
        $h->y += 14;
        $pie = "$rs · Embarcación $emb · $periodoTxt";
        $l->texto($pie, ($W - $l->ancho($pie, 8)) / 2, $H - $h->y - 8, 8, false, Hoja::MUTED);
        return $l->salida();
    }

    private function kpis(Hoja $h, array $k, array $p): void
    {
        $l = $h->l;
        $M = Recibo::M;
        $defs = [
            ['bg' => Hoja::NAVY, 'claro' => false, 'label' => 'GASTOS OPERATIVOS', 'valor' => Hoja::mxn($k['opTotal']), 'notas' => ['50% c/u: ' . Hoja::mxn($k['opMitad']), 'Extraordinarios: ' . Hoja::mxn($k['extraTotal'])]],
            ['bg' => Hoja::TEAL, 'claro' => false, 'label' => 'GASTO VARIABLE', 'valor' => Hoja::mxn($k['varTotal']), 'notas' => ['Comb. ' . Hoja::mxn($k['combTotal']) . ' · Mar. ' . Hoja::mxn($k['marTotal']), 'Cons. ' . Hoja::mxn($k['consTotal'])]],
            ['bg' => Hoja::WHITE, 'claro' => true, 'label' => 'INGRESOS POR RENTA', 'valor' => Hoja::mxn($k['ingresosRenta']), 'notas' => [(float) $k['ingresosRenta'] > 0 ? 'Renta del mes' : 'Sin renta este mes']],
            ['bg' => Hoja::NAVY, 'claro' => false, 'label' => 'VIAJES REALIZADOS', 'valor' => $k['numViajes'] . ' viajes', 'notas' => [Hoja::nf($k['litrosTotal']) . ' Lts consumo total', 'PU: $' . Hoja::nf($p['precio_litro']) . ' / Lt']],
        ];
        $kw = ($h->cw - 3 * 8) / 4;
        $kh = 58;
        $h->necesito($kh + 8);
        foreach ($defs as $i => $d) {
            $x = $M + $i * ($kw + 8);
            $l->rect($x, Recibo::H - $h->y - $kh, $kw, $kh, $d['bg'], $d['claro'] ? Hoja::BORDER : null, $d['claro'] ? 1 : 0);
            $tinte = fn(float $a) => $d['claro'] ? Hoja::MUTED : Lienzo::mezcla(Hoja::WHITE, $d['bg'], $a);
            $l->texto($d['label'], $x + 9, Recibo::H - $h->y - 14, 6.3, true, $tinte(0.85));
            $l->texto($d['valor'], $x + 9, Recibo::H - $h->y - 30, 12, true, $d['claro'] ? Hoja::NAVY : Hoja::WHITE);
            foreach ($d['notas'] as $j => $nt) {
                $l->texto($h->ajustar($nt, 6.2, $kw - 16), $x + 9, Recibo::H - $h->y - 42 - $j * 8, 6.2, false, $tinte(0.8));
            }
        }
        $h->y += $kh;
    }

    private function socios(Hoja $h, array $data): void
    {
        $h->seccion('Estado de cuenta por socio');
        $l = $h->l;
        $M = Recibo::M;
        $colW = ($h->cw - 12) / 2;
        $variable = fn($sid) => array_values(array_filter($data['variable'], fn($v) => $v['socio_id'] === $sid))[0] ?? null;
        $extra = fn($sid) => array_values(array_filter($data['extraSocio'], fn($e) => $e['socio_id'] === $sid))[0] ?? null;
        $alto = function ($b) use ($extra) {
            $x = $extra($b['socio_id']);
            return 26 + 5 * 16 + 3 * 12 + 46 + ($x && (float) $x['liquidacion_extraordinaria'] > 0 ? 42 : 0) + 14;
        };
        $cardH = max(array_map($alto, $data['balances']));
        $h->necesito($cardH + 6);
        foreach ($data['balances'] as $i => $b) {
            $v = $variable($b['socio_id']);
            $x = $extra($b['socio_id']);
            $cx = $M + $i * ($colW + 12);
            $top = $h->y;
            $H = Recibo::H;
            $l->rect($cx, $H - $top - $cardH, $colW, $cardH, Hoja::WHITE, Hoja::BORDER, 1);
            $l->rect($cx, $H - $top - 3, $colW, 3, $i === 0 ? Hoja::OCEAN : Hoja::GREEN);
            $yy = $top + 22;
            $l->texto($h->ajustar($b['nombre'], 11, $colW - 24, true), $cx + 12, $H - $yy, 11, true, Hoja::TEXT);
            $yy += 18;
            $fila = function (string $desc, string $val, array $c, bool $neg = false) use ($l, $h, $cx, $colW, &$yy, $H) {
                $l->texto($desc, $cx + 12, $H - $yy, 8, $neg, $neg ? Hoja::TEXT : Hoja::MUTED);
                $h->TR($val, $cx + $colW - 12, $H - $yy, 8.5, false, $c);
                $l->linea($cx + 12, $H - $yy - 5, $cx + $colW - 12, $H - $yy - 5, 0.4, Hoja::BG2);
                $yy += 16;
            };
            $f = fn($n) => (float) ($n ?? 0);
            $fila('Saldo de inicio', '+ ' . Hoja::mxn($f($b['saldo_inicio'])), Hoja::GREEN);
            $fila('Aportaciones este mes', '+ ' . Hoja::mxn($f($b['aportaciones'])), Hoja::GREEN);
            $fila('Gastos generales (50%)', '- ' . Hoja::mxn($f($b['gastos_generales'])), Hoja::RED);
            $fila('Gastos variables', '- ' . Hoja::mxn($f($b['gastos_variables'])), Hoja::RED, true);
            $sub = function (string $desc, string $val) use ($l, $h, $cx, $colW, &$yy, $H) {
                $l->texto("•  $desc", $cx + 20, $H - $yy, 7.2, false, Hoja::MUTED);
                $h->TR($val, $cx + $colW - 12, $H - $yy, 7.2, false, Hoja::MUTED);
                $yy += 12;
            };
            $sub('Combustible · ' . ($v['num_viajes'] ?? 0) . ' viajes', '- ' . Hoja::mxn($f($v['combustible'] ?? 0)));
            $sub('Marinero', '- ' . Hoja::mxn($f($v['marinero'] ?? 0)));
            $sub('Consumibles', '- ' . Hoja::mxn($f($v['consumibles'] ?? 0)));
            $yy += 4;
            $ur = $f($b['utilidad_renta']);
            $fila('Utilidad renta', ($ur > 0 ? '+ ' : '') . Hoja::mxn($ur), $ur > 0 ? Hoja::GREEN : Hoja::MUTED);
            $yy += 2;
            $l->linea($cx + 12, $H - $yy + 8, $cx + $colW - 12, $H - $yy + 8, 1.5, Hoja::BG2);
            $l->texto('BALANCE OPERATIVO', $cx + 12, $H - $yy - 4, 7, true, Hoja::MUTED);
            $l->texto('Extraordinarios se liquidan por separado', $cx + 12, $H - $yy - 13, 6.2, false, Hoja::MUTED);
            $h->TR(Hoja::mxn($f($b['balance_operativo'])), $cx + $colW - 12, $H - $yy - 10, 15, true, Hoja::GREEN);
            $yy += 30;
            if ($x && (float) $x['liquidacion_extraordinaria'] > 0) {
                $l->rect($cx + 12, $H - $yy - 32, $colW - 24, 32, self::CREMA, self::CREMA_B, 1);
                $l->texto('PAGO EXTRAORDINARIO', $cx + 20, $H - $yy - 12, 6.8, true, Hoja::AMBER);
                $l->texto('Liquidación separada', $cx + 20, $H - $yy - 21, 6, false, Hoja::MUTED);
                $h->TR(Hoja::mxn($f($x['liquidacion_extraordinaria'])), $cx + $colW - 20, $H - $yy - 20, 12.5, true, Hoja::AMBER);
            }
        }
        $h->y += $cardH + 4;
    }

    private function tablas(Hoja $h, array $data, array $k): void
    {
        $cw = $h->cw;
        $f = fn($n) => (float) ($n ?? 0);
        $mx = fn($r) => Hoja::mxn($f($r['monto_mxn']));
        $fecha = fn($r) => Recibo::cortaFecha((string) $r['fecha']);
        $h->seccion('Gastos operativos del mes');
        $h->tabla([
            ['titulo' => '#', 'w' => 22, 'get' => fn($r, $i) => (string) ($i + 1)],
            ['titulo' => 'FECHA', 'w' => 42, 'get' => $fecha],
            ['titulo' => 'PROVEEDOR', 'w' => 112, 'get' => fn($r) => $r['proveedor'] ?: '—'],
            ['titulo' => 'CONCEPTO', 'w' => $cw - 22 - 42 - 112 - 76 - 86, 'get' => fn($r) => $r['concepto'] ?: '—'],
            ['titulo' => 'CARGO', 'w' => 76, 'alinear' => 'r', 'get' => $mx],
            ['titulo' => 'COMPROBANTE', 'w' => 86, 'alinear' => 'c', 'get' => fn($r) => $r['comprobante_folio'] ?: '—'],
        ], $data['operativos'], [
            ['', '', '', 'SUBTOTAL OPERATIVO', Hoja::mxn($k['opTotal']), ''],
            ['', '', '', '50% por socio', Hoja::mxn($k['opMitad']), ''],
        ]);

        if ($data['insumos']) {
            $h->seccion('Insumos de viajes');
            $h->tabla([
                ['titulo' => '#', 'w' => 22, 'get' => fn($r, $i) => (string) ($i + 1)],
                ['titulo' => 'FECHA', 'w' => 42, 'get' => $fecha],
                ['titulo' => 'VIAJE', 'w' => 118, 'get' => fn($r) => $r['viaje_id'] ? $r['viaje_cliente'] . ' · ' . Recibo::cortaFecha((string) $r['viaje_fecha']) : '—'],
                ['titulo' => 'PROVEEDOR', 'w' => 88, 'get' => fn($r) => $r['proveedor'] ?: '—'],
                ['titulo' => 'CONCEPTO', 'w' => $cw - 22 - 42 - 118 - 88 - 72 - 78, 'get' => fn($r) => $r['concepto'] ?: '—'],
                ['titulo' => 'CARGO', 'w' => 72, 'alinear' => 'r', 'get' => $mx],
                ['titulo' => 'COMPROBANTE', 'w' => 78, 'alinear' => 'c', 'get' => fn($r) => $r['comprobante_folio'] ?: '—'],
            ], $data['insumos'], [['', '', '', '', 'TOTAL INSUMOS', Hoja::mxn($k['insumosTotal']), '']]);
        }

        $var = fn($b) => array_values(array_filter($data['variable'], fn($v) => $v['socio_id'] === $b['socio_id']))[0] ?? null;
        $h->seccion('Resumen de gastos variables por socio');
        $h->tabla([
            ['titulo' => 'SOCIO', 'w' => $cw - 5 * 76, 'get' => fn($b) => $b['nombre']],
            ['titulo' => 'VIAJES', 'w' => 76, 'alinear' => 'c', 'get' => fn($b) => (string) ($var($b)['num_viajes'] ?? 0)],
            ['titulo' => 'COMBUSTIBLE', 'w' => 76, 'alinear' => 'r', 'get' => fn($b) => Hoja::mxn($f($var($b)['combustible'] ?? 0))],
            ['titulo' => 'MARINERO', 'w' => 76, 'alinear' => 'r', 'get' => fn($b) => Hoja::mxn($f($var($b)['marinero'] ?? 0))],
            ['titulo' => 'CONSUMIBLES', 'w' => 76, 'alinear' => 'r', 'get' => fn($b) => Hoja::mxn($f($var($b)['consumibles'] ?? 0))],
            ['titulo' => 'TOTAL', 'w' => 76, 'alinear' => 'r', 'get' => fn($b) => Hoja::mxn($f($var($b)['total_variable'] ?? 0))],
        ], $data['balances'], [['TOTAL', '', Hoja::mxn($k['combTotal']), Hoja::mxn($k['marTotal']), Hoja::mxn($k['consTotal']), Hoja::mxn($k['varTotal'])]]);
    }

    private function extraordinarios(Hoja $h, array $data, array $k): void
    {
        $l = $h->l;
        $H = Recibo::H;
        $M = Recibo::M;
        $f = fn($n) => (float) ($n ?? 0);
        $hay = array_filter($data['extraCuenta'], fn($c) => $c['saldoAnterior'] != 0 || $c['cargoMes'] != 0 || $c['abonosMes'] != 0 || $c['saldoPendiente'] != 0);
        if ($hay) {
            $h->seccion('Estado de extraordinarios por socio', Hoja::AMBER);
            $colW = ($h->cw - 12) / 2;
            $cardH = 26 + 3 * 16 + 40 + 12;
            $h->necesito($cardH + 6);
            foreach ($data['extraCuenta'] as $i => $c) {
                $cx = $M + $i * ($colW + 12);
                $top = $h->y;
                $l->rect($cx, $H - $top - $cardH, $colW, $cardH, Hoja::WHITE, Hoja::BORDER, 1);
                $l->rect($cx, $H - $top - 3, $colW, 3, Hoja::AMBER);
                $yy = $top + 22;
                $l->texto($h->ajustar($c['nombre'], 11, $colW - 24, true), $cx + 12, $H - $yy, 11, true, Hoja::TEXT);
                $yy += 18;
                $fila = function (string $desc, string $val, array $col) use ($l, $h, $cx, $colW, &$yy, $H) {
                    $l->texto($desc, $cx + 12, $H - $yy, 8, false, Hoja::MUTED);
                    $h->TR($val, $cx + $colW - 12, $H - $yy, 8.5, false, $col);
                    $l->linea($cx + 12, $H - $yy - 5, $cx + $colW - 12, $H - $yy - 5, 0.4, Hoja::BG2);
                    $yy += 16;
                };
                $fila('Saldo anterior pendiente', Hoja::mxn($f($c['saldoAnterior'])), Hoja::RED);
                $fila('Cargo del mes (50%)', '+ ' . Hoja::mxn($f($c['cargoMes'])), Hoja::RED);
                $fila('Abonos del mes', ($f($c['abonosMes']) > 0 ? '- ' : '') . Hoja::mxn($f($c['abonosMes'])), $f($c['abonosMes']) > 0 ? Hoja::GREEN : Hoja::MUTED);
                $l->linea($cx + 12, $H - $yy + 8, $cx + $colW - 12, $H - $yy + 8, 1.5, Hoja::BG2);
                $l->texto('SALDO PENDIENTE', $cx + 12, $H - $yy - 4, 7, true, Hoja::MUTED);
                $l->texto('Gastos extraordinarios', $cx + 12, $H - $yy - 13, 6.2, false, Hoja::MUTED);
                $h->TR(Hoja::mxn($f($c['saldoPendiente'])), $cx + $colW - 12, $H - $yy - 10, 15, true, $f($c['saldoPendiente']) > 0 ? Hoja::RED : Hoja::GREEN);
            }
            $h->y += $cardH + 4;
        }

        if ($data['extraordinarios']) {
            $cw = $h->cw;
            $h->seccion('Gastos extraordinarios', Hoja::AMBER);
            $h->tabla([
                ['titulo' => '#', 'w' => 34, 'get' => fn($r, $i) => 'E-' . ($i + 1)],
                ['titulo' => 'FECHA', 'w' => 42, 'get' => fn($r) => Recibo::cortaFecha((string) $r['fecha'])],
                ['titulo' => 'PROVEEDOR', 'w' => 88, 'get' => fn($r) => $r['proveedor'] ?: '—'],
                ['titulo' => 'CONCEPTO', 'w' => $cw - 34 - 42 - 88 - 50 - 76 - 50 - 56, 'get' => fn($r) => $r['concepto'] ?: '—'],
                ['titulo' => 'USD', 'w' => 50, 'alinear' => 'r', 'get' => fn($r) => $r['moneda'] === 'USD' ? '$' . Hoja::nf($r['monto_original']) : '—'],
                ['titulo' => 'MXN', 'w' => 76, 'alinear' => 'r', 'get' => fn($r) => Hoja::mxn($f($r['monto_mxn']))],
                ['titulo' => 'PAGADO', 'w' => 50, 'alinear' => 'c', 'get' => fn($r) => $r['liquidado'] ? 'Pagado' : 'Pendiente', 'color' => fn($r) => $r['liquidado'] ? Hoja::GREEN : Hoja::AMBER],
                ['titulo' => 'COMPR.', 'w' => 56, 'alinear' => 'c', 'get' => fn($r) => $r['comprobante_folio'] ?: '—'],
            ], $data['extraordinarios'], [
                ['', '', '', 'Total extraordinarios', '', Hoja::mxn($k['extraTotal']), '', ''],
                ['', '', '', '50% por socio', '', Hoja::mxn($k['extraMitad']), '', ''],
            ]);
        }
    }
}
