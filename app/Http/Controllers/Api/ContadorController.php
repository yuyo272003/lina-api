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
     * Procesa la transición de estado en la etapa de Contaduría.
     * Permite avanzar el flujo a "en revisión 3" o rechazar la solicitud, notificando al estudiante.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Solicitud $solicitud
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateEstadoContador(Request $request, Solicitud $solicitud)
    {
        // Verificación de permisos RBAC (Role-Based Access Control)
        if (!$this->tieneRolAdministrativo(Auth::id())) {
            return response()->json(['message' => 'No autorizado para cambiar el estado de la solicitud.'], 403);
        }

        // Validación de payload: Restricción de estados destino y obligatoriedad de observaciones
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

        // Validación de integridad de flujo: Solo permite aprobar si la etapa previa es correcta ('en revisión 2')
        if ($estadoActual !== 'en revisión 2' && $nuevoEstado !== 'rechazada') {
            return response()->json([
                'message' => "El estado actual es '{$estadoActual}'. No se puede realizar esta acción desde esta etapa."
            ], 409);
        }

        // Persistencia del nuevo estado y metadatos de rechazo (si aplica)
        $solicitud->estado = $nuevoEstado;

        if ($nuevoEstado === 'rechazada') {
            $solicitud->observaciones = $request->input('observaciones', null);
            $solicitud->rol_rechazo = $this->obtenerRolAccion();
        } else {
            $solicitud->observaciones = null;
            $solicitud->rol_rechazo = null;
        }

        $solicitud->save();

        // Disparo de notificación asíncrona al estudiante mediante Mailable
        if ($nuevoEstado === 'rechazada') {
            try {
                $coordinador = Auth::user();
                $estudiante = $solicitud->user;

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

        return response()->json([
            'message' => 'Estado de la solicitud actualizado con éxito.',
            'solicitud' => $solicitud
        ], 200);
    }
}