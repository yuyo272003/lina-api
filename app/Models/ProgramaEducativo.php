<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProgramaEducativo extends Model
{
    use HasFactory;

    // Configuración explícita de tabla y llave primaria personalizada
    protected $table = 'programas_educativos';
    protected $primaryKey = 'idPE';

    /**
     * Atributos asignables en masa.
     * Vincula el programa educativo a una facultad específica mediante 'facultad_id'.
     */
    protected $fillable = [
        'nombrePE', 
        'facultad_id'
    ];
}