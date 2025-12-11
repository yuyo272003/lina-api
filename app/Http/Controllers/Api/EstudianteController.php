<?php

namespace App\Http\Controllers\Api;

use App\Models\Solicitud;
use App\Models\Tramite;
use App\Models\Requisito;
use App\Models\SolicitudRespuesta;
use App\Models\Configuracion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Controllers\Api\SolicitudController;

class EstudianteController extends SolicitudController
{
    /**
     * Recupera el perfil académico del estudiante con su programa educativo asociado.
     */
    public function getProfile()
    {
        $user = Auth::user();
        $estudiante = $user->estudiante()->with('programaEducativo')->first();

        if (!$estudiante) {
            return response()->json(['error' => 'Perfil de estudiante no encontrado para este usuario.'], 404);
        }

        return response()->json([
            'nombre_completo'      => $user->name,
            'matricula'            => $estudiante->matriculaEstudiante,
            'programa_educativo'   => $estudiante->programaEducativo->nombrePE ?? 'No asignado',
            'correo_institucional' => $user->email,
            'grupo'                => $estudiante->grupoEstudiante ?? 'No asignado'
        ]);
    }

    /**
     * Procesa la creación de una solicitud compleja.
     * 1. Decodifica el payload JSON de trámites.
     * 2. Calcula costos y valida integridad.
     * 3. Gestiona la persistencia de respuestas de texto y archivos PDF.
     * 4. Genera y retorna la Orden de Pago (PDF) en la misma transacción.
     * * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response PDF Stream
     */
    public function store(Request $request)
    {
        

        // Decodificación y validación de estructura JSON dentro de FormData
        $tramitesJson = $request->input('tramites');
        $tramitesData = json_decode($tramitesJson, true);

        if (empty($tramitesData) || !is_array($tramitesData)) {
            return response()->json([
                'error' => 'La estructura de datos de los trámites es inválida o falta.',
                'details' => ['tramites_json' => $tramitesJson]
            ], 422);
        }

        $request->merge(['tramites_data' => $tramitesData]);
        $request->validate([
            'tramites_data.*.id' => 'required|integer|exists:tramites,idTramite',
        ]);

        $user = Auth::user();
        $tramite_ids = collect($tramitesData)->pluck('id')->all();
        $tramites = Tramite::find($tramite_ids);
        $montoTotal = $tramites->sum('costoTramite');

        $numeroCuentaDestino = Configuracion::where('clave', 'NUMERO_CUENTA_DESTINO')->value('valor');

        if (!$numeroCuentaDestino) {
            return response()->json(['error' => 'No se encontró el número de cuenta bancaria de destino en la configuración.'], 500);
        }

        // Persistencia de Entidades: Solicitud -> Relación Trámites -> Orden de Pago
        $solicitud = Solicitud::create([
            'user_id' => $user->id,
            'folio' => 'SOL-' . now()->format('Ymd') . '-' . Str::random(6),
            'estado' => 'en proceso',
        ]);

        $solicitud->tramites()->attach($tramite_ids);

        $ordenPago = $solicitud->ordenesPago()->create([
            'montoTotal' => $montoTotal,
            'numeroCuentaDestino' => $numeroCuentaDestino,
        ]);

        // Procesamiento iterativo de requisitos (Textos y Archivos)
        $allRequisitos = Requisito::all()->keyBy('nombreRequisito');

        foreach ($tramitesData as $tramiteData) {
            if (!empty($tramiteData['respuestas'])) {
                foreach ($tramiteData['respuestas'] as $nombreRequisito => $respuesta) {
                    $requisito = $allRequisitos[$nombreRequisito] ?? null;
                    if (!$requisito) continue;

                    $respuestaFinal = $respuesta;

                    // Manejo de Archivos: Validación MIME y Almacenamiento en disco público
                    if ($requisito->tipo === 'documento') {
                        // El archivo se recupera usando la clave indexada construida en el frontend
                        $archivo = $request->file("files.{$tramiteData['id']}.{$nombreRequisito}");

                        if ($archivo) {
                            if ($archivo->getClientMimeType() !== 'application/pdf' || $archivo->getSize() > 10 * 1024 * 1024) {
                                continue;
                            }

                            $nombreArchivo = "{$nombreRequisito}_" . time() . '.' . $archivo->extension();
                            $ruta = $archivo->storeAs("documentos/{$solicitud->idSolicitud}", $nombreArchivo, 'public');
                            $respuestaFinal = $ruta;
                        } else {
                            continue;
                        }
                    }

                    SolicitudRespuesta::create([
                        'solicitud_id' => $solicitud->idSolicitud,
                        'tramite_id' => $tramiteData['id'],
                        'requisito_id' => $requisito->idRequisito,
                        'respuesta' => $respuestaFinal,
                    ]);
                }
            }
        }

        // Generación de PDF con DomPDF
        $data = [
            'solicitud' => $solicitud,
            'ordenPago' => $ordenPago,
            'tramites' => $tramites,
            'user' => $user->load('estudiante.programaEducativo'),
        ];

        $pdf = Pdf::loadView('pdf.orden_pago', $data);
        $nombreArchivo = 'orden-de-pago-' . $solicitud->folio . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $nombreArchivo . '"',
        ]);
    }

    /**
     * Sube el comprobante de pago y gestiona la transición de estados.
     * Si fue rechazado por Contador -> Regresa a 'en revisión 2'.
     * En cualquier otro caso -> Avanza a 'en revisión 1'.
     */
    public function subirComprobante(Request $request, Solicitud $solicitud)
    {
        $request->validate([
            'comprobante' => 'required|file|mimes:pdf|max:10000',
        ]);

        $rechazadaPorContador = (
            strtolower($solicitud->estado) === 'rechazada' &&
            $solicitud->rol_rechazo == 7
        );

        if ($request->hasFile('comprobante')) {
            $nombreArchivo = 'comprobante_' . $solicitud->id . '_' . time() . '.' . $request->file('comprobante')->extension();
            $ruta = $request->file('comprobante')->storeAs('comprobantes', $nombreArchivo, 'public');

            $solicitud->ruta_comprobante = $ruta;

            // Máquina de estados para reingreso al flujo
            if ($rechazadaPorContador) {
                $solicitud->estado = 'en revisión 2';
            } else {
                $solicitud->estado = 'en revisión 1';
            }

            $solicitud->rol_rechazo = null;
            $solicitud->observaciones = null;
            $solicitud->save();

            return response()->json([
                'message' => 'Comprobante subido con éxito.',
                'solicitud' => $solicitud
            ], 200);
        }

        return response()->json(['error' => 'No se encontró el archivo del comprobante.'], 400);
    }

    /**
     * Cancela una solicitud activa.
     * Restricción: Solo permitido en estados 'en proceso' o 'rechazada'.
     */
    public function cancelar(Solicitud $solicitud)
    {
        // Validación de propiedad (RBAC Ownership)
        if (Auth::id() !== $solicitud->user_id) {
            return response()->json(['message' => 'No autorizado para cancelar esta solicitud.'], 403);
        }

        $estadoActual = strtolower($solicitud->estado);

        if ($estadoActual !== 'en proceso' && !Str::contains($estadoActual, 'rechazada')) {
            return response()->json([
                'message' => "La solicitud no se puede cancelar en el estado actual: '{$solicitud->estado}'."
            ], 409);
        }

        $solicitud->estado = 'cancelada';
        $solicitud->observaciones = 'Cancelada por el usuario.';
        $solicitud->save();

        // Recarga de relaciones para actualizar la UI del cliente
        $solicitud->load([
            'tramites',
            'user' => function ($query) {
                $query->select('id', 'name', 'first_name', 'last_name', 'email');
            }
        ]);

        // Mapeo manual de respuestas para el frontend
        foreach ($solicitud->tramites as $tramite) {
            $respuestas = SolicitudRespuesta::where('solicitud_id', $solicitud->idSolicitud)
                ->where('tramite_id', $tramite->idTramite)
                ->join('requisitos', 'solicitud_respuestas.requisito_id', '=', 'requisitos.idRequisito')
                ->select('requisitos.nombreRequisito', 'solicitud_respuestas.respuesta')
                ->get();
            $tramite->respuestas = $respuestas;
        }

        $solicitud->comprobante = null;

        return response()->json([
            'message' => 'Solicitud cancelada con éxito.',
            'solicitud' => $solicitud
        ], 200);
    }

    /**
     * Permite la corrección de respuestas (archivos/datos) tras un rechazo por Coordinación.
     * Implementa lógica de reemplazo de archivos (eliminar viejo -> guardar nuevo).
     */
    public function modificarRespuestas(Request $request, Solicitud $solicitud)
    {
        

        if (Auth::id() !== $solicitud->user_id) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        // Validación de estado: Solo editable si fue rechazada por roles de Coordinación (5 o 6)
        $estadoActual = strtolower($solicitud->estado);
        $rechazadoPorCoordinador = (
            $estadoActual === 'rechazada' &&
            ($solicitud->rol_rechazo == 5 || $solicitud->rol_rechazo == 6)
        );

        if (!$rechazadoPorCoordinador) {
            return response()->json([
                'message' => 'Esta solicitud no puede ser modificada en este momento.'
            ], 409);
        }

        $tramitesJson = $request->input('tramites');
        $tramitesData = json_decode($tramitesJson, true);

        if (empty($tramitesData) || !is_array($tramitesData)) {
            return response()->json(['error' => 'La estructura de datos de los trámites es inválida.'], 422);
        }

        $allRequisitos = Requisito::all()->keyBy('nombreRequisito');

        foreach ($tramitesData as $tramiteData) {
            if (empty($tramiteData['respuestas'])) continue;

            $tramite_id = $tramiteData['id'];

            foreach ($tramiteData['respuestas'] as $nombreRequisito => $nuevaRespuesta) {
                $requisito = $allRequisitos[$nombreRequisito] ?? null;
                if (!$requisito) continue;

                $respuestaExistente = SolicitudRespuesta::where('solicitud_id', $solicitud->idSolicitud)
                    ->where('tramite_id', $tramite_id)
                    ->where('requisito_id', $requisito->idRequisito)
                    ->first();

                if (!$respuestaExistente) continue;

                if ($requisito->tipo === 'documento') {
                    // Procesamiento de reemplazo de archivo
                    $archivo = $request->file("files.{$tramite_id}.{$nombreRequisito}");

                    if ($archivo) {
                        if ($archivo->getClientMimeType() !== 'application/pdf' || $archivo->getSize() > 10 * 1024 * 1024) {
                            continue;
                        }
                        
                        // Limpieza de almacenamiento: Eliminar archivo obsoleto
                        if ($respuestaExistente->respuesta && Storage::disk('public')->exists($respuestaExistente->respuesta)) {
                            Storage::disk('public')->delete($respuestaExistente->respuesta);
                        }

                        $nombreArchivo = "{$nombreRequisito}_" . time() . '.' . $archivo->extension();
                        $ruta = $archivo->storeAs("documentos/{$solicitud->idSolicitud}", $nombreArchivo, 'public');
                        
                        $respuestaExistente->respuesta = $ruta;
                        $respuestaExistente->save();
                    }
                } else {
                    // Actualización directa de dato escalar
                    $respuestaExistente->respuesta = $nuevaRespuesta;
                    $respuestaExistente->save();
                } 
            }
        }

        // Reingreso al flujo de revisión
        $solicitud->estado = 'en revisión 1';
        $solicitud->rol_rechazo = null;
        $solicitud->observaciones = null;
        $solicitud->save();

        return response()->json([
            'message' => 'Solicitud actualizada y enviada a revisión con éxito.',
            'solicitud' => $solicitud->fresh()->load('tramites')
        ], 200);
    }
}