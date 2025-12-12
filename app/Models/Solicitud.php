<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Solicitud extends Model
{
    use HasFactory;

    protected $table = 'solicitudes';

    /**
     * Sobreescritura de la llave primaria para compatibilidad con el esquema heredado.
     * Default: 'id' -> Configurado: 'idSolicitud'.
     */
    protected $primaryKey = 'idSolicitud';

    /**
     * Atributos asignables en masa.
     * 'estado': Gestiona el ciclo de vida (en proceso -> en revisión X -> completado/rechazada).
     * 'rol_rechazo': Auditoría de quién detuvo el flujo.
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
     * Relación 1:N con OrdenPago.
     * Una solicitud puede generar múltiples órdenes de pago (aunque usualmente es una),
     * vinculadas por 'idSolicitud'.
     */
    public function ordenesPago()
    {
        return $this->hasMany(OrdenPago::class, 'idSolicitud', 'idSolicitud');
    }

    /**
     * Relación N:M con Tramite.
     * Define la composición de la solicitud.
     * Incluye datos pivote críticos para el flujo administrativo:
     * - 'datos_requisitos': Payload JSON histórico (si aplica).
     * - 'ruta_archivo_final': Documento entregado por Secretaría.
     * - 'completado_manual': Flag de anulación de archivo.
     */
    public function tramites()
    {
        return $this->belongsToMany(Tramite::class, 'solicitud_tramite', 'idSolicitud', 'idTramite')
                ->withPivot('id', 'datos_requisitos', 'ruta_archivo_final', 'completado_manual');
    }

    /**
     * Relación inversa N:1 con User.
     * Identifica al propietario/estudiante que inició el trámite.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}