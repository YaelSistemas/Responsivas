<x-app-layout title="Órdenes de compra">
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight text-center">Órdenes de compra</h2>
  </x-slot>

  <style>
    /* ====== Zoom responsivo ====== */
    .zoom-outer{ overflow-x:hidden; }
    .zoom-inner{
      --zoom: .95;
      transform: scale(var(--zoom));
      transform-origin: top left;
      width: calc(100% / var(--zoom));
    }
    @media (max-width:1024px){ .zoom-inner{ --zoom:.95 } .page-wrap{max-width:94vw;padding:0 4vw} }
    @media (max-width:768px){  .zoom-inner{ --zoom:.90 } .page-wrap{max-width:94vw;padding:0 4vw} }
    @media (max-width:640px){  .zoom-inner{ --zoom:.70 } .page-wrap{max-width:94vw;padding:0 4vw} }
    @media (max-width:400px){  .zoom-inner{ --zoom:.55 } .page-wrap{max-width:94vw;padding:0 4vw} }
    @media (max-width:768px){ input,select,textarea{ font-size:16px } }

    /* ==== Evitar “salto” al abrir modal (compensación scrollbar) ==== */
    body.modal-open .zoom-outer { padding-right: var(--sbw, 0); }

    /* ====== Estilos propios ====== */
    .page-wrap{max-width:1900px;margin:0 auto}
    .card{background:#fff;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,.06)}
    .btn{display:inline-block;padding:.45rem .8rem;border-radius:.5rem;font-weight:600;text-decoration:none}
    .btn-primary{background:#2563eb;color:#fff}.btn-primary:hover{background:#1e4ed8}

    /* ====== LISTADO (scopeado a .oc-index) ====== */
    .oc-index .tbl{width:100%;border-collapse:separate;border-spacing:0}
    .oc-index .tbl th,.oc-index .tbl td{
      padding:.70rem .9rem;text-align:center;vertical-align:middle;
      white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
    }
    .oc-index .tbl thead th{font-weight:700;color:#374151;background:#f9fafb;border-bottom:1px solid #e5e7eb}
    .oc-index .tbl tbody tr+tr td{border-top:1px solid #f1f5f9}

    /* Anchos (colgroup definido en el partial) */
    .oc-index .tbl col.c-no    { width:8% }
    .oc-index .tbl col.c-fecha { width:8% }
    .oc-index .tbl col.c-soli  { width:20% }
    .oc-index .tbl col.c-prov  { width:18% }
    .oc-index .tbl col.c-conceptos  { width:18% }
    .oc-index .tbl col.c-desc  { width:22% }
    .oc-index .tbl col.c-monto { width:10% }
    .oc-index .tbl col.c-fact  { width:6%  }
    .oc-index .tbl col.c-creo  { width:10% }
    .oc-index .tbl col.c-edito { width:10% }
    .oc-index .tbl col.c-acc   { width:4%  }

    .oc-index .tbl td.desc{ white-space:normal; overflow:visible; text-overflow:unset; line-height:1.15; }

    /* Toolbar del listado */
    .oc-index #oc-toolbar .select-wrap{position:relative;display:inline-block}
    .oc-index #oc-toolbar select[name="per_page"]{
      -webkit-appearance:none;appearance:none;background-image:none;width:88px;
      padding:6px 28px 6px 10px;height:34px;line-height:1.25;font-size:14px;color:#111827;
      background:#fff;border:1px solid #d1d5db;border-radius:6px;
    }
    .oc-index #oc-toolbar .select-wrap .caret{
      position:absolute;right:10px;top:50%;transform:translateY(-50%);
      pointer-events:none;color:#6b7280;font-size:12px
    }

    /* ===== SELECT como píldora (sin flecha, tamaño fijo) ===== */
    .oc-index .tag-select{
      appearance:none; -webkit-appearance:none; -moz-appearance:none;
      background-image:none;
      display:block; margin:0 auto; box-sizing:border-box;
      border-radius:9999px; cursor:pointer;
      font-size:.75rem; font-weight:600; line-height:1.1;
      width:100px; height:28px;
      padding:.20rem 1.25rem .20rem .60rem;
      text-align-last:center;
      border:1px solid transparent;
    }
    .oc-index .tag-select::-ms-expand{ display:none; }
    .oc-index .tag-select:focus{ outline:none; box-shadow:0 0 0 3px rgba(59,130,246,.25); }

    /* ===== Span de solo lectura con el MISMO tamaño ===== */
    .oc-index .tag-readonly{
      display:inline-block; box-sizing:border-box;
      width:100px; height:28px;
      padding:.20rem 1.25rem .20rem .60rem;
      border-radius:9999px; font-size:.75rem; font-weight:600; line-height:1.1;
      text-align:center; border:1px solid transparent;
    }

    /* ===== Colores (select y span) ===== */
    .oc-index .tag-blue  { background:#e0f2fe; color:#075985; border:1px solid #bae6fd; }
    .oc-index .tag-green { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
    .oc-index .tag-red   { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }

    /* ===== Columna estado compacta/centrada ===== */
    .oc-index .tbl col.c-estado { width: 7% }
    .oc-index .tbl td.estado{
      text-align:center;
      padding-top:.4rem; padding-bottom:.4rem;
      padding-left:.35rem; padding-right:.35rem;
    }

    /* ===== Factura / Clip ===== */
    .oc-index .tbl td.factura{ text-align:center; }
    .oc-index .clip-btn{
      display:inline-flex; align-items:center; justify-content:center;
      gap:.25rem; border-radius:9999px; border:1px solid transparent;
      padding:.25rem .55rem; line-height:1; font-weight:600;
      transition:all .2s ease; background:#f3f4f6; color:#9ca3af; border-color:#e5e7eb;
      cursor:pointer;
    }
    .oc-index .clip-btn .clip-count{ font-size:.8rem; line-height:1; }
    .oc-index .clip-btn.has-adj{
      background:#eef2ff; color:#3730a3; border-color:#c7d2fe;
    }
    .oc-index .clip-btn.has-adj:hover{ background:#e0e7ff; }
    .oc-index .clip-btn.no-adj{ /* gris */ }

    /* Acciones */
    .oc-index .tbl td.actions a,
    .oc-index .tbl td.actions button{
      display:inline-flex; align-items:center; justify-content:center;
      gap:.35rem; padding:.25rem .45rem; border-radius:.375rem;
      text-decoration:none; border:1px solid transparent; background:#f9fafb; color:#1f2937;
    }
    .oc-index .tbl td.actions a:hover,
    .oc-index .tbl td.actions button:hover{ background:#eef2ff; color:#1e40af; }
    .oc-index .tbl td.actions .danger{ background:#fef2f2; color:#991b1b; }
    .oc-index .tbl td.actions .danger:hover{ background:#fee2e2; color:#7f1d1d; }

    /* utilidades SOLO para el index */
    .oc-index .is-disabled{ opacity:.55; cursor:not-allowed; pointer-events:none; }
    .oc-index .sr-only{ position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
    .oc-index .muted{ color:#6b7280; }

    /* Base del modal (por si el modal no trae su <style> embebido) */
    .oc-modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;z-index:1000}
    .oc-modal{background:#fff;width:min(960px,94vw);border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.25);overflow:hidden}
  </style>

  <div class="zoom-outer">
    <div class="zoom-inner">
      <div class="page-wrap py-6 oc-index">  {{-- ← AÑADIDO .oc-index --}}
        {{-- Header --}}
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-xl font-semibold">Órdenes de compra</h2>
          @can('oc.create')
            <a href="{{ route('oc.create') }}" class="btn btn-primary">+ Nueva</a>
          @endcan
        </div>

        {{-- Toolbar --}}
        <form id="oc-toolbar" method="GET" action="{{ route('oc.index') }}" class="mb-3">
          <div class="flex items-center justify-between gap-3">
            <div class="text-sm text-gray-700 flex items-center gap-2">
              <span>Mostrar</span>
              <div class="select-wrap">
                <select name="per_page">
                  @foreach([10,25,50,100] as $n)
                    <option value="{{ $n }}" {{ (int)($perPage ?? 10) === $n ? 'selected' : '' }}>{{ $n }}</option>
                  @endforeach
                </select>
                <span class="caret">▾</span>
              </div>
            </div>

            <div class="text-sm text-gray-700 flex items-center gap-2">
              <label for="q">Buscar:</label>
              <input id="q" name="q" value="{{ $q ?? '' }}" autocomplete="off"
                     class="border rounded px-3 py-1 w-64 focus:outline-none"
                     placeholder="No. orden, solicitante, proveedor o factura">
            </div>
          </div>
        </form>

        {{-- Alerts --}}
        @if (session('created') || session('updated') || session('deleted') || session('error'))
          @php
            $msg = session('created') ? 'Orden creada.'
                 : (session('updated') ? 'Orden actualizada.'
                 : (session('deleted') ? 'Orden eliminada.'
                 : (session('error') ?: '')));
            $cls = session('deleted') ? 'background:#fee2e2;color:#991b1b;border:1px solid #fecaca'
                 : (session('updated') ? 'background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe'
                 : (session('error') ? 'background:#fee2e2;color:#991b1b;border:1px solid #fecaca'
                 : 'background:#dcfce7;color:#166534;border:1px solid #bbf7d0'));
          @endphp
          <div id="alert" style="border-radius:8px;padding:.6rem .9rem; {{ $cls }}" class="mb-4">{{ $msg }}</div>
          <script>
            setTimeout(()=>{
              const a=document.getElementById('alert');
              if(a){a.style.opacity='0';a.style.transition='opacity .4s'; setTimeout(()=>a.remove(),400)}
            },2500);
          </script>
        @endif

        {{-- Tabla (parcial) --}}
        <div class="card">
          <div class="overflow-x-auto" id="oc-wrap">
            @include('oc.partials.table', ['ocs' => $ocs])
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- AJAX: búsqueda / per_page / paginación --}}
  <script>
  (function(){
    const input = document.getElementById('q');
    const perPageSelect = document.querySelector('#oc-toolbar select[name="per_page"]');
    const wrap = document.getElementById('oc-wrap');
    let t, ctl;

    function buildUrl(pageUrl = null){
      const base = pageUrl || "{{ route('oc.index') }}";
      const url = new URL(base, window.location.origin);
      const q = (input?.value || '').trim();
      const per = perPageSelect ? perPageSelect.value : '';
      if(q)  url.searchParams.set('q', q);
      if(per) url.searchParams.set('per_page', per);
      url.searchParams.set('partial', '1');
      return url.toString();
    }

    function wirePagination(){
      wrap.querySelectorAll('.pagination a, nav a').forEach(a=>{
        a.addEventListener('click', function(ev){
          ev.preventDefault();
          ajaxLoad(a.getAttribute('href'));
        });
      });
    }

    function ajaxLoad(pageUrl = null){
      if(ctl) ctl.abort();
      ctl = new AbortController();
      const url = buildUrl(pageUrl);

      if (history.pushState) {
        const pretty = new URL(url);
        pretty.searchParams.delete('partial');
        history.pushState({}, '', pretty.toString());
      }

      fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: ctl.signal })
        .then(r=>r.text())
        .then(html=>{
          wrap.innerHTML = html.trim();
          wirePagination();
        })
        .catch(err=>{ if(err.name!=='AbortError') console.error(err); });
    }

    if(input){
      input.addEventListener('input', ()=>{ clearTimeout(t); t = setTimeout(()=> ajaxLoad(), 300); });
      input.addEventListener('keydown', (e)=>{ if(e.key==='Enter'){ e.preventDefault(); clearTimeout(t); ajaxLoad(); }});
    }
    if(perPageSelect){ perPageSelect.addEventListener('change', ()=> ajaxLoad()); }

    wirePagination();
  })();
  </script>

  {{-- AJAX: cambiar estado --}}
  <script>
    (function(){
      const wrap = document.getElementById('oc-wrap');

      function flash(msg, ok=true){
        const el = document.createElement('div');
        el.textContent = msg;
        el.style.cssText = "position:fixed;right:16px;bottom:16px;padding:.6rem .9rem;border-radius:8px;font-weight:600;z-index:9999;"
          + (ok ? "background:#dcfce7;color:#166534;border:1px solid #bbf7d0"
                : "background:#fee2e2;color:#991b1b;border:1px solid #fecaca");
        document.body.appendChild(el);
        setTimeout(()=>{el.style.opacity='0';el.style.transition='opacity .4s'; setTimeout(()=>el.remove(),400)}, 1800);
      }

      wrap.addEventListener('change', async (ev)=>{
        const sel = ev.target.closest('select[data-estado]');
        if(!sel) return;

        const url = sel.getAttribute('data-url');
        const val = sel.value;

        try{
          const r = await fetch(url, {
            method: 'PATCH',
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
              'Accept': 'application/json',
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({ estado: val })
          });

          if(!r.ok){
            const t = await r.text();
            throw new Error(t || ('HTTP '+r.status));
          }

          const data = await r.json();
          const colorClass = data.class || (val === 'pagada' ? 'tag-green' : (val === 'cancelada' ? 'tag-red' : 'tag-blue'));
          sel.className = 'tag-select ' + colorClass;

          flash(data.msg || 'Estado actualizado.', true);
        }catch(err){
          console.error(err);
          flash('No se pudo actualizar el estado.', false);
        }
      });
    })();
  </script>

  {{-- Abrir MODAL de adjuntos (wrapper .oc-mount) --}}
  <script>
  (function(){
    const wrap = document.getElementById('oc-wrap');

    wrap.addEventListener('click', async (ev)=>{
      const btn = ev.target.closest('button[data-open-adjuntos]');
      if(!btn) return;

      const url = btn.getAttribute('data-open-adjuntos');

      try{
        const res = await fetch(url, { headers:{ 'X-Requested-With':'XMLHttpRequest' } });
        const html = (await res.text()).trim();

        // Monta TODO dentro de un wrapper para poder eliminar estilos/scripts al cerrar
        const tmp = document.createElement('div');
        tmp.innerHTML = html;

        const mount = document.createElement('div');
        mount.className = 'oc-mount';
        Array.from(tmp.childNodes).forEach(n => mount.appendChild(n));
        document.body.appendChild(mount);

        /* Re-ejecutar los <script> embebidos del modal */
        const scripts = mount.querySelectorAll('script');
        scripts.forEach((old) => {
          const s = document.createElement('script');
          if (old.src) s.src = old.src;
          else s.textContent = old.textContent;
          if (old.type) s.type = old.type;
          old.parentNode.replaceChild(s, old);
        });

        // Cerrar al click en backdrop o botón ✕ (y eliminar wrapper completo)
        function onClose(e){
          const backdrop = document.querySelector('.oc-modal-backdrop');
          if(!backdrop) { document.body.removeEventListener('click', onClose); return; }
          const closeBtn = e.target.closest('.oc-modal-close');
          if (e.target === backdrop || closeBtn) {
            const m = document.querySelector('.oc-mount');
            if (m) m.remove();
            document.body.classList.remove('modal-open');
            document.documentElement.style.removeProperty('--sbw');
            document.body.removeEventListener('click', onClose);
          }
        }
        document.body.addEventListener('click', onClose);

      }catch(err){
        console.error(err);
        alert('No se pudo abrir los adjuntos.');
      }
    });
  })();
  </script>

  {{-- Historial (cache off + wrapper + estilo local) --}}
<script>
(function(){
  const wrap = document.getElementById('oc-wrap');

  function openHistModal(html){
    // Wrapper que podremos eliminar completo
    const mount = document.createElement('div');
    mount.className = 'hist-mount';
    // Inyecta un pequeño estilo SOLO para el historial (evita recortes)
    const style = document.createElement('style');
    style.textContent = `
      .hist-mount .tbl th, .hist-mount .tbl td{
        white-space: normal !important;
        overflow: visible !important;
        text-overflow: initial !important;
        vertical-align: top;
      }
    `;
    mount.appendChild(style);

    // Contenido del modal que viene del servidor
    const box = document.createElement('div');
    box.innerHTML = html;
    mount.appendChild(box);

    document.body.appendChild(mount);

    // Cerrar
    const backdrop = mount.querySelector('.modal-backdrop, .oc-modal-backdrop');
    const closeBtn = mount.querySelector('[data-close-modal], .oc-modal-close');

    function close(){ mount.remove(); }

    if (backdrop) backdrop.addEventListener('click', close);
    if (closeBtn) closeBtn.addEventListener('click', close);

    // ESC para cerrar
    document.addEventListener('keydown', function onEsc(e){
      if(e.key === 'Escape'){ close(); document.removeEventListener('keydown', onEsc); }
    });
  }

  wrap.addEventListener('click', async (ev)=>{
    const btn = ev.target.closest('[data-open-historial]');
    if(!btn) return;

    // Cache-busting para ver cambios recientes
    const baseUrl = btn.getAttribute('data-open-historial');
    const url = baseUrl + (baseUrl.includes('?') ? '&' : '?') + '_=' + Date.now();

    try{
      const r = await fetch(url, {
        headers: {
          'X-Requested-With':'XMLHttpRequest',
          'Cache-Control': 'no-cache'
        },
        cache: 'no-store'
      });
      if(!r.ok) throw new Error('HTTP '+r.status);
      const html = await r.text();
      openHistModal(html);
    }catch(e){
      alert('No se pudo abrir el historial.');
      console.error(e);
    }
  });
})();
</script>


  <script>
  // Delegación para modales OC cargados por AJAX (sin cambios)
  (function () {
    document.addEventListener('click', function (e) {
      const closeBtn = e.target.closest('[data-modal-close]');
      if (closeBtn) {
        const backdrop = closeBtn.closest('[data-modal-backdrop]') 
                      || document.querySelector('[data-modal-backdrop]');
        if (backdrop) backdrop.remove();
        return;
      }
      if (e.target.matches('[data-modal-backdrop]')) {
        e.target.remove();
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        document.querySelectorAll('[data-modal-backdrop]').forEach(b => b.remove());
      }
    });
  })();
  </script>

</x-app-layout>
