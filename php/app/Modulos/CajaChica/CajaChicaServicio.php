<?php
namespace Lebleu\Modulos\CajaChica;

use Lebleu\Compartido\Dinero;
use Lebleu\Modulos\Adjuntos\AdjuntosServicio;

// Movimientos de caja chica con su balance corrido (+ abono − monto) y sus respaldos.
final class CajaChicaServicio
{
    public function movimientos(): array
    {
        $adjuntos = (new AdjuntosServicio())->deCajaChica();
        $saldo = 0;
        return array_map(function ($m) use (&$saldo, $adjuntos) {
            $saldo += Dinero::aCentavos($m['abono']) - Dinero::aCentavos($m['monto']);
            return [
                'id' => (int) $m['id'], 'fecha' => $m['fecha'], 'tipo' => $m['tipo'],
                'caja_numero' => $m['caja_numero'] !== null ? (int) $m['caja_numero'] : null,
                'factura' => $m['factura'], 'proveedor' => $m['proveedor'], 'concepto' => $m['concepto'],
                'monto' => $m['monto'], 'abono' => $m['abono'], 'observaciones' => $m['observaciones'],
                'balance' => Dinero::aFloat($saldo), 'adjuntos' => $adjuntos[(int) $m['id']] ?? [],
            ];
        }, (new CajaChicaRepositorio())->todos());
    }
}
