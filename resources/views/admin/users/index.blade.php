@extends('layouts.admin')

@section('title', 'Usuarios')

@section('content')
<style>
  /* ====== Zoom responsivo: MISMA VISTA, SOLO MÁS “PEQUEÑA” EN MÓVIL ====== */
  .zoom-outer{ overflow-x:hidden; }
  .zoom-inner{
    --zoom: 1;
    transform: scale(var(--zoom));
    transform-origin: top left;
    width: calc(100% / var(--zoom));
  }
  /* Breakpoints (ajusta si quieres) */
  @media (max-width: 1024px){ .zoom-inner{ --zoom:.95; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }  /* tablets landscape */
  @media (max-width: 768px){  .zoom-inner{ --zoom:.90; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }  /* tablets/phones grandes */
  @media (max-width: 640px){  .zoom-inner{ --zoom:.70; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} } /* phones comunes */
  @media (max-width: 400px){  .zoom-inner{ --zoom:.55; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }  /* phones muy chicos */

  /* iOS: evita auto-zoom al enfocar inputs */
  @media (max-width:768px){ input, select, textarea{ font-size:16px; } }

  /* ====== Estilos ya existentes ====== */
  .page-wrap{max-width:1100px;margin:0 auto}
  .card{background:#fff;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,.06)}
  .btn{display:inline-block;padding:.45rem .8rem;border-radius:.5rem;font-weight:600;text-decoration:none}
  .btn-primary{background:#2563eb;color:#fff}.btn-primary:hover{background:#1e4ed8}
  .chip{display:inline-block;background:#eef2ff;color:#4338ca;border:1px solid #c7d2fe;border-radius:999px;padding:.15rem .5rem;font-size:.78rem;margin:.15rem .25rem 0 0}
  .tbl{width:100%;border-collapse:separate;border-spacing:0}
  .tbl th,.tbl td{padding:.75rem .9rem;text-align:left;vertical-align:middle}
  .tbl thead th{font-weight:700;color:#374151;background:#f9fafb;border-bottom:1px solid #e5e7eb}
  .tbl tbody tr+tr td{border-top:1px solid #f1f5f9}

  /* Toolbar: select con caret consistente */
  #users-toolbar .select-wrap{position:relative;display:inline-block}
  #users-toolbar select[name="per_page"]{
    -webkit-appearance:none; -moz-appearance:none; appearance:none; background-image:none;
    width:88px; padding:6px 28px 6px 10px; height:34px; line-height:1.25; font-size:14px;
    color:#111827; background:#fff; border:1px solid #d1d5db; border-radius:6px;
  }
  #users-toolbar .select-wrap .caret{
    position:absolute; right:10px; top:50%; transform:translateY(-50%);
    pointer-events:none; color:#6b7280; font-size:12px;
  }

  .mono{font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono","Courier New", monospace}
</style>

<!-- Envoltura de zoom (igual que Áreas) -->
<div class="zoom-outer">
  <div class="zoom-inner">

    <div class="page-wrap py-6">
      {{-- Header --}}
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-semibold">Usuarios</h2>
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary">Nuevo usuario</a>
      </div>

      {{-- Toolbar --}}
      <form id="users-toolbar" method="GET" action="{{ route('admin.users.index') }}" class="mb-3">
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
                   class="border rounded px-3 py-1 w-56 focus:outline-none" placeholder="Nombre, correo, rol o empresa">
          </div>
        </div>
      </form>

      {{-- Alerts (unificadas) --}}
      @if (session('success') || session('created') || session('updated') || session('deleted') || session('error'))
        @php
          $msg = session('success') ?: (session('created') ? 'Usuario creado.' : (session('updated') ? 'Usuario actualizado.' : (session('deleted') ? 'Usuario eliminado.' : (session('error') ?: ''))));
          $cls = session('deleted') ? 'background:#fee2e2;color:#991b1b;border:1px solid #fecaca'
               : (session('updated') ? 'background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe'
               : (session('error') ? 'background:#fee2e2;color:#991b1b;border:1px solid #fecaca'
               : 'background:#dcfce7;color:#166534;border:1px solid #bbf7d0'));
        @endphp
        <div id="alert" style="border-radius:8px;padding:.6rem .9rem; {{ $cls }}" class="mb-4">{{ $msg }}</div>
        <script>
          setTimeout(()=>{const a=document.getElementById('alert'); if(a){a.style.opacity='0';a.style.transition='opacity .4s'; setTimeout(()=>a.remove(),400)}},2500);
        </script>
      @endif

      {{-- Tabla --}}
      <div class="card">
        <div class="overflow-x-auto">
          <table class="tbl">
            <thead>
              <tr>
                <th style="width:220px">Nombre</th>
                <th style="width:240px">Correo</th>
                <th style="width:160px">Rol</th>
                <th style="width:160px">Empresa</th>
                <th style="width:100px">Activo</th>
                <th style="width:110px">Acciones</th>
              </tr>
            </thead>
            <tbody id="users-tbody">
              @include('admin.users.partials.tbody', ['users' => $users])
            </tbody>
          </table>
        </div>
      </div>

      {{-- Paginación --}}
      <div id="users-pagination" class="mt-4">
        @include('admin.users.partials.pagination', ['users' => $users])
      </div>
    </div>

  </div>
</div>

{{-- Búsqueda + paginación AJAX (debounce) --}}
<script>
(function(){
  const input = document.getElementById('q');
  const perPageSelect = document.querySelector('#users-toolbar select[name="per_page"]');
  const tbody = document.getElementById('users-tbody');
  const pager = document.getElementById('users-pagination');
  let t, ctl;

  function buildUrl(pageUrl = null){
    const base = pageUrl || "{{ route('admin.users.index') }}";
    const url = new URL(base, window.location.origin);
    const q = (input?.value || '').trim();
    const per = perPageSelect ? perPageSelect.value : '';
    if(q)  url.searchParams.set('q', q);
    if(per) url.searchParams.set('per_page', per);
    return url.toString();
  }

  function wirePagination(){
    pager.querySelectorAll('a').forEach(a=>{
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

    // Actualiza la barra de direcciones (sin recargar)
    if (history.pushState) history.pushState({}, '', url);

    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: ctl.signal })
      .then(r=>r.json())
      .then(({tbody:tbodyHtml, pagination})=>{
        tbody.innerHTML = tbodyHtml;
        pager.innerHTML = pagination;
        wirePagination();
      })
      .catch(err=>{
        if(err.name!=='AbortError') console.error(err);
      });
  }

  // Buscar con debounce
  if(input){
    input.addEventListener('input', ()=>{
      clearTimeout(t);
      t = setTimeout(()=> ajaxLoad(), 300);
    });
    // Enter = buscar inmediato
    input.addEventListener('keydown', (e)=>{ if(e.key==='Enter'){ e.preventDefault(); clearTimeout(t); ajaxLoad(); }});
  }

  // Cambiar "per page" por AJAX
  if(perPageSelect){
    perPageSelect.addEventListener('change', ()=> ajaxLoad());
  }

  // Inicializa paginación (enlaces actuales del SSR)
  wirePagination();
})();
</script>
@endsection
