{{-- resources/views/devoluciones/firma-externa-ok.blade.php --}}
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Firma registrada</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body class="bg-gray-100">
<div class="min-h-screen flex items-center justify-center p-4">
    <div class="bg-white shadow-md rounded-lg p-4 max-w-md w-full text-center">
        <h1 class="text-lg font-bold mb-2">¡Gracias!</h1>
        <p class="text-sm mb-2">
            Tu firma para la devolución <strong>{{ $devolucion->folio }}</strong> ha sido registrada correctamente.
        </p>
    </div>
</div>
</body>
</html>