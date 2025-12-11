<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tramite extends Model
{
    use HasFactory;

    protected $table = 'tramites';
    
    // Configuración de llave primaria personalizada (Legacy schema)
    protected $primaryKey = 'idTramite';

    protected $fillable = [
        'nombreTramite', 
        'costoTramite'
    ]; 

    /**
     * Relación N:M con Requisito.
     * Define la configuración del trámite (qué documentos/datos se necesitan).
     * Utiliza la tabla pivote 'requisito_tramite'.
     */
    public function requisitos()
    {
        return $this->belongsToMany(Requisito::class, 'requisito_tramite', 'idTramite', 'idRequisito');
    }

    /**
     * Relación inversa N:M con Solicitud.
     * Permite consultar en qué solicitudes ha sido incluido este trámite.
     * Incluye acceso a los metadatos de estado en la pivote.
     */
    public function solicitudes()
    {
        return $this->belongsToMany(Solicitud::class, 'solicitud_tramite', 'idTramite', 'idSolicitud')
                    ->withPivot('ruta_archivo_final', 'completado_manual');
    }
}