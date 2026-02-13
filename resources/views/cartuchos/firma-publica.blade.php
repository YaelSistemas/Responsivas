<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Firmar Consumibles {{ $cartucho->folio ?? ('#'.$cartucho->id) }}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  @vite('resources/css/app.css')
</head>

<body class="bg-gray-100">
@php
  $nombreCol = trim(($cartucho->colaborador?->nombre ?? '') . ' ' . ($cartucho->colaborador?->apellidos ?? ''));
  $folio = $cartucho->folio ?? ('#'.$cartucho->id);
@endphp

<div class="min-h-screen flex items-center justify-center p-4">
  <div class="w-full max-w-6xl">

    <div class="bg-white shadow-md rounded-xl p-4 md:p-6">
      <div class="mb-4">
        <h1 class="text-lg md:text-xl font-bold text-center">
          Firma de Salida de Consumibles {{ $folio }}
        </h1>

        <p class="text-xs text-gray-500 text-center mt-1">
          Tipo de firma: <strong>RECIBIÓ (colaborador)</strong>
        </p>

        <p class="text-sm text-gray-700 text-center mt-1">
          Nombre del usuario: <strong>{{ $nombreCol ?: '—' }}</strong>
        </p>
      </div>

      @if ($errors->any())
        <div class="mb-3 text-sm text-red-600 text-center">
          {{ $errors->first() }}
        </div>
      @endif

      {{-- alturas --}}
      <div class="[--panelH:720px] [--sigH:420px]">

        {{-- 👇 CLAVE: desde md ya son 2 columnas (NO se apila) --}}
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-6 items-stretch">

          {{-- IZQ: preview --}}
          <div class="md:col-span-1 xl:col-span-2">
            <div class="border rounded-lg p-3 bg-gray-50 flex flex-col h-auto md:h-[var(--panelH)]">
              <div class="flex items-center justify-between mb-2">
                <p class="text-sm font-semibold">Vista previa</p>

                <button type="button"
                        id="btnOpenPdf"
                        class="px-3 py-1.5 rounded border text-xs bg-white hover:bg-gray-100">
                  Ver en grande
                </button>
              </div>

              <div class="rounded border bg-white overflow-hidden flex-1 min-h-0">
                <iframe
                  src="{{ $pdfUrl }}#page=1&zoom=page-fit"
                  class="w-full h-full block"
                  style="border:0;"
                  title="Vista previa PDF"
                ></iframe>
              </div>

              <p class="text-[11px] text-gray-500 mt-2">
                Puedes desplazarte con scroll dentro de la vista previa. Para ver en grande usa “Ver en grande”.
              </p>
            </div>
          </div>

          {{-- DER: firma --}}
          <div class="md:col-span-1 xl:col-span-3">
            <form method="POST" action="{{ route('cartuchos.firma.publica.store', $token) }}">
              @csrf
              <input type="hidden" name="firma_data" id="firmaData">

              <p class="text-sm text-gray-700 mb-2">
                Por favor dibuja tu firma dentro del recuadro:
              </p>

              <div class="border border-dashed rounded-lg p-2 bg-white">
                <canvas id="canvasFirma"
                        style="width:100%; height:var(--sigH); touch-action:none;"></canvas>
              </div>

              <p class="text-xs text-gray-500 mt-2">
                Puedes firmar con el mouse o con el dedo (en celular). Si te equivocas usa “Limpiar”.
              </p>

              <div class="flex items-center justify-between mt-4 flex-wrap gap-2">
                <button type="button" id="btnLimpiar"
                        class="px-4 py-2 rounded-lg border text-sm bg-white hover:bg-gray-50">
                  Limpiar
                </button>

                <button type="submit"
                        class="px-5 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
                  Firmar y enviar
                </button>
              </div>

              <p class="text-[11px] text-gray-500 mt-4">
                Al presionar "Firmar y enviar" autorizas el registro de tu firma para la salida de consumibles.
              </p>
            </form>
          </div>

        </div>
      </div>
    </div>

    {{-- MODAL PDF --}}
    <div id="pdfModal"
         class="fixed inset-0 hidden items-center justify-center bg-black/60 p-3 z-50">
      <div class="bg-white w-full max-w-6xl rounded-xl shadow-xl overflow-hidden relative">
        <div class="flex items-center justify-between px-4 py-2 border-b">
          <p class="text-sm font-semibold">
            Vista previa — Salida de consumibles {{ $folio }}
          </p>

          <button type="button" id="btnClosePdf"
                  class="h-9 w-9 flex items-center justify-center rounded-lg border hover:bg-gray-100">
            ✕
          </button>
        </div>

        <div class="p-0">
          <iframe id="pdfModalFrame"
                  src=""
                  class="w-full block"
                  style="height:85vh;border:0;"
                  title="PDF en grande"></iframe>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
let c, ctx, drawing = false, lastX = 0, lastY = 0, hasStrokes = false;

function fitCanvasToCssSize() {
  const dpr = window.devicePixelRatio || 1;
  const rect = c.getBoundingClientRect();
  c.width  = Math.round(rect.width  * dpr);
  c.height = Math.round(rect.height * dpr);
  ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  ctx.lineWidth   = 2;
  ctx.lineJoin    = 'round';
  ctx.lineCap     = 'round';
  ctx.strokeStyle = '#000000';
}

function initCanvas() {
  c   = document.getElementById('canvasFirma');
  ctx = c.getContext('2d');
  fitCanvasToCssSize();
  ctx.clearRect(0, 0, c.width, c.height);
  hasStrokes = false;
}

function getPos(clientX, clientY) {
  const rect = c.getBoundingClientRect();
  return { x: clientX - rect.left, y: clientY - rect.top };
}

function startDraw(x, y) { drawing = true; lastX = x; lastY = y; }
function moveDraw(x, y) {
  if (!drawing) return;
  ctx.beginPath();
  ctx.moveTo(lastX, lastY);
  ctx.lineTo(x, y);
  ctx.stroke();
  lastX = x; lastY = y;
  hasStrokes = true;
}
function stopDraw() { drawing = false; }

document.addEventListener('DOMContentLoaded', () => {
  initCanvas();

  window.addEventListener('resize', () => initCanvas());

  c.addEventListener('mousedown', e => {
    const p = getPos(e.clientX, e.clientY);
    startDraw(p.x, p.y);
  });
  c.addEventListener('mousemove', e => {
    const p = getPos(e.clientX, e.clientY);
    moveDraw(p.x, p.y);
  });
  c.addEventListener('mouseup', stopDraw);
  c.addEventListener('mouseleave', stopDraw);

  c.addEventListener('touchstart', e => {
    e.preventDefault();
    const t = e.touches[0];
    const p = getPos(t.clientX, t.clientY);
    startDraw(p.x, p.y);
  }, {passive:false});

  c.addEventListener('touchmove', e => {
    e.preventDefault();
    const t = e.touches[0];
    const p = getPos(t.clientX, t.clientY);
    moveDraw(p.x, p.y);
  }, {passive:false});

  c.addEventListener('touchend', e => { e.preventDefault(); stopDraw(); }, {passive:false});

  document.getElementById('btnLimpiar').addEventListener('click', () => {
    ctx.clearRect(0, 0, c.width, c.height);
    hasStrokes = false;
  });

  const form = document.querySelector('form');
  form.addEventListener('submit', (ev) => {
    if (!hasStrokes) {
      ev.preventDefault();
      alert('Por favor dibuja tu firma antes de continuar.');
      return;
    }
    document.getElementById('firmaData').value = c.toDataURL('image/png');
  });

  const modal = document.getElementById('pdfModal');
  const frame = document.getElementById('pdfModalFrame');
  const pdfUrl = @json($pdfUrl);

  function openPdf() {
    frame.src = pdfUrl + '#page=1&zoom=page-fit';
    modal.classList.remove('hidden');
    modal.classList.add('flex');
  }

  function closePdf() {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    frame.src = '';
  }

  document.getElementById('btnOpenPdf').addEventListener('click', openPdf);
  document.getElementById('btnClosePdf').addEventListener('click', closePdf);

  modal.addEventListener('click', (e) => {
    if (e.target === modal) closePdf();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !modal.classList.contains('hidden')) closePdf();
  });
});
</script>
</body>
</html>
