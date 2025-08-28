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

    /* Modal */
    .modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;z-index:50}
    .modal.open{display:flex}
    .modal .backdrop{position:absolute;inset:0;background:rgba(0,0,0,.45)}
    .modal .panel{position:relative;background:#fff;width:min(700px,92vw);border-radius:10px;box-shadow:0 15px 35px rgba(0,0,0,.25);padding:18px}
    .modal .panel h3{font-weight:700;font-size:18px;margin:2px 0 10px}
    .modal .close{position:absolute;right:10px;top:10px;font-size:22px;line-height:1;cursor:pointer;color:#6b7280}
    .modal textarea{width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px}
    .modal .actions{display:flex;justify-content:flex-end;gap:10px;margin-top:12px}
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

    {{-- Toolbar: Buscar + Botón modal + Volver --}}
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

    {{-- Tabla --}}
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

                  {{-- chips SOLO para equipo_pc --}}
                  @if($producto->tipo === 'equipo_pc' && !empty($sp))
                    <div class="chips">
                      @if(!empty($sp['procesador']))
                        <span class="chip">{{ $sp['procesador'] }}</span>
                      @endif
                      @if(!empty($sp['ram_gb']))
                        <span class="chip">{{ (int)$sp['ram_gb'] }} GB RAM</span>
                      @endif
                      @php
                        $alm = $sp['almacenamiento'] ?? [];
                        $t = $alm['tipo'] ?? null;
                        $cap = $alm['capacidad_gb'] ?? null;
                      @endphp
                      @if($t || $cap)
                        <span class="chip">
                          {{ strtoupper($t ?? '') }}{{ $t && $cap ? ' ' : '' }}@if($cap) {{ (int)$cap }} GB @endif
                        </span>
                      @endif
                      @if(!empty($sp['color']))
                        <span class="chip">Color: {{ $sp['color'] }}</span>
                      @endif
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
        <div class="hint" style="font-size:12px;color:#6b7280;margin-top:6px">
          Se crearán como <b>disponibles</b>. Duplicadas se omiten.
        </div>
        @error('lotes') <div class="err">{{ $message }}</div> @enderror

        <div class="actions">
          <button type="button" class="btn btn-light" data-close="1">Cancelar</button>
          <button type="submit" class="btn btn-primary">Agregar series</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    (function(){
      const alert = document.getElementById('alert');
      const modal = document.getElementById('bulk-modal');
      const openBtn = document.getElementById('open-bulk');

      function openModal(){
        modal.classList.add('open');
        setTimeout(()=> modal.querySelector('textarea')?.focus(), 30);
      }
      function closeModal(){
        modal.classList.remove('open');
      }

      openBtn?.addEventListener('click', openModal);
      modal?.addEventListener('click', (e)=>{
        if(e.target.dataset.close) closeModal();
      });
      document.addEventListener('keydown', (e)=>{
        if(e.key === 'Escape' && modal.classList.contains('open')) closeModal();
      });

      // Si hubo error de validación en 'lotes', abre el modal automáticamente
      @if ($errors->has('lotes'))
        openModal();
      @endif
    })();
  </script>
</x-app-layout>
