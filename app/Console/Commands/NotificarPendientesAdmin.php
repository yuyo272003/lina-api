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
    protected $signature = 'notificaciones:revisar-pendientes';
    protected $description = 'Audita el backlog de solicitudes y notifica a los administradores si se superan los umbrales de atención.';

    /**
     * Ejecuta la auditoría de pendientes.
     * Reglas de negocio (SLA):
     * 1. Volumen: >= 5 solicitudes en bandeja.
     * 2. Latencia: > 24 horas sin actualización de estado.
     */
    public function handle()
    {
        $this->info('Iniciando auditoría de SLA...');

        // Recuperación de usuarios con roles administrativos (5: Coord Gen, 6: Coord PE, 7: Contador, 8: Secretario)
        $admins = User::whereHas('roles', fn($q) => $q->whereIn('IdRole', [5, 6, 7, 8]))
                      ->with('roles')
                      ->get();

        foreach ($admins as $admin) {
            $rol = $admin->roles->first(fn($r) => in_array($r->IdRole, [5, 6, 7, 8]));
            if (!$rol) continue;

            $query = Solicitud::query();

            // Configuración del contexto de consulta según el rol (RBAC)
            switch ($rol->IdRole) {
                case 5: // Coordinador General
                    $query->where('estado', 'en revisión 1');
                    break;

                case 6: // Coordinador PE (Alcance limitado por Programa Educativo)
                    if (!$admin->idPE) continue 2; 
                    $query->where('estado', 'en revisión 1')
                          ->whereHas('user.estudiante', fn(Builder $q) => $q->where('idPE', $admin->idPE));
                    break;

                case 7: // Contador
                    $query->where('estado', 'en revisión 2');
                    break;

                case 8: // Secretario
                    $query->where('estado', 'en revisión 3');
                    break;
                
                default:
                    continue 2;
            }

            // Cálculo de métricas
            $totalPendientes = (clone $query)->count();
            $totalAtrasadas = (clone $query)->where('updated_at', '<', now()->subHours(24))->count();

            // Evaluación de disparadores de alerta
            if ($totalPendientes >= 5 || $totalAtrasadas > 0) {
                $this->info("Disparando alerta para: {$admin->email} (Rol: {$rol->IdRole})");

                try {
                    Mail::to($admin->email)->send(
                        new AlertaAdministrativaMail($totalPendientes, $totalAtrasadas, $rol->NombreRole ?? 'Administrador')
                    );
                } catch (\Exception $e) {
                    $this->error("Fallo en envío SMTP a {$admin->email}: " . $e->getMessage());
                }
            }
        }

        $this->info('Auditoría finalizada.');
    }
}