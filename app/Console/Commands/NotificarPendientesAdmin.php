<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Solicitud;
use Illuminate\Support\Facades\Mail;
use App\Mail\AlertaAdministrativaMail;
use Illuminate\Database\Eloquent\Builder;

class NotificarPendientesAdmin extends Command
{
    /**
     * El nombre y la firma del comando de consola.
     */
    protected $signature = 'notificaciones:revisar-pendientes';

    /**
     * La descripción del comando.
     */
    protected $description = 'Revisa si los administradores tienen solicitudes acumuladas o atrasadas y envía notificación.';

    public function handle()
    {
        $this->info('Iniciando revisión de pendientes...');

        // Obtenemos todos los usuarios que tengan roles 5, 6, 7 u 8
        $admins = User::whereHas('roles', function ($q) {
            $q->whereIn('IdRole', [5, 6, 7, 8]);
        })->with('roles')->get();

        foreach ($admins as $admin) {
            // Obtenemos el ID del rol administrativo principal (tomamos el primero si tuviera varios)
            $rol = $admin->roles->whereIn('IdRole', [5, 6, 7, 8])->first();
            
            if (!$rol) continue;

            $rolId = $rol->IdRole;
            $nombreRol = $rol->NombreRole ?? 'Administrador';

            // Construimos la consulta base según el Rol
            $query = Solicitud::query();

            // --- LÓGICA DE FILTRADO POR ROL ---
            if ($rolId == 5) { // Coordinador General
                $query->where('estado', 'en revisión 1');

            } elseif ($rolId == 6) { // Coordinador PE
                // Ve revisión 1 PERO solo de su Programa Educativo
                if (!$admin->idPE) continue;

                $query->where('estado', 'en revisión 1')
                      ->whereHas('user.estudiante', function (Builder $q) use ($admin) {
                          $q->where('idPE', $admin->idPE);
                      });

            } elseif ($rolId == 7) { // Contador
                $query->where('estado', 'en revisión 2');

            } elseif ($rolId == 8) { // Secretario
                $query->where('estado', 'en revisión 3');
            }

            // Evaluamos las condiciones   
            // Condición A: Cantidad Total en su bandeja
            $totalPendientes = (clone $query)->count();

            // Condición B: Tiempo (24hrs sin actualizar estado)
            $totalAtrasadas = (clone $query)
                ->where('updated_at', '<', now()->subHours(24))
                ->count();

            // Disparador: Si tiene 5 o más pendientes O al menos 1 atrasada
            if ($totalPendientes >= 5 || $totalAtrasadas > 0) {
                
                $this->info("Enviando alerta a {$admin->email} (Rol: $rolId)");

                try {
                    Mail::to($admin->email)->send(
                        new AlertaAdministrativaMail($totalPendientes, $totalAtrasadas, $nombreRol)
                    );
                } catch (\Exception $e) {
                    $this->error("Error enviando correo a {$admin->email}: " . $e->getMessage());
                }
            }
        }

        $this->info('Revisión finalizada.');
    }
}