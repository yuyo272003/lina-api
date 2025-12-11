<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Requisito extends Model
{
    use HasFactory;

    protected $table = 'requisitos';
    protected $primaryKey = 'idRequisito';

    /**
     * Atributos asignables en masa.
     * 'tipo': Define la naturaleza del input ('dato' para texto/número, 'documento' para archivos PDF).
     */
    protected $fillable = ['nombreRequisito', 'tipo'];

    /**
     * Relación N:M con Tramite.
     * Un requisito puede ser reutilizado en múltiples trámites distintos.
     */
    public function tramites()
    {
        return $this->belongsToMany(Tramite::class, 'tramite_requisito', 'idRequisito', 'idTramite');
    }
}