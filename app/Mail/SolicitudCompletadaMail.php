<?php

namespace App\Mail;

use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class SolicitudCompletadaMail extends Mailable
{
    use Queueable, SerializesModels;

    public $solicitud;
    public $secretaria;

    public function __construct(Solicitud $solicitud, User $secretaria)
    {
        $this->solicitud = $solicitud;
        $this->secretaria = $secretaria;
    }

    /**
     * Construye el mensaje y adjunta dinámicamente los documentos finales.
     * Itera sobre los trámites asociados, resuelve las rutas absolutas del Storage
     * y renombra los archivos adjuntos para mejorar la experiencia del usuario final.
     */
    public function build()
    {
        $email = $this->subject('¡Tu solicitud ha sido completada! (Archivos Adjuntos)')
                      ->markdown('emails.solicitudes.completada');

        foreach ($this->solicitud->tramites as $tramite) {
            // Acceso a metadatos en tabla pivote (solicitud_tramite)
            $rutaRelativa = $tramite->pivot->ruta_archivo_final;

            if ($rutaRelativa && Storage::disk('public')->exists($rutaRelativa)) {
                
                // Resolución de ruta absoluta requerida por el método attach()
                $rutaAbsoluta = Storage::disk('public')->path($rutaRelativa);
                $extension = pathinfo($rutaAbsoluta, PATHINFO_EXTENSION);
                
                // Sanitización del nombre visible en el correo
                $nombreLegible = $tramite->nombreTramite . '.' . $extension;

                $email->attach($rutaAbsoluta, [
                    'as' => $nombreLegible,
                    'mime' => Storage::disk('public')->mimeType($rutaRelativa)
                ]);

            } else {
                // Registro de fallos silenciosos para auditoría sin interrumpir el envío
                Log::warning("Fallo de adjunto en Solicitud {$this->solicitud->idSolicitud}: Ruta '{$rutaRelativa}' no encontrada para trámite {$tramite->idTramite}.");
            }
        }

        return $email;
    }
}