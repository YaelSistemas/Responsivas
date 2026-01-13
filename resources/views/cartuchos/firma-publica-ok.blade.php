<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Firma registrada</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @vite('resources/css/app.css')
</head>
<body class="bg-gray-100">
<div class="min-h-screen flex items-center justify-center p-4">
    <div class="bg-white shadow-md rounded-lg p-4 max-w-md w-full text-center">
        @php
            $folio = $cartucho->folio ?? ('#'.$cartucho->id);
        @endphp

        <h1 class="text-lg font-bold mb-2">¡Gracias!</h1>
        <p class="text-sm mb-2">
            Tu firma para la salida de cartuchos <strong>{{ $folio }}</strong> ha sido registrada correctamente.
        </p>

        <p class="text-xs text-gray-500 mt-3">
            Ya puedes cerrar esta ventana.
        </p>
    </div>
</div>
</body>
</html>
