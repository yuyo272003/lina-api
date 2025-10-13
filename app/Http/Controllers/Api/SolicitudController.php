<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Solicitud;
use App\Models\Tramite;
use App\Models\Requisito; // <-- ¡AÑADIR ESTE IMPORT!
use App\Models\SolicitudRespuesta; // <-- ¡AÑADIR ESTE IMPORT!
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class SolicitudController extends Controller
{
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


        // 4. GENERACIÓN DE PDF (sin cambios)
        $data = [
            'solicitud' => $solicitud,
            'ordenPago' => $ordenPago,
            'tramites' => $tramites,
            'user' => $user->load('estudiante.programaEducativo'),
        ];

        $pdf = Pdf::loadView('pdf.orden_pago', $data);

        return $pdf->download('orden-de-pago-' . $solicitud->folio . '.pdf');
    }

    public function index(Request $request)
    {
        $user = Auth::user();

        // La consulta original era:
        /*
        $solicitudes = Solicitud::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get(['idSolicitud', 'folio', 'estado', 'created_at']);
        */

        // --- NUEVA CONSULTA MEJORADA ---

        $solicitudes = Solicitud::query()
            // Especificamos la tabla principal para evitar ambigüedad en las columnas
            ->where('solicitudes.user_id', $user->id)

            // Unimos con la tabla pivote y luego con la de trámites
            // Usamos leftJoin para no omitir solicitudes que pudieran no tener trámites
            ->leftJoin('solicitud_tramite', 'solicitudes.idSolicitud', '=', 'solicitud_tramite.idSolicitud')
            ->leftJoin('tramites', 'solicitud_tramite.idTramite', '=', 'tramites.idTramite')

            // Seleccionamos las columnas originales de la solicitud y añadimos la nueva
            ->select(
                'solicitudes.idSolicitud',
                'solicitudes.folio',
                'solicitudes.estado',
                'solicitudes.created_at',
                // Usamos DB::raw para ejecutar GROUP_CONCAT y crear el nuevo campo
                DB::raw("GROUP_CONCAT(tramites.nombreTramite SEPARATOR ', ') as tramites_nombres")
            )

            // Agrupamos por cada solicitud para que GROUP_CONCAT funcione correctamente
            ->groupBy('solicitudes.idSolicitud', 'solicitudes.folio', 'solicitudes.estado', 'solicitudes.created_at')

            // Mantenemos el orden descendente por fecha de creación
            ->orderBy('solicitudes.created_at', 'desc')
            ->get();


        return response()->json($solicitudes);
    }


}
