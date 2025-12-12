<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Academico extends Model
{
    use HasFactory;

    // Definición explícita de la tabla para anular la convención de pluralización
    protected $table = 'academicos';

    // Sobreescritura de la llave primaria personalizada
    protected $primaryKey = 'idAcademico';

    // Deshabilita la gestión automática de columnas created_at y updated_at
    public $timestamps = false;

    /**
     * Relación inversa 1:1 con el modelo User.
     * Vincula la identidad de acceso con el perfil académico.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relación N:1 con el modelo Facultad.
     * Un académico pertenece a una facultad específica mediante 'idFacultad'.
     */
    public function facultad()
    {
        return $this->belongsTo(Facultad::class, 'idFacultad', 'idFacultad');
    }
}