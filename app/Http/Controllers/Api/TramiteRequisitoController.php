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
    private $rolesPermitidos = [5]; 

    // Verificación manual de permisos RBAC para proteger endpoints de gestión
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
     * Lista todos los trámites con sus requisitos asociados (Eager Loading).
     */
    public function index()
    {
        $this->checkAuthorization();

        $tramites = Tramite::with('requisitos:idRequisito,nombreRequisito')
                            ->orderBy('idTramite', 'asc')
                            ->get();
        return response()->json($tramites);
    }

    public function getRequisitos()
    {
        $this->checkAuthorization();
        
        $requisitos = Requisito::select('idRequisito', 'nombreRequisito', 'tipo')->get();
        return response()->json($requisitos);
    }
    
    /**
     * Crea un nuevo requisito dinámicamente en el catálogo.
     */
    public function storeRequisito(Request $request) 
    {
        $this->checkAuthorization();

        $request->validate([
            'nombreRequisito' => 'required|string|max:255|unique:requisitos,nombreRequisito',
            'tipo' => ['required', 'string', Rule::in(['dato', 'documento'])], 
        ]);

        try {
            $requisito = Requisito::create($request->only(['nombreRequisito', 'tipo']));
            return response()->json($requisito, 201); 
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al crear el requisito.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Crea un trámite completo y asocia requisitos existentes mediante transacción.
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
     * Modifica un trámite existente y sincroniza la relación many-to-many de requisitos.
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
            // Sincronización inteligente de la tabla pivote (Elimina deseleccionados, agrega nuevos)
            $tramite->requisitos()->sync($request->requisito_ids);
            
            DB::commit();
            return response()->json($tramite->load('requisitos:idRequisito,nombreRequisito'), 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al actualizar el trámite.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Elimina un trámite y limpia sus asociaciones en la tabla pivote.
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