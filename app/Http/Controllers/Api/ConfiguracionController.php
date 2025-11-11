<?php

namespace App\Http\Controllers\Api;

use App\Models\Configuracion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Api\SolicitudController;

class ConfiguracionController extends SolicitudController
{
    /**
     * Actualiza el NUMERO_CUENTA_DESTINO GLOBAL en la tabla de configuraciones.
     *
     * @param \Illuminate\Http\Request $request
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