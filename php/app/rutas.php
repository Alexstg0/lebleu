<?php
// Tabla de rutas. Cada módulo agrega las suyas aquí; los nombres coinciden con la versión Next.js.
use Lebleu\Compartido\SaludController;
use Lebleu\Core\Ruteador;
use Lebleu\Modulos\Adjuntos\AdjuntosController;
use Lebleu\Modulos\Auth\AuthController;
use Lebleu\Modulos\Auth\AuthPaginaController;
use Lebleu\Modulos\EstadoCuenta\EstadoCuentaController;
use Lebleu\Modulos\Extraordinarios\ExtraordinariosController;
use Lebleu\Modulos\Manuales\ManualesController;
use Lebleu\Modulos\Periodos\PeriodosController;
use Lebleu\Modulos\Periodos\PeriodosPaginaController;

return function (Ruteador $r): void {
    // Páginas
    $r->agregar('GET', '/login', [AuthPaginaController::class, 'login']);
    $r->agregar('GET', '/', [EstadoCuentaController::class, 'pagina']);
    $r->agregar('GET', '/periodos', [PeriodosPaginaController::class, 'pagina']);

    // API
    $r->agregar('GET', '/api/salud', [SaludController::class, 'estado']);
    $r->agregar('POST', '/api/login', [AuthController::class, 'login']);
    $r->agregar('POST', '/api/logout', [AuthController::class, 'logout']);
    $r->agregar('POST', '/api/periodos', [PeriodosController::class, 'crear']);
    $r->agregar('POST', '/api/periodos/estado', [PeriodosController::class, 'estado']);
    $r->agregar('POST', '/api/extraordinarios/pagado', [ExtraordinariosController::class, 'pagado']);
    $r->agregar('GET', '/api/adjuntos/{id}', [AdjuntosController::class, 'ver']);
    $r->agregar('GET', '/api/manual', [ManualesController::class, 'ver']);
};
