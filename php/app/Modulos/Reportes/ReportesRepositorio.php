<?php
namespace Lebleu\Modulos\Reportes;

use Lebleu\Core\Db;

// Consultas de los reportes en PDF (solo lectura, salvo el guardado de PDF generados en AdjuntosGenerados).
final class ReportesRepositorio
{
    private const VIAJE_SELECT =
        "SELECT v.id, v.fecha, v.duracion_horas, v.num_personas, v.costo_marinero,
                COALESCE(NULLIF(TRIM(v.marinero), ''), 'Sin asignar') AS marinero,
                COALESCE(v.cliente_nombre, c.nombre, so.nombre, 'Renta') AS cliente,
                p.anio, p.mes, e.nombre AS embarcacion, e.razon_social
           FROM viajes v JOIN periodos p ON p.id = v.periodo_id JOIN embarcaciones e ON e.id = p.embarcacion_id
           LEFT JOIN clientes c ON c.id = v.cliente_id LEFT JOIN socios so ON so.id = v.socio_id";

    public function periodoConEmbarcacion(int $periodoId): ?array
    {
        return Db::fila(
            'SELECT p.anio, p.mes, e.nombre AS embarcacion, e.razon_social
               FROM periodos p JOIN embarcaciones e ON e.id = p.embarcacion_id WHERE p.id = ?',
            [$periodoId]
        );
    }

    public function viaje(int $viajeId): ?array
    {
        return Db::fila(self::VIAJE_SELECT . ' WHERE v.id = ?', [$viajeId]);
    }

    public function viajesDelPeriodo(int $periodoId): array
    {
        return Db::filas(self::VIAJE_SELECT . ' WHERE v.periodo_id = ? ORDER BY v.fecha, v.id', [$periodoId]);
    }

    /** PDF de respaldo (base64) de una partida del periodo. @param string $tabla gastos_operativos | insumos | extraordinarios */
    public function soportes(string $tabla, int $periodoId): array
    {
        $col = ['gastos_operativos' => 'gasto_id', 'insumos' => 'insumo_id', 'extraordinarios' => 'extraordinario_id'][$tabla]
            ?? throw new \LogicException("Tabla sin soportes: $tabla");
        return Db::filas(
            "SELECT a.datos, t.concepto, t.comprobante_folio, t.monto_mxn
               FROM adjuntos a JOIN `$tabla` t ON t.id = a.`$col`
              WHERE t.periodo_id = ? ORDER BY t.fecha, t.id, a.id",
            [$periodoId]
        );
    }

    public function cajaMovimientos(): array
    {
        return Db::filas(
            'SELECT id, fecha, tipo, caja_numero, factura, proveedor, concepto, monto, abono, observaciones
               FROM caja_chica ORDER BY fecha, id'
        );
    }

    public function cajaAdjuntos(): array
    {
        return Db::filas('SELECT id, caja_id, nombre, datos FROM adjuntos WHERE caja_id IS NOT NULL ORDER BY caja_id, id');
    }

    /** Gasto operativo con los datos que necesita su recibo. */
    public function gastoParaRecibo(int $gastoId): ?array
    {
        return Db::fila(
            "SELECT go.id, go.proveedor, go.concepto, go.categoria, go.monto_mxn, go.comprobante_folio, go.fecha,
                    e.nombre AS embarcacion, e.razon_social, YEAR(go.fecha) AS anio, MONTH(go.fecha) AS mes
               FROM gastos_operativos go JOIN periodos p ON p.id = go.periodo_id JOIN embarcaciones e ON e.id = p.embarcacion_id
              WHERE go.id = ?",
            [$gastoId]
        );
    }

    /** Guarda un PDF generado por el sistema, reemplazando el anterior de la misma partida. @param string $columna gasto_id | periodo_id */
    public function guardarGenerado(string $columna, int $id, string $nombre, string $pdf): void
    {
        if (!in_array($columna, ['gasto_id', 'periodo_id'], true)) {
            throw new \LogicException("Columna no permitida: $columna");
        }
        Db::ejecutar("DELETE FROM adjuntos WHERE `$columna` = ? AND generado = 1", [$id]);
        Db::ejecutar(
            "INSERT INTO adjuntos (`$columna`, nombre, mime, datos, generado) VALUES (?, ?, 'application/pdf', ?, 1)",
            [$id, $nombre, base64_encode($pdf)]
        );
    }
}
