<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Importación de Controladores
use App\Http\Controllers\Api\{
    AcademicoController,
    TramiteController,
    AdminController,
    SolicitudController,
    EstudianteController,
    CoordinadorController,
    ContadorController,
    SecretarioController,
    ConfiguracionController,
    TramiteRequisitoController
};

/*
|--------------------------------------------------------------------------
| API ENDPOINTS PROTEGIDOS (Sanctum)
| RUTAS PROTEGIDAS
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->group(function () {

    // --- 1. Identidad y Perfiles ---
    Route::get('/user', function (Request $request) {
        $user = $request->user();
        return response()->json([
            'id'         => $user->id,
            'name'       => $user->name,
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'email'      => $user->email,
            'idPE'       => $user->idPE,
            'roles'      => $user->roles->map(fn($r) => ['id' => $r->IdRole, 'name' => $r->name]),
        ]);
    });
    Route::get('/perfil-estudiante', [EstudianteController::class, 'getProfile']);
    Route::get('/perfil-academico', [AcademicoController::class, 'getProfile']);

    // --- 2. Catálogos Públicos (Read-Only) ---
    Route::get('/tramites', [TramiteController::class, 'index']);
    Route::get('/programas-educativos', [AdminController::class, 'getProgramasEducativos']);

    // --- 3. Gestión de Roles (Académico -> Admin) ---
    Route::prefix('academico')->group(function () {
        Route::post('/solicitar-rol', [AcademicoController::class, 'solicitarRol']);
        Route::get('/estado-rol', [AcademicoController::class, 'getEstadoRol']);
    });

    // --- 4. Flujo de Solicitudes (Core) ---
    Route::prefix('solicitudes')->group(function () {
        // Consultas y Descargas (sin rate limiting estricto)
        Route::get('/', [SolicitudController::class, 'index']);
        Route::get('/{solicitud}', [SolicitudController::class, 'show']);
        Route::get('/{solicitud}/orden-de-pago', [SolicitudController::class, 'downloadOrdenDePago']);
        // Descarga autenticada de archivos privados (F-05)
        Route::get('/{solicitud}/archivo', [SolicitudController::class, 'downloadArchivo']);

        // Acciones del Estudiante con rate limiting (F-08)
        Route::middleware('throttle:20,1')->group(function () {
            Route::post('/', [EstudianteController::class, 'store']);
            Route::post('/{solicitud}/comprobante', [EstudianteController::class, 'subirComprobante']);
            Route::patch('/{solicitud}/cancelar', [EstudianteController::class, 'cancelar']);
            Route::post('/{solicitud}/modificar-respuestas', [EstudianteController::class, 'modificarRespuestas']);
        });

        // Workflow: Coordinación (throttle moderado)
        Route::middleware('throttle:30,1')->group(function () {
            Route::patch('/{solicitud}/estado', [CoordinadorController::class, 'updateEstado']);
            Route::patch('/{solicitud}/estado-contador', [ContadorController::class, 'updateEstadoContador']);
            Route::post('/{solicitud}/subir-archivo', [SecretarioController::class, 'subir']);
            Route::post('/{solicitud}/completar', [SecretarioController::class, 'completar']);
            Route::post('/{solicitud}/marcar-manual', [SecretarioController::class, 'marcarManual']);
        });
    });

    // --- 5. Configuración del Sistema ---
    Route::prefix('configuracion')->group(function () {
        Route::middleware('throttle:10,1')->put('/numero-cuenta', [ConfiguracionController::class, 'updateNumeroCuentaGlobal']);
        Route::get('/numero-cuenta', [ConfiguracionController::class, 'getNumeroCuentaGlobal']);
    });

    // --- 6. Administración y CRUD (Trámites y Requisitos) ---
    Route::prefix('gestion')->group(function () {
        Route::resource('tramites', TramiteRequisitoController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('/requisitos', [TramiteRequisitoController::class, 'getRequisitos']);
        Route::post('/requisitos', [TramiteRequisitoController::class, 'storeRequisito']);
    });

    // --- 7. Administración de Usuarios y Permisos (throttle estricto) ---
    Route::prefix('admin')->middleware('throttle:15,1')->group(function () {
        Route::get('/solicitudes-rol', [AdminController::class, 'getSolicitudesRol']);
        Route::get('/usuarios-activos', [AdminController::class, 'getUsuariosActivos']);
        Route::post('/assign-local-role', [AdminController::class, 'assignLocalRole']);
        Route::post('/remove-admin-role', [AdminController::class, 'removeAdminRole']);
    });
});