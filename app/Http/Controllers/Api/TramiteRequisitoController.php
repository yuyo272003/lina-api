<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tramite;
use App\Models\Requisito;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TramiteRequisitoController extends Controller
{
    // Roles permitidos para esta gestión
    private $rolesPermitidos = [5]; 

    private function checkAuthorization()
    {
        $userId = auth()->id();
        $isAuthorized = DB::table('role_usuario')
            ->where('user_id', $userId)
            ->whereIn('role_id', $this->rolesPermitidos)
            ->exists();
        
        if (!$isAuthorized) {
            abort(response()->json(['message' => 'No autorizado para esta acción.'], 403));
        }
    }

    /**
     * Obtiene todos los trámites con sus requisitos asociados.
     * GET /api/gestion/tramites
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $this->checkAuthorization();

        $tramites = Tramite::with('requisitos:idRequisito,nombreRequisito')
                            ->orderBy('idTramite', 'asc')
                            ->get();
        return response()->json($tramites);
    }

    /**
     * Obtiene todos los requisitos disponibles.
     * GET /api/gestion/requisitos
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRequisitos()
    {
        $this->checkAuthorization();
        
        $requisitos = Requisito::select('idRequisito', 'nombreRequisito', 'tipo')->get();
        return response()->json($requisitos);
    }
    
    /**
     * POST /api/gestion/requisitos
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeRequisito(Request $request) 
    {
        $this->checkAuthorization();

        $request->validate([
            'nombreRequisito' => 'required|string|max:255|unique:requisitos,nombreRequisito',
            // El tipo debe ser 'dato' o 'documento', si no hay otras opciones
            'tipo' => ['required', 'string', Rule::in(['dato', 'documento'])], 
        ]);

        try {
            $requisito = Requisito::create($request->only(['nombreRequisito', 'tipo']));
            // Retorna el requisito creado con código 201 (Created)
            return response()->json($requisito, 201); 
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al crear el requisito.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Almacena un nuevo trámite con sus requisitos.
     * POST /api/gestion/tramites
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $this->checkAuthorization();

        $request->validate([
            'nombreTramite' => 'required|string|max:255|unique:tramites,nombreTramite',
            'costoTramite' => 'required|numeric|min:0',
            'requisito_ids' => 'required|array',
            'requisito_ids.*' => 'integer|exists:requisitos,idRequisito',
        ]);

        DB::beginTransaction();
        try {
            $tramite = Tramite::create($request->only(['nombreTramite', 'costoTramite']));
            $tramite->requisitos()->attach($request->requisito_ids);
            
            DB::commit();
            return response()->json($tramite->load('requisitos:idRequisito,nombreRequisito'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al crear el trámite.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Actualiza un trámite y sus requisitos asociados.
     * PUT/PATCH /api/gestion/tramites/{tramite}
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Tramite  $tramite
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Tramite $tramite)
    {
        $this->checkAuthorization();

        $request->validate([
            'nombreTramite' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tramites', 'nombreTramite')->ignore($tramite->idTramite, 'idTramite'),
            ],
            'costoTramite' => 'required|numeric|min:0',
            'requisito_ids' => 'required|array',
            'requisito_ids.*' => 'integer|exists:requisitos,idRequisito',
        ]);

        DB::beginTransaction();
        try {
            $tramite->update($request->only(['nombreTramite', 'costoTramite']));
            $tramite->requisitos()->sync($request->requisito_ids);
            
            DB::commit();
            return response()->json($tramite->load('requisitos:idRequisito,nombreRequisito'), 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al actualizar el trámite.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Elimina un trámite. La relación se elimina automáticamente por el sync/detach de Laravel.
     * DELETE /api/gestion/tramites/{tramite}
     *
     * @param  \App\Models\Tramite  $tramite
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Tramite $tramite)
    {
        $this->checkAuthorization();

        try {
            $tramite->requisitos()->detach();
            $tramite->delete();
            return response()->json(['message' => 'Trámite eliminado con éxito.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar el trámite.', 'error' => $e->getMessage()], 500);
        }
    }
}