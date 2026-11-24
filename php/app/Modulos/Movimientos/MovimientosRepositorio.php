<?php
namespace Lebleu\Modulos\Movimientos;

use Lebleu\Core\Db;

// Lecturas de la pantalla Movimientos (único lugar del módulo con SQL).
final class MovimientosRepositorio
{
    /** Clave de fila (prefijo de la pantalla) => tabla editable. */
    private const TABLAS = [
        'op' => 'gastos_operativos', 'in' => 'insumos', 'vi' => 'viajes', 'ap' => 'aportaciones',
        'ex' => 'extraordinarios', 'ae' => 'abonos_extraordinarios', 're' => 'ingresos_renta',
    ];

    public static function tablaDe(string $prefijo): ?string
    {
        return self::TABLAS[$prefijo] ?? null;
    }

    /** Una fila completa para precargar su formulario de edición. */
    public function fila(string $prefijo, int $id): ?array
    {
        $tabla = self::TABLAS[$prefijo] ?? null;
        if ($tabla === null) {
            return null;
        }
        $fila = Db::fila("SELECT * FROM `$tabla` WHERE id = ?", [$id]);
        if ($fila && $prefijo === 'vi') {
            $fila['horometros'] = Db::filas('SELECT viaje_id, motor_id, lectura_inicio, lectura_fin FROM viaje_horometros WHERE viaje_id = ?', [$id]);
        }
        return $fila;
    }

    public function operativos(int $periodoId): array
    {
        return Db::filas(
            'SELECT id, fecha, proveedor, concepto, categoria, moneda, monto_original, tipo_cambio, monto_mxn, comprobante_folio, clave_recurrente
               FROM gastos_operativos WHERE periodo_id = ? ORDER BY fecha, id',
            [$periodoId]
        );
    }

    public function extraordinarios(int $periodoId): array
    {
        return Db::filas(
            'SELECT id, fecha, proveedor, concepto, categoria, moneda, monto_original, tipo_cambio, monto_mxn, comprobante_folio
               FROM extraordinarios WHERE periodo_id = ? ORDER BY fecha, id',
            [$periodoId]
        );
    }

    public function viajes(int $periodoId): array
    {
        return Db::filas(
            "SELECT v.*, COALESCE(v.cliente_nombre, c.nombre, so.nombre, 'Renta') AS cliente_disp, so.nombre AS socio_nombre
               FROM viajes v LEFT JOIN clientes c ON c.id = v.cliente_id LEFT JOIN socios so ON so.id = v.socio_id
              WHERE v.periodo_id = ? ORDER BY v.fecha, v.id",
            [$periodoId]
        );
    }

    public function aportaciones(int $periodoId): array
    {
        return Db::filas(
            'SELECT a.id, a.fecha, a.socio_id, so.nombre AS socio_nombre, a.monto, a.metodo, a.referencia
               FROM aportaciones a JOIN socios so ON so.id = a.socio_id WHERE a.periodo_id = ? ORDER BY a.fecha, a.id',
            [$periodoId]
        );
    }

    public function renta(int $periodoId): array
    {
        return Db::filas(
            'SELECT id, fecha, cliente, monto, costos_asociados, utilidad FROM ingresos_renta WHERE periodo_id = ? ORDER BY fecha, id',
            [$periodoId]
        );
    }

    public function abonosExtra(int $periodoId): array
    {
        return Db::filas(
            'SELECT a.id, a.fecha, a.socio_id, so.nombre AS socio_nombre, a.monto, a.metodo, a.referencia
               FROM abonos_extraordinarios a JOIN socios so ON so.id = a.socio_id WHERE a.periodo_id = ? ORDER BY a.fecha, a.id',
            [$periodoId]
        );
    }

    public function insumos(int $periodoId): array
    {
        return Db::filas(
            "SELECT i.id, i.fecha, i.viaje_id, i.categoria, i.proveedor, i.concepto, i.moneda, i.monto_original, i.tipo_cambio,
                    i.monto_mxn, i.comprobante_folio,
                    COALESCE(v.cliente_nombre, c.nombre, so.nombre, 'Renta') AS viaje_cliente, v.fecha AS viaje_fecha
               FROM insumos i LEFT JOIN viajes v ON v.id = i.viaje_id LEFT JOIN clientes c ON c.id = v.cliente_id
               LEFT JOIN socios so ON so.id = v.socio_id
              WHERE i.periodo_id = ? ORDER BY i.fecha, i.id",
            [$periodoId]
        );
    }

    public function horometros(int $periodoId): array
    {
        return Db::filas(
            'SELECT h.viaje_id, h.motor_id, h.lectura_inicio, h.lectura_fin
               FROM viaje_horometros h JOIN viajes v ON v.id = h.viaje_id WHERE v.periodo_id = ?',
            [$periodoId]
        );
    }
}
