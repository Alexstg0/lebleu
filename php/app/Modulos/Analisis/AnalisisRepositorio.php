<?php
namespace Lebleu\Modulos\Analisis;

use Lebleu\Core\Db;

// Consultas agregadas de la pantalla Análisis (solo lectura).
final class AnalisisRepositorio
{
    /** Una fila por periodo: gasto operativo, variable, extraordinario, viajes y litros. */
    public function porMes(): array
    {
        return Db::filas(
            'SELECT p.id, p.anio, p.mes,
                    COALESCE((SELECT SUM(monto_mxn) FROM gastos_operativos g WHERE g.periodo_id = p.id), 0) AS operativos,
                    COALESCE((SELECT SUM(total) FROM viajes v WHERE v.periodo_id = p.id), 0) AS variables,
                    COALESCE((SELECT SUM(monto_mxn) FROM extraordinarios e WHERE e.periodo_id = p.id), 0) AS extraordinarios,
                    COALESCE((SELECT COUNT(*) FROM viajes v WHERE v.periodo_id = p.id), 0) AS num_viajes,
                    COALESCE((SELECT SUM(litros) FROM viajes v WHERE v.periodo_id = p.id), 0) AS litros
               FROM periodos p ORDER BY p.anio, p.mes'
        );
    }

    public function viajes(): array
    {
        return Db::filas(
            "SELECT v.fecha, v.litros, COALESCE(v.cliente_nombre, c.nombre, so.nombre, 'Renta') AS cliente
               FROM viajes v LEFT JOIN clientes c ON c.id = v.cliente_id LEFT JOIN socios so ON so.id = v.socio_id
              ORDER BY v.fecha, v.id"
        );
    }
}
