<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\SolicitudCompletadaMail;
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
    protected function tieneRolAdministrativo(int $userId): bool
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
    protected function obtenerRolAccion(): ?int
    {
        return DB::table('role_usuario')
            ->where('user_id', Auth::id())
            ->whereIn('role_id', $this->rolesAdministrativos)
            ->value('role_id');
    }

    /**
     * Muestra una lista de solicitudes basadas en el rol del usuario autenticado.
     *
     * @param \Illuminate\Http\Request $request
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

        // AGREGAR: Todos los roles administrativos (5, 6, 7, 8) ven las solicitudes 'completada'
        if (in_array($userRole, $this->rolesAdministrativos)) { 
            $estados_visibles[] = 'completado';
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
            // Roles administrativos (5-8) ven todas las solicitudes en sus fases de revisión, 'completada' y sus propios rechazos.
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
                } // Para Contador (Rol 7) y Secretario (Rol 8): ver rechazos solo de su ID de rol
                elseif (in_array($userRole, [7, 8])) {
                    $query->orWhere(function ($q) use ($userRole) {
                        $q->where(DB::raw('LOWER(solicitudes.estado)'), 'rechazada')
                            ->where('solicitudes.rol_rechazo', $userRole);
                    });
                }
            });

        } elseif ($userRole == 3 || $userRole == 4) {
            // ROL 3 Y 4 (Estudiantes): Solo pueden ver sus propias solicitudes en CUALQUIER estado, incluyendo 'completada'.
            $solicitudesQuery->where('solicitudes.user_id', $user->id);

        } else {
            // Cualquier otro rol ve nada
            $solicitudesQuery->whereRaw('1 = 0');
        }
        $solicitudes = $solicitudesQuery->get();
        return response()->json($solicitudes);
    }

    /**
     * Muestra los detalles de una solicitud específica.
     *
     * @param \App\Models\Solicitud $solicitud
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

        // Cargar las respuestas de requisitos subidos por el alumno
        foreach ($solicitud->tramites as $tramite) {
            $respuestas = SolicitudRespuesta::where('solicitud_id', $solicitud->idSolicitud)
                ->where('tramite_id', $tramite->idTramite)
                ->join('requisitos', 'solicitud_respuestas.requisito_id', '=', 'requisitos.idRequisito')
                ->select('requisitos.nombreRequisito', 'solicitud_respuestas.respuesta', 'requisitos.tipo')
                ->get();

            // Mapear la respuesta para generar la URL si es un documento (subido por el alumno)
            $tramite->respuestas = $respuestas->map(function ($respuesta) {
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

        // Buscar comprobante físico (si existe)
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
            $rolId = (int)$solicitud->rol_rechazo;
            $solicitud->rol_rechazo_nombre = $this->mapaRoles[$rolId] ?? 'Rol Desconocido';
        } else {
            $solicitud->rol_rechazo_nombre = null;
        }

        if (strtolower($solicitud->estado) === 'completado') {
            foreach ($solicitud->tramites as $tramite) {
                // La ruta está en la tabla pivote solicitud_tramite (ruta_archivo_final)
                $rutaFinal = $tramite->pivot->ruta_archivo_final ?? null;

                if ($rutaFinal && Storage::disk('public')->exists($rutaFinal)) {
                    
                    // Obtener la extensión del archivo almacenado
                    $extension = pathinfo(Storage::disk('public')->path($rutaFinal), PATHINFO_EXTENSION);
                    
                    // Crear el nombre de archivo legible: NombreTramite.ext
                    $nombreLegible = $tramite->nombreTramite . '.' . $extension;

                    // Adjuntar al objeto del trámite para el frontend
                    $tramite->url_archivo_final = asset('storage/' . $rutaFinal);
                    $tramite->nombre_archivo_final = $nombreLegible;
                } else {
                    $tramite->url_archivo_final = null;
                    $tramite->nombre_archivo_final = null;
                }
            }
        }

        return response()->json($solicitud);
    }

    /**
     * Genera y descarga el PDF de la orden de pago para una solicitud existente.
     *
     * @param \App\Models\Solicitud $solicitud
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
            'tramites' => $solicitud->tramites,
            'user' => $user,
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
}