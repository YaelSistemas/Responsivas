{{-- resources/views/public/responsivas/signed.blade.php --}}
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Responsiva firmada</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,sans-serif;margin:0;background:#f3f4f6}
    .wrap{max-width:720px;margin:40px auto;padding:24px}
    .card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 10px 20px rgba(0,0,0,.06)}
    .inner{padding:26px 28px;text-align:center}
    h1{font-size:20px;margin:0 0 6px}
    .muted{color:#6b7280;font-size:13px;margin:0 0 16px}
    .firma{max-width:280px;max-height:120px;display:block;margin:12px auto;mix-blend-mode:multiply}
    .btn{padding:10px 14px;border-radius:10px;border:1px solid #2563eb;background:#2563eb;color:#fff;font-weight:600;text-decoration:none;display:inline-block}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="inner">
        <h1>¡Gracias! Tu responsiva ha sido firmada.</h1>
        <p class="muted">Folio: <b>{{ $responsiva->folio }}</b></p>

        @if (!empty($firma_url))
          <img class="firma" src="{{ $firma_url }}" alt="Firma">
        @endif

        <a class="btn" href="{{ $pdf_url }}" target="_blank" rel="noopener">Ver/Descargar PDF</a>
      </div>
    </div>
  </div>
</body>
</html>
