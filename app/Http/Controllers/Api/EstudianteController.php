<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EstudianteController extends Controller
{
    public function getProfile()
    {
        $user = Auth::user();

        $estudiante = $user->estudiante()->with('programaEducativo')->first();

        if (!$estudiante) {
            return response()->json(['error' => 'Perfil de estudiante no encontrado para este usuario.'], 404);
        }

        return response()->json([
            'nombre_completo'      => $user->name,
            'matricula'            => $estudiante->matriculaEstudiante,
            'programa_educativo'   => $estudiante->programaEducativo->nombrePE ?? 'No asignado',
            'correo_institucional' => $user->email,
            'grupo'                => $estudiante->grupoEstudiante ?? 'No asignado'
        ]);
    }
}