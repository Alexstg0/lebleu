<?php
// Tarea diaria (Programador de tareas de Windows): crea el periodo del mes si falta y genera los cargos
// recurrentes de los periodos abiertos. Equivale al cron de Vercel de la versión Next.js.
// Uso:  php scripts/cron_recurrentes.php        (ver herramientas/LEEME-tarea-programada.txt)
require __DIR__ . '/../app/arranque.php';

use Lebleu\Modulos\Auditoria\AuditoriaServicio;
use Lebleu\Modulos\Periodos\PeriodosServicio;
use Lebleu\Modulos\Recurrentes\RecurrentesServicio;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
try {
    $hoy = date('Y-m-d'); // hora de la embarcación (zona_horaria de config)
    $periodos = new PeriodosServicio();
    $nuevo = $periodos->asegurarPeriodoActual($hoy);
    if ($nuevo) {
        AuditoriaServicio::registrar(null, 'crear', 'periodos', $nuevo, 'El sistema creó automáticamente el periodo del mes ' . substr($hoy, 0, 7));
    }
    $servicio = new RecurrentesServicio();
    $nomina = $admin = 0;
    foreach ($periodos->listar() as $periodo) {
        if ($periodo['estado'] === 'cerrado') {
            continue;
        }
        $r = $servicio->asegurar($periodo, $hoy);
        $nomina += $r['nomina'];
        $admin += $r['admin'];
    }
    echo json_encode(['ok' => true, 'hoy' => $hoy, 'nuevoPeriodo' => $nuevo, 'nomina' => $nomina, 'admin' => $admin]), "\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[' . date('c') . '] cron_recurrentes: ' . $e->getMessage() . "\n");
    exit(1);
}
