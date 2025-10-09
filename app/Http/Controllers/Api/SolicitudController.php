<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Solicitud;
use App\Models\Tramite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;

class SolicitudController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'tramite_ids' => 'required|array|exists:tramites,idTramite',
        ]);

        $user = Auth::user();
        $tramites = Tramite::find($request->tramite_ids);

        // 1. Calcular el monto total
        $montoTotal = $tramites->sum('costoTramite');

        // 2. Crear la Solicitud
        $solicitud = Solicitud::create([
            'user_id' => $user->id,
            'folio' => 'SOL-' . now()->format('Ymd') . '-' . Str::random(6),
            'estado' => 'en proceso', // Como pide la HU005 [cite: 63]
        ]);

        // 3. Vincular los trámites a la solicitud en la tabla pivote
        $solicitud->tramites()->attach($request->tramite_ids);

        // 4. Crear la Orden de Pago
        $ordenPago = $solicitud->ordenesPago()->create([
            'montoTotal' => $montoTotal,
            // ¡Añade este número de cuenta en tu archivo .env!
            'numeroCuentaDestino' => config('app.numero_cuenta_bancaria'),
        ]);

        // 5. Generar el PDF [cite: 62]
        $data = [
            'solicitud' => $solicitud,
            'ordenPago' => $ordenPago,
            'tramites' => $tramites,
            'user' => $user->load('estudiante.programaEducativo'),
        ];

        $pdf = Pdf::loadView('pdf.orden_pago', $data);

        // Devolvemos el PDF para que el navegador lo descargue
        return $pdf->download('orden-de-pago-' . $solicitud->folio . '.pdf');
    }
}