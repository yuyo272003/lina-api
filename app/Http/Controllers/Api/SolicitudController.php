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

class SolicitudController extends Controller
{
    /**
     * Define los IDs de los roles administrativos/de coordinación que deben ver todas las solicitudes.
     * @var array
     */
    private $rolesAdministrativos = [5, 6, 7, 8]; 

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

    public function store(Request $request)
    {
        // VALIDACIÓN
        $request->validate([
            'tramites' => 'required|array',
            'tramites.*.id' => 'required|integer|exists:tramites,idTramite',
            'tramites.*.respuestas' => 'present|array'
        ]);

        $user = Auth::user();

        // Extraemos solo los IDs para las relaciones
        $tramite_ids = collect($request->tramites)->pluck('id')->all();
        $tramites = Tramite::find($tramite_ids);
        $montoTotal = $tramites->sum('costoTramite');

        // CREACIÓN DE SOLICITUD Y ORDEN DE PAGO
        $solicitud = Solicitud::create([
            'user_id' => $user->id,
            'folio' => 'SOL-' . now()->format('Ymd') . '-' . Str::random(6),
            'estado' => 'en proceso',
        ]);

        $solicitud->tramites()->attach($tramite_ids);

        $ordenPago = $solicitud->ordenesPago()->create([
            'montoTotal' => $montoTotal,
            'numeroCuentaDestino' => config('app.numero_cuenta_bancaria'),
        ]);

        // GUARDAR LAS RESPUESTAS DE LOS REQUISITOS
        foreach ($request->tramites as $tramiteData) {
            if (!empty($tramiteData['respuestas'])) {
                foreach ($tramiteData['respuestas'] as $nombreRequisito => $respuesta) {
                    // Busca el requisito por su nombre para obtener su ID
                    $requisito = Requisito::where('nombreRequisito', $nombreRequisito)->first();

                    if ($requisito && $respuesta) {
                        SolicitudRespuesta::create([
                            'solicitud_id' => $solicitud->idSolicitud,
                            'tramite_id'   => $tramiteData['id'],
                            'requisito_id' => $requisito->idRequisito,
                            'respuesta'    => $respuesta,
                        ]);
                    }
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

    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Obtener el role_id del usuario.
        $userRole = DB::table('role_usuario')
            ->where('user_id', $user->id)
            ->value('role_id'); 

        // Fases 'En revisión'
        // 'en revisión 1' -> En Revisión por Coordinación (Roles 5 y 6)
        // 'en revisión 2' -> En Revisión por Contaduría (Rol 7)
        // 'en revisión 3' -> En Revisión por Secretaría (Rol 8)
        $estados_visibles = [];
        
        // Lógica de Visibilidad Escalonada para 'En revisión'
        if ($userRole == 5 || $userRole == 6) { 
            // Coordinadores (Rol 5 y 6) ven la FASE INICIAL de 'En revisión'
            $estados_visibles = ['en revisión 1', 'en revisión 2', 'en revisión 3']; // Ven todas las fases de revisión
        } elseif ($userRole == 7) { 
            // Contador (Rol 7) ve a partir de la FASE 2
            $estados_visibles = ['en revisión 2', 'en revisión 3']; // Ven su fase y las siguientes
        } elseif ($userRole == 8) { 
            // Secretario (Rol 8) ve a partir de la FASE 3
            $estados_visibles = ['en revisión 3']; 
        }

        // Se mantienen los estados finales visibles para todos los roles administrativos
        if (in_array($userRole, [5, 6, 7, 8])) {
            $estados_visibles[] = 'completada';
            $estados_visibles[] = 'rechazada';
        }

        // 🔹 Construimos la query base
        $solicitudesQuery = DB::table('solicitudes')
            ->leftJoin('solicitud_tramite', 'solicitudes.idSolicitud', '=', 'solicitud_tramite.idSolicitud')
            ->leftJoin('tramites', 'solicitud_tramite.idTramite', '=', 'tramites.idTramite')
            ->select(
                'solicitudes.idSolicitud',
                'solicitudes.folio',
                'solicitudes.estado',
                'solicitudes.created_at',
                DB::raw("GROUP_CONCAT(tramites.nombreTramite SEPARATOR ', ') as tramites_nombres")
            )
            ->groupBy('solicitudes.idSolicitud', 'solicitudes.folio', 'solicitudes.estado', 'solicitudes.created_at')
            ->orderBy('solicitudes.created_at', 'desc');

        // 🔹 Lógica de Filtrado por Rol
        if (in_array($userRole, [5, 6, 7, 8])) {
            if (!empty($estados_visibles)) {
                $solicitudesQuery->whereIn(DB::raw('LOWER(solicitudes.estado)'), $estados_visibles);
            } else {
                $solicitudesQuery->whereRaw('1 = 0');
            }
        } elseif ($userRole == 3 || $userRole == 4) { 
            // ROL 3 Y 4: Solo pueden ver sus propias solicitudes en CUALQUIER estado.
            $solicitudesQuery->where('solicitudes.user_id', $user->id);
        } else {
            $solicitudesQuery->whereRaw('1 = 0'); 
        }

        // 🔹 Ejecutamos la consulta
        $solicitudes = $solicitudesQuery->get();

        return response()->json($solicitudes);
    }

    /**
     * Muestra los detalles de una solicitud específica.
     *
     * @param 	\App\Models\Solicitud 	$solicitud
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Solicitud $solicitud)
    {
        // 1. Verificación de autorización: El usuario debe ser el dueño O tener un rol administrativo
        // El ROL 6 ya está incluido en $rolesAdministrativos, así que tiene acceso total.
        $esAdminODirectivo = $this->tieneRolAdministrativo(Auth::id()); 

        if (Auth::id() !== $solicitud->user_id && !$esAdminODirectivo) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        // 2. Cargar la relación de trámites de la solicitud
        // ... (código anterior sin cambios) ...
        $solicitud->load('tramites');

        // 3. Para cada trámite, cargar sus respuestas y el nombre del requisito asociado
        foreach ($solicitud->tramites as $tramite) {
            $respuestas = SolicitudRespuesta::where('solicitud_id', $solicitud->idSolicitud)
                ->where('tramite_id', $tramite->idTramite)
                ->join('requisitos', 'solicitud_respuestas.requisito_id', '=', 'requisitos.idRequisito')
                ->select('requisitos.nombreRequisito', 'solicitud_respuestas.respuesta')
                ->get();

            // Añadimos las respuestas encontradas como un nuevo atributo al objeto trámite
            $tramite->respuestas = $respuestas;
        }

        // 4. Devolver la solicitud con todos los datos anidados
        return response()->json($solicitud);
    }

    /**
     * Genera y descarga el PDF de la orden de pago para una solicitud existente.
     *
     * @param   \App\Models\Solicitud  $solicitud
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

        // 4. OBTENER EL USUARIO AUTENTICADO DIRECTAMENTE (CAMBIO CLAVE)
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

    public function subirComprobante(Request $request, Solicitud $solicitud)
    {
        // Validación
        $request->validate([
            'comprobante' => 'required|file|mimes:pdf|max:10000', // Max 10MB
        ]);

        // Guardar el archivo
        if ($request->hasFile('comprobante')) {
            // Generar un nombre único para evitar colisiones
            $nombreArchivo = 'comprobante_' . $solicitud->idSolicitud . '_' . time() . '.' . $request->file('comprobante')->extension();

            // Guardar en 'storage/app/public/comprobantes'
            $ruta = $request->file('comprobante')->storeAs('comprobantes', $nombreArchivo, 'public');

            // Actualizar la base de datos
            $solicitud->ruta_comprobante = $ruta;
            // Asignar el estado a la FASE INICIAL DE REVISIÓN
            $solicitud->estado = 'En revisión 1';
            $solicitud->save();

            // Devolver respuesta de éxito
            return response()->json([
                'message' => 'Comprobante subido con éxito. Solicitud enviada a Coordinación para revisión inicial.',
                'solicitud' => $solicitud
            ], 200);
        }

        return response()->json(['error' => 'No se encontró el archivo del comprobante.'], 400);
    }
}
