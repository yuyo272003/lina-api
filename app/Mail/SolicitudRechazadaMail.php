<?php

namespace App\Mail;

use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage; // <-- 1. Importa el Storage
use Illuminate\Support\Facades\Log;      // <-- 2. (Opcional) Para registrar errores

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
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        // --- 3. Empezamos a construir el correo ---
        $email = $this->subject('¡Tu solicitud ha sido completada! (Archivos Adjuntos)')
            ->markdown('emails.solicitudes.completada');

        // --- 4. Lógica para adjuntar CADA archivo ---
        foreach ($this->solicitud->tramites as $tramite) {

            // Obtenemos la ruta (ej: 'tramitesEnviados/sol3_tram9_...')
            $rutaRelativa = $tramite->pivot->ruta_archivo_final;

            // Verificamos que la ruta exista en nuestro disco 'public'
            if ($rutaRelativa && Storage::disk('public')->exists($rutaRelativa)) {

                // Obtenemos la ruta absoluta (ej: /var/www/storage/app/public/...)
                $rutaAbsoluta = Storage::disk('public')->path($rutaRelativa);

                // Obtenemos la extensión original (pdf, docx, etc.)
                $extension = pathinfo($rutaAbsoluta, PATHINFO_EXTENSION);

                // Creamos un nombre de archivo legible (ej: "Baja Temporal.pdf")
                $nombreLegible = $tramite->nombreTramite . '.' . $extension;

                // ¡Adjuntamos el archivo al correo!
                $email->attach($rutaAbsoluta, [
                    'as' => $nombreLegible, // El nombre que verá el usuario
                    'mime' => Storage::disk('public')->mimeType($rutaRelativa)
                ]);

            } else {
                // Si no encontramos un archivo, lo registramos en el log
                Log::warning("Correo Solicitud {$this->solicitud->idSolicitud}: No se pudo adjuntar el archivo para el trámite {$tramite->id}. Ruta no encontrada: '{$rutaRelativa}'.");
            }
        }

        // --- 5. Devolvemos el correo ya con todos los adjuntos ---
        return $email;
    }
}
