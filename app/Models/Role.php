<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    /**
     * La tabla asociada con el modelo.
     * @var string
     */
    protected $table = 'roles'; // Es bueno ser explícito

    /**
     * La llave primaria para el modelo.
     * @var string
     */
    protected $primaryKey = 'IdRole'; // <-- ¡ESTE ES EL CAMBIO MÁS IMPORTANTE!

    /**
     * Indica si el modelo debe tener timestamps (created_at, updated_at).
     * @var bool
     */
    public $timestamps = false; // Tu tabla tiene las columnas pero no se usan, es mejor desactivarlas.
    
    /**
     * Los atributos que se pueden asignar masivamente.
     * @var array
     */
    protected $fillable = [
        'NombreRole', // Para coincidir con el nombre de tu columna
    ];


    // La relación inversa (opcional, pero buena práctica)
    public function users()
    {
        return $this->belongsToMany(User::class, 'role_usuario', 'role_id', 'user_id');
    }
}