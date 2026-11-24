<?php
namespace Lebleu\Modulos\Viajes;

use Lebleu\Compartido\Entrada;
use Lebleu\Compartido\Movimientos\MovimientoControlador;
use Lebleu\Compartido\Movimientos\MovimientoRepositorio;

// Viajes (bitácora): los registran el administrador y el capitán; solo el administrador los borra.
final class ViajesController extends MovimientoControlador
{
    protected function repo(): MovimientoRepositorio
    {
        return new ViajesRepositorio();
    }

    protected function tabla(): string
    {
        return 'viajes';
    }

    protected function roles(): array
    {
        return ['admin', 'capitan'];
    }

    protected function valores(array $b, bool $esAlta): array
    {
        $v = [
            'socio_id' => Entrada::entero($b, 'socio_id', 'El socio', false),
            'cliente_id' => Entrada::entero($b, 'cliente_id', 'El cliente', false),
            'cliente_nombre' => Entrada::textoOpcional($b, 'cliente_nombre', 'El nombre del cliente', 160),
            'marinero' => Entrada::textoOpcional($b, 'marinero', 'El marinero', 160),
            'fecha' => Entrada::fecha($b, 'fecha'),
            'duracion_horas' => self::ceroEsNulo(Entrada::numeroOpcional($b, 'duracion_horas', 'La duración')),
            'num_personas' => Entrada::entero($b, 'num_personas', 'El número de personas', false),
            'litros' => Entrada::numero($b, 'litros', 'Los litros', false, 0),
            'precio_litro' => Entrada::numero($b, 'precio_litro', 'El precio por litro', false, 0),
            'costo_combustible' => Entrada::numero($b, 'costo_combustible', 'El costo de combustible', false, 0),
            'costo_marinero' => Entrada::numero($b, 'costo_marinero', 'El costo del marinero', false, 0),
            'costo_consumibles' => Entrada::numero($b, 'costo_consumibles', 'El costo de consumibles', false, 0),
            'consumibles_comprobante' => Entrada::textoOpcional($b, 'consumibles_comprobante', 'El comprobante', 120),
            'bandera' => empty($b['bandera']) ? 0 : 1,
            'combustible_inicio' => Entrada::numeroOpcional($b, 'combustible_inicio', 'El combustible inicial'),
            'combustible_fin' => Entrada::numeroOpcional($b, 'combustible_fin', 'El combustible final'),
        ];
        if (!$esAlta) {
            return $v;
        }
        return [
            'periodo_id' => Entrada::entero($b, 'periodo_id', 'El periodo'),
            'embarcacion_id' => Entrada::entero($b, 'embarcacion_id', 'La embarcación', false) ?? 1,
            'es_renta' => empty($b['es_renta']) ? 0 : 1,
        ] + $v;
    }

    protected function alGuardar(int $id, array $b, ?array $previo): void
    {
        (new ViajesRepositorio())->reemplazarHorometros($id, self::lecturas($b['horometros'] ?? []));
    }

    protected function detalle(string $accion, array $b, ?array $previo): string
    {
        return match ($accion) {
            'crear' => "Registró el viaje del {$b['fecha']} (" . (($b['cliente_nombre'] ?? '') ?: 'cliente ' . ($b['cliente_id'] ?? '—')) . ')',
            'editar' => "Editó el viaje del {$b['fecha']}",
            default => "Borró el viaje del {$previo['fecha']} ({$previo['cliente']})",
        };
    }

    /** Solo cuentan las lecturas con motor, inicio y fin; el fin no puede ser menor que el inicio. */
    private static function lecturas(mixed $lista): array
    {
        $salida = [];
        foreach (is_array($lista) ? $lista : [] as $h) {
            $inicio = $h['inicio'] ?? '';
            $fin = $h['fin'] ?? '';
            if (empty($h['motor_id']) || $inicio === '' || $fin === '' || $inicio === null || $fin === null) {
                continue;
            }
            if (!is_numeric($inicio) || !is_numeric($fin) || (float) $fin < (float) $inicio) {
                throw new \InvalidArgumentException('La lectura final del horómetro no puede ser menor que la inicial.');
            }
            $salida[] = ['motor_id' => (int) $h['motor_id'], 'inicio' => $inicio, 'fin' => $fin];
        }
        return $salida;
    }

    private static function ceroEsNulo(int|float|string|null $v): int|float|string|null
    {
        return $v !== null && (float) $v == 0 ? null : $v;
    }
}
