<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $table = 'roles';

    /**
     * Sobreescritura de la llave primaria para coincidir con la definición de la tabla.
     * Default: 'id' -> Configurado: 'IdRole'.
     */
    protected $primaryKey = 'IdRole';

    // Deshabilita la gestión automática de timestamps (created_at, updated_at)
    public $timestamps = false;
    
    protected $fillable = [
        'NombreRole',
    ];

    /**
     * Relación N:M con User.
     * Define la estructura del Control de Acceso Basado en Roles (RBAC) 
     * a través de la tabla pivote 'role_usuario'.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'role_usuario', 'role_id', 'user_id');
    }
}