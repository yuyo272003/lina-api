<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EstudianteController;
use App\Http\Controllers\Api\TramiteController;
use App\Http\Controllers\Api\SolicitudController;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

require __DIR__.'/auth.php';

Route::middleware('auth:sanctum')->get('/perfil-estudiante', [EstudianteController::class, 'getProfile']);

Route::middleware('auth:sanctum')->get('/tramites', [TramiteController::class, 'index']);

Route::middleware('auth:sanctum')->post('/solicitudes', [SolicitudController::class, 'store']);

Route::middleware('auth:sanctum')->get('/solicitudes', [SolicitudController::class, 'index']);

Route::get('/solicitudes/{solicitud}', [SolicitudController::class, 'show']);
