<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AlertaAdministrativaMail extends Mailable
{
    use Queueable, SerializesModels;

    // Las propiedades públicas son inyectadas automáticamente en la vista Blade.
    public $conteoPendientes;
    public $conteoAtrasadas;
    public $rolNombre;

    /**
     * Inicializa la instancia con las métricas de rendimiento (SLA) calculadas por el comando de consola.
     * * @param int $conteoPendientes Total de solicitudes en bandeja.
     * @param int $conteoAtrasadas Solicitudes que exceden el tiempo de atención (>24h).
     * @param string $rolNombre Contexto del rol para el mensaje personalizado.
     * Create a new message instance.
     */
    public function __construct($conteoPendientes, $conteoAtrasadas, $rolNombre)
    {
        $this->conteoPendientes = $conteoPendientes;
        $this->conteoAtrasadas = $conteoAtrasadas;
        $this->rolNombre = $rolNombre;
    }

    /**
     * Configura el sobre del correo (Asunto y Vista).
     * Build the message.
     */
    public function build()
    {
        return $this->subject('⚠️ Alerta de Solicitudes Pendientes - Sistema de Gestión')
                    ->view('emails.solicitudes.alerta_admin'); 
    }
}