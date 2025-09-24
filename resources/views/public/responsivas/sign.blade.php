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
    .wrap{max-width:1040px;margin:40px auto;padding:0 24px}
    .card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 10px 20px rgba(0,0,0,.06);overflow:hidden}
    .inner{padding:20px 22px}

    .grid{display:grid;gap:18px}
    @media (min-width: 980px){
      .grid{grid-template-columns: 330px 1fr;}
    }

    h1{font-size:20px;margin:0 0 4px}
    .muted{color:#6b7280;font-size:13px;margin:0 0 14px}
    .row{display:flex;gap:16px;align-items:center;justify-content:space-between}
    .btn{padding:10px 14px;border-radius:10px;border:1px solid #d1d5db;background:#f8fafc;cursor:pointer;font-weight:600}
    .btn-primary{background:#2563eb;color:#fff;border-color:#2563eb}
    .btn-danger{background:#fef2f2;border-color:#fecaca;color:#b91c1c}

    /* ========== Firma ========== */
    .canvas-wrap{
      border:2px dashed #cbd5e1;border-radius:12px;background:#fff;padding:10px;
      /* Importante en móviles: evita pan/zoom y overscroll mientras firmas */
      touch-action: none;
      overscroll-behavior: contain;
      user-select: none;
    }
    #pad{
      width:100%;height:auto;display:block;background:#ffffff;
      /* Refuerzo: bloquear gestos directamente en el canvas */
      touch-action: none;
      -ms-touch-action: none; /* Edge heredado */
      user-select: none;
    }

    .hint{font-size:12px;color:#6b7280;margin-top:6px}
    .meta{font-size:14px;margin:12px 0 2px}

    /* ===== Mini vista (A4 thumbnail) ===== */
    .miniPreviewOuter{
      --scale: .34;
      --w: 794; --h: 1123;
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
      transform: scale(var(--scale));
      transform-origin: top left;
      border:0;display:block;
    }

    /* ===== Modal de vista grande ===== */
    .modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;z-index:60}
    .modal.open{display:flex}
    .modal .backdrop{position:absolute;inset:0;background:rgba(0,0,0,.45)}
    .modal .panel{
      position:relative;background:#fff;border-radius:12px;box-shadow:0 20px 45px rgba(0,0,0,.35);
      width:min(96vw,980px);height:min(98vh,1200px);
      padding:12px;display:flex;flex-direction:column;overflow:hidden
    }
    .modal .title{font-weight:800;color:#111827;padding:2px 6px 8px}
    .modal .close{position:absolute;right:10px;top:10px;font-size:22px;line-height:1;cursor:pointer;color:#374151}
    .fullPreviewWrap{
      position:relative;flex:1 1 auto;display:flex;align-items:center;justify-content:center;
      overflow:clip; /* evita barras por 1px de redondeo */
      background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:8px;
    }
    .fullPreviewCanvas{
      transform-origin: top left; border:0; background:#fff; box-shadow:0 2px 8px rgba(0,0,0,.08);
    }
  </style>
</head>
<body>
  @php
    $__pdfHtmlRaw = view('responsivas.pdf', ['responsiva'=>$responsiva])->render();
    $__srcdoc = str_replace(['"',"'"], ['&quot;','&#39;'], $__pdfHtmlRaw);
  @endphp

  <div class="wrap">
    <div class="card">
      <div class="inner">
        <h1>Firma de responsiva</h1>
        <p class="muted">Folio: <b>{{ $responsiva->folio }}</b></p>

        <div class="grid">

          {{-- Columna izquierda: miniatura --}}
          <div>
            <div id="miniPreview" class="miniPreviewOuter" title="Ver responsiva en grande">
              <iframe id="miniFrame" class="miniPreviewFrame" srcdoc='{!! $__srcdoc !!}' loading="lazy"></iframe>
            </div>
          </div>

          {{-- Columna derecha: pad de firma --}}
          <div>
            <p class="meta">Por favor dibuja tu firma dentro del recuadro:</p>
            <div class="canvas-wrap">
              <canvas id="pad" width="640" height="220"></canvas>
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

        </div>
      </div>
    </div>
  </div>

  {{-- MODAL: Vista grande --}}
  <div id="previewModal" class="modal" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="backdrop" data-close="1"></div>
    <div class="panel">
      <div class="title">Vista previa — Responsiva {{ $responsiva->folio }}</div>
      <button class="close" type="button" aria-label="Cerrar" data-close="1">&times;</button>
      <div id="fullWrap" class="fullPreviewWrap">
        <iframe id="fullFrame" class="fullPreviewCanvas" srcdoc='{!! $__srcdoc !!}'></iframe>
      </div>
    </div>
  </div>

<script>
/* ===== Firma (canvas) ===== */
(function(){
  const canvas = document.getElementById('pad');
  const ctx = canvas.getContext('2d');
  const DPR = window.devicePixelRatio || 1;

  function fitCanvas(){
    const rect = canvas.getBoundingClientRect();
    canvas.width  = Math.max(1, Math.round(rect.width * DPR));
    canvas.height = Math.max(1, Math.round(220 * DPR));
    ctx.setTransform(DPR, 0, 0, DPR, 0, 0);
    ctx.lineWidth = 2; ctx.lineCap='round'; ctx.lineJoin='round'; ctx.strokeStyle='#111827';
    ctx.clearRect(0,0,canvas.width/DPR,canvas.height/DPR);
    hasStrokes = false;
  }
  let hasStrokes=false, drawing=false, last=null;
  const pos = e => { const r = canvas.getBoundingClientRect(); return {x:e.clientX-r.left, y:e.clientY-r.top}; };

  function start(e){
    e.preventDefault();                 // evita scroll/pinch en táctiles
    drawing=true; hasStrokes=true; last=pos(e);
    if (canvas.setPointerCapture) canvas.setPointerCapture(e.pointerId);
  }
  function move(e){
    if(!drawing) return;
    e.preventDefault();                 // evita scroll mientras dibujas
    const p=pos(e);
    ctx.beginPath(); ctx.moveTo(last.x,last.y); ctx.lineTo(p.x,p.y); ctx.stroke();
    last=p;
  }
  function end(){ drawing=false; }

  window.addEventListener('resize', fitCanvas, {passive:true});
  setTimeout(fitCanvas, 0);

  // Pointer Events (moderno)
  canvas.addEventListener('pointerdown', start, {passive:false});
  canvas.addEventListener('pointermove',  move,  {passive:false});
  window.addEventListener('pointerup',    end,   {passive:false});
  canvas.addEventListener('pointerleave', end,   {passive:false});
  canvas.addEventListener('pointercancel',end,   {passive:false});

  // Fallback iOS/Safari antiguos (touch events)
  canvas.addEventListener('touchstart', (e)=>e.preventDefault(), {passive:false});
  canvas.addEventListener('touchmove',  (e)=>e.preventDefault(), {passive:false});

  document.getElementById('btnClear').addEventListener('click', ()=>{
    ctx.clearRect(0,0,canvas.width,canvas.height);
    hasStrokes=false;
  });

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
/* ===== Vista previa (mini + grande) ===== */
(function(){
  const modal     = document.getElementById('previewModal');
  const fullWrap  = document.getElementById('fullWrap');
  const fullFrame = document.getElementById('fullFrame');
  const miniBox   = document.getElementById('miniPreview');
  const miniFrame = document.getElementById('miniFrame');

  function prepareIframe(iframe){
    try{
      const doc = iframe.contentDocument || iframe.contentWindow.document;
      doc.documentElement.style.overflow = 'hidden';
      if (doc.body){ doc.body.style.overflow='hidden'; doc.body.style.margin='0'; doc.body.style.background='#fff'; }

      // Evita que imágenes empujen columnas y reduce el logo
      const style = doc.createElement('style');
      style.textContent = `img{max-width:100% !important;height:auto !important}`;
      doc.head.appendChild(style);

      const firstImg = doc.querySelector('img');
      if (firstImg){
        firstImg.removeAttribute('width');
        firstImg.removeAttribute('height');
        firstImg.style.maxWidth = '82%';
        firstImg.style.height   = 'auto';
        firstImg.style.objectFit= 'contain';
        firstImg.style.display  = 'block';
        firstImg.style.margin   = '0 auto';
      }
    }catch(e){}
  }

  function measureIframe(iframe){
    try{
      const doc  = iframe.contentDocument || iframe.contentWindow.document;
      const html = doc.documentElement, body = doc.body || html;
      const W = Math.max(html.scrollWidth,  body.scrollWidth,  html.offsetWidth,  body.offsetWidth);
      const H = Math.max(html.scrollHeight, body.scrollHeight, html.offsetHeight, body.offsetHeight);
      return { W, H };
    }catch(e){ return { W: 794, H: 1123 }; }
  }

  /* Miniatura */
  function setMini(){
    const { W, H } = measureIframe(miniFrame);
    prepareIframe(miniFrame);
    miniFrame.style.width  = W + 'px';
    miniFrame.style.height = H + 'px';
    document.querySelector('.miniPreviewOuter')?.style.setProperty('--w', W);
    document.querySelector('.miniPreviewOuter')?.style.setProperty('--h', H);
  }

  /* Grande: encaja completo; scroll si falta alto */
  function setFull(){
    const { W, H } = measureIframe(fullFrame);
    prepareIframe(fullFrame);
    fullFrame.style.width  = W + 'px';
    fullFrame.style.height = H + 'px';

    const cs = getComputedStyle(fullWrap);
    const padX = parseFloat(cs.paddingLeft) + parseFloat(cs.paddingRight);
    const padY = parseFloat(cs.paddingTop)  + parseFloat(cs.paddingBottom);
    const maxW = Math.max(50, fullWrap.clientWidth  - padX);
    const maxH = Math.max(50, fullWrap.clientHeight - padY);

    const SAFE = 0.94; // pequeño margen para evitar recortes y barra
    const scale = Math.max(.2, Math.min(maxW / W, maxH / H) * SAFE);

    if ('zoom' in fullFrame.style){
      fullFrame.style.transform = 'none';
      fullFrame.style.zoom = String(scale);
    }else{
      fullFrame.style.zoom = '';
      fullFrame.style.transform = `scale(${scale})`;
    }
  }

  if (miniFrame) {
    if (miniFrame.contentDocument?.readyState === 'complete') setMini();
    miniFrame.addEventListener('load', setMini, { once:true });
  }
  if (fullFrame) {
    fullFrame.addEventListener('load', ()=>{ if(modal.classList.contains('open')) setFull(); });
  }

  function open(){ modal.classList.add('open'); requestAnimationFrame(setFull); setTimeout(setFull, 80); }
  function close(){ modal.classList.remove('open'); }

  miniBox?.addEventListener('click', open);
  modal?.addEventListener('click', (e)=>{ if(e.target.dataset.close) close(); });
  window.addEventListener('resize', ()=>{ if(modal.classList.contains('open')) setFull(); });
})();
</script>
</body>
</html>
