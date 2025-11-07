<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-AR-SA">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud Completada - Sistema LINA</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 650px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 10px;
            overflow: hidden;
            border-left: 6px solid #28AD56; /* Verde institucional */
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .header {
            background-color: #ffffff;
            color: #18529D;
            text-align: center;
            padding: 25px 20px 15px;
            border-bottom: 4px solid #28AD56;
        }
        .header img {
            width: 75px;
            margin-bottom: 10px;
        }
        .header h1 {
            font-size: 20px;
            margin: 0;
            letter-spacing: .4px;
            color: #18529D;
        }
        .content {
            padding: 30px;
            color: #222;
            line-height: 1.6;
        }
        .content p {
            font-size: 15px;
            margin: 8px 0;
        }
        .details {
            background-color: #f8fafc;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 15px 20px;
            margin: 20px 0;
        }
        .details p {
            margin: 4px 0;
            font-size: 14px;
        }
        /* ¡NUEVO! Caja de éxito en lugar de rechazo */
        .notificacion-exito {
            background-color: #f0fdf4; /* Verde muy claro */
            border-left: 5px solid #22c55e; /* Verde brillante */
            padding: 12px 15px;
            color: #15803d; /* Verde oscuro */
            font-size: 14px;
            margin-bottom: 20px;
        }
        .button {
            display: inline-block;
            background-color: #28AD56;
            color: #fff !important;
            text-decoration: none;
            padding: 12px 22px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            margin-top: 10px;
        }
        .footer {
            background-color: #f9fafb;
            text-align: center;
            padding: 18px 15px;
            font-size: 13px;
            color: #000000;
            border-top: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <img src="{{ $message->embed(public_path('images/logoUV.png')) }}" alt="Logo Universidad Veracruzana">
        <h1>Sistema LINA - Universidad Veracruzana</h1>
    </div>

    <div class="content">
        <p>Estimado/a <strong>{{ $solicitud->user->name ?? 'Estudiante' }}</strong>,</p>

        <p>
            ¡Buenas noticias! Tu solicitud con el folio
            <strong style="color:#18529D;">{{ $solicitud->folio }}</strong>
            ha sido <strong style="color:#16a34a;">completada</strong>.
        </p>

        <div class="details">
            <p><strong>🧾 Detalles de la solicitud</strong></p>
            <p><strong>Procesado por:</strong> {{ $secretaria->name }}</p>
            <p><strong>Fecha:</strong> {{ now()->format('d/m/Y H:i') }}</p>
        </div>

        <div class="notificacion-exito">
            <strong>Tus archivos están listos.</strong>
            Hemos adjuntado los archivos de tus trámites solicitados.
        </div>

        <p>
            Puedes revisar el estado y descargar tus archivos desde el sistema LINA.
        </p>

        <a href="{{ config('app.frontend_url') . '/mis-solicitudes' }}" class="button">
            Ir al sistema LINA
        </a>
    </div>

    <div class="footer">
        <p>
            Atentamente,<br>
            <strong>Coordinación del Programa Educativo LINA</strong><br>
            Universidad Veracruzana
        </p>
        <p>© {{ date('Y') }} Sistema LINA - Todos los derechos reservados</p>
        </p>
    </div>

</body>
</html>
