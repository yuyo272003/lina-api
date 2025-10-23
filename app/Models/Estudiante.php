<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Estudiante extends Model
{
    use HasFactory;

    // Le decimos a Laravel que la tabla se llama 'estudiantes'
    protected $table = 'estudiantes';

    // Le decimos cuál es la clave primaria
    protected $primaryKey = 'idEstudiante';

    // Desactivamos los timestamps si tu tabla no los tiene
    public $timestamps = false;

    /**
     * Define la relación inversa: Un Estudiante pertenece a un User.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Define la relación: Un Estudiante pertenece a un ProgramaEducativo.
     */
    public function programaEducativo()
    {
        return $this->belongsTo(ProgramaEducativo::class, 'idPE');
    }
}
