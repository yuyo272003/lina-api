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
     * Gestiona la transición de estado en la etapa de Coordinación.
     * Valida permisos, integridad del flujo de aprobación y notifica rechazos vía correo.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Solicitud $solicitud
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateEstado(Request $request, Solicitud $solicitud)
    {
        // Verificación de permisos RBAC
        if (!$this->tieneRolAdministrativo(Auth::id())) {
            return response()->json(['message' => 'No autorizado para cambiar el estado de la solicitud.'], 403);
        }

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

        $estadoActual = strtolower($solicitud->estado);
        $nuevoEstado = strtolower($request->estado);

        

        // Validación de integridad de flujo: Solo permite avanzar si la etapa previa es 'en revisión 1'
        if ($estadoActual !== 'en revisión 1' && $nuevoEstado !== 'rechazada') {
            return response()->json(['message' => "El estado actual es '{$estadoActual}'. No se puede realizar la acción en este punto."], 409);
        }

        // Persistencia de estado y metadatos de rechazo
        $solicitud->estado = $nuevoEstado;

        if ($nuevoEstado === 'rechazada') {
            $solicitud->observaciones = $request->input('observaciones', null);
            $solicitud->rol_rechazo = $this->obtenerRolAccion();
        } else {
            $solicitud->observaciones = null;
            $solicitud->rol_rechazo = null;
        }

        $solicitud->save();

        // Envío de notificación asíncrona (Mailable)
        if ($nuevoEstado === 'rechazada') {
            try {
                $estudiante = $solicitud->user;
                if ($estudiante && $estudiante->email) {
                    Mail::to($estudiante->email)->send(
                        new SolicitudRechazadaCoordinadorMail(
                            $solicitud,
                            Auth::user(),
                            $request->input('observaciones', 'Sin motivo especificado.')
                        )
                    );
                }
            } catch (\Exception $e) {
                \Log::error("❌ Error al enviar correo de rechazo: " . $e->getMessage());
            }
        }

        return response()->json([
            'message' => 'Estado de la solicitud actualizado con éxito.',
            'solicitud' => $solicitud
        ], 200);
    }
}