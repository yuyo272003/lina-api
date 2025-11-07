<?php

namespace App\Mail;

use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SolicitudCompletadaMail extends Mailable
{
    use Queueable, SerializesModels;

    public $solicitud;
    public $secretaria; // El usuario que completó la acción

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Solicitud $solicitud, User $secretaria)
    {
        $this->solicitud = $solicitud;
        $this->secretaria = $secretaria;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        // Asunto y vista del correo
        return $this->subject('¡Tu solicitud ha sido completada!')
            ->markdown('emails.solicitudes.completada'); // Usaremos esta nueva vista
    }
}
