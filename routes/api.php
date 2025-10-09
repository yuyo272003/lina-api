<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EstudianteController;
use App\Http\Controllers\Api\TramiteController;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

require __DIR__.'/auth.php';

Route::middleware('auth:sanctum')->get('/perfil-estudiante', [EstudianteController::class, 'getProfile']);

Route::middleware('auth:sanctum')->get('/tramites', [TramiteController::class, 'index']);