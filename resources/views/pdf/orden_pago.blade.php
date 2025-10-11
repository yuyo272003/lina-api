<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Orden de Pago - Universidad Veracruzana</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 40px;
      color: #000;
    }
    .header {
      text-align: center;
      margin-bottom: 20px;
    }
    .header img {
      width: 100px;
      margin-bottom: 10px;
    }
    .header h1 {
      margin: 0;
      font-size: 22px;
      font-weight: bold;
    }
    .header h2 {
      margin: 0;
      font-size: 18px;
      font-weight: normal;
    }
    h3, h4 {
      margin-top: 25px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
      margin-bottom: 20px;
    }
    th, td {
      border: 1px solid #000;
      padding: 8px;
      text-align: left;
    }
    th {
      background-color: #f2f2f2;
    }
    .items th {
      background-color: #004b23;
      color: white;
    }
    .total {
      text-align: right;
      font-size: 1.2em;
      font-weight: bold;
    }
    .note {
      font-size: 0.9em;
      margin-top: 20px;
      line-height: 1.5em;
    }
    hr {
      border: none;
      border-top: 2px solid #004b23;
      margin: 30px 0;
    }
  </style>
</head>
<body>
  <div class="header">
    <img src="{{ public_path('images/logoUV.png') }}" alt="Logo Universidad Veracruzana" width="100">
    <h1>Universidad Veracruzana</h1>
    <h2>Orden de Pago</h2>
  </div>

  <h3>Folio de Solicitud: {{ $solicitud->folio }}</h3>

  <table>
    <tr>
      <th>Nombre del Estudiante</th>
      <td>{{ $user->name }}</td>
    </tr>
    <tr>
      <th>Matrícula</th>
      <td>{{ $user->estudiante->matriculaEstudiante ?? 'N/A' }}</td>
    </tr>
    <tr>
      <th>Programa Educativo</th>
      <td>{{ $user->estudiante->programaEducativo->nombrePE ?? 'N/A' }}</td>
    </tr>
    <tr>
      <th>Fecha de Solicitud</th>
      <td>{{ $solicitud->created_at->format('d/m/Y') }}</td>
    </tr>
  </table>

  <h4>Trámites Solicitados</h4>
  <table class="items">
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

  <div class="note">
    <p><strong>Nota:</strong> Verifica que el pago se realice a nombre de <strong>Universidad Veracruzana</strong>. Conserva tu comprobante para cualquier aclaración.</p>
  </div>
</body>
</html>
