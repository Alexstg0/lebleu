<?php
namespace Lebleu\Modulos\EstadoCuenta;

use Lebleu\Core\Db;

// Consultas de lectura del estado de cuenta (único lugar del módulo con SQL).
final class EstadoCuentaRepositorio
{
    private const CLIENTE_VIAJE = "COALESCE(v.cliente_nombre, c.nombre, so.nombre, 'Renta')";

    public function periodo(int $id): ?array
    {
        return Db::fila(
            'SELECT p.id, p.anio, p.mes, p.tipo_cambio, p.precio_litro, p.cargo_administracion, p.estado,
                    e.nombre AS embarcacion, e.razon_social, p.embarcacion_id
               FROM periodos p JOIN embarcaciones e ON e.id = p.embarcacion_id WHERE p.id = ?',
            [$id]
        );
    }

    public function balances(int $periodoId): array
    {
        return Db::filas(
            'SELECT b.socio_id, so.nombre, b.saldo_inicio, b.aportaciones, b.gastos_generales,
                    b.gastos_variables, b.utilidad_renta, b.balance_operativo
               FROM v_balance_operativo b JOIN socios so ON so.id = b.socio_id
              WHERE b.periodo_id = ? ORDER BY b.socio_id',
            [$periodoId]
        );
    }

    public function variable(int $periodoId): array
    {
        return Db::filas(
            'SELECT socio_id, num_viajes, combustible, marinero, consumibles, total_variable
               FROM v_variable_por_socio WHERE periodo_id = ? ORDER BY socio_id',
            [$periodoId]
        );
    }

    public function extraPorSocio(int $periodoId): array
    {
        return Db::filas(
            'SELECT e.socio_id, so.nombre, e.liquidacion_extraordinaria
               FROM v_extraordinarios_por_socio e JOIN socios so ON so.id = e.socio_id
              WHERE e.periodo_id = ? ORDER BY e.socio_id',
            [$periodoId]
        );
    }

    public function operativos(int $periodoId): array
    {
        return Db::filas(
            'SELECT id, fecha, proveedor, concepto, categoria, moneda, monto_original, tipo_cambio, monto_mxn, comprobante_folio
               FROM gastos_operativos WHERE periodo_id = ? ORDER BY fecha, id',
            [$periodoId]
        );
    }

    public function extraordinarios(int $periodoId): array
    {
        return Db::filas(
            'SELECT id, fecha, proveedor, concepto, categoria, moneda, monto_original, tipo_cambio, monto_mxn, comprobante_folio, liquidado
               FROM extraordinarios WHERE periodo_id = ? ORDER BY fecha, id',
            [$periodoId]
        );
    }

    public function insumos(int $periodoId): array
    {
        return Db::filas(
            'SELECT i.id, i.fecha, i.viaje_id, i.categoria, i.proveedor, i.concepto, i.moneda,
                    i.monto_original, i.tipo_cambio, i.monto_mxn, i.comprobante_folio,
                    ' . self::CLIENTE_VIAJE . ' AS viaje_cliente, v.fecha AS viaje_fecha
               FROM insumos i
               LEFT JOIN viajes v ON v.id = i.viaje_id
               LEFT JOIN clientes c ON c.id = v.cliente_id
               LEFT JOIN socios so ON so.id = v.socio_id
              WHERE i.periodo_id = ? ORDER BY i.fecha, i.id',
            [$periodoId]
        );
    }

    /**
     * Un adjunto por movimiento (se prefiere el recibo generado). $tabla: gastos_operativos | insumos | extraordinarios.
     * @return array<int, int> id del movimiento => id del adjunto
     */
    public function adjuntoPreferido(string $tabla, int $periodoId): array
    {
        $col = ['gastos_operativos' => 'gasto_id', 'insumos' => 'insumo_id', 'extraordinarios' => 'extraordinario_id'][$tabla];
        $filas = Db::filas(
            "SELECT a.$col AS movimiento, a.id FROM adjuntos a JOIN $tabla t ON t.id = a.$col
              WHERE t.periodo_id = ? ORDER BY a.$col, a.generado DESC, a.id",
            [$periodoId]
        );
        $salida = [];
        foreach ($filas as $f) {
            $salida[(int) $f['movimiento']] ??= (int) $f['id'];
        }
        return $salida;
    }

    /** Extraordinarios del mes inmediatamente anterior (misma embarcación): total y pendiente de liquidar. */
    public function extraMesAnterior(int $periodoId, int $anio, int $mes): ?array
    {
        return Db::fila(
            'SELECT p.anio, p.mes, COALESCE(SUM(e.monto_mxn), 0) AS total,
                    COALESCE(SUM(CASE WHEN e.liquidado = 0 THEN e.monto_mxn END), 0) AS pendiente
               FROM periodos p LEFT JOIN extraordinarios e ON e.periodo_id = p.id
              WHERE p.embarcacion_id = (SELECT embarcacion_id FROM periodos WHERE id = ?)
                AND (p.anio * 12 + p.mes) = (? * 12 + ?) - 1
              GROUP BY p.anio, p.mes',
            [$periodoId, $anio, $mes]
        );
    }

    public function extraTotalAnterior(int $embarcacionId, int $claveMes): string
    {
        return (string) Db::fila(
            'SELECT COALESCE(SUM(e.monto_mxn), 0) AS t FROM extraordinarios e JOIN periodos p ON p.id = e.periodo_id
              WHERE p.embarcacion_id = ? AND (p.anio * 12 + p.mes) < ?',
            [$embarcacionId, $claveMes]
        )['t'];
    }

    public function sociosActivos(): array
    {
        return Db::filas('SELECT id, nombre, porcentaje FROM socios WHERE activo = 1 ORDER BY id');
    }

    public function abonosExtra(int $embarcacionId): array
    {
        return Db::filas(
            'SELECT a.socio_id, a.monto, (p.anio * 12 + p.mes) AS clave, a.periodo_id
               FROM abonos_extraordinarios a JOIN periodos p ON p.id = a.periodo_id WHERE p.embarcacion_id = ?',
            [$embarcacionId]
        );
    }

    public function viajes(int $periodoId): array
    {
        return Db::filas(
            'SELECT v.*, c.nombre AS cliente, so.nombre AS socio
               FROM viajes v LEFT JOIN clientes c ON c.id = v.cliente_id JOIN socios so ON so.id = v.socio_id
              WHERE v.periodo_id = ? ORDER BY v.fecha, v.id',
            [$periodoId]
        );
    }

    public function horometros(int $periodoId): array
    {
        return Db::filas(
            'SELECT h.viaje_id, h.lectura_inicio, h.lectura_fin, h.horas, m.etiqueta
               FROM viaje_horometros h JOIN motores m ON m.id = h.motor_id JOIN viajes v ON v.id = h.viaje_id
              WHERE v.periodo_id = ? ORDER BY h.motor_id',
            [$periodoId]
        );
    }

    public function renta(int $periodoId): array
    {
        return Db::fila(
            'SELECT COALESCE(SUM(monto), 0) AS ingresos, COALESCE(SUM(utilidad), 0) AS utilidad
               FROM ingresos_renta WHERE periodo_id = ?',
            [$periodoId]
        );
    }
}
