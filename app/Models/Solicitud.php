<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Solicitud extends Model
{
    use HasFactory;

    /**
     * El nombre de la tabla.
     */
    protected $table = 'solicitudes';

    /**
     * La clave primaria de la tabla.
     */
    protected $primaryKey = 'idSolicitud'; // <-- ¡CRUCIAL #1!

    /**
     * Los atributos que se pueden asignar masivamente.
     */
    protected $fillable = [
        'user_id',
        'folio',
        'estado',
        'ruta_comprobante',
        'observaciones',
        'rol_rechazo',
    ];

    /**
     * La relación de uno a muchos con OrdenPago.
     */
    public function ordenesPago()
    {
        // Le decimos explícitamente la clave foránea y la clave local
        return $this->hasMany(OrdenPago::class, 'idSolicitud', 'idSolicitud'); // <-- ¡CRUCIAL #2!
    }

    /**
     * La relación de muchos a muchos con Tramite.
     */
    public function tramites()
    {
        // Aquí también especificamos las claves por si no siguen la convención
        return $this->belongsToMany(Tramite::class, 'solicitud_tramite', 'idSolicitud', 'idTramite');
    }

    public function user()
    {
        // Asumiendo que 'user_id' es la clave foránea en la tabla 'solicitudes'
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
