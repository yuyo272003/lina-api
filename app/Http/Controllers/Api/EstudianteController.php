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
     * Almacena una nueva solicitud junto con la generación de la orden de pago en PDF.
     * Maneja la subida de archivos (documentos) y datos (texto/número).
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // El array 'tramites' viene como JSON string dentro de FormData
        $tramitesJson = $request->input('tramites');
        $tramitesData = json_decode($tramitesJson, true);

        // Validación estricta de que los datos de trámites existen y son válidos.
        if (empty($tramitesData) || !is_array($tramitesData)) {
            return response()->json([
                'error' => 'La estructura de datos de los trámites es inválida o falta.',
                'details' => [
                    'tramites_json' => $tramitesJson,
                    'validation' => 'Fallo al decodificar JSON.'
                ]
            ], 422);
        }

        // Validación de que al menos un trámite tiene el ID requerido
        $request->merge(['tramites_data' => $tramitesData]);
        $request->validate([
            'tramites_data.*.id' => 'required|integer|exists:tramites,idTramite',
        ]);

        $user = Auth::user();

        // Extraemos solo los IDs para las relaciones
        $tramite_ids = collect($tramitesData)->pluck('id')->all();
        $tramites = Tramite::find($tramite_ids);
        $montoTotal = $tramites->sum('costoTramite');

        $numeroCuentaDestino = Configuracion::where('clave', 'NUMERO_CUENTA_DESTINO')->value('valor');

        // Si no se encuentra, usar un valor predeterminado o lanzar un error
        if (!$numeroCuentaDestino) {
            return response()->json(['error' => 'No se encontró el número de cuenta bancaria de destino en la configuración.'], 500);
        }

        // CREACIÓN DE SOLICITUD Y ORDEN DE PAGO
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

        // OBTENER REQUISITOS PARA SABER EL TIPO
        $allRequisitos = Requisito::all()->keyBy('nombreRequisito');

        // GUARDAR LAS RESPUESTAS DE LOS REQUISITOS
        foreach ($tramitesData as $tramiteData) {
            if (!empty($tramiteData['respuestas'])) {
                foreach ($tramiteData['respuestas'] as $nombreRequisito => $respuesta) {
                    $requisito = $allRequisitos[$nombreRequisito] ?? null;

                    if (!$requisito) continue;

                    $respuestaFinal = $respuesta;

                    // Lógica para documentos
                    if ($requisito->tipo === 'documento') {
                        // Buscar el archivo subido en la estructura
                        $archivo = $request->file("files.{$tramiteData['id']}.{$nombreRequisito}");

                        if ($archivo) {
                            // Validación del archivo
                            if ($archivo->getClientMimeType() !== 'application/pdf' || $archivo->getSize() > 10 * 1024 * 1024) {
                                continue;
                            }

                            // Almacenar el archivo en storage/app/public/documentos/{idSolicitud}
                            $nombreArchivo = "{$nombreRequisito}_" . time() . '.' . $archivo->extension();
                            $ruta = $archivo->storeAs("documentos/{$solicitud->idSolicitud}", $nombreArchivo, 'public');

                            // Guardar la RUTA del archivo en la BD
                            $respuestaFinal = $ruta;
                        } else {
                            continue;
                        }
                    }

                    // Guardar la respuesta (dato de texto o ruta de archivo)
                    SolicitudRespuesta::create([
                        'solicitud_id' => $solicitud->idSolicitud,
                        'tramite_id' => $tramiteData['id'],
                        'requisito_id' => $requisito->idRequisito,
                        'respuesta' => $respuestaFinal,
                    ]);
                }
            }
        }

        // GENERACIÓN DE PDF
        $data = [
            'solicitud' => $solicitud,
            'ordenPago' => $ordenPago,
            'tramites' => $tramites,
            'user' => $user->load('estudiante.programaEducativo'),
        ];

        $pdf = Pdf::loadView('pdf.orden_pago', $data);
        $nombreArchivo = 'orden-de-pago-' . $solicitud->folio . '.pdf';

        // DEVOLVER RESPUESTA CON ENCABEZADOS CORRECTOS
        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $nombreArchivo . '"',
        ]);
    }

    /**
     * Permite al estudiante subir el comprobante de pago.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Solicitud $solicitud
     * @return \Illuminate\Http\JsonResponse
     */
    public function subirComprobante(Request $request, Solicitud $solicitud)
    {
        // Validación
        $request->validate([
            'comprobante' => 'required|file|mimes:pdf|max:10000',
        ]);

        // Lógica para determinar si la solicitud fue rechazada por Contador (Rol 7)
        $rechazadaPorContador = (
            strtolower($solicitud->estado) === 'rechazada' &&
            $solicitud->rol_rechazo == 7
        );

        // Guardar el archivo
        if ($request->hasFile('comprobante')) {
            // Generar un nombre único para evitar colisiones
            $nombreArchivo = 'comprobante_' . $solicitud->id . '_' . time() . '.' . $request->file('comprobante')->extension();

            // Guardar en 'storage/app/public/comprobantes'
            $ruta = $request->file('comprobante')->storeAs('comprobantes', $nombreArchivo, 'public');

            // Actualizar la base de datos
            $solicitud->ruta_comprobante = $ruta;

            // LÓGICA DE TRANSICIÓN DE ESTADO
            if ($rechazadaPorContador) {
                // Si la rechazo el Contador, al re-subir vuelve a la fase de revisión 2
                $solicitud->estado = 'en revisión 2';
            } else {
                // Si estaba en 'en proceso' o rechazada por el coordinador, pasa a 'en revisión 1'
                $solicitud->estado = 'en revisión 1';
            }

            // Limpiar la información de rechazo anterior
            $solicitud->rol_rechazo = null;
            $solicitud->observaciones = null;

            $solicitud->save();

            // Devolver respuesta de éxito
            return response()->json([
                'message' => 'Comprobante subido con éxito.',
                'solicitud' => $solicitud
            ], 200);
        }

        return response()->json(['error' => 'No se encontró el archivo del comprobante.'], 400);
    }

    /**
     * Cancela la solicitud. Solo permitido si está en 'en proceso' o 'rechazada'.
     *
     * @param \App\Models\Solicitud $solicitud
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelar(Solicitud $solicitud)
    {
        //Solo el dueño de la solicitud puede cancelarla.
        if (Auth::id() !== $solicitud->user_id) {
            return response()->json(['message' => 'No autorizado para cancelar esta solicitud.'], 403);
        }

        $estadoActual = strtolower($solicitud->estado);

        // Solo se permite cancelar si el estado es 'en proceso' o 'rechazada'.
        if ($estadoActual !== 'en proceso' && !Str::contains($estadoActual, 'rechazada')) {
            return response()->json([
                'message' => "La solicitud no se puede cancelar en el estado actual: '{$solicitud->estado}'."
            ], 409);
        }

        // Actualizar el estado a 'cancelada'
        $solicitud->estado = 'cancelada';
        $solicitud->observaciones = 'Cancelada por el usuario.';
        $solicitud->save();

        // Devolver la solicitud actualizada
        $solicitud->load([
            'tramites',
            'user' => function ($query) {
                $query->select('id', 'name', 'first_name', 'last_name', 'email');
            }
        ]);

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
     * 💡 MÉTODO 100% LIMPIO
     * Permite al estudiante modificar las respuestas de los requisitos
     * SOLO SI la solicitud fue rechazada por un Coordinador (Rol 5 o 6).
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Solicitud $solicitud
     * @return \Illuminate\Http\JsonResponse
     */
    public function modificarRespuestas(Request $request, Solicitud $solicitud)
    {
        // 1. Autorización: ¿El usuario es el dueño de la solicitud?
        if (Auth::id() !== $solicitud->user_id) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        // 2. Autorización: ¿La solicitud está en el estado correcto?
        $estadoActual = strtolower($solicitud->estado);
        $rechazadoPorCoordinador = (
            $estadoActual === 'rechazada' &&
            ($solicitud->rol_rechazo == 5 || $solicitud->rol_rechazo == 6)
        );

        if (!$rechazadoPorCoordinador) {
            return response()->json([
                'message' => 'Esta solicitud no puede ser modificada en este momento.'
            ], 409); // 409 Conflict
        }

        // 3. Procesamiento de datos
        $tramitesJson = $request->input('tramites');
        $tramitesData = json_decode($tramitesJson, true);

        if (empty($tramitesData) || !is_array($tramitesData)) {
            return response()->json(['error' => 'La estructura de datos de los trámites es inválida.'], 422);
        }

        // 4. OBTENER REQUISITOS
        $allRequisitos = Requisito::all()->keyBy('nombreRequisito');

        // 5. ACTUALIZAR LAS RESPUESTAS
        foreach ($tramitesData as $tramiteData) {
            if (empty($tramiteData['respuestas'])) continue;

            $tramite_id = $tramiteData['id'];

            foreach ($tramiteData['respuestas'] as $nombreRequisito => $nuevaRespuesta) {
                $requisito = $allRequisitos[$nombreRequisito] ?? null;
                if (!$requisito) continue;

                $requisito_id = $requisito->idRequisito;

                // Buscar la respuesta existente
                $respuestaExistente = SolicitudRespuesta::where('solicitud_id', $solicitud->idSolicitud)
                    ->where('tramite_id', $tramite_id)
                    ->where('requisito_id', $requisito_id)
                    ->first();

                if (!$respuestaExistente) continue;

                // --- INICIO DE LÓGICA CORRECTA ---

                if ($requisito->tipo === 'documento') {
                    // 1. Buscar si se subió un archivo *nuevo*
                    $archivo = $request->file("files.{$tramite_id}.{$nombreRequisito}");

                    // 2. Si SÍ hay un archivo nuevo, lo procesamos
                    if ($archivo) {
                        // Validación
                        if ($archivo->getClientMimeType() !== 'application/pdf' || $archivo->getSize() > 10 * 1024 * 1024) {
                            continue; // Saltar este archivo si es inválido
                        }
                        
                        // Borrar el archivo anterior si existe
                        if ($respuestaExistente->respuesta && Storage::disk('public')->exists($respuestaExistente->respuesta)) {
                            Storage::disk('public')->delete($respuestaExistente->respuesta);
                        }

                        // Almacenar el nuevo
                        $nombreArchivo = "{$nombreRequisito}_" . time() . '.' . $archivo->extension();
                        $ruta = $archivo->storeAs("documentos/{$solicitud->idSolicitud}", $nombreArchivo, 'public');
                        
                        // 3. Guardar la NUEVA RUTA en la BD
                        $respuestaExistente->respuesta = $ruta;
                        $respuestaExistente->save();
                    }
                    // 4. Si NO hay archivo nuevo, no hacemos nada.

                } else {
                    // Es un requisito de tipo 'dato' (texto)
                    // Simplemente actualizamos el valor
                    $respuestaExistente->respuesta = $nuevaRespuesta; // $nuevaRespuesta es el texto del JSON
                    $respuestaExistente->save();
                } 
                
                // --- FIN DE LÓGICA CORRECTA ---
            }
        }

        // 6. Transición de Estado
        $solicitud->estado = 'en revisión 1';
        $solicitud->rol_rechazo = null;   // Limpiar el rechazo anterior
        $solicitud->observaciones = null; // Limpiar las observaciones anteriores
        $solicitud->save();

        // 7. Respuesta
        return response()->json([
            'message' => 'Solicitud actualizada y enviada a revisión con éxito.',
            'solicitud' => $solicitud->fresh()->load('tramites') // Devolver la solicitud actualizada
        ], 200);
    }
}