<?php

namespace App\Http\Controllers\Api;

use App\Models\Solicitud;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\SolicitudCompletadaMail;
use App\Http\Controllers\Api\SolicitudController;
use Illuminate\Support\Facades\Log;

class SecretarioController extends SolicitudController
{
    /**
     * Sube y asocia un archivo final a un trámite específico dentro de una solicitud.
     * Almacena en disco público y actualiza la referencia en la tabla pivote.
     */
    public function subir(Request $request, Solicitud $solicitud)
    {
        $data = $request->validate([
            'archivo' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10000',
            'tramite_id' => [
                'required',
                'integer',
                // Validación de integridad referencial: El trámite debe pertenecer a la solicitud actual.
                Rule::exists('solicitud_tramite', 'idTramite')
                    ->where('idSolicitud', $solicitud->idSolicitud)
            ],
        ]);

        $tramiteId = $data['tramite_id'];
        $file = $request->file('archivo');

        $nombreArchivo = 'sol' . $solicitud->idSolicitud . '_tram' . $tramiteId . '_' . time() . '.' . $file->extension();
        $directorio = 'tramitesEnviados';

        if (!Storage::disk('public')->exists($directorio)) {
            Storage::disk('public')->makeDirectory($directorio);
        }

        $ruta = $file->storeAs($directorio, $nombreArchivo, 'public');

        // Actualización de metadata en la relación Many-to-Many
        $solicitud->tramites()->updateExistingPivot($tramiteId, [
            'ruta_archivo_final' => $ruta,
            'completado_manual' => false // Reset de flag manual por consistencia
        ]);

        return response()->json([
            'message' => "Archivo para trámite $tramiteId subido con éxito.",
            'ruta' => $ruta
        ], 200);
    }

    /**
     * Verifica que todos los trámites estén gestionados (Archivo o Manual) y finaliza la solicitud.
     * Cambia el estado a 'completado' y notifica al estudiante.
     */
    public function completar(Request $request, Solicitud $solicitud)
    {
        $solicitud->load('tramites', 'user');

        // Cálculo de progreso basado en condiciones de completitud (Archivo existe OR Flag manual activo)
        $tramitesCompletados = $solicitud->tramites->filter(function ($tramite) {
            return !empty($tramite->pivot->ruta_archivo_final) || $tramite->pivot->completado_manual == 1;
        })->count();

        $tramitesTotales = $solicitud->tramites->count();
        $todosListos = ($tramitesTotales > 0 && $tramitesTotales === $tramitesCompletados);
        $faltantes = $tramitesTotales - $tramitesCompletados;

        if (!$todosListos) {
            return response()->json([
                'message' => "Aún faltan $faltantes trámites por gestionar (subir archivo o marcar completado).",
                'completados' => $tramitesCompletados,
                'totales' => $tramitesTotales
            ], 422);
        }

        // Transición de estado y notificación idempotente
        if ($solicitud->estado !== 'completado') {
            $solicitud->estado = 'completado';
            $solicitud->save();

            try {
                $estudiante = $solicitud->user;
                if ($estudiante && $estudiante->email) {
                    Mail::to($estudiante->email)->send(
                        new SolicitudCompletadaMail($solicitud, Auth::user())
                    );
                }
            } catch (\Exception $e) {
                Log::error("❌ Error mail: " . $e->getMessage());
            }
        }

        return response()->json([
            'message' => 'Proceso finalizado con éxito.',
            'solicitud' => $solicitud->fresh()->load('tramites'),
        ], 200);
    }

    /**
     * Marca un trámite como completado administrativamente sin necesidad de archivo adjunto.
     * Limpia referencias a archivos previos para mantener consistencia.
     */
    public function marcarManual(Request $request, Solicitud $solicitud)
    {
        $request->validate([
            'tramite_id' => 'required|integer'
        ]);
        
        $tramiteId = $request->tramite_id;

        $solicitud->tramites()->updateExistingPivot($tramiteId, [
            'completado_manual' => true,
            'ruta_archivo_final' => null
        ]);

        return response()->json(['message' => 'Trámite marcado como completado manualmente.'], 200);
    }
}