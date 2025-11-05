<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EstudianteController;
use App\Http\Controllers\Api\TramiteController;
use App\Http\Controllers\Api\SolicitudController;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/perfil-estudiante', [EstudianteController::class, 'getProfile']);
    Route::get('/tramites', [TramiteController::class, 'index']);

    // Rutas de Solicitudes
    Route::post('/solicitudes', [SolicitudController::class, 'store']);
    Route::get('/solicitudes', [SolicitudController::class, 'index']);
    Route::get('/solicitudes/{solicitud}', [SolicitudController::class, 'show']);
    Route::get('/solicitudes/{solicitud}/orden-de-pago', [SolicitudController::class, 'downloadOrdenDePago']);
    Route::post('/solicitudes/{solicitud}/comprobante', [SolicitudController::class, 'subirComprobante']);
    Route::post('/solicitudes/{solicitud}/validar', [SolicitudController::class, 'validar']);
    Route::patch('/solicitudes/{solicitud}/estado', [SolicitudController::class, 'updateEstado']);
    Route::patch('/solicitudes/{solicitud}/estado-contador', [SolicitudController::class, 'updateEstadoContador']);
    Route::patch('solicitudes/{solicitud}/cancelar', [SolicitudController::class, 'cancelar']);
    Route::resource('gestion/tramites', App\Http\Controllers\Api\TramiteRequisitoController::class)
        ->only(['index', 'store', 'update', 'destroy']);
    Route::get('gestion/requisitos', [App\Http\Controllers\Api\TramiteRequisitoController::class, 'getRequisitos']);
    Route::post('gestion/requisitos', [App\Http\Controllers\Api\TramiteRequisitoController::class, 'storeRequisito']); 
    Route::post('solicitudes/{solicitud}/requisito/{idTramite}', [SolicitudController::class, 'subirRequisitoDocumento']);
});