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

class SecretarioController extends SolicitudController
{

    public function subir(Request $request, Solicitud $solicitud)
    {
        // --- 1. VALIDACIÓN (Solo del archivo) ---
        $data = $request->validate([
            'archivo' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10000',
            'tramite_id' => [
                'required',
                'integer',
                Rule::exists('solicitud_tramite', 'idTramite')
                    ->where('idSolicitud', $solicitud->idSolicitud)
            ],
        ]);

        $tramiteId = $data['tramite_id'];
        $file = $request->file('archivo');

        // --- 2. GUARDAR EL ARCHIVO ---
        $nombreArchivo = 'sol' . $solicitud->idSolicitud . '_tram' . $tramiteId . '_' . time() . '.' . $file->extension();
        $directorio = 'tramitesEnviados';

        if (!Storage::disk('public')->exists($directorio)) {
            Storage::disk('public')->makeDirectory($directorio);
        }

        $ruta = $file->storeAs($directorio, $nombreArchivo, 'public');

        // --- 3. ACTUALIZAR LA TABLA PIVOTE ---
        $solicitud->tramites()->updateExistingPivot($tramiteId, [
            'ruta_archivo_final' => $ruta
        ]);

        // --- 4. RESPUESTA (Simple y rápida) ---
        return response()->json([
            'message' => "Archivo para trámite $tramiteId subido con éxito.",
            'ruta' => $ruta
        ], 200);
    }

    public function completar(Request $request, Solicitud $solicitud)
    {
        $solicitud->load('tramites', 'user');

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

        if ($solicitud->estado !== 'completado') {
            $solicitud->estado = 'completado';
            $solicitud->save();

            try {
                $secretaria = Auth::user();
                $estudiante = $solicitud->user;
                if ($estudiante && $estudiante->email) {
                    Mail::to($estudiante->email)->send(
                        new SolicitudCompletadaMail($solicitud, $secretaria)
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

    public function marcarManual(Request $request, Solicitud $solicitud)
    {
        $request->validate([
            'tramite_id' => 'required|integer'
        ]);
        
        $tramiteId = $request->tramite_id;

        // Actualizamos el pivot: ponemos el flag en true y borramos ruta de archivo si existía
        $solicitud->tramites()->updateExistingPivot($tramiteId, [
            'completado_manual' => true,
            'ruta_archivo_final' => null
        ]);

        return response()->json(['message' => 'Trámite marcado como completado manualmente.'], 200);
    }
}