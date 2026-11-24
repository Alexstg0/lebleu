<?php
// Tabla de rutas. Cada módulo agrega las suyas aquí; los nombres coinciden con la versión Next.js.
use Lebleu\Compartido\SaludController;
use Lebleu\Core\Ruteador;
use Lebleu\Modulos\Adjuntos\AdjuntosController;
use Lebleu\Modulos\Analisis\AnalisisController;
use Lebleu\Modulos\Bitacora\BitacoraController;
use Lebleu\Modulos\Reportes\ReportesController;
use Lebleu\Modulos\Reportes\ReportesPdfController;
use Lebleu\Modulos\Auditoria\AuditoriaPaginaController;
use Lebleu\Modulos\Auth\AuthController;
use Lebleu\Modulos\Auth\AuthPaginaController;
use Lebleu\Modulos\Captura\CapturaController;
use Lebleu\Modulos\CajaChica\CajaChicaController;
use Lebleu\Modulos\CajaChica\CajaChicaPaginaController;
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
use Lebleu\Modulos\Recurrentes\RecurrentesController;
use Lebleu\Modulos\Reservas\CalendarioController;
use Lebleu\Modulos\Reservas\ReservasController;
use Lebleu\Modulos\Periodos\PeriodosPaginaController;
use Lebleu\Modulos\Usuarios\UsuariosController;
use Lebleu\Modulos\Usuarios\UsuariosPaginaController;
use Lebleu\Modulos\Viajes\ViajesController;

return function (Ruteador $r): void {
    // Páginas
    $r->agregar('GET', '/login', [AuthPaginaController::class, 'login']);
    $r->agregar('GET', '/', [EstadoCuentaController::class, 'pagina']);
    $r->agregar('GET', '/periodos', [PeriodosPaginaController::class, 'pagina']);
    $r->agregar('GET', '/calendario', [CalendarioController::class, 'pagina']);
    $r->agregar('GET', '/bitacora', [BitacoraController::class, 'pagina']);
    $r->agregar('GET', '/reportes', [ReportesController::class, 'pagina']);
    $r->agregar('GET', '/analisis', [AnalisisController::class, 'pagina']);
    $r->agregar('GET', '/api/reportes/soportes', [ReportesPdfController::class, 'soportes']);
    $r->agregar('GET', '/api/reportes/marineros', [ReportesPdfController::class, 'marineros']);
    $r->agregar('GET', '/api/reportes/completo', [ReportesPdfController::class, 'completo']);
    $r->agregar('GET', '/api/reportes/caja-chica', [ReportesPdfController::class, 'cajaChica']);
    $r->agregar('GET', '/api/viajes/recibo', [ReportesPdfController::class, 'reciboViaje']);
    $r->agregar('POST', '/api/gastos-operativos/recibo', [ReportesPdfController::class, 'reciboGasto']);
    $r->agregar('GET', '/capturar', [CapturaController::class, 'pagina']);
    $r->agregar('GET', '/usuarios', [UsuariosPaginaController::class, 'pagina']);
    $r->agregar('GET', '/auditoria', [AuditoriaPaginaController::class, 'pagina']);
    $r->agregar('GET', '/caja-chica', [CajaChicaPaginaController::class, 'pagina']);
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
    $r->agregar('POST', '/api/recurrentes', [RecurrentesController::class, 'generar']);
    $r->agregar('POST', '/api/usuarios', [UsuariosController::class, 'crear']);
    $r->agregar('PATCH', '/api/usuarios', [UsuariosController::class, 'editar']);
    $r->agregar('DELETE', '/api/usuarios', [UsuariosController::class, 'borrar']);

    // Captura de movimientos: alta (POST), edición (PATCH) y baja (DELETE ?id=)
    foreach ([
        '/api/gastos-operativos' => GastosOperativosController::class,
        '/api/extraordinarios' => ExtraordinariosController::class,
        '/api/abonos-extraordinarios' => AbonosController::class,
        '/api/aportaciones' => AportacionesController::class,
        '/api/ingresos-renta' => IngresosRentaController::class,
        '/api/insumos' => InsumosController::class,
        '/api/viajes' => ViajesController::class,
        '/api/reservas' => ReservasController::class,
        '/api/caja-chica' => CajaChicaController::class,
    ] as $ruta => $controlador) {
        $r->agregar('POST', $ruta, [$controlador, 'crear']);
        $r->agregar('PATCH', $ruta, [$controlador, 'editar']);
        $r->agregar('DELETE', $ruta, [$controlador, 'borrar']);
    }
    $r->agregar('GET', '/api/adjuntos/{id}', [AdjuntosController::class, 'ver']);
    $r->agregar('POST', '/api/adjuntos', [AdjuntosController::class, 'subir']);
    $r->agregar('DELETE', '/api/adjuntos', [AdjuntosController::class, 'borrar']);
    $r->agregar('GET', '/api/manual', [ManualesController::class, 'ver']);
};
