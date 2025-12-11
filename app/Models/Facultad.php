<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Facultad extends Model
{
    use HasFactory;

    // Configuración explícita de tabla y llave primaria personalizada
    protected $table = 'facultades';
    protected $primaryKey = 'idFacultad';
    
    protected $fillable = ['nombreFacultad', 'idCampus'];

    /**
     * Relación N:1 con el modelo Campus.
     * Vincula la facultad a su ubicación administrativa/geográfica mediante 'idCampus'.
     */
    public function campus()
    {
        return $this->belongsTo(Campus::class, 'idCampus', 'idCampus');
    }
}