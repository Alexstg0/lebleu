<?php
namespace Lebleu\Modulos\Reportes;

use Lebleu\Compartido\Pdf\Recibo;
use Lebleu\Modulos\Recurrentes\GeneradorRecibo;

// Recibo en PDF (formato SOLMEX) de un gasto operativo; lo adjunta al gasto reemplazando el anterior.
final class ReciboGastoPdf implements GeneradorRecibo
{
    private const CATEGORIAS = [
        'nomina' => 'Nómina', 'admin' => 'Administración', 'servicio' => 'Servicio',
        'refaccion' => 'Refacción', 'consumible' => 'Consumible', 'impuesto' => 'Impuesto', 'otro' => 'Gasto',
    ];

    public function __construct(private ReportesRepositorio $repo = new ReportesRepositorio()) {}

    public function paraGasto(int $gastoId): bool
    {
        $g = $this->repo->gastoParaRecibo($gastoId);
        if (!$g) {
            return false;
        }
        $emb = $g['embarcacion'] ?: 'Le Bleu';
        $rs = $g['razon_social'] ?: 'Arrendadora Acma S de RL de CV';
        $perTxt = Recibo::capMes((int) $g['mes']) . ' ' . $g['anio'];
        $fecha = Recibo::largaFecha((string) $g['fecha']);
        $monto = (float) $g['monto_mxn'];
        $folio = $g['comprobante_folio'] ?: 'GASTO-' . $g['id'];
        $proveedor = $g['proveedor'] ?: '—';
        $esNomina = $g['categoria'] === 'nomina';

        $pdf = Recibo::unaPagina(Recibo::logo('solmex-white'), [
            'titulo' => $esNomina ? 'Recibo de nómina' : 'Nota de gasto',
            'periodoTxt' => $perTxt,
            'etiqueta' => 'Recibo',
            'nombre' => $proveedor,
            'sub' => $g['concepto'] . ' · ' . $fecha,
            'pairs' => [
                [['EMBARCACIÓN', $emb], ['PERÍODO', $perTxt]],
                [['PARTIDA', self::CATEGORIAS[$g['categoria']] ?? 'Gasto'], ['FECHA', $fecha]],
                [['CONCEPTO', (string) $g['concepto']], ['COMPROBANTE', $folio]],
            ],
            'total' => $monto,
            'totalLabel' => 'TOTAL PAGADO',
            'observaciones' => "El presente recibo ampara el gasto \"{$g['concepto']}\" de la embarcación $emb ($rs) por " . Recibo::mxn($monto)
                . " MXN, con fecha $fecha. Proveedor: $proveedor. Consultas: int.financiera@solmex.mx.",
            'firmaIzq' => ['nombre' => 'Kevin Flores', 'rol' => 'Revisó y autorizó'],
            'firmaDer' => ['nombre' => $proveedor, 'rol' => 'Recibí de conformidad'],
        ]);
        $this->repo->guardarGenerado('gasto_id', $gastoId, "Recibo $folio.pdf", $pdf);
        return true;
    }
}
