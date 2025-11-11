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
        // --- 1. CARGAR Y CONTAR ---
        $solicitud->load('tramites', 'user'); // Carga las relaciones

        $tramitesCompletados = $solicitud->tramites
            ->whereNotNull('pivot.ruta_archivo_final')
            ->count();
        $tramitesTotales = $solicitud->tramites->count();

        // Verificamos si todos los trámites pedidos ya tienen su archivo
        $todosListos = ($tramitesTotales > 0 && $tramitesTotales === $tramitesCompletados);

        // --- 2. VALIDAR LÓGICA ---
        if (!$todosListos) {
            // Si el usuario da clic en "Finalizar" pero faltan archivos
            return response()->json([
                'message' => "Aún faltan $tramitesFaltantes archivos por subir. No se puede completar.",
                'completados' => $tramitesCompletados,
                'totales' => $tramitesTotales
            ], 422); // 422 Unprocessable Content
        }

        // --- 3. CAMBIAR ESTADO Y ENVIAR CORREO ---
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
                Log::error("❌ Error al enviar correo de solicitud completada: " . $e->getMessage());
                // NOTA: No devolvemos error, la solicitud SÍ se completó, solo falló el correo.
                // Esto es una decisión de negocio, podrías querer manejarlo diferente.
            }
        }

        // --- 4. RESPUESTA FINAL ---
        return response()->json([
            'message' => 'Proceso finalizado y solicitud completada con éxito.',
            'solicitud' => $solicitud->fresh()->load('tramites'),
        ], 200);
    }
}