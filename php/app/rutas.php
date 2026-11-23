<?php
// Tabla de rutas. Cada módulo agrega las suyas aquí; el nombre coincide con la versión Next.js.
use Lebleu\Compartido\SaludController;
use Lebleu\Core\Ruteador;
use Lebleu\Modulos\Auth\AuthController;
use Lebleu\Modulos\Periodos\PeriodosController;

return function (Ruteador $r): void {
    $r->agregar('GET',  '/api/salud',  [SaludController::class, 'estado']);
    $r->agregar('POST', '/api/login',  [AuthController::class, 'login']);
    $r->agregar('POST', '/api/logout', [AuthController::class, 'logout']);
    $r->agregar('POST', '/api/periodos', [PeriodosController::class, 'crear']);
    $r->agregar('POST', '/api/periodos/estado', [PeriodosController::class, 'estado']);
};
