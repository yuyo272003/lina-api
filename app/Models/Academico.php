<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Academico extends Model
{
    use HasFactory;

    // Mapeo a la tabla 'academicos'
    protected $table = 'academicos';

    // Clave primaria, según la estructura de tu tabla
    protected $primaryKey = 'idAcademico';

    // Desactivamos los timestamps si tu tabla no los utiliza
    public $timestamps = false;

    /**
     * Define la relación inversa: Un Académico pertenece a un User.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Define la relación: Un Académico pertenece a una Facultad.
     * Asumimos que la tabla 'academicos' usa 'idFacultad' para relacionarse con 'Facultad'.
     */
    public function facultad()
    {
        return $this->belongsTo(Facultad::class, 'idFacultad', 'idFacultad');
    }
}