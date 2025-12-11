{{-- resources/views/devoluciones/firma-externa.blade.php --}}
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Firmar devolución {{ $devolucion->folio }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @vite('resources/css/app.css') {{-- o tu CSS --}}
</head>
<body class="bg-gray-100">
<div class="min-h-screen flex items-center justify-center p-4">
    <div class="bg-white shadow-md rounded-lg p-4 max-w-xl w-full">

        @php
            // ¿Este link es para ENTREGÓ o para PSITIO?
            $esPsitio  = $firmaLink->campo === 'psitio';
            $colFirma  = $esPsitio ? $devolucion->psitioColaborador : $devolucion->entregoColaborador;
            $nombreCol = trim(
                ($colFirma->nombre ?? '') . ' ' . ($colFirma->apellidos ?? '')
            );
        @endphp

        <h1 class="text-lg font-bold mb-2 text-center">
            Firma de Devolución {{ $devolucion->folio }}
        </h1>

        <p class="text-xs text-gray-500 mb-1 text-center">
            Tipo de firma:
            <strong>
                {{ $esPsitio ? 'RECIBIÓ EN SITIO' : 'ENTREGÓ (colaborador)' }}
            </strong>
        </p>

        <p class="text-sm text-gray-600 mb-4 text-center">
            Nombre del usuario:
            <strong>{{ $nombreCol ?: '—' }}</strong>
        </p>

        @if ($errors->any())
            <div class="mb-3 text-sm text-red-600 text-center">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('devoluciones.firmaExterna.store', $firmaLink->token) }}">
            @csrf
            <input type="hidden" name="firma" id="firmaData">

            <div class="border border-dashed rounded p-2 mb-3">
                <canvas id="canvasFirma"
                        width="560" height="180"
                        style="width:100%;height:180px;touch-action:none;"></canvas>
            </div>

            <div class="flex gap-2 justify-end flex-wrap">
                <button type="button" id="btnLimpiar"
                        class="px-3 py-2 rounded border text-sm">
                    Limpiar
                </button>

                {{-- Botón con clases de Tailwind + estilos inline de respaldo --}}
                <button type="submit"
                        class="px-3 py-2 rounded bg-blue-600 text-white text-sm font-semibold"
                        style="
                            background:#2563eb;
                            color:#ffffff;
                            border-radius:0.375rem;
                            padding:0.5rem 0.75rem;
                            font-size:0.875rem;
                            font-weight:600;
                            border:none;
                        ">
                    Firmar y enviar
                </button>
            </div>
        </form>

        <p class="text-[11px] text-gray-500 mt-4">
            Al presionar "Firmar y enviar" autorizas el registro de tu firma para la devolución de equipo.
        </p>
    </div>
</div>

<script>
let c, ctx, drawing = false, lastX = 0, lastY = 0, hasStrokes = false;

function initCanvas() {
    c   = document.getElementById('canvasFirma');
    ctx = c.getContext('2d');
    ctx.clearRect(0, 0, c.width, c.height);
    ctx.lineWidth   = 2;
    ctx.lineJoin    = 'round';
    ctx.lineCap     = 'round';
    ctx.strokeStyle = '#000000';
    hasStrokes = false;
}

function startDraw(x, y) {
    drawing = true;
    lastX = x; lastY = y;
}

function moveDraw(x, y) {
    if (!drawing) return;
    ctx.beginPath();
    ctx.moveTo(lastX, lastY);
    ctx.lineTo(x, y);
    ctx.stroke();
    lastX = x; lastY = y;
    hasStrokes = true;
}

function stopDraw() {
    drawing = false;
}

document.addEventListener('DOMContentLoaded', () => {
    initCanvas();

    // Mouse
    c.addEventListener('mousedown', e => {
        const rect = c.getBoundingClientRect();
        startDraw(e.clientX - rect.left, e.clientY - rect.top);
    });
    c.addEventListener('mousemove', e => {
        const rect = c.getBoundingClientRect();
        moveDraw(e.clientX - rect.left, e.clientY - rect.top);
    });
    c.addEventListener('mouseup', stopDraw);
    c.addEventListener('mouseleave', stopDraw);

    // Touch
    c.addEventListener('touchstart', e => {
        e.preventDefault();
        const rect = c.getBoundingClientRect();
        const t = e.touches[0];
        startDraw(t.clientX - rect.left, t.clientY - rect.top);
    }, {passive:false});
    c.addEventListener('touchmove', e => {
        e.preventDefault();
        const rect = c.getBoundingClientRect();
        const t = e.touches[0];
        moveDraw(t.clientX - rect.left, t.clientY - rect.top);
    }, {passive:false});
    c.addEventListener('touchend', e => {
        e.preventDefault();
        stopDraw();
    }, {passive:false});

    // Limpiar
    document.getElementById('btnLimpiar').addEventListener('click', () => {
        ctx.clearRect(0, 0, c.width, c.height);
        hasStrokes = false;
    });

    // Submit → mandar base64
    const form = document.querySelector('form');
    form.addEventListener('submit', (ev) => {
        if (!hasStrokes) {
            ev.preventDefault();
            alert('Por favor dibuja tu firma antes de continuar.');
            return;
        }
        document.getElementById('firmaData').value = c.toDataURL('image/png');
    });
});
</script>
</body>
</html>
