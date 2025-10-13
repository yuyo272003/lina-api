<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolicitudRespuesta extends Model
{
    use HasFactory;

    protected $table = 'solicitud_respuestas';

    protected $fillable = [
        'solicitud_id',
        'tramite_id',
        'requisito_id',
        'respuesta',
    ];

    // Relaciones (opcional pero recomendado)
    public function solicitud()
    {
        return $this->belongsTo(Solicitud::class, 'solicitud_id', 'idSolicitud');
    }

    public function tramite()
    {
        return $this->belongsTo(Tramite::class, 'tramite_id', 'idTramite');
    }

    public function requisito()
    {
        return $this->belongsTo(Requisito::class, 'requisito_id', 'idRequisito');
    }
}