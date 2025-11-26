<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud Completada - Sistema LINA</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }
        .container { background-color: #ffffff; padding: 20px; border-radius: 8px; max-width: 600px; margin: 0 auto; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        /* Usamos verde para éxito */
        .header { background-color: #28AD56; color: white; padding: 10px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { padding: 20px; color: #333; text-align: center; }
        /* Caja de éxito verde claro */
        .alert-box { background-color: #f0fdf4; border-left: 5px solid #28AD56; padding: 15px; margin: 15px 0; text-align: left; }
        .details { background-color: #f9f9f9; padding: 10px; border-radius: 5px; text-align: left; margin-bottom: 15px; font-size: 14px; }
        .btn { display: inline-block; padding: 10px 20px; background-color: #0275d8; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
        .footer { margin-top: 20px; font-size: 12px; color: #777; text-align: center; border-top: 1px solid #eee; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>✅ Solicitud Completada</h2>
        </div>
        
        <div class="content">
            <p>Estimado/a <strong>{{ $solicitud->user->name ?? 'Estudiante' }}</strong>,</p>
            <p>¡Buenas noticias! Tu solicitud con el folio <strong>{{ $solicitud->folio }}</strong> ha sido finalizada correctamente.</p>

            <div class="alert-box">
                <strong>📂 Tus archivos están listos.</strong>
                <p style="margin: 5px 0 0 0;">Hemos adjuntado los documentos de tus trámites solicitados.</p>
            </div>

            <div class="details">
                <p><strong>Procesado por:</strong> {{ $secretaria->name }}</p>
                <p><strong>Fecha:</strong> {{ now()->format('d/m/Y H:i') }}</p>
            </div>

            <p>Puedes revisar el estado y descargar tus archivos desde el sistema.</p>

            <a href="http://localhost:5173/login" class="btn">Ir al Sistema LINA</a>
        </div>

        <div class="footer">
            <p>Universidad Veracruzana</p>
            <p>© {{ date('Y') }} Sistema LINA - Todos los derechos reservados</p>
        </div>
    </div>
</body>
</html>