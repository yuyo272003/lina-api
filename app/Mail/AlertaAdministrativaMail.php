<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AlertaAdministrativaMail extends Mailable
{
    use Queueable, SerializesModels;

    public $conteoPendientes;
    public $conteoAtrasadas;
    public $rolNombre;

    /**
     * Create a new message instance.
     */
    public function __construct($conteoPendientes, $conteoAtrasadas, $rolNombre)
    {
        $this->conteoPendientes = $conteoPendientes;
        $this->conteoAtrasadas = $conteoAtrasadas;
        $this->rolNombre = $rolNombre;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('⚠️ Alerta de Solicitudes Pendientes - Sistema de Gestión')
                    ->view('emails.solicitudes.alerta_admin'); 
    }
}