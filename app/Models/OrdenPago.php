<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrdenPago extends Model
{
    use HasFactory;

    /**
     * El nombre de la tabla.
     */
    protected $table = 'ordenes_pago'; // <-- CORRECCIÓN 1: Nombre exacto de la tabla

    /**
     * La clave primaria de la tabla.
     */
    protected $primaryKey = 'idOrdenPago'; // <-- CORRECCIÓN 2: Nombre de la PK

    /**
     * Los atributos que se pueden asignar masivamente.
     */
    protected $fillable = [
        'idSolicitud', // <-- ¡Importante añadir la clave foránea aquí!
        'montoTotal',
        'numeroCuentaDestino',
    ];

    /**
     * Una orden de pago pertenece a una solicitud.
     */
    public function solicitud()
    {
        // CORRECCIÓN 3: Especificar la clave foránea en la relación
        return $this->belongsTo(Solicitud::class, 'idSolicitud');
    }
}