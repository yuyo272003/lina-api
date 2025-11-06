<?php

namespace App\Mail;

use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SolicitudRechazadaMail extends Mailable
{
    use Queueable, SerializesModels;

    public $solicitud;
    public $contador;
    public $motivo;

    public function __construct(Solicitud $solicitud, User $contador, string $motivo)
    {
        $this->solicitud = $solicitud;
        $this->contador = $contador;
        $this->motivo = $motivo;
    }

    public function build()
    {
        return $this->subject('Tu solicitud ha sido rechazada')
            ->markdown('emails.solicitudes.rechazada');
    }
}
