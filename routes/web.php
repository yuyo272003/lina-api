<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Ruta de bienvenida
Route::get('/', function () {
    return ['Laravel' => app()->version()];
});


// --- RUTAS PARA LA AUTENTICACIÓN CON MICROSOFT ---

// Grupo para rutas que solo pueden visitar los invitados (no logueados)
Route::middleware('guest')->group(function () {
    Route::get('/login/microsoft', [LoginController::class, 'attempt'])->name('login.microsoft');
    Route::get('/callback', [LoginController::class, 'callback'])->name('login.callback');
});

// Grupo para rutas que solo pueden visitar usuarios autenticados
Route::middleware('auth')->group(function() {
    // Ruta para cerrar la sesión (esta es para el logout de Microsoft)
    // Se podría renombrar para evitar conflictos, por ejemplo: /logout/microsoft
    Route::post('/logout-microsoft', [LoginController::class, 'logout'])->name('logout.microsoft');
});


// --- RUTAS PARA AUTENTICACIÓN ESTÁNDAR (Email/Password) ---
require __DIR__.'/auth.php';