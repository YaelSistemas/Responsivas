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
    @media (max-width:768px){ input,select,textarea{ font-size:16px } } /* iOS anti-zoom */

    /* ====== Estilos propios ====== */
    .page-wrap{max-width:1900px;margin:0 auto}
    .card{background:#fff;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,.06)}
    .btn{display:inline-block;padding:.45rem .8rem;border-radius:.5rem;font-weight:600;text-decoration:none}
    .btn-primary{background:#2563eb;color:#fff}.btn-primary:hover{background:#1e4ed8}

    .tbl{width:100%;border-collapse:separate;border-spacing:0}
    .tbl th,.tbl td{
      padding:.70rem .9rem;text-align:center;vertical-align:middle;
      white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
    }
    .tbl thead th{font-weight:700;color:#374151;background:#f9fafb;border-bottom:1px solid #e5e7eb}
    .tbl tbody tr+tr td{border-top:1px solid #f1f5f9}

    /* Anchos (colgroup definido en el partial) */
    .tbl col.c-no    { width:8% }
    .tbl col.c-fecha { width:8% }
    .tbl col.c-soli  { width:20% }
    .tbl col.c-prov  { width:18% }
    .tbl col.c-conceptos  { width:18% }
    .tbl col.c-desc  { width:22% }
    .tbl col.c-monto { width:10% }
    .tbl col.c-fact  { width:6%  }
    .tbl col.c-creo  { width:10% }
    .tbl col.c-edito { width:10% }
    .tbl col.c-acc   { width:4%  }

    .tbl td.desc{ white-space:normal; overflow:visible; text-overflow:unset; line-height:1.15; }

    /* Toolbar */
    #oc-toolbar .select-wrap{position:relative;display:inline-block}
    #oc-toolbar select[name="per_page"]{
      -webkit-appearance:none;appearance:none;background-image:none;width:88px;
      padding:6px 28px 6px 10px;height:34px;line-height:1.25;font-size:14px;color:#111827;
      background:#fff;border:1px solid #d1d5db;border-radius:6px;
    }
    #oc-toolbar .select-wrap .caret{
      position:absolute;right:10px;top:50%;transform:translateY(-50%);
      pointer-events:none;color:#6b7280;font-size:12px
    }

    /* ===== SELECT como píldora (sin flecha, tamaño fijo) ===== */
    .tag-select{
      appearance:none; -webkit-appearance:none; -moz-appearance:none;
      background-image:none;
      display:block; margin:0 auto; box-sizing:border-box;
      border-radius:9999px; cursor:pointer;
      font-size:.75rem; font-weight:600; line-height:1.1;
      width:100px; height:28px;              /* tamaño fijo UNIFICADO */
      padding:.20rem 1.25rem .20rem .60rem;  /* hueco de “flecha” */
      text-align-last:center;
      border:1px solid transparent;
    }
    .tag-select::-ms-expand{ display:none; }
    .tag-select:focus{ outline:none; box-shadow:0 0 0 3px rgba(59,130,246,.25); }

    /* ===== Span de solo lectura con el MISMO tamaño ===== */
    .tag-readonly{
      display:inline-block; box-sizing:border-box;
      width:100px; height:28px;
      padding:.20rem 1.25rem .20rem .60rem;
      border-radius:9999px; font-size:.75rem; font-weight:600; line-height:1.1;
      text-align:center; border:1px solid transparent;
    }

    /* ===== Colores (select y span) ===== */
    .tag-blue  { background:#e0f2fe; color:#075985; border:1px solid #bae6fd; }  /* Abierta */
    .tag-green { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }  /* Pagada */
    .tag-red   { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }  /* Cancelada */

    /* ===== Columna estado compacta/centrada ===== */
    .tbl col.c-estado { width: 7% }
    .tbl td.estado{
      text-align:center;
      padding-top:.4rem; padding-bottom:.4rem;
      padding-left:.35rem; padding-right:.35rem;
    }
  </style>

  <div class="zoom-outer">
    <div class="zoom-inner">
      <div class="page-wrap py-6">
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

      if (history.pushState) { // URL bonita sin ?partial=1
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

          // Mantiene tamaño fijo y cambia color
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
</x-app-layout>
