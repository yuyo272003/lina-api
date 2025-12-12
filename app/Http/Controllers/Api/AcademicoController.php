<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AcademicoController extends Controller
{
    /**
     * Recupera el perfil del académico autenticado cargando relaciones anidadas (Facultad -> Campus)
     * y retorna la estructura de datos normalizada.
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProfile()
    {
        $user = Auth::user();

        // Eager Loading para optimizar la consulta de relaciones jerárquicas
        $academico = $user->academico()->with('facultad.campus')->first();

        if (!$academico) {
            return response()->json(['error' => 'Perfil de académico no encontrado para este usuario.'], 404);
        }

        return response()->json([
            'nombre_completo'      => $user->name,
            'numero_personal'      => $academico->NoPersonalAcademico,
            'facultad'             => $academico->facultad->nombreFacultad ?? 'No asignada',
            'campus'               => $academico->facultad->campus->nombreCampus ?? 'No asignado',
            'rfc'                  => $academico->RfcAcademico ?? 'N/A',
            'correo_institucional' => $user->email,
        ]);
    }

    /**
     * Activa el flag 'solicita_rol' para el usuario actual, permitiendo su gestión por administradores.
     * Incluye validación de seguridad para restringir la acción al Rol 2 (Académico).
     */
    public function solicitarRol(Request $request)
    {
        $user = Auth::user();

        if (!$user->roles()->where('role_id', 2)->exists()) {
            return response()->json(['message' => 'Acción no permitida.'], 403);
        }

        $user->solicita_rol = true;
        $user->save();

        return response()->json(['message' => 'Solicitud enviada correctamente.']);
    }

    /**
     * Consulta el estado del flag 'solicita_rol' para condicionar el renderizado en el frontend.
     */
    public function getEstadoRol(Request $request)
    {
        $user = Auth::user();
        return response()->json(['solicita_rol' => $user->solicita_rol]);
    }
}