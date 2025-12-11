<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Solicitud;
use App\Models\SolicitudRespuesta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SolicitudController extends Controller
{
    private $rolesAdministrativos = [5, 6, 7, 8];

    // Mapeo para visualización de roles en frontend
    private $mapaRoles = [
        5 => 'Coordinación',
        6 => 'Coordinación',
        7 => 'Contaduría',
        8 => 'Secretaría'
    ];

    /**
     * Valida permisos administrativos sobre el usuario actual mediante consulta directa a la tabla pivote.
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

    protected function obtenerRolAccion(): ?int
    {
        return DB::table('role_usuario')
            ->where('user_id', Auth::id())
            ->whereIn('role_id', $this->rolesAdministrativos)
            ->value('role_id');
    }

    /**
     * Retorna el listado de solicitudes aplicando filtros de visibilidad basados en el rol del usuario (RBAC).
     * Implementa lógica escalonada para fases de revisión.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $userRole = DB::table('role_usuario')
            ->where('user_id', $user->id)
            ->value('role_id');

        // Configuración de visibilidad por fases del proceso
        $estados_visibles = [];
        $roles_coordinacion = [5, 6]; 

        if (in_array($userRole, $roles_coordinacion)) {
            $estados_visibles = ['en revisión 1', 'en revisión 2', 'en revisión 3'];
        } elseif ($userRole == 7) {
            $estados_visibles = ['en revisión 2', 'en revisión 3'];
        } elseif ($userRole == 8) {
            $estados_visibles = ['en revisión 3'];
        }

        if (in_array($userRole, $this->rolesAdministrativos)) { 
            $estados_visibles[] = 'completado';
        }

        // Compatibilidad SQL: SQLite vs MySQL
        $driver = DB::connection()->getDriverName();
        $groupConcat = $driver === 'sqlite' 
            ? "GROUP_CONCAT(tramites.nombreTramite, ', ')" 
            : "GROUP_CONCAT(tramites.nombreTramite SEPARATOR ', ')";

        $solicitudesQuery = DB::table('solicitudes')
            ->leftJoin('solicitud_tramite', 'solicitudes.idSolicitud', '=', 'solicitud_tramite.idSolicitud')
            ->leftJoin('tramites', 'solicitud_tramite.idTramite', '=', 'tramites.idTramite')
            ->join('users as u_estudiante', 'solicitudes.user_id', '=', 'u_estudiante.id')
            ->join('estudiantes', 'u_estudiante.id', '=', 'estudiantes.user_id')
            ->select(
                'solicitudes.idSolicitud',
                'solicitudes.folio',
                'solicitudes.estado',
                'solicitudes.created_at',
                'solicitudes.rol_rechazo',
                DB::raw("$groupConcat as tramites_nombres")
            )
            ->groupBy(
                'solicitudes.idSolicitud', 
                'solicitudes.folio', 
                'solicitudes.estado', 
                'solicitudes.created_at', 
                'solicitudes.rol_rechazo'
            )
            ->orderBy('solicitudes.created_at', 'desc');

        // Aplicación de filtros de seguridad
        if (in_array($userRole, $this->rolesAdministrativos)) {
            
            // Filtro específico para Coordinadores de Programa Educativo (Rol 6)
            if ($userRole == 6 && !is_null($user->idPE)) {
                $solicitudesQuery->where('estudiantes.idPE', $user->idPE);
            }

            $solicitudesQuery->where(function ($query) use ($estados_visibles, $userRole, $roles_coordinacion) {
                
                if (!empty($estados_visibles)) {
                    $query->whereIn(DB::raw('LOWER(solicitudes.estado)'), array_filter($estados_visibles, fn($e) => $e !== 'rechazada'));
                }

                // Visibilidad de rechazos según jerarquía
                if (in_array($userRole, $roles_coordinacion)) {
                    $query->orWhere(function ($q) use ($roles_coordinacion) {
                        $q->where(DB::raw('LOWER(solicitudes.estado)'), 'rechazada')
                          ->whereIn('solicitudes.rol_rechazo', $roles_coordinacion);
                    });
                } elseif (in_array($userRole, [7, 8])) {
                    $query->orWhere(function ($q) use ($userRole) {
                        $q->where(DB::raw('LOWER(solicitudes.estado)'), 'rechazada')
                          ->where('solicitudes.rol_rechazo', $userRole);
                    });
                }
            });

        } elseif ($userRole == 3 || $userRole == 4) {
            $solicitudesQuery->where('solicitudes.user_id', $user->id);

        } else {
            // Bloqueo total para roles no definidos
            $solicitudesQuery->whereRaw('1 = 0');
        }

        $solicitudes = $solicitudesQuery->get();
        
        return response()->json($solicitudes);
    }

    /**
     * Recupera el detalle completo de una solicitud, incluyendo relaciones anidadas y archivos adjuntos.
     * Genera URLs temporales para la descarga de documentos.
     */
    public function show(Solicitud $solicitud)
    {
        $esAdminODirectivo = $this->tieneRolAdministrativo(Auth::id());

        if (Auth::id() !== $solicitud->user_id && !$esAdminODirectivo) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        // Carga profunda de relaciones (User -> Estudiante -> Programa Educativo)
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

        // Procesamiento de respuestas y generación de URLs de acceso a archivos
        foreach ($solicitud->tramites as $tramite) {
            $respuestas = SolicitudRespuesta::where('solicitud_id', $solicitud->idSolicitud)
                ->where('tramite_id', $tramite->idTramite)
                ->join('requisitos', 'solicitud_respuestas.requisito_id', '=', 'requisitos.idRequisito')
                ->select('requisitos.nombreRequisito', 'solicitud_respuestas.respuesta', 'requisitos.tipo')
                ->get();

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

        $rutaAlmacenada = $solicitud->ruta_comprobante;
        if ($rutaAlmacenada && Storage::disk('public')->exists($rutaAlmacenada)) {
            $solicitud->comprobante = [
                'nombreArchivo' => basename($rutaAlmacenada),
                'url' => asset('storage/' . $rutaAlmacenada),
            ];
        } else {
            $solicitud->comprobante = null;
        }

        if (strtolower($solicitud->estado) === 'rechazada' && $solicitud->rol_rechazo) {
            $rolId = (int)$solicitud->rol_rechazo;
            $solicitud->rol_rechazo_nombre = $this->mapaRoles[$rolId] ?? 'Rol Desconocido';
        } else {
            $solicitud->rol_rechazo_nombre = null;
        }

        if (strtolower($solicitud->estado) === 'completado') {
            foreach ($solicitud->tramites as $tramite) {
                $rutaFinal = $tramite->pivot->ruta_archivo_final ?? null;

                if ($rutaFinal && Storage::disk('public')->exists($rutaFinal)) {
                    $extension = pathinfo(Storage::disk('public')->path($rutaFinal), PATHINFO_EXTENSION);
                    $nombreLegible = $tramite->nombreTramite . '.' . $extension;

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
     * Genera la Orden de Pago en formato PDF utilizando DomPDF.
     * Retorna el archivo como stream para descarga directa.
     */
    public function downloadOrdenDePago(Solicitud $solicitud)
    {
        $esAdminODirectivo = $this->tieneRolAdministrativo(Auth::id());

        if (Auth::id() !== $solicitud->user_id && !$esAdminODirectivo) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $solicitud->load('tramites', 'ordenesPago');
        $ordenPago = $solicitud->ordenesPago->first();

        if (!$ordenPago) {
            return response()->json(['message' => 'Orden de pago no encontrada.'], 404);
        }

        $user = Auth::user();
        $user->load('estudiante.programaEducativo');

        $data = [
            'solicitud' => $solicitud,
            'ordenPago' => $ordenPago,
            'tramites' => $solicitud->tramites,
            'user' => $user,
        ];

        $pdf = Pdf::loadView('pdf.orden_pago', $data);
        $nombreArchivo = 'orden-de-pago-' . $solicitud->folio . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $nombreArchivo . '"',
        ]);
    }
}