<x-app-layout title="Editar producto">
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight text-center">Editar producto</h2>
  </x-slot>

  <style>
    /* ====== Zoom responsivo: misma UI, solo se “reduce” en pantallas chicas ====== */
    .zoom-outer{ overflow-x:hidden; } /* evita scroll horizontal por el ancho compensado */
    .zoom-inner{
      --zoom: 1;                       /* desktop */
      transform: scale(var(--zoom));
      transform-origin: top left;
      width: calc(100% / var(--zoom)); /* compensa el ancho visual */
    }
    /* Breakpoints (ajústalos si gustas) */
    @media (max-width: 1024px){ .zoom-inner{ --zoom:.95; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }  /* tablets landscape */
    @media (max-width: 768px){  .zoom-inner{ --zoom:.90; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }  /* tablets/phones grandes */
    @media (max-width: 640px){  .zoom-inner{ --zoom:.70; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} } /* phones comunes */
    @media (max-width: 400px){  .zoom-inner{ --zoom:.55; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }  /* phones muy chicos */

    /* iOS: evita auto-zoom al enfocar inputs en móvil */
    @media (max-width: 768px){
      input, select, textarea{ font-size:16px; }
    }

    /* ====== Estilos propios ====== */
    .form-container{max-width:700px;margin:0 auto;background:#fff;padding:24px;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,.1)}
    .form-group{margin-bottom:16px}
    .form-container input,.form-container select,.form-container textarea{width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;font-size:14px}
    .hint{font-size:12px;color:#6b7280;margin-top:6px}
    .err{color:#dc2626;font-size:12px;margin-top:6px}
    .form-buttons{display:flex;justify-content:space-between;padding-top:20px}
    .btn-cancel{background:#dc2626;color:#fff;padding:8px 16px;border:none;border-radius:6px;font-weight:600;text-decoration:none}
    .btn-cancel:hover{background:#b91c1c}
    .btn-save{background:#16a34a;color:#fff;padding:8px 16px;border:none;border-radius:6px;font-weight:600;cursor:pointer}
    .btn-save:hover{background:#15803d}
  </style>

  <!-- Envoltura de zoom -->
  <div class="zoom-outer">
    <div class="zoom-inner">
      <div class="py-6">
        <div class="form-container">
          @if ($errors->any())
            <div style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:8px;padding:12px;margin-bottom:14px;">
              <div style="font-weight:700;margin-bottom:6px;">Se encontraron errores:</div>
              <ul style="margin-left:18px;list-style:disc;">
                @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
              </ul>
            </div>
          @endif

          <form method="POST" action="{{ route('productos.update', $producto) }}">
            @csrf
            @method('PUT')

            {{-- Nombre --}}
            <div class="form-group">
              <label>Nombre <span class="hint">(requerido)</span></label>
              <input name="nombre" value="{{ old('nombre', $producto->nombre) }}" required autofocus maxlength="255">
              @error('nombre') <div class="err">{{ $message }}</div> @enderror
            </div>

            {{-- Marca --}}
            <div class="form-group">
              <label>Marca</label>
              <input name="marca" value="{{ old('marca', $producto->marca) }}" maxlength="100">
              @error('marca') <div class="err">{{ $message }}</div> @enderror
            </div>

            {{-- Modelo --}}
            <div class="form-group">
              <label>Modelo</label>
              <input name="modelo" value="{{ old('modelo', $producto->modelo) }}" maxlength="100">
              @error('modelo') <div class="err">{{ $message }}</div> @enderror
            </div>

            {{-- Tipo / Categoría --}}
            <div class="form-group">
              <label>Tipo</label>
              <select id="tipo" name="tipo" required>
                @php
                  $tipos = [
                    'equipo_pc'  => 'Equipo de Cómputo',
                    'impresora'  => 'Impresora/Multifuncional',
                    'monitor'    => 'Monitor',
                    'pantalla'   => 'Pantalla/TV',
                    'periferico' => 'Periférico',
                    'consumible' => 'Consumible',
                    'otro'       => 'Otro',
                  ];
                @endphp
                @foreach($tipos as $val=>$text)
                  <option value="{{ $val }}" @selected(old('tipo', $producto->tipo)===$val)>{{ $text }}</option>
                @endforeach
              </select>
              @error('tipo') <div class="err">{{ $message }}</div> @enderror
            </div>

            {{-- Tracking (solo visible si tipo = "otro") --}}
            @php $oldTipo = old('tipo', $producto->tipo); @endphp
            <div class="form-group" id="tracking-wrap" style="{{ $oldTipo==='otro' ? '' : 'display:none' }}">
              <label>Tracking</label>
              <select name="tracking" id="tracking" {{ $oldTipo==='otro' ? 'required' : '' }}>
                <option value="serial"   @selected(old('tracking', $producto->tracking)==='serial')>Por número de serie</option>
                <option value="cantidad" @selected(old('tracking', $producto->tracking)==='cantidad')>Por cantidad (stock)</option>
              </select>
              @error('tracking') <div class="err">{{ $message }}</div> @enderror
            </div>

            {{-- SKU (visible si: tipo = consumible  o (tipo=otro y tracking=cantidad) ) --}}
            @php
              $showSku = (old('tipo', $producto->tipo)==='consumible')
                        || (old('tipo', $producto->tipo)==='otro' && old('tracking', $producto->tracking)==='cantidad');
            @endphp
            <div class="form-group" id="sku-wrap" style="{{ $showSku ? '' : 'display:none' }}">
              <label>SKU <span class="hint">(para consumibles/variantes)</span></label>
              <input name="sku" value="{{ old('sku', $producto->sku) }}" maxlength="100">
              @error('sku') <div class="err">{{ $message }}</div> @enderror
            </div>

            {{-- Descripción (solo impresora / periférico / otro) --}}
            @php
              $showDesc = in_array(old('tipo', $producto->tipo), ['impresora','periferico','otro'], true);
            @endphp
            <div class="form-group" id="descripcion-wrap" style="{{ $showDesc ? '' : 'display:none' }}">
              <label>Descripción</label>
              <textarea name="descripcion" rows="3" placeholder="Detalles relevantes...">{{ old('descripcion', $producto->descripcion) }}</textarea>
              @error('descripcion') <div class="err">{{ $message }}</div> @enderror
            </div>

            {{-- Unidad de medida (solo si tracking = cantidad) --}}
            @php $isCantidad = old('tracking', $producto->tracking)==='cantidad'; @endphp
            <div class="form-group" id="um-wrap" style="{{ $isCantidad ? '' : 'display:none' }}">
              <label>Unidad de medida <span class="hint">(solo para stock por cantidad)</span></label>
              <input name="unidad_medida" id="unidad_medida"
                     value="{{ old('unidad_medida', $producto->unidad ?? 'pieza') }}"
                     maxlength="30">
              @error('unidad_medida') <div class="err">{{ $message }}</div> @enderror
            </div>

            {{-- Activo --}}
            <div class="form-group">
              <label>Activo</label>
              <select name="activo">
                <option value="1" @selected(old('activo', $producto->activo))>Sí</option>
                <option value="0" @selected(!old('activo', $producto->activo))>No</option>
              </select>
            </div>

            <div class="form-buttons">
              <a href="{{ route('productos.index') }}" class="btn-cancel">Cancelar</a>
              <button class="btn-save" type="submit">Actualizar</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <script>
  (function(){
    const tipo         = document.getElementById('tipo');
    const tracking     = document.getElementById('tracking');
    const trackingWrap = document.getElementById('tracking-wrap');
    const skuWrap      = document.getElementById('sku-wrap');
    const umWrap       = document.getElementById('um-wrap');
    const descWrap     = document.getElementById('descripcion-wrap');

    // Defaults por tipo (cuando NO es "otro")
    const defaultTracking = {
      consumible: 'cantidad',
      periferico: 'cantidad',
      equipo_pc:  'serial',
      impresora:  'serial'
    };

    function updateSKUVisibility() {
      const t = (tipo.value || '').toLowerCase();
      const showSKU = (t === 'consumible') || (t === 'otro' && tracking?.value === 'cantidad');
      skuWrap.style.display = showSKU ? '' : 'none';
    }

    function updateDescripcionVisibility() {
      const t = (tipo.value || '').toLowerCase();
      const show = (t === 'impresora' || t === 'periferico' || t === 'otro');
      descWrap.style.display = show ? '' : 'none';
    }

    function toggleByTracking() {
      if (!tracking || !tracking.value) {
        umWrap.style.display = 'none';
        updateSKUVisibility();
        return;
      }
      const isCantidad = tracking.value === 'cantidad';
      umWrap.style.display = isCantidad ? '' : 'none';
      updateSKUVisibility();
    }

    function applyByTipo() {
      const t = (tipo.value || '').toLowerCase();
      updateDescripcionVisibility();

      // Tracking solo visible cuando tipo = "otro"
      if (t === 'otro') {
        trackingWrap.style.display = '';
        tracking?.setAttribute('required','required');
      } else {
        trackingWrap.style.display = 'none';
        tracking?.removeAttribute('required');
        if (tracking) tracking.value = defaultTracking[t] || tracking.value || 'serial';
      }

      toggleByTracking(); // Esto también actualiza SKU
    }

    // Listeners
    tipo.addEventListener('change', applyByTipo);
    tracking?.addEventListener('change', toggleByTracking);

    // Estado inicial coherente:
    applyByTipo();
  })();
  </script>
</x-app-layout>
