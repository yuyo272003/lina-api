<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\User;

// Controladores generales
use App\Http\Controllers\Api\AcademicoController;
use App\Http\Controllers\Api\TramiteController;
use App\Http\Controllers\Api\AdminController;

// Controladores de Solicitud
use App\Http\Controllers\Api\SolicitudController;
use App\Http\Controllers\Api\EstudianteController; 
use App\Http\Controllers\Api\CoordinadorController; 
use App\Http\Controllers\Api\ContadorController;
use App\Http\Controllers\Api\SecretarioController;
use App\Http\Controllers\Api\ConfiguracionController;
use App\Http\Controllers\Api\TramiteRequisitoController;

/*
|--------------------------------------------------------------------------
| RUTA "BACKDOOR" PARA PRUEBAS DE CARGA (k6)
|--------------------------------------------------------------------------
| Esta ruta permite obtener un token sin pasar por Microsoft Graph.
| Solo funciona en entorno LOCAL.
*/
if (app()->environment('local', 'testing')) {
    Route::post('/test/login-k6', function (Request $request) {
        $user = User::where('email', $request->input('email'))->first();

        if (!$user) {
            return response()->json(['error' => 'Usuario no encontrado. Asegúrate de que exista en la BD local.'], 404);
        }

        // Crear token de Sanctum directo
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
| RUTAS PROTEGIDAS
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // --- Rutas de Perfil y Académico ---
    Route::get('/perfil-estudiante', [EstudianteController::class, 'getProfile']);
    Route::get('/perfil-academico', [AcademicoController::class, 'getProfile']);
    
    // NUEVAS RUTAS para que el Académico (Rol 2) solicite rol
    Route::post('/academico/solicitar-rol', [AcademicoController::class, 'solicitarRol']);
    Route::get('/academico/estado-rol', [AcademicoController::class, 'getEstadoRol']);
    
    Route::get('/tramites', [TramiteController::class, 'index']);

    // VISTAS GENERALES - SolicitudController
    Route::get('/solicitudes', [SolicitudController::class, 'index']);
    Route::get('/solicitudes/{solicitud}', [SolicitudController::class, 'show']);
    Route::get('/solicitudes/{solicitud}/orden-de-pago', [SolicitudController::class, 'downloadOrdenDePago']);

    // ESTUDIANTE
    Route::post('/solicitudes', [EstudianteController::class, 'store']);
    Route::post('/solicitudes/{solicitud}/comprobante', [EstudianteController::class, 'subirComprobante']);
    Route::patch('solicitudes/{solicitud}/cancelar', [EstudianteController::class, 'cancelar']);
    Route::post('solicitudes/{solicitud}/requisito/{idTramite}', [EstudianteController::class, 'subirRequisitoDocumento']); 
    Route::post('solicitudes/{solicitud}/modificar-respuestas', [EstudianteController::class, 'modificarRespuestas']);
    
    // Coordinador
    Route::patch('/solicitudes/{solicitud}/estado', [CoordinadorController::class, 'updateEstado']);
    
    // Contador
    Route::patch('/solicitudes/{solicitud}/estado-contador', [ContadorController::class, 'updateEstadoContador']);
    
    // Secretario
    Route::post('/solicitudes/{solicitud}/subir-archivo', [SecretarioController::class, 'subir']);
    Route::post('/solicitudes/{solicitud}/completar', [SecretarioController::class, 'completar']);
    Route::post('/solicitudes/{solicitud}/marcar-manual', [SecretarioController::class, 'marcarManual']);

    // Configuración
    Route::put('configuracion/numero-cuenta', [ConfiguracionController::class, 'updateNumeroCuentaGlobal']);
    Route::get('configuracion/numero-cuenta', [ConfiguracionController::class, 'getNumeroCuentaGlobal']);

    // Otras rutas
    Route::resource('gestion/tramites', TramiteRequisitoController::class)
        ->only(['index', 'store', 'update', 'destroy']);
    Route::get('gestion/requisitos', [TramiteRequisitoController::class, 'getRequisitos']);
    Route::post('gestion/requisitos', [TramiteRequisitoController::class, 'storeRequisito']);

    // --- Rutas de Administración de Cuentas ---
    Route::get('/admin/solicitudes-rol', [AdminController::class, 'getSolicitudesRol']);
    Route::get('/admin/usuarios-activos', [AdminController::class, 'getUsuariosActivos']);
    Route::post('/admin/assign-local-role', [AdminController::class, 'assignLocalRole']);
    Route::post('/admin/remove-admin-role', [AdminController::class, 'removeAdminRole']);
    Route::get('/programas-educativos', [AdminController::class, 'getProgramasEducativos']);
});