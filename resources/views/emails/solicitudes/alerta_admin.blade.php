<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }
        .container { background-color: #ffffff; padding: 20px; border-radius: 8px; max-width: 600px; margin: 0 auto; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { background-color: #d9534f; color: white; padding: 10px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { padding: 20px; color: #333; }
        .alert-box { background-color: #fce8e6; border-left: 5px solid #d9534f; padding: 15px; margin: 15px 0; }
        .btn { display: inline-block; padding: 10px 20px; background-color: #0275d8; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
        .footer { margin-top: 20px; font-size: 12px; color: #777; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>⚠️ Atención Requerida: {{ $rolNombre }}</h2>
        </div>
        
        <div class="content">
            <p>Hola,</p>
            <p>El sistema ha detectado una acumulación de trabajo que requiere tu atención inmediata.</p>

            <div class="alert-box">
                @if($conteoPendientes >= 5)
                    <p><strong>📂 Solicitudes acumuladas:</strong> Tienes <strong>{{ $conteoPendientes }}</strong> solicitudes esperando revisión.</p>
                @endif

                @if($conteoAtrasadas > 0)
                    <p><strong>⏰ Retraso detectado:</strong> Hay <strong>{{ $conteoAtrasadas }}</strong> solicitud(es) que no han tenido movimiento en las últimas 24 horas.</p>
                @endif
            </div>

            <p>Por favor, ingresa a la plataforma para gestionar estos trámites y evitar retrasos a los estudiantes.</p>

            <a href="http://localhost:5173/login" class="btn">Ir al Sistema</a>
        </div>

        <div class="footer">
            Este es un mensaje automático del Sistema de Gestión de Trámites.
        </div>
    </div>
</body>
</html>