<?php

namespace App\Http\Controllers\Api;

use App\Models\Solicitud;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\SolicitudRechazadaMail;
use App\Http\Controllers\Api\SolicitudController;

class ContadorController extends SolicitudController
{
    /**
     * Actualiza el estado de una solicitud por parte del contador.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Solicitud $solicitud
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateEstadoContador(Request $request, Solicitud $solicitud)
    {
        // 1️⃣ Autorización: Solo usuarios con rol contadora pueden hacer este cambio.
        if (!$this->tieneRolAdministrativo(Auth::id())) {
            return response()->json(['message' => 'No autorizado para cambiar el estado de la solicitud.'], 403);
        }

        // 2️⃣ Validación: solo permitir "en revisión 3" o "rechazada"
        $request->validate([
            'estado' => [
                'required',
                'string',
                Rule::in(['rechazada', 'en revisión 3']),
            ],
            'observaciones' => [
                Rule::requiredIf($request->input('estado') === 'rechazada'),
                'nullable',
                'string',
                'max:500'
            ]
        ]);

        $estadoActual = strtolower($solicitud->estado);
        $nuevoEstado = strtolower($request->estado);

        // 3️⃣ Reglas de transición válidas para el contador
        if ($estadoActual !== 'en revisión 2' && $nuevoEstado !== 'rechazada') {
            return response()->json([
                'message' => "El estado actual es '{$estadoActual}'. No se puede realizar esta acción desde esta etapa."
            ], 409);
        }

        // 4️⃣ Actualizar el estado y guardar el rol si se rechaza
        $solicitud->estado = $nuevoEstado;

        if ($nuevoEstado === 'rechazada') {
            $solicitud->observaciones = $request->input('observaciones', null);
            $solicitud->rol_rechazo = $this->obtenerRolAccion();
        } else {
            $solicitud->observaciones = null;
            $solicitud->rol_rechazo = null;
        }

        $solicitud->save();

        // 5️⃣ Si la solicitud fue rechazada, enviar correo al alumno
        if ($nuevoEstado === 'rechazada') {
            try {
                $coordinador = Auth::user(); // Usuario que rechazó
                $estudiante = $solicitud->user; // Alumno dueño de la solicitud

                if ($estudiante && $estudiante->email) {
                    Mail::to($estudiante->email)->send(
                        new SolicitudRechazadaMail(
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

        // 6️⃣ Respuesta final
        return response()->json([
            'message' => 'Estado de la solicitud actualizado con éxito.',
            'solicitud' => $solicitud
        ], 200);
    }
}