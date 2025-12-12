<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    /**
     * Lista blanca de IDs de roles con privilegios administrativos.
     * 5: Coordinador General, 6: Coordinador PE, 7: Contador, 8: Secretario.
     */
    private $rolesPermitidos = [5, 6, 7, 8];

    /**
     * Recupera usuarios que han solicitado elevación de privilegios.
     * Excluye usuarios que ya poseen alguno de los roles administrativos.
     */
    public function getSolicitudesRol(Request $request)
    {
        // Verificación RBAC (Role-Based Access Control)
        if (!Auth::user()->roles()->whereIn('role_id', $this->rolesPermitidos)->exists()) {
             return response()->json(['message' => 'No autorizado.'], 403);
        }

        $usuarios = User::where('solicita_rol', true)
                        ->whereDoesntHave('roles', function($q) {
                            // Ocultar si ya tienen un rol admin
                            $q->whereIn('roles.IdRole', $this->rolesPermitidos);
                        })
                        ->get(['id', 'name', 'email', 'created_at']);
        
        return response()->json($usuarios);
    }

    /**
     * Lista todos los usuarios con roles administrativos activos y sus descripciones.
     */
    public function getUsuariosActivos(Request $request)
    {
        if (!Auth::user()->roles()->whereIn('role_id', $this->rolesPermitidos)->exists()) {
             return response()->json(['message' => 'No autorizado.'], 403);
        }

        // Join explícito para obtener metadatos del rol asociado
        $usuarios = DB::table('users')
            ->join('role_usuario', 'users.id', '=', 'role_usuario.user_id')
            ->join('roles', 'role_usuario.role_id', '=', 'roles.IdRole')
            
            ->whereIn('roles.IdRole', $this->rolesPermitidos)
            
            ->select(
                'users.id', 
                'users.name', 
                'users.email', 
                'roles.NombreRole as nombre_rol', 
                'roles.IdRole as role_id'
            )
            ->distinct('users.id')
            ->get();
            
        return response()->json($usuarios);
    }

    /**
     * Procesa la asignación de un rol administrativo.
     * Gestiona la lógica específica para Coordinadores de PE (ID 6) y limpia roles previos.
     */
    public function assignLocalRole(Request $request)
    {
        if (!Auth::user()->roles()->whereIn('role_id', $this->rolesPermitidos)->exists()) {
             return response()->json(['message' => 'No autorizado.'], 403);
        }

        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'role_id' => ['required', 'integer', Rule::in([5, 6, 7, 8])],
            'idPE'    => 'nullable|integer|exists:programas_educativos,idPE',
        ]);

        $user = User::find($request->input('user_id'));
        $roleId = $request->input('role_id');
        $idPE = $request->input('idPE');

        try {
            DB::beginTransaction();

            // Regla de negocio: Si es Coordinador PE (6), asignar programa; si no, limpiar campo.
            if ($roleId == 6) {
                $user->idPE = $idPE; 
            } else {
                $user->idPE = null;
            }

            // Revocación de roles previos (Académico + Admin) y asignación del nuevo rol
            $user->roles()->detach([2, 5, 6, 7, 8]);
            
            // Asignar el nuevo rol administrativo
            $user->roles()->attach($roleId);

            // Cierre de la solicitud
            $user->solicita_rol = false;
            $user->save();

            DB::commit();
            return response()->json(['message' => "Rol asignado correctamente a {$user->name}."]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error asignando rol local: ' . $e->getMessage());
            return response()->json(['message' => 'Error en el servidor durante la asignación.'], 500);
        }
    }

    /**
     * Revoca privilegios administrativos y revierte al usuario al rol base (Académico).
     * Incluye protección contra auto-revocación.
     */
    public function removeAdminRole(Request $request)
    {
        if (!Auth::user()->roles()->whereIn('role_id', $this->rolesPermitidos)->exists()) {
             return response()->json(['message' => 'No autorizado.'], 403);
        }
        
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);
        
        // Compara el ID del usuario autenticado con el ID que se quiere modificar.
        $authenticatedUserId = Auth::id();
        $targetUserId = (int)$request->input('user_id');

        if ($authenticatedUserId === $targetUserId) {
            return response()->json(['message' => 'No puedes quitarte el rol a ti mismo.'], 403);
        }

        $user = User::find($targetUserId);

        try {
            DB::beginTransaction();

            // Reset a Rol Académico (ID 2)
            $user->roles()->sync(2);

            // Limpieza de atributos administrativos
            $user->solicita_rol = false; 
            $user->idPE = null;
            $user->save();
            
            DB::commit();
            
            return response()->json(['message' => "Roles administrativos quitados a {$user->name}. Ahora es Académico."]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error al quitar rol: ' . $e->getMessage());
            return response()->json(['message' => 'Fallo al quitar el rol en la DB.'], 500);
        }
    }

    /**
     * Helper para poblar selectores de UI con Programas Educativos disponibles.
     */
    public function getProgramasEducativos()
    {
        return response()->json(DB::table('programas_educativos')->select('idPE', 'nombrePE')->get());
    }
}