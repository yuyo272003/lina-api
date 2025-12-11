<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolicitudRespuesta extends Model
{
    use HasFactory;

    protected $table = 'solicitud_respuestas';

    /**
     * Atributos asignables en masa.
     * Esta tabla actúa como una intersección con datos:
     * Vincula una Solicitud -> un Trámite específico dentro de ella -> un Requisito -> y la Respuesta.
     */
    protected $fillable = [
        'solicitud_id',
        'tramite_id',
        'requisito_id',
        'respuesta', // Puede ser texto plano o la ruta relativa del archivo en Storage
    ];

    /**
     * Relación N:1 con Solicitud.
     * Referencia a la solicitud padre utilizando 'idSolicitud'.
     */
    public function solicitud()
    {
        return $this->belongsTo(Solicitud::class, 'solicitud_id', 'idSolicitud');
    }

    /**
     * Relación N:1 con Tramite.
     * Indica a qué trámite pertenece este requisito específico.
     */
    public function tramite()
    {
        return $this->belongsTo(Tramite::class, 'tramite_id', 'idTramite');
    }

    /**
     * Relación N:1 con Requisito.
     * Provee la definición del dato solicitado (Nombre, Tipo).
     */
    public function requisito()
    {
        return $this->belongsTo(Requisito::class, 'requisito_id', 'idRequisito');
    }
}