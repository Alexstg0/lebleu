<?php
namespace Lebleu\Modulos\Reservas;

use Lebleu\Compartido\Entrada;
use Lebleu\Compartido\Movimientos\MovimientoControlador;
use Lebleu\Compartido\Movimientos\MovimientoRepositorio;

// Alta, edición y baja de reservas: las pueden hacer el administrador, el capitán y los socios.
final class ReservasController extends MovimientoControlador
{
    protected function repo(): MovimientoRepositorio
    {
        return new ReservasRepositorio();
    }

    protected function tabla(): string
    {
        return 'reservas';
    }

    protected function roles(): array
    {
        return ['admin', 'capitan', 'socio'];
    }

    protected function rolesBorrar(): array
    {
        return ['admin', 'capitan', 'socio'];
    }

    protected function auditar(): bool
    {
        return false;
    }

    protected function valores(array $b, bool $esAlta): array
    {
        $hora = trim((string) ($b['hora'] ?? ''));
        if ($hora !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $hora)) {
            throw new \InvalidArgumentException('La hora no es válida.');
        }
        $duracion = Entrada::numeroOpcional($b, 'duracion_horas', 'La duración');
        return [
            'fecha' => Entrada::fecha($b, 'fecha'),
            'hora' => $hora === '' ? null : $hora,
            'cliente' => Entrada::textoOpcional($b, 'cliente', 'El cliente', 160),
            'socio_id' => Entrada::entero($b, 'socio_id', 'El socio', false),
            'num_personas' => Entrada::entero($b, 'num_personas', 'El número de personas', false),
            'duracion_horas' => $duracion !== null && (float) $duracion == 0 ? null : $duracion,
            'notas' => Entrada::textoOpcional($b, 'notas', 'Las notas', 1000),
        ];
    }

    /** No se agenda hacia atrás: ni una reserva nueva ni mover una existente a una fecha que ya pasó (hora de la embarcación). */
    protected function validar(array $valores, ?array $previo): void
    {
        $fecha = (string) $valores['fecha'];
        $cambia = $previo === null || $fecha !== substr((string) $previo['fecha'], 0, 10);
        if ($cambia && $fecha < date('Y-m-d')) {
            throw new \InvalidArgumentException('No se pueden agendar reservas en fechas que ya pasaron.');
        }
    }

    protected function detalle(string $accion, array $b, ?array $previo): string
    {
        return '';
    }
}
