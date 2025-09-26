{{-- resources/views/public/responsivas/sign.blade.php --}}
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Firmar responsiva</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root { color-scheme: light dark; }
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,sans-serif;margin:0;background:#f3f4f6;color:#111827}

    /* ====== Zoom responsivo del layout ====== */
    .zoom-outer{ overflow-x:hidden; }
    .zoom-inner{
      --zoom: 1;
      transform: scale(var(--zoom));
      transform-origin: top left;
      width: calc(100% / var(--zoom));
    }
    @media (max-width:1024px){ .zoom-inner{ --zoom:.95; } .wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }
    @media (max-width:768px){  .zoom-inner{ --zoom:.90; } .wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }
    @media (max-width:640px){  .zoom-inner{ --zoom:.70; } .wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }
    @media (max-width:400px){  .zoom-inner{ --zoom:.55; } .wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }

    @media (max-width:768px){ input, select, textarea, button{ font-size:16px; } }

    .wrap{max-width:1040px;margin:40px auto;padding:0 24px}
    .card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 10px 20px rgba(0,0,0,.06);overflow:hidden}
    .inner{padding:20px 22px}

    .grid{display:grid;gap:18px}
    @media (min-width:980px){ .grid{grid-template-columns:330px 1fr;} }

    h1{font-size:20px;margin:0 0 4px}
    .muted{color:#6b7280;font-size:13px;margin:0 0 14px}
    .row{display:flex;gap:16px;align-items:center;justify-content:space-between}
    .btn{padding:10px 14px;border-radius:10px;border:1px solid #d1d5db;background:#f8fafc;cursor:pointer;font-weight:600}
    .btn-primary{background:#2563eb;color:#fff;border-color:#2563eb}
    .btn-danger{background:#fef2f2;border-color:#fecaca;color:#b91c1c}

    /* ========== Firma ========== */
    .canvas-wrap{
      border:2px dashed #cbd5e1;border-radius:12px;background:#fff;padding:10px;
      touch-action:none; overscroll-behavior:contain; user-select:none;
    }
    #pad{
      width:100%; height:520px; display:block; background:#fff;
      touch-action:none; -ms-touch-action:none; user-select:none;
    }

    .hint{font-size:12px;color:#6b7280;margin-top:6px}
    .meta{font-size:14px;margin:12px 0 2px}

    /* ===== Mini vista (A4 thumbnail con srcdoc HTML) ===== */
    .miniPreviewOuter{
      --scale:.34; --w:794; --h:1123;
      width: calc(var(--w) * var(--scale) * 1px);
      height: calc(var(--h) * var(--scale) * 1px);
      border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;
      background:#fff;box-shadow:0 4px 12px rgba(0,0,0,.06);
      cursor:pointer;position:relative;
    }
    .miniPreviewOuter::after{
      content:'Toca/click para ver en grande';
      position:absolute;left:8px;top:8px;padding:4px 8px;border-radius:6px;
      background:rgba(243,244,246,.9);color:#111827;font-size:12px;font-weight:600;
    }
    .miniPreviewFrame{
      width: calc(var(--w) * 1px);
      height: calc(var(--h) * 1px);
      transform: scale(var(--scale)); transform-origin: top left; border:0; display:block;
      pointer-events:none; /* que no intente hacer scroll dentro */
    }

    /* ===== Modal de vista grande (PDF real) ===== */
    .modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;z-index:60}
    .modal.open{display:flex}
    .modal .backdrop{position:absolute;inset:0;background:rgba(0,0,0,.45)}

    .modal .panel{
      position:relative;background:#fff;border-radius:12px;box-shadow:0 20px 45px rgba(0,0,0,.35);
      width:min(96vw,980px);
      height:calc(100svh - env(safe-area-inset-top,0px) - env(safe-area-inset-bottom,0px) - 8px);
      margin-top:env(safe-area-inset-top,0px);
      margin-bottom:env(safe-area-inset-bottom,0px);
      padding:12px;display:flex;flex-direction:column;overflow:hidden
    }
    @media (max-width:640px){ .modal .panel{ padding:6px; } .modal .title{ font-size:14px; padding:2px 4px 6px; } }

    /* Botón de cierre fijo */
    .modal .close-fixed{
      position:fixed;
      top:calc(env(safe-area-inset-top,0px) + 10px);
      right:calc(env(safe-area-inset-right,0px) + 10px);
      z-index:1000;
      width:44px;height:44px;border-radius:12px;border:1px solid #e5e7eb;background:#fff;
      display:flex;align-items:center;justify-content:center;
      font-size:22px;line-height:1;color:#374151;box-shadow:0 4px 12px rgba(0,0,0,.15);
    }

    .fullPreviewWrap{
      position:relative;flex:1 1 auto;display:flex;align-items:center;justify-content:center;
      overflow:hidden;background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:0;
    }
    .fullPdf{ width:100%; height:100%; border:0; display:block; }
  </style>
</head>
<body>
  @php
    // HTML de la página PDF (para miniatura)
    $__pdfHtmlRaw = view('responsivas.pdf', ['responsiva'=>$responsiva])->render();
    $__srcdoc = str_replace(['"',"'"], ['&quot;','&#39;'], $__pdfHtmlRaw);

    // URL pública del PDF real (para el modal)
    $pdfUrl = route('public.sign.pdf', $responsiva->sign_token);
  @endphp

  <!-- Envoltura -->
  <div class="zoom-outer">
    <div class="zoom-inner">
      <div class="wrap">
        <div class="card">
          <div class="inner">
            <h1>Firma de responsiva</h1>
            <p class="muted">Folio: <b>{{ $responsiva->folio }}</b></p>

            <div class="grid">
              {{-- Columna izquierda: miniatura (srcdoc HTML) --}}
              <div>
                <div id="miniPreview" class="miniPreviewOuter" title="Ver responsiva en grande">
                  <iframe id="miniFrame" class="miniPreviewFrame" srcdoc='{!! $__srcdoc !!}' loading="lazy"></iframe>
                </div>
              </div>

              {{-- Columna derecha: pad de firma --}}
              <div>
                <p class="meta">Por favor dibuja tu firma dentro del recuadro:</p>
                <div class="canvas-wrap">
                  <canvas id="pad" width="640" height="520"></canvas>
                </div>
                <div class="hint">Puedes firmar con el mouse o con el dedo (en celular). Si te equivocas usa “Limpiar”.</div>

                <form id="formFirmar" method="POST" action="{{ route('public.sign.store', $responsiva->sign_token) }}" style="margin-top:14px">
                  @csrf
                  <input type="hidden" name="firma" id="firma">
                  <div class="row">
                    <button type="button" class="btn btn-danger" id="btnClear">Limpiar</button>
                    <div style="flex:1"></div>
                    <button type="submit" class="btn btn-primary">Firmar y enviar</button>
                  </div>
                </form>
              </div>
            </div> <!-- /.grid -->
          </div>
        </div>
      </div> <!-- /.wrap -->
    </div>
  </div>

  {{-- MODAL: PDF real a pantalla completa --}}
  <div id="previewModal" class="modal" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="backdrop" data-close="1"></div>
    <button class="close-fixed" type="button" aria-label="Cerrar" data-close="1">×</button>

    <div class="panel">
      <div class="title">Vista previa — Responsiva {{ $responsiva->folio }}</div>
      <div id="fullWrap" class="fullPreviewWrap">
        <iframe id="fullPdf" class="fullPdf" title="Responsiva PDF"></iframe>
      </div>
    </div>
  </div>

<script>
/* ===== Firma (canvas) — preserva trazos en cambios de viewport ===== */
(function(){
  const canvas = document.getElementById('pad');
  const ctx = canvas.getContext('2d', { willReadFrequently: true });

  let hasStrokes = false;
  let drawing = false;
  let last = null;

  // recordamos el último tamaño "lógico" para evitar resizes innecesarios
  let lastCssW = 0, lastCssH = 0, lastDPR = 0;

  function preserveImageDataURL() {
    try {
      // Usamos dataURL para re-escalar bien al nuevo backing store
      return hasStrokes ? canvas.toDataURL('image/png') : null;
    } catch { return null; }
  }

  function restoreFromDataURL(dataURL, cssW, cssH) {
    if (!dataURL) return;
    const img = new Image();
    img.onload = () => {
      // pintamos en coordenadas CSS (ya tenemos el transform por DPR aplicado)
      ctx.drawImage(img, 0, 0, cssW, cssH);
    };
    img.src = dataURL;
  }

  function fitCanvas(){
    const cs   = getComputedStyle(canvas);
    const cssW = Math.max(1, Math.round(parseFloat(cs.width)));
    const cssH = Math.max(1, Math.round(parseFloat(cs.height)));
    const DPR  = window.devicePixelRatio || 1;

    // Si nada cambió, no hacemos nada (evita perder contenido)
    if (cssW === lastCssW && cssH === lastCssH && DPR === lastDPR) return;

    const snapshot = preserveImageDataURL();

    // Ajustamos backing store a pixeles reales
    canvas.width  = Math.max(1, Math.round(cssW * DPR));
    canvas.height = Math.max(1, Math.round(cssH * DPR));

    // Dibujamos en coordenadas CSS
    ctx.setTransform(DPR, 0, 0, DPR, 0, 0);
    ctx.lineWidth = 2;
    ctx.lineCap='round'; ctx.lineJoin='round'; ctx.strokeStyle='#111827';

    // Limpiamos sólo el área visible y restauramos la imagen previa
    ctx.clearRect(0,0,cssW,cssH);
    restoreFromDataURL(snapshot, cssW, cssH);

    // NO tocamos hasStrokes aquí
    lastCssW = cssW; lastCssH = cssH; lastDPR = DPR;
  }

  function pointerPos(e){
    const rect = canvas.getBoundingClientRect();
    const cs   = getComputedStyle(canvas);
    const cssW = parseFloat(cs.width);
    const cssH = parseFloat(cs.height);

    const scaleX = rect.width  / cssW || 1;
    const scaleY = rect.height / cssH || 1;

    const clientX = (e.clientX ?? (e.touches && e.touches[0]?.clientX) ?? 0);
    const clientY = (e.clientY ?? (e.touches && e.touches[0]?.clientY) ?? 0);

    let x = (clientX - rect.left) / scaleX;
    let y = (clientY - rect.top)  / scaleY;

    x = Math.max(0, Math.min(x, cssW - 0.001));
    y = Math.max(0, Math.min(y, cssH - 0.001));
    return { x, y };
  }

  function start(e){
    e.preventDefault();
    drawing = true; hasStrokes = true; last = pointerPos(e);
    // “punto” inicial
    ctx.beginPath(); ctx.moveTo(last.x,last.y); ctx.lineTo(last.x+0.01,last.y); ctx.stroke();
    if (canvas.setPointerCapture && e.pointerId!=null) canvas.setPointerCapture(e.pointerId);
  }
  function move(e){
    if(!drawing) return;
    e.preventDefault();
    const p=pointerPos(e);
    ctx.beginPath(); ctx.moveTo(last.x,last.y); ctx.lineTo(p.x,p.y); ctx.stroke();
    last=p;
  }
  function end(){ drawing=false; }

  // Redimensiona preservando el trazo
  window.addEventListener('resize', () => requestAnimationFrame(fitCanvas), {passive:true});
  if (window.visualViewport){
    // solo en resize (cuando cambia la altura útil al ocultarse/mostrarse la barra)
    visualViewport.addEventListener('resize', () => requestAnimationFrame(fitCanvas));
    // IMPORTANTE: NO redimensionar en scroll, eso borraba el bitmap
    // visualViewport.addEventListener('scroll', ... )  <-- eliminado
  }
  // primer ajuste
  setTimeout(fitCanvas, 0);

  // Dibujo
  canvas.addEventListener('pointerdown', start, {passive:false});
  canvas.addEventListener('pointermove',  move,  {passive:false});
  window.addEventListener('pointerup',    end,   {passive:false});
  canvas.addEventListener('pointerleave', end,   {passive:false});
  canvas.addEventListener('pointercancel',end,   {passive:false});

  // Evita que el navegador intente hacer scroll/zoom mientras firmas
  canvas.addEventListener('touchstart', (e)=>e.preventDefault(), {passive:false});
  canvas.addEventListener('touchmove',  (e)=>e.preventDefault(), {passive:false});

  // Botón limpiar
  document.getElementById('btnClear').addEventListener('click', ()=>{
    const cs = getComputedStyle(canvas);
    ctx.clearRect(0,0,parseFloat(cs.width),parseFloat(cs.height));
    hasStrokes=false;
  });

  // Envío
  document.getElementById('formFirmar').addEventListener('submit', (ev)=>{
    if(!hasStrokes){
      ev.preventDefault();
      alert('Por favor dibuja tu firma antes de continuar.');
      return;
    }
    document.getElementById('firma').value = canvas.toDataURL('image/png');
  });
})();
</script>

<script>
/* ===== Miniatura (HTML) -> Modal con PDF real ===== */
(function(){
  const modal   = document.getElementById('previewModal');
  const miniBox = document.getElementById('miniPreview');
  const fullPdf = document.getElementById('fullPdf');
  const pdfUrl  = @json($pdfUrl . '#view=FitH'); // Fit width en la mayoría de visores

  function open(){ 
    if (!fullPdf.src) fullPdf.src = pdfUrl; // carga perezosa del PDF real
    modal.classList.add('open');
  }
  function close(){ modal.classList.remove('open'); }

  miniBox?.addEventListener('click', open);
  document.querySelector('.close-fixed')?.addEventListener('click', close);
  document.querySelector('#previewModal .backdrop')?.addEventListener('click', close);
})();
</script>
</body>
</html>
