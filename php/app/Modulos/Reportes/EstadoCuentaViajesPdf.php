<?php
namespace Lebleu\Modulos\Reportes;

use Lebleu\Compartido\Pdf\Recibo;

// Sección "Reporte de viajes — detalle por salida" del estado de cuenta en PDF (tarjetas de dos en dos).
final class EstadoCuentaViajesPdf
{
    public function dibujar(Hoja $h, array $data, array $p): void
    {
        $viajes = $data['viajes'];
        if (!$viajes) {
            return;
        }
        $h->seccion('Reporte de viajes — detalle por salida');
        $l = $h->l;
        [$H, $M] = [Recibo::H, Recibo::M];
        $colW = ($h->cw - 12) / 2;
        $primerSocio = $data['balances'][0]['socio_id'] ?? null;
        $f = fn($n) => (float) ($n ?? 0);
        $alto = fn($v) => 26 + (int) ceil(count($v['horometros'] ?? []) / 2) * 26 + 3 * 15 + 22 + 10;

        for ($i = 0; $i < count($viajes); $i += 2) {
            $par = array_slice($viajes, $i, 2);
            $hMax = max(array_map($alto, $par));
            $h->necesito($hMax + 8);
            foreach ($par as $k => $v) {
                $cx = $M + $k * ($colW + 12);
                $top = $h->y;
                $l->rect($cx, $H - $top - $hMax, $colW, $hMax, Hoja::WHITE, Hoja::BORDER, 1);
                $l->rect($cx, $H - $top - 26, $colW, 26, $v['socio_id'] === $primerSocio ? Hoja::OCEAN : Hoja::GREEN);
                $l->texto(Recibo::cortaFecha((string) $v['fecha']) . ' / ' . $p['anio'], $cx + 10, $H - $top - 11, 8, true, Hoja::WHITE);
                $l->texto($h->ajustar((string) ($v['cliente'] ?? $v['socio'] ?? ''), 8.5, $colW - 110, true), $cx + 10, $H - $top - 21, 8.5, true, Hoja::WHITE);
                $duracion = $v['duracion_horas'] ? Hoja::nf($v['duracion_horas'], 0) . ' Hrs' : '—';
                $h->TR($duracion . ' · ' . ($v['num_personas'] ?? 'NA') . ' pers.', $cx + $colW - 10, $H - $top - 16, 7, false, Hoja::WHITE);
                $yy = $top + 38;
                $horos = $v['horometros'] ?? [];
                foreach ($horos as $j => $ho) {
                    $hx = $cx + 10 + ($j % 2) * (($colW - 20) / 2);
                    $l->texto(mb_strtoupper((string) ($ho['etiqueta'] ?? '')), $hx, $H - $yy, 6, true, Hoja::MUTED);
                    $l->texto(Hoja::nf($ho['lectura_inicio']) . ' › ' . Hoja::nf($ho['lectura_fin']), $hx, $H - $yy - 9, 8, false, Hoja::TEXT);
                    if ($j % 2 === 1) {
                        $yy += 26;
                    }
                }
                if (count($horos) % 2 === 1) {
                    $yy += 26;
                }
                $costo = function (string $lbl, ?string $sub, string $val) use ($l, $h, $cx, $colW, &$yy, $H) {
                    $l->texto($lbl, $cx + 10, $H - $yy, 7.5, false, Hoja::TEXT);
                    if ($sub) {
                        $l->texto($h->ajustar($sub, 6.2, $colW - 120), $cx + 10 + $l->ancho($lbl, 7.5) + 5, $H - $yy, 6.2, false, Hoja::MUTED);
                    }
                    $h->TR($val, $cx + $colW - 10, $H - $yy, 8, false, Hoja::TEXT);
                    $yy += 15;
                };
                $costo('Combustible', Hoja::nf($v['litros']) . ' Lts × $' . Hoja::nf($v['precio_litro']), Hoja::mxn($f($v['costo_combustible'])));
                $costo('Marinero', $v['marinero'] ?: null, Hoja::mxn($f($v['costo_marinero'])));
                $costo('Consumibles', $v['consumibles_comprobante'] ?: null, $f($v['costo_consumibles']) > 0 ? Hoja::mxn($f($v['costo_consumibles'])) : '—');
                $l->linea($cx + 10, $H - $yy + 8, $cx + $colW - 10, $H - $yy + 8, 1, Hoja::BG2);
                $l->texto('Total viaje', $cx + 10, $H - $yy - 2, 8, true, Hoja::TEXT);
                $h->TR(Hoja::mxn($f($v['total'])), $cx + $colW - 10, $H - $yy - 2, 9.5, true, Hoja::NAVY);
            }
            $h->y += $hMax + 8;
        }
    }
}
