@php
  $puedeAdjuntar = auth()->user()->hasAnyRole(['Administrador','Compras Superior']) || auth()->user()->can('oc.edit');
@endphp

<div class="oc-modal-backdrop" data-oc-modal>
  <div class="oc-modal">
    <div class="oc-modal-header">
      <h3>Adjuntos — OC {{ $oc->numero_orden }}</h3>
      <button class="oc-modal-close" title="Cerrar">✕</button>
    </div>

    <div class="oc-modal-body">
      @if($oc->adjuntos->isEmpty())
        <div class="oc-empty text-center text-gray-500 py-6">Aún no hay archivos.</div>
      @else
        <div class="oc-grid">
          @foreach($oc->adjuntos as $adj)
            <div class="oc-card">
              @php
                $ext = strtolower(pathinfo($adj->original_name, PATHINFO_EXTENSION));
                $isImg = in_array($ext, ['jpg','jpeg','png']);
              @endphp

              <div class="oc-card-preview">
                @if($isImg)
                  <img src="{{ $adj->url() }}" alt="{{ $adj->original_name }}">
                @else
                  <div class="oc-file-icon">{{ strtoupper($ext) }}</div>
                @endif
              </div>

              <div class="oc-card-name" title="{{ $adj->original_name }}">{{ $adj->original_name }}</div>
              @if($adj->nota)
                <div class="oc-card-note" title="{{ $adj->nota }}">{{ $adj->nota }}</div>
              @endif

              <div class="oc-card-actions">
                <a class="btn btn-sm" href="{{ route('oc.adjuntos.download', $adj) }}">Descargar</a>
                @if($puedeAdjuntar)
                  <button type="button" class="btn btn-sm danger" data-delete="{{ route('oc.adjuntos.destroy', $adj) }}">Eliminar</button>
                @endif
              </div>
            </div>
          @endforeach
        </div>
      @endif
    </div>

    @if($puedeAdjuntar)
    <form class="oc-upload" method="POST" enctype="multipart/form-data" action="{{ route('oc.adjuntos.store', $oc) }}">
      @csrf
      <input type="file" name="files[]" multiple class="oc-file" accept=".pdf,.xml,.jpg,.jpeg,.png,.zip">
      <input type="text" name="nota" class="oc-note" placeholder="Nota / descripción (opcional)">
      <button type="submit" class="btn btn-primary">Subir</button>
    </form>
    @endif
  </div>
</div>

<style>
:root{ --app-zoom: 1; } /* usado sólo por este modal */

.oc-modal-backdrop{
  position:fixed; inset:0; background:rgba(0,0,0,.45);
  display:flex; align-items:center; justify-content:center; z-index:1000;
}

.oc-modal{
  background:#fff; width:min(960px,94vw); border-radius:14px;
  box-shadow:0 20px 60px rgba(0,0,0,.25); overflow:hidden;
  transform: scale(var(--app-zoom));
  transform-origin: center center;
}

.oc-modal-header{display:flex;align-items:center;justify-content:space-between;padding:.9rem 1rem;border-bottom:1px solid #e5e7eb}
.oc-modal-header h3{font-weight:700}
.oc-modal-close{background:#f3f4f6;border:1px solid #e5e7eb;border-radius:8px;padding:.25rem .5rem}
.oc-modal-close:hover{background:#e5e7eb}
.oc-modal-body{padding:1rem;max-height:60vh;overflow:auto}
.oc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:.75rem}
.oc-card{border:1px solid #e5e7eb;border-radius:12px;padding:.6rem;display:flex;flex-direction:column;gap:.4rem}
.oc-card-preview{height:110px;background:#f9fafb;border:1px dashed #e5e7eb;border-radius:10px;display:flex;align-items:center;justify-content:center;overflow:hidden}
.oc-card-preview img{max-width:100%;max-height:100%;display:block}
.oc-file-icon{font-weight:800;color:#334155}
.oc-card-name{font-size:.85rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.oc-card-note{font-size:.75rem;color:#6b7280;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.oc-card-actions{display:flex;gap:.4rem;justify-content:space-between}

/* ====== SCOPE: SOLO dentro del modal ====== */
[data-oc-modal] .btn{display:inline-flex;align-items:center;gap:.25rem;padding:.3rem .6rem;border-radius:8px;border:1px solid #e5e7eb;background:#f9fafb}
[data-oc-modal] .btn:hover{background:#eef2ff;border-color:#c7d2fe}
[data-oc-modal] .btn-sm{padding:.2rem .45rem;font-size:.8rem}
[data-oc-modal] .btn-primary{background:#2563eb;color:#fff;border-color:#1e4ed8}
[data-oc-modal] .btn-primary:hover{background:#1e4ed8}
[data-oc-modal] .btn.danger{background:#fef2f2;color:#991b1b;border-color:#fecaca}
[data-oc-modal] .btn.danger:hover{background:#fee2e2}
[data-oc-modal] .oc-upload{display:flex;gap:.5rem;align-items:center;padding:0 1rem 1rem 1rem;border-top:1px solid #e5e7eb}
[data-oc-modal] .oc-upload .oc-note{flex:1;border:1px solid #e5e7eb;border-radius:8px;padding:.4rem .6rem}
</style>

<script>
(function(){
  const modal = document.querySelector('[data-oc-modal]');
  if(!modal) return;

  // Bloquear scroll y compensar la barra usando :root (--sbw)
  if (!document.body.classList.contains('modal-open')) {
    document.body.classList.add('modal-open');
    const sbw = window.innerWidth - document.documentElement.clientWidth;
    document.documentElement.style.setProperty('--sbw', sbw + 'px');
  }

  const onResize = () => {
    const sbw = window.innerWidth - document.documentElement.clientWidth;
    document.documentElement.style.setProperty('--sbw', sbw + 'px');
  };
  window.addEventListener('resize', onResize);

  const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

  function toast(msg, ok=true){
    const el = document.createElement('div');
    el.textContent = msg;
    el.style.cssText =
      "position:fixed;right:16px;bottom:16px;padding:.6rem .9rem;border-radius:8px;font-weight:600;z-index:9999;"
      + (ok ? "background:#dcfce7;color:#166534;border:1px solid #bbf7d0"
            : "background:#fee2e2;color:#991b1b;border:1px solid #fecaca");
    document.body.appendChild(el);
    setTimeout(()=>{el.style.opacity='0';el.style.transition='opacity .25s';setTimeout(()=>el.remove(),260)},1400);
  }

  function grid(){ return modal.querySelector('.oc-grid'); }
  function emptyMsg(){ return modal.querySelector('.oc-empty'); }

  function ensureGrid(){
    if(grid()) return grid();
    if(emptyMsg()) emptyMsg().remove();
    const g = document.createElement('div');
    g.className = 'oc-grid';
    modal.querySelector('.oc-modal-body').appendChild(g);
    return g;
  }

  function updateClipCount(n){
    const cell = document.querySelector(`tr[data-row-id='{{ $oc->id }}'] td.factura`);
    if(!cell) return;
    const clip = cell.querySelector('.clip-btn');
    if(!clip) return;

    const num = Number(n);
    let span = clip.querySelector('.clip-count');
    if(num > 0){
      clip.classList.add('has-adj'); clip.classList.remove('no-adj');
      if(!span){ span = document.createElement('span'); span.className='clip-count'; clip.appendChild(span); }
      span.textContent = num;
      clip.title = 'Ver adjuntos';
    }else{
      clip.classList.remove('has-adj'); clip.classList.add('no-adj');
      if(span) span.remove();
      clip.title = 'Sin archivos adjuntos (clic para agregar)';
    }
  }

  function recountFromDom(){
    const n = modal.querySelectorAll('.oc-card').length;
    updateClipCount(n);
    if(n === 0){
      if(grid()) grid().remove();
      const empty = document.createElement('div');
      empty.className = 'oc-empty text-center text-gray-500 py-6';
      empty.textContent = 'Aún no hay archivos.';
      modal.querySelector('.oc-modal-body').appendChild(empty);
    }
    return n;
  }

  function closeModal(){
    document.body.classList.remove('modal-open');
    document.documentElement.style.removeProperty('--sbw');
    window.removeEventListener('resize', onResize);
    // Nota: el wrapper .oc-mount será eliminado por el código del index
  }

  modal.addEventListener('click', (e)=>{
    if(e.target.classList.contains('oc-modal-close') || e.target === modal){
      closeModal();
      const mount = document.querySelector('.oc-mount');
      if (mount) mount.remove();
    }
  });

  // Eliminar
  modal.addEventListener('click', async (e)=>{
    const btn = e.target.closest('button[data-delete]');
    if(!btn) return;

    if(!confirm('¿Eliminar adjunto?')) return;

    const url = btn.getAttribute('data-delete');
    const card = btn.closest('.oc-card');

    let res;
    try{
      res = await fetch(url, {
        method: 'DELETE',
        headers: {
          'X-Requested-With':'XMLHttpRequest',
          'X-CSRF-TOKEN': csrf,
          'Accept':'application/json'
        }
      });
    }catch{
      toast('No se pudo contactar al servidor.', false);
      return;
    }

    if(res.ok){
      if(card) card.remove();
      recountFromDom();
      toast('Adjunto eliminado.', true);
    }else{
      let msg = 'Error ' + res.status;
      try{ const data = await res.json(); msg = data?.message || data?.error || data?.msg || msg; }catch{}
      toast('No se pudo eliminar. ' + msg, false);
    }
  });

  // Subir múltiples
  const form = modal.querySelector('.oc-upload');
  if(form){
    form.addEventListener('submit', async (e)=>{
      e.preventDefault();

      const fd = new FormData(form);
      let res, data=null;
      try{
        res = await fetch(form.getAttribute('action'), {
          method: 'POST',
          headers: {
            'X-Requested-With':'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf,
            'Accept':'application/json'
          },
          body: fd
        });
      }catch{
        toast('No se pudo adjuntar.', false);
        return;
      }

      try{ data = await res.json(); }catch{ data=null; }

      if(!(res.ok && data && data.ok)){
        toast((data && (data.message || data.msg)) || 'No se pudo adjuntar.', false);
        return;
      }

      const modalUrl = "{{ route('oc.adjuntos.modal', $oc) }}";
      const tmp = await fetch(modalUrl, { headers:{'X-Requested-With':'XMLHttpRequest'} }).then(r=>r.text());
      const sandbox = document.createElement('div'); sandbox.innerHTML = tmp;
      const newBody = sandbox.querySelector('.oc-modal-body');
      if(newBody){
        document.querySelector('[data-oc-modal] .oc-modal-body').replaceChildren(...Array.from(newBody.childNodes));
      }

      recountFromDom();
      toast(data.msg || 'Adjunto(s) subido(s).', true);
      form.reset();
    });
  }
})();
</script>
