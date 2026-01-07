<x-app-layout title="Editar serie {{ $serie->serie }}">
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight text-center">
      Editar características – {{ $producto->nombre }} (Serie: {{ $serie->serie }})
    </h2>
  </x-slot>

  @php
    $eff  = (array) ($serie->specs ?? []);              // Producto + overrides (si tu accessor existe)
    $over = (array) ($serie->especificaciones ?? []);   // Overrides guardados en la serie
  @endphp

  <style>
    .zoom-outer{ overflow-x:hidden; }
    .zoom-inner{ --zoom:1; transform:scale(var(--zoom)); transform-origin:top left; width:calc(100%/var(--zoom)); }
    @media (max-width:1024px){ .zoom-inner{ --zoom:.95; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }
    @media (max-width:768px){  .zoom-inner{ --zoom:.90; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }
    @media (max-width:640px){  .zoom-inner{ --zoom:.70; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }
    @media (max-width:400px){  .zoom-inner{ --zoom:.55; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }
    @media (max-width:768px){ input,select,textarea{ font-size:16px; } }

    .box{max-width:760px;margin:0 auto;background:#fff;padding:24px;border-radius:10px;box-shadow:0 2px 6px rgba(0,0,0,.08)}
    .grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    label{display:block;margin-bottom:6px;color:#111827;font-weight:600}
    .inp{width:100%;padding:8px;border:1px solid #d1d5db;border-radius:8px}
    .hint{font-size:12px;color:#6b7280}
    .err{color:#dc2626;font-size:12px;margin-top:6px}
    .actions{display:flex;justify-content:space-between;gap:10px;margin-top:14px;flex-wrap:wrap}
    .btn-save{background:#16a34a;color:#fff;padding:10px 16px;border:none;border-radius:8px;font-weight:700;cursor:pointer}
    .btn-save:hover{background:#15803d}
    .btn-cancel{background:#f3f4f6;border:1px solid #e5e7eb;color:#374151;padding:10px 16px;border-radius:8px;font-weight:700;text-decoration:none}
  </style>

  <div class="zoom-outer">
    <div class="zoom-inner">
      <div class="py-6">
        <div class="box space-y-4">

          @if ($errors->any())
            <div style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:8px;padding:12px;">
              <b>Revisa los campos:</b>
              <ul style="margin-left:18px;list-style:disc;">
                @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
              </ul>
            </div>
          @endif

          {{-- ✅ IMPORTANTE: usa $producto (no $serie->producto) --}}
          <form id="serieSpecsForm" method="POST" action="{{ route('productos.series.update', [$producto, $serie]) }}">
            @csrf
            @method('PUT')

            <p class="hint">
              Solo cambia lo que <b>difiera</b> del producto base. Si dejas un campo vacío, se usará el valor del producto.
            </p>

            {{-- ✅ Subsidiaria --}}
            <div>
              <label>Subsidiaria</label>
              <select class="inp" name="subsidiaria_id" id="subsidiaria_id">
                <option value="">— Sin subsidiaria —</option>
                @foreach(($subsidiarias ?? []) as $sub)
                  <option value="{{ $sub->id }}" @selected(old('subsidiaria_id', $serie->subsidiaria_id) == $sub->id)>
                    {{ $sub->nombre }}
                  </option>
                @endforeach
              </select>
              @error('subsidiaria_id') <div class="err">{{ $message }}</div> @enderror
              <div class="hint">Esto se guarda directamente en la serie.</div>
            </div>

            {{-- ✅ Unidad de servicio --}}
            <div>
              <label>Unidad de servicio</label>
              <select class="inp" name="unidad_servicio_id" id="unidad_servicio_id">
                <option value="">— Sin unidad de servicio —</option>
                @foreach(($unidadesServicio ?? []) as $u)
                  <option value="{{ $u->id }}" @selected(old('unidad_servicio_id', $serie->unidad_servicio_id) == $u->id)>
                    {{ $u->nombre }}
                  </option>
                @endforeach
              </select>
              @error('unidad_servicio_id') <div class="err">{{ $message }}</div> @enderror
              <div class="hint">Esto se guarda directamente en la serie.</div>
            </div>

            <div class="grid2">
              <div>
                <label>Color</label>
                <input class="inp" id="color" name="spec[color]"
                       value="{{ old('spec.color', data_get($over,'color')) }}"
                       placeholder="{{ data_get($eff,'color') }}">
                @error('spec.color') <div class="err">{{ $message }}</div> @enderror
              </div>

              <div>
                <label>RAM (GB)</label>
                <input class="inp" id="ram" type="number" min="1" step="1" name="spec[ram_gb]"
                       value="{{ old('spec.ram_gb', data_get($over,'ram_gb')) }}"
                       placeholder="{{ data_get($eff,'ram_gb') }}">
                @error('spec.ram_gb') <div class="err">{{ $message }}</div> @enderror
              </div>

              <div>
                <label>Almacenamiento (tipo)</label>
                @php $curTipoOver = old('spec.almacenamiento.tipo', data_get($over,'almacenamiento.tipo')); @endphp
                <select class="inp" id="alm_tipo" name="spec[almacenamiento][tipo]">
                  <option value="">Selecciona…</option>
                  @foreach (['ssd'=>'SSD','hdd'=>'HDD','m2'=>'M.2'] as $k=>$v)
                    <option value="{{ $k }}" @selected($curTipoOver===$k)>{{ $v }}</option>
                  @endforeach
                </select>
                @error('spec.almacenamiento.tipo') <div class="err">{{ $message }}</div> @enderror
              </div>

              <div>
                <label>Almacenamiento (capacidad GB)</label>
                <input class="inp" id="alm_cap" type="number" min="1" step="1"
                       name="spec[almacenamiento][capacidad_gb]"
                       value="{{ old('spec.almacenamiento.capacidad_gb', data_get($over,'almacenamiento.capacidad_gb')) }}"
                       placeholder="{{ data_get($eff,'almacenamiento.capacidad_gb') }}">
                @error('spec.almacenamiento.capacidad_gb') <div class="err">{{ $message }}</div> @enderror
              </div>

              <div style="grid-column:1/-1">
                <label>Procesador</label>
                <input class="inp" id="cpu" name="spec[procesador]"
                       value="{{ old('spec.procesador', data_get($over,'procesador')) }}"
                       placeholder="{{ data_get($eff,'procesador') }}">
                @error('spec.procesador') <div class="err">{{ $message }}</div> @enderror
              </div>
            </div>

            <div class="actions">
              <a href="{{ route('productos.series', $producto) }}" class="btn-cancel">Cancelar</a>
              <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <button type="button" class="btn-cancel" id="btn-clear-all">Limpiar overrides</button>
                <button class="btn-save" type="submit" id="btn-submit">Guardar</button>
              </div>
            </div>
          </form>

          <div class="hint">
            <b>Valores actuales:</b><br>
            Color: {{ data_get($eff,'color') ?: '—' }} |
            RAM: {{ data_get($eff,'ram_gb') ? (int) data_get($eff,'ram_gb').' GB' : '—' }} |
            Almacenamiento: {{ strtoupper((string) data_get($eff,'almacenamiento.tipo')) ?: '—' }}
            {{ data_get($eff,'almacenamiento.capacidad_gb') ? (int) data_get($eff,'almacenamiento.capacidad_gb').' GB' : '' }} |
            CPU: {{ data_get($eff,'procesador') ?: '—' }}
          </div>

        </div>
      </div>
    </div>
  </div>

  <script>
    (function(){
      const form = document.getElementById('serieSpecsForm');
      const btnClearAll = document.getElementById('btn-clear-all');
      const btnSubmit = document.getElementById('btn-submit');

      const fields = ['#subsidiaria_id','#unidad_servicio_id','#color','#ram','#alm_tipo','#alm_cap','#cpu']
        .map(s=>document.querySelector(s)).filter(Boolean);

      (fields.find(el => (el.tagName==='SELECT' ? !el.value : (el.value||'').trim()==='')) || fields[0])?.focus();

      btnClearAll?.addEventListener('click', ()=>{
        fields.forEach(el => { el.value=''; });
        fields[0]?.focus();
      });

      let sending=false;
      form?.addEventListener('submit',(e)=>{
        if(sending){e.preventDefault();return;}
        sending=true;
        btnSubmit.disabled=true;
        btnSubmit.style.opacity=.7;
      });

      document.addEventListener('keydown',(e)=>{
        if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='s'){ e.preventDefault(); btnSubmit?.click(); }
        if(e.key==='Escape'){ e.preventDefault(); window.location.href = "{{ route('productos.series', $producto) }}"; }
      });

      let dirty=false;
      form?.addEventListener('input', ()=> dirty=true);
      window.addEventListener('beforeunload', (e)=>{ if(dirty && !sending){ e.preventDefault(); e.returnValue=''; }});
    })();
  </script>
</x-app-layout>
