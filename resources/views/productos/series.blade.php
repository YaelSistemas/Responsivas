@php
  $tituloProd = trim("{$producto->nombre} {$producto->marca} {$producto->modelo}");
@endphp

<x-app-layout title="Series - {{ $tituloProd }}">
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight text-center">
      Series — {{ $tituloProd }}
    </h2>
  </x-slot>


  <style>
    /* ====== Zoom responsivo: MISMA VISTA, solo “más pequeña” en pantallas chicas ====== */
    .zoom-outer{ overflow-x:hidden; } /* evita scroll horizontal por el ancho compensado */
    .zoom-inner{
      --zoom: 1;                       /* desktop */
      transform: scale(var(--zoom));
      transform-origin: top left;
      width: calc(100% / var(--zoom)); /* compensa el ancho visual */
    }
    /* Breakpoints (ajusta si quieres) */
    @media (max-width: 1024px){ .zoom-inner{ --zoom:.95; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }  /* tablets landscape */
    @media (max-width: 768px){  .zoom-inner{ --zoom:.90; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }  /* tablets/phones grandes */
    @media (max-width: 640px){  .zoom-inner{ --zoom:.70; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} } /* phones comunes */
    @media (max-width: 400px){  .zoom-inner{ --zoom:.55; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }  /* phones muy chicos */

    /* iOS: evita auto-zoom al enfocar inputs en móvil */
    @media (max-width:768px){
      input, select, textarea{ font-size:16px; }
    }

    /* ====== Estilos propios ====== */
    .page-wrap{max-width:950px;margin:0 auto}
    .card{background:#fff;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,.06)}
    .btn{display:inline-block;padding:.45rem .8rem;border-radius:.5rem;font-weight:600;text-decoration:none}
    .btn-primary{background:#2563eb;color:#fff}.btn-primary:hover{background:#1e4ed8}
    .btn-light{background:#f3f4f6;border:1px solid #e5e7eb;color:#374151}
    .tbl{width:100%;border-collapse:separate;border-spacing:0}
    .tbl th,.tbl td{padding:.6rem .8rem;vertical-align:middle}
    .tbl thead th{font-weight:700;color:#374151;background:#f9fafb;border-bottom:1px solid #e5e7eb}
    .tbl tbody tr+tr td{border-top:1px solid #f1f5f9}
    .err{color:#dc2626;font-size:12px;margin-top:6px}

    .chips{display:flex;gap:.35rem;flex-wrap:wrap;margin-top:.35rem}
    .chip{font-size:.72rem;border:1px solid #e5e7eb;background:#f3f4f6;color:#374151;border-radius:9999px;padding:.12rem .5rem}
    .badge-mod{font-size:.65rem;background:#fef3c7;border:1px solid #fde68a;color:#92400e;border-radius:9999px;padding:.08rem .4rem}

    .state-wrap{position:relative;display:inline-block}
    .state-select{
      -webkit-appearance:none;appearance:none;
      background:#fff;border:1px solid #d1d5db;border-radius:8px;
      padding:.4rem 2rem .4rem .6rem;
      min-width:160px;font-size:.9rem;line-height:1.25;
    }
    .state-wrap::after{
      content:'▾';position:absolute;right:.55rem;top:50%;transform:translateY(-50%);
      color:#6b7280;pointer-events:none;font-size:.85rem;
    }

    .modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;z-index:50}
    .modal.open{display:flex}
    .modal .backdrop{position:absolute;inset:0;background:rgba(0,0,0,.45)}
    .modal .panel{
      position:relative;background:#fff;border-radius:10px;box-shadow:0 15px 35px rgba(0,0,0,.25);
      width:min(900px,96vw);max-height:90vh;padding:18px;display:flex;flex-direction:column;overflow:hidden
    }
    .modal .panel h3{font-weight:700;font-size:18px;margin:2px 0 10px}
    .modal .close{position:absolute;right:10px;top:10px;font-size:22px;line-height:1;cursor:pointer;color:#6b7280}
    .modal textarea{width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px}
    .modal .actions{display:flex;justify-content:flex-end;gap:10px;margin-top:12px}

    .modal .scroll{overflow:auto;padding-right:4px;margin-bottom:10px}
    .gallery{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px}
    .tile{border:1px solid #e5e7eb;border-radius:8px;padding:8px;text-align:center;background:#fff}
    .tile img{width:100%;height:120px;object-fit:cover;border-radius:6px}
    .tile .meta{font-size:12px;color:#374151;margin-top:6px}

    .upload-row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
    .upload-row input[type="file"]{flex:1 1 260px;min-width:220px}
    .upload-row input[type="text"]{flex:1 1 260px;min-width:220px}
    .upload-row .btn{flex:0 0 auto}
    .hint{font-size:12px;color:#6b7280;margin-top:6px}

    .tbl th:nth-child(2), .tbl td:nth-child(2),
    .tbl th:nth-child(3), .tbl td:nth-child(3),
    .tbl th:nth-child(4), .tbl td:nth-child(4){ text-align:center; }
    .tbl td:nth-child(3) form{display:inline-block;}

      .badge-estado{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:.25rem .75rem;
    border-radius:9999px;
    font-size:.75rem;
    font-weight:600;
    border-width:1px;
    border-style:solid;
  }

  .badge-disponible{
    background:#f3f4f6;   /* gris claro */
    color:#374151;
    border-color:#d1d5db;
  }

  .badge-asignado{
    background:#dcfce7;   /* verde */
    color:#166534;
    border-color:#4ade80;
  }

  .badge-prestamo{
    background:#fef9c3;   /* amarillo pastel */
    color:#92400e;
    border-color:#eab308;
  }

  .badge-devuelto{
    background:#dbeafe;   /* azul claro */
    color:#1d4ed8;
    border-color:#93c5fd;
  }

  .badge-baja{
    background:#fee2e2;   /* rojo claro */
    color:#b91c1c;
    border-color:#fecaca;
  }

  .badge-reparacion{
    background:#ffedd5;   /* naranja */
    color:#c2410c;
    border-color:#fed7aa;
  }

    .loading::after{
      content: '';
      width:14px;height:14px;border:2px solid #cbd5e1;border-top-color:#1d4ed8;border-radius:50%;
      display:inline-block;margin-left:8px;vertical-align:middle;animation:spin .6s linear infinite;
    }
    @keyframes spin{to{transform:rotate(360deg)}}
  </style>

  <!-- Escalamos SOLO el contenido principal.
       Dejamos los modales FUERA del .zoom-inner para que su position:fixed
       siga anclado al viewport y no al contenedor transformado. -->
  <div class="zoom-outer">
    <div class="zoom-inner">
      <div class="page-wrap py-6">
        @if (session('created') || session('updated') || session('deleted') || session('error'))
          @php
            $msg = session('created') ?: (session('updated') ?: (session('deleted') ?: (session('error') ?: '')));
            $cls = session('deleted') ? 'background:#fee2e2;color:#991b1b;border:1px solid #fecaca'
                 : (session('updated') ? 'background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe'
                 : (session('error') ? 'background:#fee2e2;color:#991b1b;border:1px solid #fecaca'
                 : 'background:#dcfce7;color:#166534;border:1px solid #bbf7d0'));
          @endphp
          <div id="alert" style="border-radius:8px;padding:.6rem .9rem; {{ $cls }}" class="mb-4">{{ $msg }}</div>
          <script>setTimeout(()=>{const a=document.getElementById('alert'); if(a){a.style.opacity='0';a.style.transition='opacity .4s'; setTimeout(()=>a.remove(),400)}},2500);</script>
        @endif

        <div class="card p-4 mb-4">
          <div class="flex items-center justify-between gap-3">
            <div class="text-lg font-semibold">Series registradas</div>
            <div class="flex items-center gap-2">
              <form id="search-form" method="GET" class="flex items-center gap-2" onsubmit="return false">
                <label for="q" class="text-sm text-gray-600">Buscar:</label>
                <input id="q" name="q" value="{{ $q ?? '' }}" autocomplete="off"
                       class="border rounded px-3 py-1 focus:outline-none" placeholder="Serie...">
              </form>

              @can('productos.create')
                <button id="open-bulk" type="button" class="btn btn-primary">Alta masiva</button>
              @endcan

              <a href="{{ route('productos.index') }}" class="btn btn-light">Volver a productos</a>
            </div>
          </div>
        </div>

        <div class="card p-4">
          <div class="overflow-x-auto">
            <table class="tbl w-full">
              <thead>
                <tr>
                  <th>Serie</th>
                  <th class="text-center" style="width:130px">Fotos</th>
                  <th class="text-center" style="width:200px">Estado</th>
                  <th class="text-center" style="width:120px">Historial</th>
                  <th class="text-center" style="width:120px">Acciones</th>
                </tr>
              </thead>
              <tbody id="series-tbody">
                @forelse($series as $s)
                  @php $sp = $s->specs; @endphp
                  <tr>
                    {{-- Serie + chips --}}
                    <td>
                      <div class="flex items-center gap-2">
                        <span class="font-mono">{{ $s->serie }}</span>
                        @if(!empty($s->especificaciones))
                          <span class="badge-mod">Mod.</span>
                        @endif
                      </div>

                      @if($producto->tipo === 'equipo_pc' && !empty($sp))
                        <div class="chips">
                          @if(!empty($sp['procesador'])) <span class="chip">{{ $sp['procesador'] }}</span>@endif
                          @if(!empty($sp['ram_gb']))     <span class="chip">{{ (int)$sp['ram_gb'] }} GB RAM</span>@endif
                          @php
                            $alm = $sp['almacenamiento'] ?? [];
                            $t = $alm['tipo'] ?? null; $cap = $alm['capacidad_gb'] ?? null;
                          @endphp
                          @if($t || $cap)
                            <span class="chip">{{ strtoupper($t ?? '') }} @if($cap) {{ (int)$cap }} GB @endif</span>
                          @endif
                          @if(!empty($sp['color'])) <span class="chip">Color: {{ $sp['color'] }}</span>@endif
                        </div>
                      @else
                        @php
                          // 1) override de la serie si existe, 2) cae a la descripción del producto
                          $descSerie = data_get($s->especificaciones, 'descripcion');
                          $descProd  = $producto->descripcion;
                          $desc      = $descSerie ?? $descProd;
                        @endphp
                        @if($desc)
                          <div class="chips">
                            <span class="chip" title="{{ $desc }}">
                              {{ \Illuminate\Support\Str::limit($desc, 70) }}
                            </span>
                          </div>
                        @endif
                      @endif
                    </td>

                    {{-- Fotos --}}
                    <td>
                      <a href="#" class="text-blue-600 hover:underline" data-open-fotos="{{ $s->id }}">
                        Fotos ({{ $s->fotos->count() }})
                      </a>
                    </td>

                    {{-- Estado (solo lectura, calculado) --}}
                    <td class="text-center">
                        @php
                            // Estado técnico que guarda producto_series
                            $estadoRaw = $s->estado; // disponible / asignado / devuelto / baja / reparacion
                            $estado    = $estadoRaw;

                            // Si está asignado, revisamos el historial para ver el estado lógico actual
                            if ($estadoRaw === 'asignado') {

                                // Tomamos el ÚLTIMO registro relevante de estado
                                $ultimoEstado = $s->historial()
                                    ->whereIn('accion', ['asignacion', 'edicion_asignacion'])
                                    ->orderByDesc('id')
                                    ->first();

                                $estadoLogico = $ultimoEstado->estado_nuevo ?? null; // asignado / prestamo_provisional

                                if ($estadoLogico === 'prestamo_provisional') {
                                    $estado = 'prestamo';      // se muestra como “Préstamo”
                                } elseif ($estadoLogico === 'asignado') {
                                    $estado = 'asignado';      // se asegura que vuelva a “Asignado”
                                }
                            }

                            $labels = [
                                'disponible' => 'Disponible',
                                'asignado'   => 'Asignado',
                                'prestamo'   => 'Préstamo',
                                'devuelto'   => 'Devuelto',
                                'baja'       => 'Baja',
                                'reparacion' => 'Reparación',
                            ];

                            $badgeClass = match($estado) {
                                'disponible' => 'badge-disponible',
                                'asignado'   => 'badge-asignado',
                                'prestamo'   => 'badge-prestamo',
                                'devuelto'   => 'badge-devuelto',
                                'baja'       => 'badge-baja',
                                'reparacion' => 'badge-reparacion',
                                default      => 'badge-disponible',
                            };
                        @endphp

                        <span class="badge-estado {{ $badgeClass }}">
                            {{ $labels[$estado] ?? ucfirst($estado) }}
                        </span>
                    </td>

                    {{-- HISTORIAL --}}
                    <td class="text-center">
                      <button type="button"
                              class="text-blue-600 hover:text-blue-800 font-semibold"
                              onclick="openSerieHistorial('{{ $s->id }}')">
                              Historial
                      </button>
                    </td>

                    {{-- Acciones --}}
                    <td>
                      <div class="flex items-center justify-center gap-3">
                        @can('productos.edit')
                          <a href="{{ route('productos.series.edit', [$producto, $s]) }}" 
                          class="text-gray-800 hover:text-gray-900" title="Editar">
                            <i class="fa-solid fa-pen"></i>
                          </a>
                        @endcan

                        @can('productos.delete')
                          @if($s->estado === 'disponible')
                            <form method="POST" action="{{ route('productos.series.destroy', [$producto,$s]) }}"
                                  onsubmit="return confirm('¿Eliminar esta serie?');">
                              @csrf @method('DELETE')
                              <button class="text-red-600 hover:text-red-800" type="submit" title="Eliminar">
                                <i class="fa-solid fa-trash"></i>
                              </button>
                            </form>
                          @endif
                        @endcan
                      </div>
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="4" class="text-center text-gray-500 py-6">Sin series.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>

          <div id="series-pagination" class="mt-4">
            {{ $series->appends(['q' => $q ?? ''])->links() }}
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- MODAL: Alta masiva (solo crear) --}}
  @can('productos.create')
    <div id="bulk-modal" class="modal" aria-hidden="true" role="dialog" aria-modal="true">
      <div class="backdrop" data-close="1"></div>
      <div class="panel">
        <button class="close" type="button" aria-label="Cerrar" data-close="1">&times;</button>
        <h3>Alta masiva de series</h3>
        <form method="POST" action="{{ route('productos.series.store', $producto) }}">
          @csrf
          <textarea name="lotes" rows="8" placeholder="Pega o escribe una serie por línea...">{{ old('lotes') }}</textarea>
          <div class="hint">Se crearán como <b>disponibles</b>. Duplicadas se omiten.</div>
          @error('lotes') <div class="err">{{ $message }}</div> @enderror
          <div class="actions">
            <button type="button" class="btn btn-light" data-close="1">Cancelar</button>
            <button type="submit" class="btn btn-primary">Agregar series</button>
          </div>
        </form>
      </div>
    </div>
  @endcan

  {{-- MODALES DE FOTOS (uno por serie) --}}
  @foreach($series as $s)
    <div id="fotos-modal-{{ $s->id }}" class="modal" aria-hidden="true">
      <div class="backdrop" data-close="1"></div>
      <div class="panel">
        <button class="close" type="button" aria-label="Cerrar" data-close="1">&times;</button>
        <h3>Fotos — Serie {{ $s->serie }}</h3>

        <div class="scroll">
          <div class="gallery">
            @forelse($s->fotos as $f)
              <div class="tile">
                <a href="{{ $f->url }}" target="_blank" rel="noopener">
                  <img src="{{ $f->url }}" alt="foto">
                </a>
                <div class="meta">{{ $f->caption }}</div>

                @can('productos.edit')
                  <form method="POST" action="{{ route('productos.series.fotos.destroy', [$producto,$s,$f]) }}"
                        onsubmit="return confirm('¿Eliminar esta foto?')" style="margin-top:6px">
                    @csrf @method('DELETE')
                    <button class="btn btn-light" type="submit">Eliminar</button>
                  </form>
                @endcan
              </div>
            @empty
              <div style="grid-column:1/-1;color:#6b7280;text-align:center">Sin fotos.</div>
            @endforelse
          </div>
        </div>

        @can('productos.edit')
          <form method="POST" action="{{ route('productos.series.fotos.store', [$producto,$s]) }}"
                enctype="multipart/form-data">
            @csrf
            <div class="upload-row">
              <input type="file" name="imagenes[]" accept="image/*" multiple required>
              <input type="text" name="caption" placeholder="Nota / descripción (opcional)" class="border rounded px-2 py-1">
              <button type="submit" class="btn btn-primary">Subir</button>
            </div>
            <div class="hint">Puedes seleccionar varias imágenes a la vez. Máx. 4MB por archivo.</div>
          </form>
        @endcan
      </div>
    </div>
  @endforeach

  {{-- CONTENEDOR GLOBAL PARA MODAL AJAX DE HISTORIAL --}}
  <div id="ajax-modal-container"></div>

  <script>
    (function(){
      const modal = document.getElementById('bulk-modal');
      const openBtn = document.getElementById('open-bulk');
      function openModal(){ modal.classList.add('open'); setTimeout(()=> modal.querySelector('textarea')?.focus(), 30); }
      function closeModal(){ modal.classList.remove('open'); }
      openBtn?.addEventListener('click', openModal);
      modal?.addEventListener('click', (e)=>{ if(e.target.dataset.close) closeModal(); });
      document.addEventListener('keydown', (e)=>{ if(e.key==='Escape' && modal?.classList.contains('open')) closeModal(); });
      @if ($errors->has('lotes')) openModal(); @endif
    })();
  </script>

  <script>
    (function(){
      document.querySelectorAll('[data-open-fotos]').forEach(a=>{
        a.addEventListener('click', (e)=>{
          e.preventDefault();
          const id = a.getAttribute('data-open-fotos');
          document.getElementById('fotos-modal-'+id)?.classList.add('open');
        });
      });
      document.querySelectorAll('[id^="fotos-modal-"]').forEach(m=>{
        m.addEventListener('click', (e)=>{ if (e.target.dataset.close) m.classList.remove('open'); });
      });
      document.addEventListener('keydown', (e)=>{
        if(e.key==='Escape'){
          document.querySelectorAll('[id^="fotos-modal-"].open').forEach(m=> m.classList.remove('open'));
        }
      });
    })();
  </script>

  <script>
    (function(){
      const input = document.getElementById('q');
      const tbody = document.getElementById('series-tbody');
      const pager = document.getElementById('series-pagination');
      const form  = document.getElementById('search-form');

      form.addEventListener('keydown', (e)=>{ if(e.key === 'Enter') e.preventDefault(); });

      let t=null, controller=null;
      function debounceFetch(){
        clearTimeout(t);
        t = setTimeout(runFetch, 300);
        input.classList.add('loading');
      }

      function buildURL(q, page){
        const url = new URL(window.location.href);
        url.searchParams.set('q', q);
        if(page) url.searchParams.set('page', page); else url.searchParams.delete('page');
        return url;
      }

      async function runFetch(page=null){
        const q = input.value || '';
        const url = buildURL(q, page);

        if(controller) controller.abort();
        controller = new AbortController();

        try{
          const res = await fetch(url.toString(), {
            headers: {'X-Requested-With':'XMLHttpRequest'},
            signal: controller.signal
          });
          const html = await res.text();
          const doc  = new DOMParser().parseFromString(html, 'text/html');

          const newBody = doc.querySelector('#series-tbody');
          const newPager= doc.querySelector('#series-pagination');

          if(newBody) tbody.replaceChildren(...newBody.childNodes);
          if(newPager) pager.replaceChildren(...newPager.childNodes);

          window.history.replaceState({}, '', url.toString());

          rebindFotoModals();
          rebindPaginationLinks();
        }catch(err){
          if(err.name !== 'AbortError'){ console.error(err); }
        }finally{
          input.classList.remove('loading');
        }
      }

      function rebindFotoModals(){
        document.querySelectorAll('[data-open-fotos]').forEach(a=>{
          a.addEventListener('click', (e)=>{
            e.preventDefault();
            const id = a.getAttribute('data-open-fotos');
            document.getElementById('fotos-modal-'+id)?.classList.add('open');
          }, { once:true });
        });
      }

      function rebindPaginationLinks(){
        pager.querySelectorAll('a').forEach(a=>{
          a.addEventListener('click', (e)=>{
            e.preventDefault();
            const u = new URL(a.href);
            const page = u.searchParams.get('page') || null;
            runFetch(page);
          }, { once:true });
        });
      }

      input.addEventListener('input', debounceFetch);
      rebindPaginationLinks();

    })();
  </script>

  <script>
  /* ===========================
    ABRIR MODAL DE HISTORIAL
  =========================== */
  async function openSerieHistorial(id) {
      try {
          const res = await fetch(`/producto-series/${id}/historial`, {
              headers: { 'X-Requested-With': 'XMLHttpRequest' }
          });

          if (!res.ok) throw new Error(`HTTP ${res.status}`);

          const html = await res.text();

          // Si ya existe un modal previo → eliminarlo
          const existing = document.querySelector('[data-modal-backdrop]');
          if (existing) existing.remove();

          // Insertar modal recibido desde AJAX
          document.body.insertAdjacentHTML('beforeend', html);

          // Bloquear scroll del fondo
          document.body.classList.add('modal-open');

      } catch (error) {
          console.error("Error al abrir historial:", error);
          alert("No se pudo abrir el historial de la serie.");
      }
  }

  /* ===========================
    CIERRE GLOBAL DEL MODAL
  =========================== */
  document.addEventListener('click', (e) => {
      const closeBtn = e.target.closest('[data-modal-close]');
      const backdrop = e.target.closest('[data-modal-backdrop]');

      // Clic en la X
      if (closeBtn && backdrop) {
          backdrop.remove();
          document.body.classList.remove('modal-open');
      }

      // Clic en fondo oscuro
      if (backdrop && !e.target.closest('.colab-modal')) {
          backdrop.remove();
          document.body.classList.remove('modal-open');
      }
  });

  // Cerrar con tecla ESC
  document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
          const backdrop = document.querySelector('[data-modal-backdrop]');
          if (backdrop) {
              backdrop.remove();
              document.body.classList.remove('modal-open');
          }
      }
  });
  </script>

</x-app-layout>
