<?php

// 1. ¡MUY IMPORTANTE! Asegúrate de que estas dos líneas estén al principio del archivo.
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Ruta de bienvenida (puedes dejar la que tenías)
Route::get('/', function () {
    return ['Laravel' => app()->version()];
});


// --- RUTAS PARA LA AUTENTICACIÓN CON MICROSOFT ---

// Ruta para iniciar el proceso de login (a la que apunta tu frontend)
Route::get('/login/microsoft', [LoginController::class, 'attempt'])->name('login.microsoft');

// Ruta de callback a la que Microsoft redirigirá
Route::get('/callback', [LoginController::class, 'callback'])->name('login.callback');

// Ruta para cerrar sesión
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Aplicamos el middleware 'guest' a un grupo de rutas.
// Solo los usuarios NO autenticados podrán acceder a ellas.
Route::middleware('guest')->group(function () {
    // Ruta para iniciar el proceso de login
    Route::get('/login/microsoft', [LoginController::class, 'attempt'])->name('login.microsoft');

    // Ruta de callback a la que Microsoft redirigirá
    Route::get('/callback', [LoginController::class, 'callback'])->name('login.callback');
});

// La ruta de logout se queda afuera, porque solo los usuarios
// autenticados deben poder cerrar sesión.
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');