<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AcademicoController extends Controller
{
    /**
     * Obtiene el perfil del académico autenticado.
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProfile()
    {
        // Obtiene el usuario autenticado
        $user = Auth::user();

        // Carga la relación 'academico' del usuario, y dentro de ella, la relación 'facultad'
        // Esto asume que la relación 'academico' está definida en el modelo User (ver nota 3)
        $academico = $user->academico()->with('facultad.campus')->first();

        if (!$academico) {
            return response()->json(['error' => 'Perfil de académico no encontrado para este usuario.'], 404);
        }

        // Estructura los datos para la respuesta API
        return response()->json([
            'nombre_completo'      => $user->name,
            'numero_personal'      => $academico->NoPersonalAcademico, // Usando el campo de la DB
            'facultad'             => $academico->facultad->nombreFacultad ?? 'No asignada',
            'campus'               => $academico->facultad->campus->nombreCampus ?? 'No asignado', // Asumiendo relación Facultad -> Campus
            'rfc'                  => $academico->RfcAcademico ?? 'N/A',
            'correo_institucional' => $user->email,
        ]);
    }
}