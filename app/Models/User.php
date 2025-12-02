<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens; 
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Role;
use App\Models\Academico;
use App\Models\Estudiante;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable; 

    protected $with = ['roles'];

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'password',
        'solicita_rol',
        'idPE',
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
        ];
    }

    /**
     * Un usuario puede ser un estudiante.
     */
    public function estudiante()
    {
        // Se corrigió la notación de clase para la importación
        return $this->hasOne(Estudiante::class, 'user_id');
    }
    
    /**
     * Un usuario puede ser un académico.
     */
    public function academico()
    {
        return $this->hasOne(Academico::class, 'user_id');
    }

    /**
     * Relación con los roles del usuario.
     */
    public function roles()
    {
        return $this->belongsToMany(\App\Models\Role::class, 'role_usuario', 'user_id', 'role_id');
    }

    /**
     * Relación: Un usuario (Coordinador) puede tener asignado un Programa Educativo
     */
    public function programaEducativo()
    {
        return $this->belongsTo(ProgramaEducativo::class, 'idPE', 'idPE');
    }
}