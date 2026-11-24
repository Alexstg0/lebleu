<?php
namespace Lebleu\Modulos\Reportes;

use Lebleu\Compartido\Formato;
use Lebleu\Compartido\Pdf\Fusionador;
use Lebleu\Compartido\Pdf\Lienzo;
use Lebleu\Compartido\Pdf\Recibo;

// Reporte general de soportes: portada por partida + los PDF de respaldo de esa partida.
final class SoportesPdf
{
    private const NAVY = [0.086, 0.153, 0.28];
    private const NAVY2 = [0.13, 0.22, 0.38];
    private const GRAY = [0.5, 0.55, 0.62];
    private const LIGHT = [0.93, 0.95, 0.97];

    public function __construct(private ReportesRepositorio $repo = new ReportesRepositorio(), private MarinerosPdf $marineros = new MarinerosPdf()) {}

    /** $incluirMarineros = false cuando va dentro del reporte completo (los recibos ya vienen en su sección). */
    public function generar(int $periodoId, bool $incluirMarineros = true): ?string
    {
        $p = $this->repo->periodoConEmbarcacion($periodoId);
        if (!$p) {
            return null;
        }
        $periodoTxt = Formato::mesNombre((int) $p['mes']) . ' ' . $p['anio'];
        $partidas = [
            ['Gastos Operativos del Mes', "Relación de gastos generales compartidos al 50% entre socios · $periodoTxt", $this->repo->soportes('gastos_operativos', $periodoId)],
            ['Insumos de Viajes', "Comprobantes de insumos asignados a los viajes · $periodoTxt", $this->repo->soportes('insumos', $periodoId)],
            ['Gastos Extraordinarios', "Comprobantes de gastos extraordinarios (liquidación separada) · $periodoTxt", $this->repo->soportes('extraordinarios', $periodoId)],
        ];
        $portada = fn(string $t, string $s) => $this->portada([
            'titulo' => $t, 'subtitulo' => $s, 'periodoTxt' => $periodoTxt, 'elabora' => 'Kevin Flores',
            'empresa' => $p['razon_social'] ?: 'Acma S de RL', 'embarcacion' => $p['embarcacion'] ?: 'Le Bleu',
        ]);

        $f = new Fusionador();
        $hay = false;
        foreach ($partidas as [$titulo, $subtitulo, $filas]) {
            if (!$filas) {
                continue;
            }
            $hay = true;
            $f->agregar($portada($titulo, $subtitulo));
            foreach ($filas as $r) {
                $this->incrustar($f, $r);
            }
        }
        if ($incluirMarineros) {
            $viajes = $this->repo->viajesDelPeriodo($periodoId);
            $conPortada = false;
            foreach ($viajes as $v) {
                $recibo = $this->marineros->reciboViaje((int) $v['id']);
                if (!$recibo) {
                    continue;
                }
                if (!$conPortada) {
                    $f->agregar($portada('Pagos a Marineros', "Recibos de pago a marineros por viaje · $periodoTxt"));
                    $conPortada = $hay = true;
                }
                $f->agregar($recibo);
            }
        }
        return $hay ? $f->generar() : null;
    }

    private function incrustar(Fusionador $f, array $r): void
    {
        try {
            $f->agregar((string) base64_decode($this->repo->datosDeAdjunto((int) $r['adjunto_id']), false));
        } catch (\Throwable) {
            $l = new Lienzo();
            $l->nuevaPagina();
            $l->texto('Comprobante no se pudo incrustar', 46, 760, 14, true, self::NAVY);
            $l->texto(($r['concepto'] ?? '') . ' · ' . ($r['comprobante_folio'] ?? ''), 46, 738, 10, false, self::GRAY);
            $f->agregar($l->salida());
        }
    }

    private function portada(array $d): string
    {
        [$W, $H, $M] = [Recibo::W, Recibo::H, Recibo::M];
        $l = new Lienzo();
        $l->nuevaPagina();
        $T = fn(string $s, float $x, float $top, float $tam, bool $neg = false, array $c = self::NAVY) => $l->texto($s, $x, $H - $top, $tam, $neg, $c);
        $TC = fn(string $s, float $top, float $tam, bool $neg = false, array $c = self::NAVY) => $T($s, ($W - $l->ancho($s, $tam, $neg)) / 2, $top, $tam, $neg, $c);

        $l->rect(0, $H - 250, $W, 250, Recibo::BAND);
        $TC(mb_strtoupper('Arrendadora Acma S de RL de CV · Embarcación ' . $d['embarcacion']), 44, 8.5, true, [1, 1, 1]);
        $logo = Recibo::logo('lebleu-hi');
        [$iw, $ih] = Lienzo::medidas($logo);
        $lw = 218;
        $lh = $ih / $iw * $lw;
        $l->imagen($logo, ($W - $lw) / 2, $H - 60 - $lh, $lw, $lh);
        $TC('Reporte de soportes', 205, 11, false, [0.75, 0.8, 0.87]);

        $l->elipse($M + 20, $H - 320, 20, 20, self::LIGHT, self::NAVY, 1.5);
        $T('DOCUMENTO DE SOPORTE', $M + 52, 316, 10, true);

        $top = 380;
        foreach ($this->partir($l, $d['titulo'], 30, true, $W - 2 * $M) as $ln) {
            $T($ln, $M, $top, 30, true);
            $top += 36;
        }
        $top += 6;
        $l->rect($M, $H - $top - 4, 3, 34, self::NAVY);
        foreach ($this->partir($l, $d['subtitulo'], 12, false, $W - 2 * $M - 16) as $i => $ln) {
            $T($ln, $M + 14, $top + $i * 16, 12, false, self::GRAY);
        }
        $top += 60;
        $l->rect($M, $H - $top, $W - 2 * $M, 1, self::NAVY);
        $top += 26;

        $col = ($W - 2 * $M) / 3;
        foreach ([['PERÍODO', $d['periodoTxt']], ['ELABORA', $d['elabora']], ['EMPRESA', $d['empresa']]] as $i => [$lbl, $val]) {
            $T($lbl, $M + $i * $col, $top, 8, true);
            $T($val, $M + $i * $col, $top + 15, 11, true);
        }

        $l->rect(0, 0, $W, 64, self::LIGHT);
        $T('Le Bleu', $M, $H - 30, 13, true);
        $r1 = $d['periodoTxt'] . ' · Cifras en MXN';
        $r2 = 'Confidencial — uso interno';
        $T($r1, $W - $M - $l->ancho($r1, 8.5), $H - 26, 8.5, false, self::GRAY);
        $T($r2, $W - $M - $l->ancho($r2, 8.5, true), $H - 40, 8.5, true, self::NAVY2);
        return $l->salida();
    }

    /** Parte un texto en renglones que quepan en $maxW (por palabras). @return array<int, string> */
    private function partir(Lienzo $l, string $texto, float $tam, bool $neg, float $maxW): array
    {
        $lineas = [];
        $linea = '';
        foreach (preg_split('/\s+/', $texto) ?: [] as $w) {
            $t = $linea !== '' ? "$linea $w" : $w;
            if ($l->ancho($t, $tam, $neg) > $maxW && $linea !== '') {
                $lineas[] = $linea;
                $linea = $w;
            } else {
                $linea = $t;
            }
        }
        if ($linea !== '') {
            $lineas[] = $linea;
        }
        return $lineas;
    }
}
