<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\User;

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
| BACKDOOR PARA PRUEBAS DE CARGA (k6)
|--------------------------------------------------------------------------
| Endpoint exclusivo para entornos locales/testing. Permite la generación
| de tokens Sanctum sin pasar por el flujo OAuth de Microsoft Graph.
*/
if (app()->environment('local', 'testing')) {
    Route::post('/test/login-k6', function (Request $request) {
        $user = User::where('email', $request->input('email'))->first();

        if (!$user) {
            return response()->json(['error' => 'Usuario no encontrado en BD local.'], 404);
        }

        $token = $user->createToken('k6-test-token')->plainTextToken;

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $token,
            'user' => $user
        ]);
    });
}

/*
|--------------------------------------------------------------------------
| API ENDPOINTS PROTEGIDOS (Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->group(function () {
    
    // --- 1. Identidad y Perfiles ---
    Route::get('/user', function (Request $request) {
        return $request->user();
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
        // Consultas Generales
        Route::get('/', [SolicitudController::class, 'index']);
        Route::get('/{solicitud}', [SolicitudController::class, 'show']);
        Route::get('/{solicitud}/orden-de-pago', [SolicitudController::class, 'downloadOrdenDePago']);

        // Acciones del Estudiante
        Route::post('/', [EstudianteController::class, 'store']); // Crear solicitud
        Route::post('/{solicitud}/comprobante', [EstudianteController::class, 'subirComprobante']);
        Route::patch('/{solicitud}/cancelar', [EstudianteController::class, 'cancelar']);
        Route::post('/{solicitud}/modificar-respuestas', [EstudianteController::class, 'modificarRespuestas']);
        
        // Workflow: Coordinación
        Route::patch('/{solicitud}/estado', [CoordinadorController::class, 'updateEstado']);
        
        // Workflow: Contaduría
        Route::patch('/{solicitud}/estado-contador', [ContadorController::class, 'updateEstadoContador']);
        
        // Workflow: Secretaría (Entrega Final)
        Route::post('/{solicitud}/subir-archivo', [SecretarioController::class, 'subir']);
        Route::post('/{solicitud}/completar', [SecretarioController::class, 'completar']);
        Route::post('/{solicitud}/marcar-manual', [SecretarioController::class, 'marcarManual']);
    });

    // --- 5. Configuración del Sistema ---
    Route::prefix('configuracion')->group(function () {
        Route::put('/numero-cuenta', [ConfiguracionController::class, 'updateNumeroCuentaGlobal']);
        Route::get('/numero-cuenta', [ConfiguracionController::class, 'getNumeroCuentaGlobal']);
    });

    // --- 6. Administración y CRUD (Trámites y Requisitos) ---
    Route::prefix('gestion')->group(function () {
        Route::resource('tramites', TramiteRequisitoController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('/requisitos', [TramiteRequisitoController::class, 'getRequisitos']);
        Route::post('/requisitos', [TramiteRequisitoController::class, 'storeRequisito']);
    });

    // --- 7. Administración de Usuarios y Permisos ---
    Route::prefix('admin')->group(function () {
        Route::get('/solicitudes-rol', [AdminController::class, 'getSolicitudesRol']);
        Route::get('/usuarios-activos', [AdminController::class, 'getUsuariosActivos']);
        Route::post('/assign-local-role', [AdminController::class, 'assignLocalRole']);
        Route::post('/remove-admin-role', [AdminController::class, 'removeAdminRole']);
    });
});