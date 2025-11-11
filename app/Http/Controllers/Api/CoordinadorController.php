<?php

namespace App\Http\Controllers\Api;

use App\Models\Solicitud;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\SolicitudRechazadaCoordinadorMail;
use App\Http\Controllers\Api\SolicitudController;

class CoordinadorController extends SolicitudController
{
    /**
     * Actualiza el estado de una solicitud (Coordinador).
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Solicitud $solicitud
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateEstado(Request $request, Solicitud $solicitud)
    {
        // 1. Autorización: Solo usuarios con roles administrativos pueden cambiar el estado.
        if (!$this->tieneRolAdministrativo(Auth::id())) {
            return response()->json(['message' => 'No autorizado para cambiar el estado de la solicitud.'], 403);
        }

        // 2. Validación de la solicitud
        $request->validate([
            'estado' => [
                'required',
                'string',
                Rule::in(['rechazada', 'en revisión 2']),
            ],
            'observaciones' => [
                Rule::requiredIf($request->input('estado') === 'rechazada'),
                'nullable',
                'string',
                'max:500'
            ]
        ]);

        // 3. Lógica de Transición de Estado Específica
        $estadoActual = strtolower($solicitud->estado);
        $nuevoEstado = strtolower($request->estado);

        // Verificamos la transición válida
        if ($estadoActual !== 'en revisión 1' && $nuevoEstado !== 'rechazada') {
            return response()->json(['message' => "El estado actual es '{$estadoActual}'. No se puede realizar la acción de Aceptar/Rechazar en este punto."], 409);
        }

        // 4. Actualizar el estado y guardar el rol si se rechaza
        $solicitud->estado = $nuevoEstado;

        if ($nuevoEstado === 'rechazada') {
            $solicitud->observaciones = $request->input('observaciones', null);
            $solicitud->rol_rechazo = $this->obtenerRolAccion();
        } else {
            // Limpiar observaciones y rol_rechazo si se acepta/avanza
            $solicitud->observaciones = null;
            $solicitud->rol_rechazo = null;
        }

        $solicitud->save();

        // Dentro de tu lógica de actualización:
        if ($nuevoEstado === 'rechazada') {
            try {
                $coordinador = Auth::user(); // El usuario que realiza la acción
                $estudiante = $solicitud->user; // Alumno que creó la solicitud

                if ($estudiante && $estudiante->email) {
                    // Enviar correo
                    Mail::to($estudiante->email)->send(
                        new SolicitudRechazadaCoordinadorMail(
                            $solicitud,
                            $coordinador,
                            $request->input('observaciones', 'Sin motivo especificado.')
                        )
                    );
                }
            } catch (\Exception $e) {
                \Log::error("❌ Error al enviar correo de rechazo: " . $e->getMessage());
            }
        }

        // 6. Respuesta final
        return response()->json([
            'message' => 'Estado de la solicitud actualizado con éxito.',
            'solicitud' => $solicitud
        ], 200);
    }
}