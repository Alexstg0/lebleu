<?php
// Tabla de rutas. Cada módulo agrega las suyas aquí; los nombres coinciden con la versión Next.js.
use Lebleu\Compartido\SaludController;
use Lebleu\Core\Ruteador;
use Lebleu\Modulos\Adjuntos\AdjuntosController;
use Lebleu\Modulos\Auth\AuthController;
use Lebleu\Modulos\Auth\AuthPaginaController;
use Lebleu\Modulos\Captura\CapturaController;
use Lebleu\Modulos\EstadoCuenta\EstadoCuentaController;
use Lebleu\Modulos\Aportaciones\AportacionesController;
use Lebleu\Modulos\Extraordinarios\AbonosController;
use Lebleu\Modulos\Extraordinarios\ExtraordinariosController;
use Lebleu\Modulos\GastosOperativos\GastosOperativosController;
use Lebleu\Modulos\IngresosRenta\IngresosRentaController;
use Lebleu\Modulos\Insumos\InsumosController;
use Lebleu\Modulos\Marineros\MarinerosController;
use Lebleu\Modulos\Manuales\ManualesController;
use Lebleu\Modulos\Movimientos\MovimientosController;
use Lebleu\Modulos\Periodos\PeriodosController;
use Lebleu\Modulos\Periodos\PeriodosPaginaController;
use Lebleu\Modulos\Viajes\ViajesController;

return function (Ruteador $r): void {
    // Páginas
    $r->agregar('GET', '/login', [AuthPaginaController::class, 'login']);
    $r->agregar('GET', '/', [EstadoCuentaController::class, 'pagina']);
    $r->agregar('GET', '/periodos', [PeriodosPaginaController::class, 'pagina']);
    $r->agregar('GET', '/capturar', [CapturaController::class, 'pagina']);
    $r->agregar('GET', '/movimientos', [MovimientosController::class, 'pagina']);
    $r->agregar('GET', '/movimientos/formulario', [MovimientosController::class, 'formulario']);

    // API
    $r->agregar('GET', '/api/salud', [SaludController::class, 'estado']);
    $r->agregar('POST', '/api/login', [AuthController::class, 'login']);
    $r->agregar('POST', '/api/logout', [AuthController::class, 'logout']);
    $r->agregar('POST', '/api/periodos', [PeriodosController::class, 'crear']);
    $r->agregar('POST', '/api/periodos/estado', [PeriodosController::class, 'estado']);
    $r->agregar('POST', '/api/extraordinarios/pagado', [ExtraordinariosController::class, 'pagado']);
    $r->agregar('POST', '/api/marineros', [MarinerosController::class, 'crear']);

    // Captura de movimientos: alta (POST), edición (PATCH) y baja (DELETE ?id=)
    foreach ([
        '/api/gastos-operativos' => GastosOperativosController::class,
        '/api/extraordinarios' => ExtraordinariosController::class,
        '/api/abonos-extraordinarios' => AbonosController::class,
        '/api/aportaciones' => AportacionesController::class,
        '/api/ingresos-renta' => IngresosRentaController::class,
        '/api/insumos' => InsumosController::class,
        '/api/viajes' => ViajesController::class,
    ] as $ruta => $controlador) {
        $r->agregar('POST', $ruta, [$controlador, 'crear']);
        $r->agregar('PATCH', $ruta, [$controlador, 'editar']);
        $r->agregar('DELETE', $ruta, [$controlador, 'borrar']);
    }
    $r->agregar('GET', '/api/adjuntos/{id}', [AdjuntosController::class, 'ver']);
    $r->agregar('GET', '/api/manual', [ManualesController::class, 'ver']);
};
