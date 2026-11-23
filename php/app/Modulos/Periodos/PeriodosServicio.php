<?php
namespace Lebleu\Modulos\Periodos;

use Lebleu\Core\Db;

// Reglas de periodos: crear con arrastre de saldos, cerrar/reabrir y bloqueo de periodos cerrados.
final class PeriodosServicio
{
    private PeriodosRepositorio $repo;

    public function __construct(?PeriodosRepositorio $repo = null, private ?ArchivadorEstado $archivador = null)
    {
        $this->repo = $repo ?? new PeriodosRepositorio();
        $this->archivador ??= new SinArchivo();
    }

    public function listar(): array
    {
        return $this->repo->listar();
    }

    /** Datos de la pantalla de Periodos: cada periodo con su balance y su archivo, y el mes sugerido. */
    public function resumen(): array
    {
        $periodos = $this->repo->listar();
        $balances = $this->repo->balancePorPeriodo();
        $archivos = $this->repo->archivoPorPeriodo();
        $ultimo = $periodos[0] ?? null;
        $mes = (int) ($ultimo['mes'] ?? 0) + 1;
        $anio = (int) ($ultimo['anio'] ?? date('Y'));
        if ($mes > 12) {
            [$mes, $anio] = [1, $anio + 1];
        }
        return [
            'periodos' => array_map(fn($p) => $p + [
                'balance' => $balances[$p['id']] ?? '0',
                'archivo_id' => $archivos[$p['id']] ?? null,
            ], $periodos),
            'ultimo' => $ultimo,
            'sugerido' => ['anio' => $anio, 'mes' => $mes],
        ];
    }

    /** Crea el periodo, arrastra el saldo de cada socio y cierra el periodo anterior. Devuelve el id nuevo. */
    public function crear(array $d): int
    {
        $emb = (int) ($d['embarcacion_id'] ?? 0) ?: 1;
        $anio = (int) ($d['anio'] ?? 0);
        $mes = (int) ($d['mes'] ?? 0);
        if ($anio < 2000 || $anio > 2100 || $mes < 1 || $mes > 12) {
            throw new \InvalidArgumentException('Año o mes inválido.');
        }
        $anteriorId = $this->repo->idAnterior($emb, $anio, $mes);

        $nuevoId = Db::transaccion(function () use ($d, $emb, $anio, $mes, $anteriorId) {
            $id = $this->repo->insertar(
                $emb, $anio, $mes,
                (string) (self::numero($d['tipo_cambio'] ?? null) ?: 1),
                (string) (self::numero($d['precio_litro'] ?? null) ?: 0),
                (string) (self::numero($d['cargo_administracion'] ?? null) ?: 0)
            );
            foreach ($this->repo->idsSociosActivos() as $socioId) {
                $this->repo->insertarSaldoInicio($id, $socioId, $anteriorId ? $this->saldoDeArrastre($anteriorId, $socioId) : '0');
            }
            return $id;
        });

        if ($anteriorId) {
            $this->cerrar($anteriorId);
        }
        return $nuevoId;
    }

    /** Crea el periodo del mes en curso si aún no existe (cron diario), copiando T/C, litro y cargo admin. */
    public function asegurarPeriodoActual(string $hoyISO): ?int
    {
        [$anio, $mes] = array_map('intval', explode('-', $hoyISO));
        if ($this->repo->existeEnMes(1, $anio, $mes)) {
            return null;
        }
        $u = $this->repo->ultimo(1);
        return $this->crear([
            'embarcacion_id' => 1, 'anio' => $anio, 'mes' => $mes,
            'tipo_cambio' => $u['tipo_cambio'] ?? 1,
            'precio_litro' => $u['precio_litro'] ?? 0,
            'cargo_administracion' => $u['cargo_administracion'] ?? 0,
        ]);
    }

    /** Cierra el periodo y archiva su estado de cuenta. Devuelve si el archivo se generó. */
    public function cerrar(int $periodoId): bool
    {
        $this->repo->fijarEstado($periodoId, 'cerrado');
        try {
            return $this->archivador->archivar($periodoId);
        } catch (\Throwable $e) {
            error_log('cerrar periodo, archivo: ' . $e->getMessage());
            return false;
        }
    }

    public function reabrir(int $periodoId): void
    {
        $this->repo->fijarEstado($periodoId, 'abierto');
    }

    public function etiqueta(int $periodoId): ?string
    {
        $p = $this->repo->porId($periodoId);
        return $p ? "{$p['mes']}/{$p['anio']}" : null;
    }

    /** null si el periodo está abierto (o no se pidió uno); mensaje de error si está cerrado o no existe. */
    public function mensajeSiCerrado(?int $periodoId): ?string
    {
        if (!$periodoId) {
            return null;
        }
        $p = $this->repo->porId($periodoId);
        if (!$p) {
            return 'El periodo no existe.';
        }
        return $p['estado'] === 'cerrado'
            ? 'El periodo está cerrado. Reábrelo en la pestaña Periodos para poder capturar o editar.'
            : null;
    }

    /** saldo_fin congelado del periodo anterior; si aún no existe, el balance operativo (y se congela). */
    private function saldoDeArrastre(int $anteriorId, int $socioId): string
    {
        $fin = $this->repo->saldoFin($anteriorId, $socioId)['saldo_fin'] ?? null;
        if ($fin === null) {
            $fin = $this->repo->balanceOperativo($anteriorId, $socioId) ?? '0';
            $this->repo->fijarSaldoFin($anteriorId, $socioId, $fin);
        }
        return (string) $fin;
    }

    private static function numero(mixed $v): float
    {
        return is_numeric($v) ? (float) $v : 0.0;
    }
}
