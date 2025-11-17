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
        $academico = $user->academico()->with('facultad.campus')->first();

        if (!$academico) {
            return response()->json(['error' => 'Perfil de académico no encontrado para este usuario.'], 404);
        }

        // Estructura los datos para la respuesta API
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
     * Marca al usuario autenticado (Académico) como solicitante de rol.
     */
    public function solicitarRol(Request $request)
    {
        $user = Auth::user();

        // Asegurarse de que sea un académico (Rol 2)
        if (!$user->roles()->where('role_id', 2)->exists()) {
            return response()->json(['message' => 'Acción no permitida.'], 403);
        }

        $user->solicita_rol = true;
        $user->save();

        return response()->json(['message' => 'Solicitud enviada correctamente.']);
    }

    /**
     * Obtiene el estado de la solicitud de rol del usuario actual.
     */
    public function getEstadoRol(Request $request)
    {
        $user = Auth::user();
        return response()->json(['solicita_rol' => $user->solicita_rol]);
    }
}