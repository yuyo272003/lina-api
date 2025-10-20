<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Role;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    // Esto asegura que la relación 'roles' se cargue automáticamente
    // cada vez que se obtiene una instancia de User desde la base de datos.
    protected $with = ['roles'];

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'password',
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

    public function estudiante()
    {
        return $this->hasOne(\App\Models\Estudiante::class, 'user_id');
    }

    // --- ¡AÑADE ESTO! ---
    public function roles()
    {
        return $this->belongsToMany(\App\Models\Role::class, 'role_usuario', 'user_id', 'role_id');
    }
}