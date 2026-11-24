<?php
namespace Lebleu\Modulos\Reportes;

use Lebleu\Compartido\Pdf\Lienzo;
use Lebleu\Compartido\Pdf\Recibo;

// Reporte de marineros (resumen + un recibo por viaje) y recibo de pago de un viaje.
final class MarinerosPdf
{
    private const LOGO = 'lebleu-hi';

    public function __construct(private ReportesRepositorio $repo = new ReportesRepositorio()) {}

    /** Recibo de UN viaje (botón "Ver PDF" de Movimientos). */
    public function reciboViaje(int $viajeId): ?string
    {
        $v = $this->repo->viaje($viajeId);
        if (!$v) {
            return null;
        }
        return Recibo::unaPagina(Recibo::logo(self::LOGO), self::datosRecibo($v, [
            'emb' => $v['embarcacion'] ?: 'Le Bleu',
            'rs' => $v['razon_social'] ?: 'Arrendadora Acma S de RL de CV',
            'periodoTxt' => Recibo::capMes((int) $v['mes']) . ' ' . $v['anio'],
        ]));
    }

    public function reporte(int $periodoId): ?string
    {
        $p = $this->repo->periodoConEmbarcacion($periodoId);
        if (!$p) {
            return null;
        }
        $viajes = $this->repo->viajesDelPeriodo($periodoId);
        if (!$viajes) {
            return null;
        }
        $meta = [
            'emb' => $p['embarcacion'] ?: 'Le Bleu', 'rs' => $p['razon_social'] ?: 'Arrendadora Acma S de RL de CV',
            'periodoTxt' => Recibo::capMes((int) $p['mes']) . ' ' . $p['anio'],
        ];
        $logo = Recibo::logo(self::LOGO);
        $l = new Lienzo();
        $l->nuevaPagina();
        $this->resumen($l, $logo, $viajes, $meta);
        foreach ($viajes as $i => $v) {
            $l->nuevaPagina();
            Recibo::dibujar($l, $logo, self::datosRecibo($v, $meta + ['label' => 'N.º ' . ($i + 1) . ' de ' . count($viajes)]));
        }
        return $l->salida();
    }

    private function resumen(Lienzo $l, string $logo, array $viajes, array $meta): void
    {
        [$W, $H, $M] = [Recibo::W, Recibo::H, Recibo::M];
        Recibo::encabezado($l, $logo, 'Reporte de Marineros', $meta['periodoTxt']);
        $l->texto('Relación de viajes y pagos a marineros', $M, $H - 118, 17, true, Recibo::NAVY);
        $l->texto($meta['emb'] . ' · ' . $meta['rs'], $M, $H - 136, 10, false, Recibo::GRAY);

        $cw = $W - 2 * $M;
        [$c1, $c2, $c3, $c4, $c5] = [$M + 8, $M + 62, $M + 250, $M + $cw - 130, $M + $cw - 8];
        $y = $H - 162;
        $l->rect($M, $y - 22, $cw, 22, Recibo::NAVY);
        foreach ([['FECHA', $c1], ['CLIENTE / VIAJE', $c2], ['MARINERO', $c3], ['DUR.', $c4]] as [$t, $x]) {
            $l->texto($t, $x, $y - 15, 8.5, true, Recibo::WHITE);
        }
        $l->texto('PAGO', $c5 - $l->ancho('PAGO', 8.5, true), $y - 15, 8.5, true, Recibo::WHITE);
        $y -= 22;
        $total = 0.0;
        foreach (array_values($viajes) as $i => $v) {
            $rh = 22;
            $costo = (float) $v['costo_marinero'];
            $total += $costo;
            if ($i % 2 === 1) {
                $l->rect($M, $y - $rh, $cw, $rh, [0.97, 0.98, 0.99]);
            }
            $l->texto(Recibo::cortaFecha((string) $v['fecha']), $c1, $y - 15, 9, false, Recibo::NAVY);
            $l->texto(mb_substr((string) $v['cliente'], 0, 30), $c2, $y - 15, 9, false, Recibo::NAVY);
            $l->texto(mb_substr((string) $v['marinero'], 0, 26), $c3, $y - 15, 9, false, $v['marinero'] === 'Sin asignar' ? Recibo::GRAY : Recibo::NAVY);
            $l->texto($v['duracion_horas'] !== null ? Recibo::numJs($v['duracion_horas']) . ' h' : '—', $c4, $y - 15, 9, false, Recibo::NAVY);
            $pg = Recibo::mxn($costo);
            $l->texto($pg, $c5 - $l->ancho($pg, 9), $y - 15, 9, false, Recibo::NAVY);
            $l->linea($M, $y - $rh, $M + $cw, $y - $rh, 0.5, Recibo::BORDER);
            $y -= $rh;
        }
        $l->rect($M, $y - 26, $cw, 26, [0.94, 0.95, 0.97]);
        $n = count($viajes);
        $l->texto("TOTAL ($n viaje" . ($n === 1 ? '' : 's') . ')', $c1, $y - 17, 11, true, Recibo::NAVY);
        $gt = Recibo::mxn($total);
        $l->texto($gt, $c5 - $l->ancho($gt, 12, true), $y - 17, 12, true, Recibo::NAVY);
    }

    public static function datosRecibo(array $v, array $meta): array
    {
        $fecha = Recibo::largaFecha((string) $v['fecha']);
        $costo = (float) $v['costo_marinero'];
        return [
            'titulo' => 'Recibo de pago a marinero',
            'periodoTxt' => $meta['periodoTxt'],
            'etiqueta' => 'Recibo de pago' . (!empty($meta['label']) ? '  ·  ' . $meta['label'] : ''),
            'nombre' => $v['marinero'],
            'sub' => "Viaje del $fecha · " . $v['cliente'],
            'pairs' => [
                [['MARINERO', $v['marinero']], ['FECHA', $fecha]],
                [['CLIENTE / VIAJE', (string) $v['cliente']], ['DURACIÓN', $v['duracion_horas'] !== null ? Recibo::numJs($v['duracion_horas']) . ' h' : '—']],
                [['EMBARCACIÓN', $meta['emb']], ['N.º DE PERSONAS', $v['num_personas'] !== null ? (string) $v['num_personas'] : '—']],
            ],
            'total' => $costo,
            'totalLabel' => 'TOTAL A PAGAR',
            'observaciones' => "El presente recibo ampara el pago al marinero {$v['marinero']} por el viaje del $fecha ({$v['cliente']}) de la embarcación {$meta['emb']} ({$meta['rs']}), por " . Recibo::mxn($costo) . ' MXN.',
            'firmaIzq' => ['nombre' => $v['marinero'], 'rol' => 'Recibí de conformidad'],
            'firmaDer' => ['nombre' => 'Le Bleu — Administración', 'rol' => 'Pagó'],
        ];
    }
}
