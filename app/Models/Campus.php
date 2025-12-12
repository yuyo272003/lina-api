<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Campus extends Model
{
    use HasFactory;

    // Configuración explícita de tabla y llave primaria personalizada
    protected $table = 'campuses';
    protected $primaryKey = 'idCampus';
    
    protected $fillable = ['nombreCampus'];

    /**
     * Relación 1:N con Facultad.
     * Un campus agrupa múltiples facultades asociadas mediante 'idCampus'.
     */
    public function facultades()
    {
        return $this->hasMany(Facultad::class, 'idCampus', 'idCampus');
    }
}