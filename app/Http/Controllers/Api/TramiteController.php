<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tramite; // <-- Asegúrate de importar el modelo
use Illuminate\Http\Request;

class TramiteController extends Controller
{
    public function index()
    {
        // Esta línea es la que devuelve los trámites con sus requisitos
        return Tramite::with('requisitos')->get();
    }
}