<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tramite;
use Illuminate\Http\Request;

class TramiteController extends Controller
{
    /**
     * Recupera el catálogo completo de trámites disponibles.
     * Implementa Eager Loading para incluir la relación 'requisitos' en la misma consulta.
     * * @return \Illuminate\Database\Eloquent\Collection
     */
    public function index()
    {
        return Tramite::with('requisitos')->get();
    }
}