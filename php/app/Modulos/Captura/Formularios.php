<?php
namespace Lebleu\Modulos\Captura;

use Lebleu\Compartido\Formato;
use Lebleu\Core\Vista;

// Formularios de captura. Cada uno es una plantilla que sirve para ALTA ($d = null) y para EDICIÓN
// ($d = fila existente), tanto en la pantalla Capturar como dentro de Movimientos.
final class Formularios
{
    private const PLANTILLAS = [
        'gasto' => 'gasto', 'extraordinario' => 'extraordinario', 'aportacion' => 'aportacion',
        'abono' => 'abono', 'renta' => 'renta', 'insumo' => 'insumo', 'viaje' => 'viaje', 'viaje_capitan' => 'viaje_capitan',
    ];

    public static function existe(string $tipo): bool
    {
        return isset(self::PLANTILLAS[$tipo]);
    }

    /**
     * @param array $ctx periodoId, tipoCambio, precioLitro, socios, clientes, motores, marineros, viajes
     */
    public static function renderizar(string $tipo, ?array $d, array $ctx): string
    {
        return Vista::parcial(__DIR__ . '/vistas/formularios/' . self::PLANTILLAS[$tipo] . '.php', [
            'd' => $d, 'ctx' => $ctx, 'editando' => !empty($d['id']),
        ]);
    }

    /** Fila de botones y mensaje que cierra cada formulario. */
    public static function pie(bool $editando, string $textoGuardar, string $claseBoton = 'btn', string $extra = '', string $estilo = ''): string
    {
        return '<div style="margin-top:16px;display:flex;align-items:center;gap:14px' . $estilo . '">'
            . '<button class="' . $claseBoton . '" data-boton data-texto="' . Formato::e($editando ? 'Actualizar' : $textoGuardar) . '">'
            . Formato::e($editando ? 'Actualizar' : $textoGuardar) . '</button>'
            . ($editando ? '<button type="button" class="btn-ghost" data-cancelar>Cancelar</button>' : '')
            . $extra . '<span data-mensaje hidden></span></div>';
    }

    /** Atributos comunes de <form>: dónde se envía y qué campos se limpian tras guardar. */
    public static function atributosForm(string $url, bool $editando, string $limpiar): string
    {
        return 'data-form data-url="' . Formato::e($url) . '" data-metodo="' . ($editando ? 'PATCH' : 'POST') . '" data-limpiar="' . Formato::e($limpiar) . '"';
    }

    /** Menú de marineros con la opción de agregar uno nuevo (lo maneja marinero.js). */
    public static function marineroSelect(array $marineros, ?string $valor): string
    {
        $nombres = array_column($marineros, 'nombre');
        $h = '<div data-marinero><select name="marinero" data-marinero-select><option value="">— Sin marinero —</option>';
        if ($valor && !in_array($valor, $nombres, true)) {
            $h .= '<option value="' . Formato::e($valor) . '" selected>' . Formato::e($valor) . '</option>';
        }
        foreach ($nombres as $n) {
            $h .= '<option value="' . Formato::e($n) . '"' . ($n === $valor ? ' selected' : '') . '>' . Formato::e($n) . '</option>';
        }
        return $h . '<option value="__nuevo__">➕ Agregar nuevo marinero…</option></select></div>';
    }

    public static function ocultos(?array $d, int $periodoId): string
    {
        return '<input type="hidden" name="periodo_id" value="' . $periodoId . '">'
            . (!empty($d['id']) ? '<input type="hidden" name="id" value="' . (int) $d['id'] . '">' : '');
    }
}
