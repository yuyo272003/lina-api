<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Orden de Pago</title>
    <style>
        body { font-family: sans-serif; margin: 40px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; }
        .header h2 { margin: 0; font-weight: normal; }
        .details, .items { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .details th, .details td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .details th { background-color: #f2f2f2; }
        .items th { background-color: #333; color: white; }
        .total { text-align: right; font-size: 1.2em; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Universidad Veracruzana</h1>
        <h2>Orden de Pago</h2>
    </div>

    <h3>Folio de Solicitud: {{ $solicitud->folio }}</h3>

    <table class="details">
        <tr>
            <th>Nombre del Estudiante:</th>
            <td>{{ $user->name }}</td>
        </tr>
        <tr>
            <th>Matrícula:</th>
            <td>{{ $user->estudiante->matriculaEstudiante ?? 'N/A' }}</td>
        </tr>
        <tr>
            <th>Programa Educativo:</th>
            <td>{{ $user->estudiante->programaEducativo->nombrePE ?? 'N/A' }}</td>
        </tr>
        <tr>
            <th>Fecha de Solicitud:</th>
            <td>{{ $solicitud->created_at->format('d/m/Y') }}</td>
        </tr>
    </table>

    <h4>Trámites Solicitados</h4>
    <table class="details items">
        <thead>
            <tr>
                <th>Trámite</th>
                <th>Costo</th>
            </tr>
        </thead>
        <tbody>
            @foreach($tramites as $tramite)
            <tr>
                <td>{{ $tramite->nombreTramite }}</td>
                <td>${{ number_format($tramite->costoTramite, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <p class="total">Monto Total a Pagar: ${{ number_format($ordenPago->montoTotal, 2) }}</p>

    <hr>
    <h4>Instrucciones de Pago</h4>
    <p>Realizar el depósito a la siguiente cuenta:</p>
    <p><strong>Número de Cuenta:</strong> {{ $ordenPago->numeroCuentaDestino }}</p>
    <p>Una vez realizado el pago, sube tu comprobante en el portal para continuar con el proceso.</p>

</body>
</html>