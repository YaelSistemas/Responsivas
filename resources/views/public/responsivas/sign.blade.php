{{-- resources/views/public/responsivas/sign.blade.php --}}
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Firmar responsiva</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root { color-scheme: light dark; }
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,sans-serif;margin:0;background:#f3f4f6}
    .wrap{max-width:720px;margin:40px auto;padding:24px}
    .card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 10px 20px rgba(0,0,0,.06)}
    .inner{padding:20px 22px}
    h1{font-size:20px;margin:0 0 4px}
    .muted{color:#6b7280;font-size:13px;margin:0 0 14px}
    .row{display:flex;gap:16px;align-items:center;justify-content:space-between}
    .btn{padding:10px 14px;border-radius:10px;border:1px solid #d1d5db;background:#f8fafc;cursor:pointer;font-weight:600}
    .btn-primary{background:#2563eb;color:#fff;border-color:#2563eb}
    .btn-danger{background:#fef2f2;border-color:#fecaca;color:#b91c1c}
    .canvas-wrap{border:2px dashed #cbd5e1;border-radius:12px;background:#fff;padding:10px}
    .hint{font-size:12px;color:#6b7280;margin-top:6px}
    .meta{font-size:14px;margin:12px 0 2px}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="inner">
        <h1>Firma de responsiva</h1>
        <p class="muted">Folio: <b>{{ $responsiva->folio }}</b></p>

        <p class="meta">Por favor dibuja tu firma dentro del recuadro:</p>

        <div class="canvas-wrap">
          <canvas id="pad" width="640" height="220" style="width:100%;height:auto;display:block;background:#ffffff"></canvas>
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

<script>
(function(){
  const canvas = document.getElementById('pad');
  const ctx = canvas.getContext('2d');
  const DPR = window.devicePixelRatio || 1;
  // Escalar para pantallas retina
  const cssWidth  = canvas.clientWidth;
  const cssHeight = canvas.clientHeight;
  canvas.width  = Math.round(cssWidth  * DPR);
  canvas.height = Math.round(cssHeight * DPR);
  ctx.scale(DPR, DPR);
  ctx.lineWidth = 2;
  ctx.lineCap = 'round';
  ctx.lineJoin = 'round';
  ctx.strokeStyle = '#111827';

  let drawing = false, hasStrokes = false, last = null;

  function pos(e){
    const r = canvas.getBoundingClientRect();
    const x = (e.clientX - r.left);
    const y = (e.clientY - r.top);
    return {x,y};
  }

  function start(e){
    drawing = true;
    hasStrokes = true;
    last = pos(e);
  }
  function move(e){
    if(!drawing) return;
    const p = pos(e);
    ctx.beginPath();
    ctx.moveTo(last.x, last.y);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
    last = p;
  }
  function end(){ drawing = false; }

  canvas.addEventListener('pointerdown', start);
  canvas.addEventListener('pointermove', move);
  window.addEventListener('pointerup', end);
  canvas.addEventListener('pointerleave', end);

  document.getElementById('btnClear').addEventListener('click', () => {
    ctx.clearRect(0,0,canvas.width/DPR,canvas.height/DPR);
    hasStrokes = false;
  });

  document.getElementById('formFirmar').addEventListener('submit', (ev) => {
    if(!hasStrokes){
      ev.preventDefault();
      alert('Por favor dibuja tu firma antes de continuar.');
      return;
    }
    // Exporta como PNG (dataURL)
    const dataURL = canvas.toDataURL('image/png');
    document.getElementById('firma').value = dataURL;
  });
})();
</script>
</body>
</html>
