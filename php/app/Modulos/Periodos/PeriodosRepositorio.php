<?php
namespace Lebleu\Modulos\Periodos;

use Lebleu\Core\Db;

// Único lugar del módulo Periodos que escribe SQL.
final class PeriodosRepositorio
{
    public function listar(): array
    {
        return Db::filas(self::SELECT_CON_EMBARCACION . ' ORDER BY p.anio DESC, p.mes DESC');
    }

    /** @return array<int, string> periodo_id => suma del balance operativo de ambos socios */
    public function balancePorPeriodo(): array
    {
        return array_column(Db::filas('SELECT periodo_id, SUM(balance_operativo) AS total FROM v_balance_operativo GROUP BY periodo_id'), 'total', 'periodo_id');
    }

    /** @return array<int, int> periodo_id => id del estado de cuenta archivado más reciente */
    public function archivoPorPeriodo(): array
    {
        return array_map('intval', array_column(Db::filas(
            'SELECT periodo_id, MAX(id) AS id FROM adjuntos WHERE periodo_id IS NOT NULL AND generado = 1 GROUP BY periodo_id'
        ), 'id', 'periodo_id'));
    }

    public function porId(int $id): ?array
    {
        return Db::fila(self::SELECT_CON_EMBARCACION . ' WHERE p.id = ?', [$id]);
    }

    public function idAnterior(int $embarcacionId, int $anio, int $mes): ?int
    {
        $f = Db::fila(
            'SELECT id FROM periodos WHERE embarcacion_id = ? AND (anio * 12 + mes) < (? * 12 + ?)
              ORDER BY anio DESC, mes DESC LIMIT 1',
            [$embarcacionId, $anio, $mes]
        );
        return $f ? (int) $f['id'] : null;
    }

    public function existeEnMes(int $embarcacionId, int $anio, int $mes): bool
    {
        return (bool) Db::fila('SELECT id FROM periodos WHERE embarcacion_id = ? AND anio = ? AND mes = ?', [$embarcacionId, $anio, $mes]);
    }

    public function ultimo(int $embarcacionId): ?array
    {
        return Db::fila(
            'SELECT tipo_cambio, precio_litro, cargo_administracion FROM periodos
              WHERE embarcacion_id = ? ORDER BY anio DESC, mes DESC LIMIT 1',
            [$embarcacionId]
        );
    }

    public function insertar(int $embarcacionId, int $anio, int $mes, string $tipoCambio, string $precioLitro, string $cargoAdmin): int
    {
        Db::ejecutar(
            "INSERT INTO periodos (embarcacion_id, anio, mes, tipo_cambio, precio_litro, cargo_administracion, estado)
             VALUES (?, ?, ?, ?, ?, ?, 'abierto')",
            [$embarcacionId, $anio, $mes, $tipoCambio, $precioLitro, $cargoAdmin]
        );
        return Db::ultimoId();
    }

    /** @return int[] */
    public function idsSociosActivos(): array
    {
        return array_map('intval', array_column(Db::filas('SELECT id FROM socios WHERE activo = 1 ORDER BY id'), 'id'));
    }

    public function saldoFin(int $periodoId, int $socioId): ?array
    {
        return Db::fila('SELECT saldo_fin FROM saldos_socio WHERE periodo_id = ? AND socio_id = ?', [$periodoId, $socioId]);
    }

    public function balanceOperativo(int $periodoId, int $socioId): ?string
    {
        $f = Db::fila('SELECT balance_operativo FROM v_balance_operativo WHERE periodo_id = ? AND socio_id = ?', [$periodoId, $socioId]);
        return $f ? (string) $f['balance_operativo'] : null;
    }

    public function fijarSaldoFin(int $periodoId, int $socioId, string $saldo): void
    {
        Db::ejecutar('UPDATE saldos_socio SET saldo_fin = ? WHERE periodo_id = ? AND socio_id = ?', [$saldo, $periodoId, $socioId]);
    }

    public function insertarSaldoInicio(int $periodoId, int $socioId, string $saldo): void
    {
        Db::ejecutar('INSERT INTO saldos_socio (periodo_id, socio_id, saldo_inicio) VALUES (?, ?, ?)', [$periodoId, $socioId, $saldo]);
    }

    public function fijarEstado(int $id, string $estado): void
    {
        Db::ejecutar('UPDATE periodos SET estado = ? WHERE id = ?', [$estado, $id]);
    }

    private const SELECT_CON_EMBARCACION =
        'SELECT p.id, p.anio, p.mes, p.tipo_cambio, p.precio_litro, p.cargo_administracion, p.estado,
                e.nombre AS embarcacion, e.razon_social
           FROM periodos p JOIN embarcaciones e ON e.id = p.embarcacion_id';
}
