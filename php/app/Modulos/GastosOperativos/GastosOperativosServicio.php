<?php
namespace Lebleu\Modulos\GastosOperativos;

// Operaciones sobre gastos operativos que piden otros módulos (cargos recurrentes).
final class GastosOperativosServicio
{
    /**
     * Crea un gasto recurrente en pesos. Es idempotente: si ya existe uno con esa clave no hace nada.
     * @return int|null id del gasto nuevo, o null si ya existía
     */
    public function crearRecurrente(int $periodoId, string $fecha, string $proveedor, string $concepto, string $categoria, int|float $monto, string $folio, string $clave): ?int
    {
        $repo = new GastosOperativosRepositorio();
        if ($repo->existeClaveRecurrente($clave)) {
            return null;
        }
        try {
            return $repo->insertar([
                'periodo_id' => $periodoId, 'fecha' => $fecha, 'proveedor' => $proveedor, 'concepto' => $concepto,
                'categoria' => $categoria, 'moneda' => 'MXN', 'monto_original' => $monto, 'tipo_cambio' => 1,
                'comprobante_folio' => $folio, 'clave_recurrente' => $clave,
            ]);
        } catch (\PDOException $e) {
            if (($e->errorInfo[1] ?? 0) === 1062) {
                return null; // otro proceso (el cron o un clic simultáneo) lo creó un instante antes
            }
            throw $e;
        }
    }

    /** ¿Existe ya el cargo con esta clave y le falta su recibo? (un fallo anterior pudo dejarlo sin recibo). */
    public function idSinRecibo(string $clave): ?int
    {
        $repo = new GastosOperativosRepositorio();
        $id = $repo->idPorClaveRecurrente($clave);
        return $id !== null && !$repo->tieneRecibo($id) ? $id : null;
    }
}
