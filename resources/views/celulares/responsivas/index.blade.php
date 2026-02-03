<x-app-layout title="Bitácora de Celulares (Responsivas)">
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight text-center">
      Bitácora de Celulares (Responsivas)
    </h2>
  </x-slot>

  <style>
    /* ====== Zoom responsivo ====== */
    .zoom-outer{ overflow-x:hidden; }
    .zoom-inner{
      --zoom: 1;
      transform: scale(var(--zoom));
      transform-origin: top left;
      width: calc(100% / var(--zoom));
    }
    @media (max-width: 1024px){ .zoom-inner{ --zoom:.95; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }
    @media (max-width: 768px){  .zoom-inner{ --zoom:.90; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }
    @media (max-width: 640px){  .zoom-inner{ --zoom:.60; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }
    @media (max-width: 400px){  .zoom-inner{ --zoom:.55; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }

    @media (max-width: 768px){
      input, select, textarea{ font-size:16px; }
    }

    /* ====== Estilos propios (idénticos a Productos) ====== */
    .page-wrap{max-width:1500px;margin:0 auto}
    .card{background:#fff;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,.06)}
    .btn{display:inline-block;padding:.45rem .8rem;border-radius:.5rem;font-weight:600;text-decoration:none}
    .btn-primary{background:#2563eb;color:#fff}.btn-primary:hover{background:#1e4ed8}
    .tbl{width:100%;border-collapse:separate;border-spacing:0}
    .tbl th,.tbl td{padding:.75rem .9rem;text-align:left;vertical-align:middle}
    .tbl thead th{font-weight:700;color:#374151;background:#f9fafb;border-bottom:1px solid #e5e7eb}
    .tbl tbody tr+tr td{border-top:1px solid #f1f5f9}

    /* Toolbar */
    #cel-toolbar .select-wrap{position:relative;display:inline-block}
    #cel-toolbar select[name="per_page"]{
      -webkit-appearance:none;appearance:none;background-image:none;width:88px;
      padding:6px 28px 6px 10px;height:34px;line-height:1.25;font-size:14px;
      color:#111827;background:#fff;border:1px solid #d1d5db;border-radius:6px;
    }
    #cel-toolbar .select-wrap .caret{
      position:absolute;right:10px;top:50%;transform:translateY(-50%);
      pointer-events:none;color:#6b7280;font-size:12px;
    }

    body.modal-open { overflow: hidden; }
    .badge{display:inline-flex;align-items:center;padding:.1rem .5rem;border-radius:999px;font-size:12px;font-weight:700}
    .badge-blue{background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe}
  </style>

  <div class="zoom-outer">
    <div class="zoom-inner">
      <div class="page-wrap py-6 page-pad">

        {{-- Header --}}
        <div class="flex items-center justify-between mb-4">
          <div>
            <h2 class="text-xl font-semibold">Bitácora de Celulares</h2>
            <div class="text-sm text-gray-600">
              Mostrando solo documentos tipo <span class="badge badge-blue">CEL</span>
            </div>
          </div>

          {{-- Botón crear --}}
          @can('responsivas.create')
            <a href="{{ route('responsivas.create', ['tipo_documento'=>'CEL']) }}" class="btn btn-primary">
              Nueva responsiva
            </a>
          @endcan
        </div>

        {{-- Toolbar --}}
        <form id="cel-toolbar" method="GET" action="{{ route('celulares.responsivas.index') }}" class="mb-3">
          <div class="flex items-center justify-between gap-3">

            <div class="text-sm text-gray-700 flex items-center gap-2">
              <span>Mostrar</span>
              <div class="select-wrap">
                <select name="per_page">
                  @foreach([10,25,50,100] as $n)
                    <option value="{{ $n }}" {{ (int)($perPage ?? 50) === $n ? 'selected' : '' }}>
                      {{ $n }}
                    </option>
                  @endforeach
                </select>
                <span class="caret">▾</span>
              </div>
            </div>

            <div class="text-sm text-gray-700 flex items-center gap-2">
              <label for="q">Buscar:</label>
              <input id="q" name="q" value="{{ $q ?? '' }}" autocomplete="off"
                    class="border rounded px-3 py-1 w-56 focus:outline-none"
                    placeholder="Folio, Colaborador, Entregado por">
            </div>

          </div>
        </form>

        @if (session('deleted'))
          <div class="cel-flash" style="margin:12px 0; padding:12px; border-radius:8px; background:#fee2e2; color:#991b1b; border:1px solid #fecaca;">
            {{ session('deleted') }}
          </div>
        @endif

        @if (session('error'))
          <div class="cel-flash" style="margin:12px 0; padding:12px; border-radius:8px; background:#fee2e2; color:#991b1b; border:1px solid #fecaca;">
            {{ session('error') }}
          </div>
        @endif

        <script>
          (function () {
            document.querySelectorAll('.cel-flash').forEach((el) => {
              setTimeout(() => {
                el.style.transition = 'opacity .25s ease';
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 300);
              }, 4000);
            });
          })();
        </script>

        {{-- Tabla principal --}}
        <div class="card">
          <div class="overflow-x-auto" id="cel-wrap">
            @include('celulares.responsivas.partials.table')
          </div>
        </div>

      </div>
    </div>
  </div>

  {{-- Búsqueda, paginación y per_page AJAX (idéntico a Productos) --}}
  <script>
  (function(){
    const input = document.getElementById('q');
    const perPageSelect = document.querySelector('#cel-toolbar select[name="per_page"]');
    const wrap = document.getElementById('cel-wrap');
    let t, ctl;

    function buildUrl(pageUrl = null){
      const base = pageUrl || "{{ route('celulares.responsivas.index') }}";
      const url = new URL(base, window.location.origin);
      const q = (input?.value || '').trim();
      const per = perPageSelect ? perPageSelect.value : '';
      if(q) url.searchParams.set('q', q);
      if(per) url.searchParams.set('per_page', per);
      url.searchParams.set('partial', '1');
      return url.toString();
    }

    function wirePagination(){
      wrap.querySelectorAll('.pagination a, nav a').forEach(a=>{
        a.addEventListener('click', ev=>{
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
      fetch(url, { headers:{'X-Requested-With':'XMLHttpRequest'}, signal:ctl.signal })
        .then(r=>r.text())
        .then(html=>{ wrap.innerHTML = html.trim(); wirePagination(); })
        .catch(err=>{ if(err.name!=='AbortError') console.error(err); });
    }

    if(input){
      input.addEventListener('input', ()=>{ clearTimeout(t); t=setTimeout(()=>ajaxLoad(),300); });
      input.addEventListener('keydown', e=>{ if(e.key==='Enter'){ e.preventDefault(); clearTimeout(t); ajaxLoad(); }});
    }
    if(perPageSelect){ perPageSelect.addEventListener('change', ()=> ajaxLoad()); }
    wirePagination();
  })();
  </script>
</x-app-layout>
