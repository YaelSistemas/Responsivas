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
        <div class="text-center text-gray-500 py-6">Aún no hay archivos.</div>
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
                  <button class="btn btn-sm danger" data-delete="{{ route('oc.adjuntos.destroy', $adj) }}">Eliminar</button>
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
.oc-modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;z-index:1000}
.oc-modal{background:#fff;width:min(960px,94vw);border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.25);overflow:hidden}
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
.btn{display:inline-flex;align-items:center;gap:.25rem;padding:.3rem .6rem;border-radius:8px;border:1px solid #e5e7eb;background:#f9fafb}
.btn:hover{background:#eef2ff;border-color:#c7d2fe}
.btn-sm{padding:.2rem .45rem;font-size:.8rem}
.btn-primary{background:#2563eb;color:#fff;border-color:#1e4ed8}
.btn-primary:hover{background:#1e4ed8}
.btn.danger{background:#fef2f2;color:#991b1b;border-color:#fecaca}
.btn.danger:hover{background:#fee2e2}
.oc-upload{display:flex;gap:.5rem;align-items:center;padding:0 1rem 1rem 1rem;border-top:1px solid #e5e7eb}
.oc-upload .oc-note{flex:1;border:1px solid #e5e7eb;border-radius:8px;padding:.4rem .6rem}
</style>

<script>
(function(){
  const root = document.currentScript.closest('[data-oc-modal]');

  // Cerrar
  root.querySelector('.oc-modal-close').addEventListener('click', ()=> root.remove());
  root.addEventListener('click', (e)=>{ if(e.target === root) root.remove(); });

  // Eliminar
  root.addEventListener('click', async (e)=>{
    const btn = e.target.closest('button[data-delete]');
    if(!btn) return;
    if(!confirm('¿Eliminar adjunto?')) return;

    const url = btn.getAttribute('data-delete');
    const r = await fetch(url, { method:'DELETE', headers: {'X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').getAttribute('content')}});
    const data = await r.json();

    if(r.ok){
      // refrescar modal
      const modalUrl = "{{ route('oc.adjuntos.modal', $oc) }}";
      const html = await (await fetch(modalUrl, {headers:{'X-Requested-With':'XMLHttpRequest'}})).text();
      root.outerHTML = html; // re-render modal
      // actualizar contador en la tabla
      const cell = document.querySelector(`tr[data-row-id='{{ $oc->id }}'] td.factura`);
      const badge = cell?.querySelector('.adj-badge'); if(badge) badge.textContent = '📎 ' + (data.count ?? 0);
    }else{
      alert('No se pudo eliminar.');
    }
  });

  // Subir múltiple
  const form = root.querySelector('.oc-upload');
  if(form){
    form.addEventListener('submit', async (e)=>{
      e.preventDefault();
      const url = form.getAttribute('action');
      const fd = new FormData(form);
      const r = await fetch(url, { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').getAttribute('content')}, body: fd });
      const data = await r.json();

      if(r.ok){
        // refrescar modal
        const modalUrl = "{{ route('oc.adjuntos.modal', $oc) }}";
        const html = await (await fetch(modalUrl, {headers:{'X-Requested-With':'XMLHttpRequest'}})).text();
        root.outerHTML = html;
        // actualizar contador en la tabla
        const cell = document.querySelector(`tr[data-row-id='{{ $oc->id }}'] td.factura`);
        const badge = cell?.querySelector('.adj-badge'); if(badge) badge.textContent = '📎 ' + (data.count ?? 0);
      }else{
        alert(data?.message || 'No se pudo adjuntar.');
      }
    });
  }
})();
</script>
