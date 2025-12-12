<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens; 
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable; 

    /**
     * Eager Loading: Carga automáticamente los roles con cada consulta de usuario
     * para optimizar la verificación de permisos (RBAC) y evitar problemas N+1.
     */
    protected $with = ['roles'];

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'password',
        'solicita_rol', // Flag: Indica si un académico solicitó elevación de privilegios
        'idPE',         // FK: Asignación específica para Coordinadores de Programa Educativo (Rol 6)
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'solicita_rol' => 'boolean',
        ];
    }

    /**
     * Relación N:M con Role.
     * Define los permisos del sistema mediante la tabla pivote 'role_usuario'.
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_usuario', 'user_id', 'role_id');
    }

    /**
     * Relación 1:1 con Perfil de Estudiante.
     * Disponible solo si el usuario tiene rol de alumno.
     */
    public function estudiante()
    {
        return $this->hasOne(Estudiante::class, 'user_id');
    }
    
    /**
     * Relación 1:1 con Perfil Académico.
     * Disponible solo si el usuario tiene rol de personal (docente/administrativo).
     */
    public function academico()
    {
        return $this->hasOne(Academico::class, 'user_id');
    }

    /**
     * Relación N:1 con Programa Educativo.
     * Utilizada exclusivamente para limitar el alcance de usuarios con Rol de Coordinador PE (6).
     */
    public function programaEducativo()
    {
        return $this->belongsTo(ProgramaEducativo::class, 'idPE', 'idPE');
    }
}