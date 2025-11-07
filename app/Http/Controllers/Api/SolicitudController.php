<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Solicitud;
use App\Models\Tramite;
use App\Models\Requisito;
use App\Models\SolicitudRespuesta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Models\Configuracion;
use Illuminate\Support\Facades\Mail;
use App\Mail\SolicitudRechazadaMail;
use App\Mail\SolicitudRechazadaCoordinadorMail;

class SolicitudController extends Controller
{
    /**
     * Define los IDs de los roles administrativos/de coordinación.
     * @var array
     */
    private $rolesAdministrativos = [5, 6, 7, 8];

    /**
     * Mapeo de IDs de rol a nombres legibles para el frontend.
     * @var array
     */
    private $mapaRoles = [
        5 => 'Coordinación',
        6 => 'Coordinación',
        7 => 'Contaduría',
        8 => 'Secretaría'
    ];

    /**
     * Verifica si el usuario autenticado tiene un rol administrativo o de coordinación.
     * @param int $userId
     * @return bool
     */
    private function tieneRolAdministrativo(int $userId): bool
    {
        return DB::table('role_usuario')
            ->where('user_id', $userId)
            ->whereIn('role_id', $this->rolesAdministrativos)
            ->exists();
    }

    /**
     * Obtiene el ID del rol administrativo del usuario actual que está realizando la acción.
     *
     * @return int|null
     */
    private function obtenerRolAccion(): ?int
    {
        return DB::table('role_usuario')
            ->where('user_id', Auth::id())
            ->whereIn('role_id', $this->rolesAdministrativos)
            ->value('role_id');
    }

    /**
     * Almacena una nueva solicitud junto con la generación de la orden de pago en PDF.
     * Maneja la subida de archivos (documentos) y datos (texto/número).
     *
     * @param    \Illuminate\Http\Request  $request
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
                        'tramite_id'   => $tramiteData['id'],
                        'requisito_id' => $requisito->idRequisito,
                        'respuesta'    => $respuestaFinal,
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
     * Muestra una lista de solicitudes basadas en el rol del usuario autenticado.
     *
     * @param    \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Obtener el role_id del usuario.
        $userRole = DB::table('role_usuario')
            ->where('user_id', $user->id)
            ->value('role_id');

        // Fases 'En revisión'
        $estados_visibles = [];
        $roles_coordinacion = [5, 6]; // Roles que ven la fase inicial


        // Lógica de Visibilidad Escalonada para 'En revisión'
        if (in_array($userRole, $roles_coordinacion)) {
            // Coordinadores (Rol 5 y 6) ven todas las fases de revisión
            $estados_visibles = ['en revisión 1', 'en revisión 2', 'en revisión 3'];
        } elseif ($userRole == 7) {
            // Contador (Rol 7) ve a partir de la FASE 2
            $estados_visibles = ['en revisión 2', 'en revisión 3'];
        } elseif ($userRole == 8) {
            // Secretario (Rol 8) ve a partir de la FASE 3
            $estados_visibles = ['en revisión 3'];
        }

        // Se mantienen los estados finales visibles para algunos roles administrativos
        if (in_array($userRole, $this->rolesAdministrativos)) { // Todos los roles administrativos ven "completada"
            $estados_visibles[] = 'completada';
        }

        // Construimos la query base
        $solicitudesQuery = DB::table('solicitudes')
            ->leftJoin('solicitud_tramite', 'solicitudes.idSolicitud', '=', 'solicitud_tramite.idSolicitud')
            ->leftJoin('tramites', 'solicitud_tramite.idTramite', '=', 'tramites.idTramite')
            ->select(
                'solicitudes.idSolicitud',
                'solicitudes.folio',
                'solicitudes.estado',
                'solicitudes.created_at',
                'solicitudes.rol_rechazo',
                DB::raw("GROUP_CONCAT(tramites.nombreTramite SEPARATOR ', ') as tramites_nombres")
            )
            // Agregamos 'rol_rechazo' al GROUP BY
            ->groupBy('solicitudes.idSolicitud', 'solicitudes.folio', 'solicitudes.estado', 'solicitudes.created_at', 'solicitudes.rol_rechazo')
            ->orderBy('solicitudes.created_at', 'desc');

        // Lógica de Filtrado por Rol
        if (in_array($userRole, $this->rolesAdministrativos)) {
            $solicitudesQuery->where(function ($query) use ($estados_visibles, $userRole, $roles_coordinacion) {
                // Mostrar las solicitudes en estados de "revisión" o "completada" según su rol
                if (!empty($estados_visibles)) {
                    $query->whereIn(DB::raw('LOWER(solicitudes.estado)'), array_filter($estados_visibles, fn($e) => $e !== 'rechazada'));
                }

                // Mostrar las solicitudes 'rechazadas' que fueron rechazadas por ESTE rol.
                // Para Coordinadores (Roles 5 y 6)
                if (in_array($userRole, $roles_coordinacion)) {
                    $query->orWhere(function ($q) use ($roles_coordinacion) {
                        $q->where(DB::raw('LOWER(solicitudes.estado)'), 'rechazada')
                          ->whereIn('solicitudes.rol_rechazo', $roles_coordinacion);
                    });
                }
                // Para Contador (Rol 7) y Secretario (Rol 8): ver rechazos solo de su ID de rol
                elseif (in_array($userRole, [7, 8])) {
                    $query->orWhere(function ($q) use ($userRole) {
                        $q->where(DB::raw('LOWER(solicitudes.estado)'), 'rechazada')
                          ->where('solicitudes.rol_rechazo', $userRole);
                    });
                }
            });

        } elseif ($userRole == 3 || $userRole == 4) {
            // ROL 3 Y 4 (Estudiantes): Solo pueden ver sus propias solicitudes en CUALQUIER estado.
            $solicitudesQuery->where('solicitudes.user_id', $user->id);
        } else {
            $solicitudesQuery->whereRaw('1 = 0');
        }
        $solicitudes = $solicitudesQuery->get();
        return response()->json($solicitudes);
    }

    /**
     * Muestra los detalles de una solicitud específica.
     *
     * @param    \App\Models\Solicitud  $solicitud
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Solicitud $solicitud)
    {
        $esAdminODirectivo = $this->tieneRolAdministrativo(Auth::id());

        if (Auth::id() !== $solicitud->user_id && !$esAdminODirectivo) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $solicitud->load([
            'tramites',
            'user' => function ($query) {
                $query->select('id', 'name', 'first_name', 'last_name', 'email')
                    ->with([
                        'estudiante' => function ($queryEstudiante) {
                            $queryEstudiante->select('idEstudiante', 'user_id', 'idPE', 'matriculaEstudiante', 'grupoEstudiante', 'semestreEstudiante')
                                ->with([
                                    'programaEducativo' => function ($queryPE) {
                                        $queryPE->select('idPE', 'nombrePE');
                                    }
                                ]);
                        }
                    ]);
            }
        ]);

        foreach ($solicitud->tramites as $tramite) {
            $respuestas = SolicitudRespuesta::where('solicitud_id', $solicitud->idSolicitud)
                ->where('tramite_id', $tramite->idTramite)
                ->join('requisitos', 'solicitud_respuestas.requisito_id', '=', 'requisitos.idRequisito')
                ->select('requisitos.nombreRequisito', 'solicitud_respuestas.respuesta', 'requisitos.tipo')
                ->get();

            // Mapear la respuesta para generar la URL si es un documento
            $tramite->respuestas = $respuestas->map(function($respuesta) {
                if ($respuesta->tipo === 'documento' && Storage::disk('public')->exists($respuesta->respuesta)) {
                    $respuesta->url_documento = asset('storage/' . $respuesta->respuesta);
                    $respuesta->nombre_archivo = basename($respuesta->respuesta);
                } else {
                    $respuesta->url_documento = null;
                    $respuesta->nombre_archivo = null;
                }
                return $respuesta;
            });
        }

        // Buscar comprobante físico
        $rutaAlmacenada = $solicitud->ruta_comprobante;
        if ($rutaAlmacenada && Storage::disk('public')->exists($rutaAlmacenada)) {
            $solicitud->comprobante = [
                'nombreArchivo' => basename($rutaAlmacenada),
                'url' => asset('storage/' . $rutaAlmacenada),
            ];
        } else {
            $solicitud->comprobante = null;
        }

        // Exponer el rol que rechazó la solicitud para el estudiante
        if (strtolower($solicitud->estado) === 'rechazada' && $solicitud->rol_rechazo) {
            $rolId = (int) $solicitud->rol_rechazo;
            $solicitud->rol_rechazo_nombre = $this->mapaRoles[$rolId] ?? 'Rol Desconocido';
        } else {
             $solicitud->rol_rechazo_nombre = null;
        }

        return response()->json($solicitud);
    }

    /**
     * Genera y descarga el PDF de la orden de pago para una solicitud existente.
     *
     * @param    \App\Models\Solicitud  $solicitud
     * @return \Illuminate\Http\Response
     */
    public function downloadOrdenDePago(Solicitud $solicitud)
    {
        // 1. Verificación de autorización: El usuario debe ser el dueño O tener un rol administrativo
        $esAdminODirectivo = $this->tieneRolAdministrativo(Auth::id());

        if (Auth::id() !== $solicitud->user_id && !$esAdminODirectivo) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        // 2. Cargar las relaciones de la solicitud
        $solicitud->load('tramites', 'ordenesPago');

        // 3. Obtener la primera orden de pago asociada
        $ordenPago = $solicitud->ordenesPago->first();

        if (!$ordenPago) {
             return response()->json(['message' => 'Orden de pago no encontrada.'], 404);
        }

        // 4. OBTENER EL USUARIO AUTENTICADO DIRECTAMENTE
        $user = Auth::user();
        $user->load('estudiante.programaEducativo');

        // 5. Preparar los datos para la vista del PDF
        $data = [
            'solicitud' => $solicitud,
            'ordenPago' => $ordenPago,
            'tramites'  => $solicitud->tramites,
            'user'      => $user,
        ];

        // 6. Generar el PDF
        $pdf = Pdf::loadView('pdf.orden_pago', $data);
        $nombreArchivo = 'orden-de-pago-' . $solicitud->folio . '.pdf';

        // 7. DEVOLVER RESPUESTA CON ENCABEZADOS CORRECTOS
        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $nombreArchivo . '"',
        ]);
    }

    /**
     * Permite al estudiante subir el comprobante de pago.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Solicitud  $solicitud
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
     * Actualiza el estado de una solicitud (Coordinador).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Solicitud  $solicitud
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

    /**
     * Actualiza el estado de una solicitud por parte del contador.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Solicitud  $solicitud
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

    /**
        * Cancela la solicitud. Solo permitido si está en 'en proceso' o 'rechazada'.
        *
        * @param  \App\Models\Solicitud  $solicitud
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
    * Actualiza el NUMERO_CUENTA_DESTINO GLOBAL en la tabla de configuraciones.
    *
    * @param  \Illuminate\Http\Request  $request
    * @return \Illuminate\Http\JsonResponse
    */
    public function updateNumeroCuentaGlobal(Request $request)
    {
        // 1. Autorización
        $rolActual = $this->obtenerRolAccion();

        if ($rolActual !== 5) {
            return response()->json([
                'message' => 'No autorizado. Solo el rol de Coordinación principal puede actualizar el número de cuenta.'
            ], 403);
        }

        // 2. Validación
        $request->validate([
            'numero_cuenta' => 'required|string|min:4|max:50',
        ]);

        // 3. Actualizar la configuración global en la BD
        $configuracion = Configuracion::where('clave', 'NUMERO_CUENTA_DESTINO')->first();

        if (!$configuracion) {
            return response()->json(['message' => 'Configuración de cuenta no encontrada.'], 404);
        }

        $configuracion->valor = $request->input('numero_cuenta');
        $configuracion->save();

        return response()->json([
            'message' => 'Número de cuenta GLOBAL actualizado con éxito.',
            'numero_cuenta' => $configuracion->valor
        ], 200);
    }

    /**
     * Obtiene el valor del NUMERO_CUENTA_DESTINO GLOBAL de la base de datos.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getNumeroCuentaGlobal()
    {
        if (!$this->tieneRolAdministrativo(Auth::id())) {
            // En este caso, permitiremos que cualquier admin lo vea.
            return response()->json(['message' => 'No autorizado para ver la configuración de la cuenta.'], 403);
        }

        $numeroCuenta = Configuracion::where('clave', 'NUMERO_CUENTA_DESTINO')->value('valor');

        return response()->json([
            'numero_cuenta' => $numeroCuenta,
        ]);
    }
}
