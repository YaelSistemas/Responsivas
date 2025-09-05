<x-app-layout title="Series - {{ $producto->nombre }}">
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight text-center">
      Series — {{ $producto->nombre }}
    </h2>
  </x-slot>

  <style>
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

    /* chips de specs */
    .chips{display:flex;gap:.35rem;flex-wrap:wrap;margin-top:.35rem}
    .chip{font-size:.72rem;border:1px solid #e5e7eb;background:#f3f4f6;color:#374151;border-radius:9999px;padding:.12rem .5rem}
    .badge-mod{font-size:.65rem;background:#fef3c7;border:1px solid #fde68a;color:#92400e;border-radius:9999px;padding:.08rem .4rem}

    /* ===== Modal base ===== */
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

    /* Contenido scrollable dentro del panel (mantiene visibles los botones de abajo) */
    .modal .scroll{overflow:auto;padding-right:4px;margin-bottom:10px}

    /* Galería responsive */
    .gallery{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px}
    .tile{border:1px solid #e5e7eb;border-radius:8px;padding:8px;text-align:center;background:#fff}
    .tile img{width:100%;height:120px;object-fit:cover;border-radius:6px}
    .tile .meta{font-size:12px;color:#374151;margin-top:6px}

    /* Fila de subida */
    .upload-row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
    .upload-row input[type="file"]{flex:1 1 260px;min-width:220px}
    .upload-row input[type="text"]{flex:1 1 260px;min-width:220px}
    .upload-row .btn{flex:0 0 auto}
    .hint{font-size:12px;color:#6b7280;margin-top:6px}
  </style>

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
          <form method="GET" class="flex items-center gap-2">
            <label for="q" class="text-sm text-gray-600">Buscar:</label>
            <input id="q" name="q" value="{{ $q ?? '' }}" class="border rounded px-3 py-1 focus:outline-none" placeholder="Serie...">
          </form>
          <button id="open-bulk" type="button" class="btn btn-primary">Alta masiva</button>
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
              <th style="width:180px">Estado</th>
              <th style="width:140px">Acciones</th>
            </tr>
          </thead>
          <tbody>
            @forelse($series as $s)
              @php $sp = $s->specs; @endphp
              <tr>
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
                      @if(!empty($sp['ram_gb'])) <span class="chip">{{ (int)$sp['ram_gb'] }} GB RAM</span>@endif
                      @php
                        $alm = $sp['almacenamiento'] ?? [];
                        $t = $alm['tipo'] ?? null; $cap = $alm['capacidad_gb'] ?? null;
                      @endphp
                      @if($t || $cap)
                        <span class="chip">{{ strtoupper($t ?? '') }}{{ $t && $cap ? ' ' : '' }}@if($cap) {{ (int)$cap }} GB @endif</span>
                      @endif
                      @if(!empty($sp['color'])) <span class="chip">Color: {{ $sp['color'] }}</span>@endif
                    </div>
                  @endif
                </td>

                <td>
                  <form method="POST" action="{{ route('productos.series.estado', [$producto,$s]) }}">
                    @csrf @method('PUT')
                    <select name="estado" class="border rounded px-2 py-1 text-sm" onchange="this.form.submit()">
                      @foreach(['disponible'=>'Disponible','asignado'=>'Asignado','devuelto'=>'Devuelto','baja'=>'Baja','reparacion'=>'Reparación'] as $val=>$lbl)
                        <option value="{{ $val }}" @selected($s->estado===$val)>{{ $lbl }}</option>
                      @endforeach
                    </select>
                  </form>
                </td>

                <td>
                  <div class="flex items-center gap-3">
                    <a href="{{ route('series.edit', $s) }}" class="text-indigo-600 hover:underline">Editar</a>
                    <a href="#" class="text-blue-600 hover:underline" data-open-fotos="{{ $s->id }}">Fotos ({{ $s->fotos->count() }})</a>
                    @if($s->estado === 'disponible')
                      <form method="POST" action="{{ route('productos.series.destroy', [$producto,$s]) }}"
                            onsubmit="return confirm('¿Eliminar esta serie?');">
                        @csrf @method('DELETE')
                        <button class="text-red-600 hover:text-red-800" type="submit">Eliminar</button>
                      </form>
                    @else
                      <span class="text-gray-400 text-sm">—</span>
                    @endif
                  </div>
                </td>
              </tr>
            @empty
              <tr><td colspan="3" class="text-center text-gray-500 py-6">Sin series.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="mt-4">
        {{ $series->links() }}
      </div>
    </div>
  </div>

  {{-- MODAL: Alta masiva --}}
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

  {{-- MODALES DE FOTOS (uno por serie) --}}
  @foreach($series as $s)
    <div id="fotos-modal-{{ $s->id }}" class="modal" aria-hidden="true">
      <div class="backdrop" data-close="1"></div>
      <div class="panel">
        <button class="close" type="button" aria-label="Cerrar" data-close="1">&times;</button>
        <h3>Fotos — Serie {{ $s->serie }}</h3>

        {{-- Contenido scrollable: galería --}}
        <div class="scroll">
          <div class="gallery">
            @forelse($s->fotos as $f)
              <div class="tile">
                <a href="{{ $f->url }}" target="_blank" rel="noopener">
                  <img src="{{ $f->url }}" alt="foto">
                </a>
                <div class="meta">{{ $f->caption }}</div>
                <form method="POST" action="{{ route('productos.series.fotos.destroy', [$producto,$s,$f]) }}"
                      onsubmit="return confirm('¿Eliminar esta foto?')" style="margin-top:6px">
                  @csrf @method('DELETE')
                  <button class="btn btn-light" type="submit">Eliminar</button>
                </form>
              </div>
            @empty
              <div style="grid-column:1/-1;color:#6b7280;text-align:center">Sin fotos.</div>
            @endforelse
          </div>
        </div>

        {{-- Fila de subida (siempre visible, no se recorta) --}}
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
      </div>
    </div>
  @endforeach

  <script>
    (function(){
      const modal = document.getElementById('bulk-modal');
      const openBtn = document.getElementById('open-bulk');
      function openModal(){ modal.classList.add('open'); setTimeout(()=> modal.querySelector('textarea')?.focus(), 30); }
      function closeModal(){ modal.classList.remove('open'); }
      openBtn?.addEventListener('click', openModal);
      modal?.addEventListener('click', (e)=>{ if(e.target.dataset.close) closeModal(); });
      document.addEventListener('keydown', (e)=>{ if(e.key==='Escape' && modal.classList.contains('open')) closeModal(); });
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
</x-app-layout>
