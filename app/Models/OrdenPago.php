<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrdenPago extends Model
{
    use HasFactory;

    // Definición explícita de tabla y llave primaria para anular convenciones de Laravel
    protected $table = 'ordenes_pago';
    protected $primaryKey = 'idOrdenPago';

    /**
     * Atributos asignables en masa.
     * Incluye la clave foránea 'idSolicitud' para permitir la creación vinculada mediante create().
     */
    protected $fillable = [
        'idSolicitud',
        'montoTotal',
        'numeroCuentaDestino',
    ];

    /**
     * Relación inversa N:1 con Solicitud.
     * Vincula el registro financiero con la petición administrativa.
     */
    public function solicitud()
    {
        return $this->belongsTo(Solicitud::class, 'idSolicitud');
    }
}