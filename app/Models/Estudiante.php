<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Estudiante extends Model
{
    use HasFactory;

    // Configuración de tabla y llave primaria personalizada
    protected $table = 'estudiantes';
    protected $primaryKey = 'idEstudiante';

    // Deshabilita la gestión automática de created_at y updated_at
    public $timestamps = false;

    /**
     * Relación inversa 1:1 con el modelo User.
     * Vincula la cuenta de acceso con el perfil académico del estudiante.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relación N:1 con ProgramaEducativo.
     * Indica la carrera o programa al que está inscrito el estudiante mediante 'idPE'.
     */
    public function programaEducativo()
    {
        return $this->belongsTo(ProgramaEducativo::class, 'idPE');
    }
}