<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud Rechazada - Sistema LINA</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }
        .container { background-color: #ffffff; padding: 20px; border-radius: 8px; max-width: 600px; margin: 0 auto; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        /* Usamos rojo para rechazo */
        .header { background-color: #d9534f; color: white; padding: 10px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { padding: 20px; color: #333; text-align: center; }
        /* Caja de alerta roja */
        .alert-box { background-color: #fce8e6; border-left: 5px solid #d9534f; padding: 15px; margin: 15px 0; text-align: left; }
        .details { background-color: #f9f9f9; padding: 10px; border-radius: 5px; text-align: left; margin-bottom: 15px; font-size: 14px; }
        .btn { display: inline-block; padding: 10px 20px; background-color: #0275d8; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
        .footer { margin-top: 20px; font-size: 12px; color: #777; text-align: center; border-top: 1px solid #eee; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>⛔ Solicitud Rechazada</h2>
        </div>
        
        <div class="content">
            
            <p>Estimado/a <strong>{{ $solicitud->user->name ?? 'Estudiante' }}</strong>,</p>
            <p>Tu solicitud con folio <strong>{{ $solicitud->folio }}</strong> ha sido rechazada por la coordinación.</p>

            <div class="alert-box">
                <strong>Motivo del rechazo:</strong>
                <p style="margin: 5px 0 0 0; font-style: italic;">"{{ $motivo }}"</p>
            </div>

            <div class="details">
                <p><strong>Revisado por:</strong> {{ $coordinador->name }}</p>
                <p><strong>Contacto:</strong> {{ $coordinador->email }}</p>
                <p><strong>Fecha:</strong> {{ now()->format('d/m/Y H:i') }}</p>
            </div>

            <p>Por favor, realiza las correcciones indicadas y actualiza tu solicitud.</p>

            <a href="http://localhost:5173/login" class="btn">Corregir Solicitud</a>
        </div>

        <div class="footer">
            <p>Universidad Veracruzana</p>
            <p>© {{ date('Y') }} Sistema LINA - Todos los derechos reservados</p>
        </div>
    </div>
</body>
</html>