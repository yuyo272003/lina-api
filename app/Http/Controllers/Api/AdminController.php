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
     * Define los IDs de los roles administrativos/de coordinación.
     * Solo estos roles (o un super-admin) pueden usar esta función.
     */
    private $rolesPermitidos = [5, 6, 7, 8]; // Roles administrativos

    /**
     * Obtiene los usuarios que han solicitado un rol (solicita_rol = true).
     */
    public function getSolicitudesRol(Request $request)
    {
        // Autorización (reutiliza la lógica de roles)
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
     * Obtiene los usuarios que ya tienen un rol administrativo (5-8).
     */
    public function getUsuariosActivos(Request $request)
    {
        if (!Auth::user()->roles()->whereIn('role_id', $this->rolesPermitidos)->exists()) {
             return response()->json(['message' => 'No autorizado.'], 403);
        }

        // Usamos un Join para obtener el nombre del rol
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
     * Asigna un rol a un usuario existente en la DB local (el que solicitó).
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

            // 1. Actualizar Roles
            $user->roles()->detach([2, 5, 6, 7, 8]);
            $user->roles()->attach($roleId);

            // Actualizar Programa Educativo (Solo si es Rol 6)
            if ($roleId == 6) {
                $user->idPE = $idPE; 
            } else {
                $user->idPE = null;
            }

            // Quitar roles administrativos previos (5-8) y el de académico base (2)
            $user->roles()->detach([2, 5, 6, 7, 8]);
            
            // Asignar el nuevo rol administrativo
            $user->roles()->attach($roleId);

            // Marcar la solicitud como atendida
            $user->solicita_rol = false;
            $user->save();

            DB::commit();
            return response()->json(['message' => "Rol asignado correctamente a {$user->name}."]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error asignando rol: ' . $e->getMessage());
            return response()->json(['message' => 'Error en el servidor.'], 500);
            \Log::error('Error al asignar rol local: ' . $e->getMessage());
            return response()->json(['message' => 'Fallo la asignación del rol en la DB.'], 500);
        }
    }

    /**
     * Quita un rol administrativo (5-8) a un usuario y lo revierte a Académico (Rol 2).
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
            // Devuelve un error 403 (Forbidden) o 400 (Bad Request)
            return response()->json(['message' => 'No puedes quitarte el rol a ti mismo.'], 403);
        }

        $user = User::find($targetUserId); // Usar la variable que ya validamos

        try {
            DB::beginTransaction();

            // Sincroniza los roles del usuario para que tenga ÚNICAMENTE el rol 2.
            $user->roles()->sync(2);

            // Reseteamos su solicitud
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

    public function getProgramasEducativos()
    {
        // Retorna lista simple para el Select del Frontend
        return response()->json(DB::table('programas_educativos')->select('idPE', 'nombrePE')->get());
    }
}