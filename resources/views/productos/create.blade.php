<x-app-layout title="Nuevo producto">
  <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight text-center">Crear producto</h2></x-slot>

  <style>
    /* ====== Zoom responsivo: MISMA VISTA, SOLO MÁS “PEQUEÑA” EN MÓVIL ====== */
    .zoom-outer{ overflow-x:hidden; }
    .zoom-inner{
      --zoom: 1;
      transform: scale(var(--zoom));
      transform-origin: top left;
      width: calc(100% / var(--zoom));
    }
    @media (max-width: 1024px){ .zoom-inner{ --zoom:.95; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }
    @media (max-width: 768px){  .zoom-inner{ --zoom:.90; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }
    @media (max-width: 640px){  .zoom-inner{ --zoom:.70; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }
    @media (max-width: 400px){  .zoom-inner{ --zoom:.55; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }

    @media (max-width: 768px){
      input, select, textarea{ font-size:16px; }
    }

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
    .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    @media (max-width: 520px){ .grid-2{ grid-template-columns:1fr; } }
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

          <form id="productoForm" method="POST" action="{{ route('productos.store') }}">
            @csrf

            <div class="form-group">
              <label>Nombre <span class="hint">(requerido)</span></label>
              <input name="nombre" value="{{ old('nombre') }}" required autofocus>
              @error('nombre') <div class="err">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
              <label>Marca</label>
              <input name="marca" value="{{ old('marca') }}">
              @error('marca') <div class="err">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
              <label>Modelo</label>
              <input name="modelo" value="{{ old('modelo') }}">
              @error('modelo') <div class="err">{{ $message }}</div> @enderror
            </div>

            {{-- Tipo (sin valor por defecto) --}}
            <div class="form-group">
              <label>Tipo</label>
              <select id="tipo" name="tipo" required>
                <option value="" disabled {{ old('tipo') ? '' : 'selected' }}>Selecciona un tipo…</option>
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
                  <option value="{{ $val }}" @selected(old('tipo')===$val)>{{ $text }}</option>
                @endforeach
              </select>
              @error('tipo') <div class="err">{{ $message }}</div> @enderror
            </div>

            {{-- Tracking (solo visible si tipo = "otro") --}}
            <div class="form-group" id="tracking-wrap" style="{{ old('tipo')==='otro' ? '' : 'display:none' }}">
              <label>Tracking</label>
              <select name="tracking" id="tracking" {{ old('tipo')==='otro' ? 'required' : '' }}>
                <option value="" disabled {{ old('tracking') ? '' : 'selected' }}>Selecciona tracking…</option>
                <option value="serial"   @selected(old('tracking')==='serial')>Por número de serie</option>
                <option value="cantidad" @selected(old('tracking')==='cantidad')>Por cantidad (stock)</option>
              </select>
              @error('tracking') <div class="err">{{ $message }}</div> @enderror
            </div>

            {{-- Color (solo visible si tipo = consumible) --}}
            <div class="form-group" id="color-consumible-wrap" style="{{ old('tipo')==='consumible' ? '' : 'display:none' }}">
              <label>Color</label>
              <select name="color_consumible" id="color-consumible">
                <option value="" disabled {{ old('color_consumible') ? '' : 'selected' }}>Selecciona color…</option>
                <option value="Black (BK)" @selected(old('color_consumible')==='Black (BK)')>Black (BK)</option>
                <option value="Magenta (M)" @selected(old('color_consumible')==='Magenta (M)')>Magenta (M)</option>
                <option value="Yellow (Y)" @selected(old('color_consumible')==='Yellow (Y)')>Yellow (Y)</option>
                <option value="Cian (C)" @selected(old('color_consumible')==='Cian (C)')>Cian (C)</option>
              </select>
              @error('color_consumible') <div class="err">{{ $message }}</div> @enderror
            </div>

            {{-- SKU (visible si: tipo = consumible  o (tipo=otro y tracking=cantidad) ) --}}
            <div class="form-group" id="sku-wrap" style="{{ (old('tipo')==='consumible' || (old('tipo')==='otro' && old('tracking')==='cantidad')) ? '' : 'display:none' }}">
              <label>SKU (p.ej. consumibles/variantes)</label>
              <input name="sku" value="{{ old('sku') }}">
              @error('sku') <div class="err">{{ $message }}</div> @enderror
            </div>

            {{-- ====== Especificaciones (solo si tipo = equipo_pc) ====== --}}
            <div id="equipo-specs" style="{{ old('tipo')==='equipo_pc' ? '' : 'display:none' }}">
              <div style="font-weight:700;margin:10px 0 6px;">Especificaciones del equipo de cómputo</div>
              <div class="grid-2">
                <div>
                  <label>Descripción o Color</label>
                  <input id="colorInput" name="spec[color]" value="{{ old('spec.color') }}">
                  @error('spec.color') <div class="err">{{ $message }}</div> @enderror
                </div>
                <div>
                  <label>RAM (GB)</label>
                  <input type="number" min="1" name="spec[ram_gb]" value="{{ old('spec.ram_gb') }}">
                  @error('spec.ram_gb') <div class="err">{{ $message }}</div> @enderror
                </div>
                <div>
                  <label>Almacenamiento (tipo)</label>
                  <select name="spec[almacenamiento][tipo]">
                    <option value="">Selecciona…</option>
                    @foreach(['ssd'=>'SSD','hdd'=>'HDD','m2'=>'M.2'] as $k=>$v)
                      <option value="{{ $k }}" @selected(old('spec.almacenamiento.tipo')===$k)>{{ $v }}</option>
                    @endforeach
                  </select>
                  @error('spec.almacenamiento.tipo') <div class="err">{{ $message }}</div> @enderror
                </div>
                <div>
                  <label>Almacenamiento (capacidad GB)</label>
                  <input type="number" min="1" name="spec[almacenamiento][capacidad_gb]" value="{{ old('spec.almacenamiento.capacidad_gb') }}">
                  @error('spec.almacenamiento.capacidad_gb') <div class="err">{{ $message }}</div> @enderror
                </div>
                <div style="grid-column:1/-1">
                  <label>Procesador</label>
                  <input name="spec[procesador]" placeholder="Ej. Intel Core i5-1135G7" value="{{ old('spec.procesador') }}">
                  @error('spec.procesador') <div class="err">{{ $message }}</div> @enderror
                </div>
              </div>
            </div>

            {{-- Descripción (visible impresora | monitor | pantalla | periférico | otro) --}}
            <div class="form-group" id="descripcion-wrap" style="{{ in_array(old('tipo'), ['impresora','monitor','pantalla','periferico','otro']) ? '' : 'display:none' }}">
              <label>Descripción</label>
              <textarea id="descripcion" name="descripcion" rows="3" placeholder="Detalles relevantes…">{{ old('descripcion') }}</textarea>
              @error('descripcion') <div class="err">{{ $message }}</div> @enderror
            </div>

            {{-- Solo para cantidad --}}
            <div class="form-group" id="um-wrap" style="{{ old('tracking')==='cantidad' ? '' : 'display:none' }}">
              <label>Unidad de medida</label>
              <input name="unidad_medida" value="{{ old('unidad_medida','pieza') }}">
              @error('unidad_medida') <div class="err">{{ $message }}</div> @enderror
            </div>

            {{-- Carga inicial SEGÚN tracking --}}
            <div id="serial-wrap" style="{{ old('tracking')==='serial' ? '' : 'display:none' }}">
              <div class="form-group">
                <label>Series (una por línea)</label>
                <textarea name="series_lotes" rows="5" placeholder="Pega o escribe una serie por línea...">{{ old('series_lotes') }}</textarea>
                <div class="hint">Se crearán como <b>disponibles</b>. Duplicadas se omiten.</div>
                @error('series_lotes') <div class="err">{{ $message }}</div> @enderror
              </div>
            </div>

            <div id="cantidad-wrap" style="{{ old('tracking')==='cantidad' ? '' : 'display:none' }}">
              <div class="form-group">
                <label>Stock inicial</label>
                <input type="number" min="0" step="1" name="stock_inicial" value="{{ old('stock_inicial', 0) }}">
                <div class="hint">Se registrará como <b>entrada</b> (motivo: “Carga inicial”).</div>
                @error('stock_inicial') <div class="err">{{ $message }}</div> @enderror
              </div>
            </div>

            <div class="form-buttons">
              <a href="{{ route('productos.index') }}" class="btn-cancel">Cancelar</a>
              <button class="btn-save" type="submit">Crear</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <script>
  (function(){
    const form         = document.getElementById('productoForm');
    const tipo         = document.getElementById('tipo');
    const tracking     = document.getElementById('tracking');
    const trackingWrap = document.getElementById('tracking-wrap');
    const skuWrap      = document.getElementById('sku-wrap');
    const umWrap       = document.getElementById('um-wrap');
    const serialWrap   = document.getElementById('serial-wrap');
    const cantWrap     = document.getElementById('cantidad-wrap');
    const equipoSpecs  = document.getElementById('equipo-specs');
    const descWrap     = document.getElementById('descripcion-wrap');

    const colorInput   = document.getElementById('colorInput');
    const descripcion  = document.getElementById('descripcion');
    const colorConsumibleWrap = document.getElementById('color-consumible-wrap');


    // defaults por tipo (monitor y pantalla como impresora; periférico ahora serial)
    const defaultTracking = {
      consumible: 'cantidad',
      periferico: 'serial',   
      equipo_pc:  'serial',
      impresora:  'serial',
      monitor:    'serial',
      pantalla:   'serial'
    };

    function resetAll() {
      if (tracking) tracking.value = '';
      trackingWrap.style.display = 'none';
      tracking?.removeAttribute('required');
      skuWrap.style.display   = 'none';
      umWrap.style.display    = 'none';
      serialWrap.style.display= 'none';
      cantWrap.style.display  = 'none';
      equipoSpecs.style.display = 'none';
      descWrap.style.display  = 'none';
      colorConsumibleWrap.style.display = 'none';
    }

    function updateSKUVisibility() {
      const t = (tipo.value || '').toLowerCase();
      const showSKU = (t === 'consumible') || (t === 'otro' && tracking?.value === 'cantidad');
      skuWrap.style.display = showSKU ? '' : 'none';
    }

    function toggleByTracking() {
      if (!tracking || !tracking.value) {
        umWrap.style.display = 'none';
        serialWrap.style.display = 'none';
        cantWrap.style.display = 'none';
        updateSKUVisibility();
        return;
      }
      const isCantidad = tracking.value === 'cantidad';
      umWrap.style.display     = isCantidad ? '' : 'none';
      cantWrap.style.display   = isCantidad ? '' : 'none';
      serialWrap.style.display = isCantidad ? 'none' : '';
      updateSKUVisibility();
    }

    function syncColorToDescripcion() {
      const t = (tipo.value || '').toLowerCase();
      if (!descripcion) return;
      if (t === 'equipo_pc') {
        const color = (colorInput?.value || '').trim();
        descripcion.value = color;
      }
    }

    function applyByTipo() {
      const t = (tipo.value || '').toLowerCase();
      if (!t) { resetAll(); return; }

      // Especificaciones solo para Equipo de Cómputo
      equipoSpecs.style.display = (t === 'equipo_pc') ? '' : 'none';

      // Descripción visible para impresora, monitor, pantalla, periférico u "otro"
      const showDesc = (t === 'impresora' || t === 'monitor' || t === 'pantalla' || t === 'periferico' || t === 'otro');
      descWrap.style.display = showDesc ? '' : 'none';

      // Tracking solo visible si tipo = "otro"
      if (t === 'otro') {
        trackingWrap.style.display = '';
        tracking?.setAttribute('required','required');
      } else {
        trackingWrap.style.display = 'none';
        tracking?.removeAttribute('required');
        if (tracking) tracking.value = defaultTracking[t] || '';
      }

      // Campo color solo visible si tipo = "consumible"
      if (t === 'consumible') {
        colorConsumibleWrap.style.display = '';
      } else {
        colorConsumibleWrap.style.display = 'none';
      }

      toggleByTracking();
      syncColorToDescripcion();
    }

    tipo.addEventListener('change', applyByTipo);
    tracking?.addEventListener('change', toggleByTracking);
    colorInput?.addEventListener('input', syncColorToDescripcion);
    form?.addEventListener('submit', syncColorToDescripcion);

    if ('{{ old('tipo') }}') {
      applyByTipo();
    } else {
      resetAll();
      syncColorToDescripcion();
    }
  })();

  // === Validar almacenamiento (tipo requerido si hay capacidad) ===
  form?.addEventListener('submit', (e) => {
    const tipoProd = (tipo.value || '').toLowerCase();
    if (tipoProd === 'equipo_pc' || tipoProd === 'otro') {
      const tipoAlmacen = form.querySelector('[name="spec[almacenamiento][tipo]"]');
      const capAlmacen = form.querySelector('[name="spec[almacenamiento][capacidad_gb]"]');

      const capacidad = parseInt(capAlmacen?.value || 0);
      const tipoSel   = (tipoAlmacen?.value || '').trim();

      // Si hay capacidad pero no se eligió tipo → error
      if (capacidad > 0 && !tipoSel) {
        e.preventDefault();
        alert('Debes seleccionar el tipo de almacenamiento (SSD, HDD o M.2) si colocas una capacidad.');
        tipoAlmacen.focus();
        return false;
      }
    }
  });
  </script>
</x-app-layout>
