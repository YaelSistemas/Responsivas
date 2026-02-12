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

    .grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}
    @media (max-width: 720px){ .grid-3{ grid-template-columns:1fr; } }

    /* ===== Almacenamientos repeater ===== */
    .storage-box{ border:1px solid #e5e7eb; border-radius:10px; padding:10px; margin-top:10px; background:#fafafa; }
    .storage-head{ display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:8px; }
    .storage-title{ font-weight:700; color:#111827; font-size:13px; }
    .btn-mini{ padding:6px 10px; font-size:12px; border-radius:8px; border:0; cursor:pointer; font-weight:700; }
    .btn-mini-add{ background:#e5f9ef; color:#166534; }
    .btn-mini-add:hover{ background:#d1fae5; }
    .btn-mini-del{ background:#fee2e2; color:#991b1b; }
    .btn-mini-del:hover{ background:#fecaca; }
    .storage-row{ border:1px dashed #e5e7eb; border-radius:10px; padding:10px; margin-bottom:10px; background:#fff; }
    .storage-row:last-child{ margin-bottom:0; }

    /* ===== Serie Cards (borde más negro + acordeón libre) ===== */
    .serie-row{ border:1px solid #9ca3af; border-radius:12px; padding:12px; margin-bottom:12px; background:#fff; }
    .serie-header{ display:flex; align-items:center; justify-content:space-between; gap:12px; cursor:pointer; user-select:none; padding:10px 10px; 
      border-radius:10px; background:#f9fafb; border:1px solid #e5e7eb; }
    .serie-title{ font-weight:800; color:#111827; }
    .serie-subtitle{ font-size:12px; color:#6b7280; margin-top:2px; }
    .serie-left{ display:flex; flex-direction:column; gap:2px; min-width:0; }
    .serie-actions{ display:flex; align-items:center; gap:8px; flex-shrink:0; }
    .serie-toggle{ font-weight:900; color:#111827; font-size:16px; line-height:1; padding:4px 10px; border-radius:8px; border:1px solid #e5e7eb; background:#fff; }
    .serie-body{ margin-top:12px; }
    .serie-row.is-collapsed .serie-body{ display:none; }
    .serie-row.has-error .serie-header{ border-color:#ef4444 !important; box-shadow:0 0 0 2px rgba(239,68,68,.15); }
    
    /* ✅ FIX: no aplicar estilos de input normal a checkbox/radio */
    .form-container input[type="checkbox"],
    .form-container input[type="radio"]{ width: auto !important; padding: 0 !important; border: 0 !important; border-radius: 0 !important; 
      box-shadow: none !important; appearance: auto !important; -webkit-appearance: auto !important; accent-color: #2563eb; }
    
    /* ✅ bloque accesorios: 4 en línea, responsivo */
    .acc-grid{ display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; align-items: center; }
      @media (max-width: 720px){
    .acc-grid{ grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .acc-item{ display: flex; align-items: center; gap: 8px; margin: 0; font-size: 14px; color: #111827; user-select: none; }
    .acc-item input[type="checkbox"]{ width: 16px !important; height: 16px !important; }
  </style>

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

            <div class="form-group">
              <label>Tipo</label>
              <select id="tipo" name="tipo" required>
                <option value="" disabled {{ old('tipo') ? '' : 'selected' }}>Selecciona un tipo…</option>
                @php
                  $tipos = [
                    'equipo_pc'  => 'Equipo de Cómputo',
                    'impresora'  => 'Impresora/Multifuncional',
                    'celular'   => 'Celular/Teléfono',
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

            <div class="form-group" id="tracking-wrap" style="{{ old('tipo')==='otro' ? '' : 'display:none' }}">
              <label>Tracking</label>
              <select name="tracking" id="tracking" {{ old('tipo')==='otro' ? 'required' : '' }}>
                <option value="" disabled {{ old('tracking') ? '' : 'selected' }}>Selecciona tracking…</option>
                <option value="serial"   @selected(old('tracking')==='serial')>Por número de serie</option>
                <option value="cantidad" @selected(old('tracking')==='cantidad')>Por cantidad (stock)</option>
              </select>
              @error('tracking') <div class="err">{{ $message }}</div> @enderror
            </div>

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

            <div class="form-group" id="sku-wrap" style="{{ (old('tipo')==='consumible' || (old('tipo')==='otro' && old('tracking')==='cantidad')) ? '' : 'display:none' }}">
              <label>SKU (p.ej. consumibles/variantes)</label>
              <input name="sku" value="{{ old('sku') }}">
              @error('sku') <div class="err">{{ $message }}</div> @enderror
            </div>

            <div class="form-group" id="descripcion-wrap" style="{{ (old('tipo')==='consumible') ? '' : 'display:none' }}">
              <label>Descripción</label>
              <textarea id="descripcion" name="descripcion" rows="3" placeholder="Detalles relevantes…">{{ old('descripcion') }}</textarea>
              @error('descripcion') <div class="err">{{ $message }}</div> @enderror
            </div>

            <div class="form-group" id="um-wrap" style="{{ old('tracking')==='cantidad' ? '' : 'display:none' }}">
              <label>Unidad de medida</label>
              <input name="unidad_medida" value="{{ old('unidad_medida','pieza') }}">
              @error('unidad_medida') <div class="err">{{ $message }}</div> @enderror
            </div>

            <div id="serial-wrap" style="{{ old('tracking')==='serial' ? '' : 'display:none' }}">
              <div class="form-group">
                <label>Series</label>

                <div id="seriesRows"></div>

                <button type="button" id="addSerieBtn" class="btn-save" style="margin-top:10px;padding:6px 12px;">
                  + Agregar serie
                </button>

                <div class="hint" style="margin-top:10px;">
                  Agrega una fila por cada serie y selecciona su subsidiaria y unidad de servicio.
                </div>

                @error('series') <div class="err">{{ $message }}</div> @enderror
                @error('series.*.serie') <div class="err">{{ $message }}</div> @enderror
                @error('series.*.subsidiaria_id') <div class="err">{{ $message }}</div> @enderror
                @error('series.*.unidad_servicio_id') <div class="err">{{ $message }}</div> @enderror
              </div>

              <template id="serieRowTpl">
                <div class="serie-row" data-serie-row data-serie-index="__i__">
                  <div class="serie-header" data-serie-toggle>
                    <div class="serie-left">
                      <div class="serie-title" data-serie-title>Serie __n__</div>
                      <div class="serie-subtitle" data-serie-preview>Captura la información de esta serie</div>
                    </div>
                    <div class="serie-actions">
                      <span class="serie-toggle" data-serie-chevron>▾</span>
                    </div>
                  </div>

                  <div class="serie-body" data-serie-body>
                    <div style="font-weight:700; margin:12px 0 10px; color:#111827;">Series + Subsidiaria + Unidad de servicio</div>

                    <div class="grid-3" style="align-items:end;">
                      <div>
                        <label style="font-size:12px;color:#374151;">Serie</label>
                        <input type="text" name="series[__i__][serie]" placeholder="Ej. ABC123" required data-serie-input>
                      </div>

                      <div>
                        <label style="font-size:12px;color:#374151;">Subsidiaria</label>
                        <select name="series[__i__][subsidiaria_id]">
                          <option value="">— Sin subsidiaria —</option>
                          @foreach($subsidiarias as $s)
                            <option value="{{ $s->id }}">{{ $s->nombre }}</option>
                          @endforeach
                        </select>
                      </div>

                      <div>
                        <label style="font-size:12px;color:#374151;">Unidad de servicio</label>
                        <select name="series[__i__][unidad_servicio_id]">
                          <option value="">— Sin unidad —</option>
                          @foreach($unidadesServicio as $u)
                            <option value="{{ $u->id }}">{{ $u->nombre }}</option>
                          @endforeach
                        </select>
                      </div>
                    </div>

                    <div class="spec-pc" style="display:none; margin-top:12px;">
                      <div style="font-weight:700;margin:6px 0 8px;">Especificaciones (por serie) - Equipo de cómputo</div>

                      <div class="grid-2">
                        <div>
                          <label>Descripción o Color</label>
                          <input name="series[__i__][spec_pc][color]" placeholder="Ej. Negro / Gris">
                        </div>

                        <div>
                          <label>RAM (GB)</label>
                          <input type="number" min="1" name="series[__i__][spec_pc][ram_gb]">
                        </div>

                        <div style="grid-column:1/-1">
                          <div class="storage-box" data-storage-box>
                            <div class="storage-head">
                              <div class="storage-title">Almacenamientos (puedes agregar más de uno)</div>
                              <button type="button" class="btn-mini btn-mini-add" data-add-storage>+ Agregar almacenamiento</button>
                            </div>

                            <div data-storage-rows></div>

                            <template data-storage-tpl>
                              <div class="storage-row">
                                <div class="grid-2" style="align-items:end;">
                                  <div>
                                    <label>Tipo</label>
                                    <select name="series[__i__][spec_pc][almacenamientos][__j__][tipo]">
                                      <option value="">Selecciona…</option>
                                      <option value="ssd">SSD</option>
                                      <option value="hdd">HDD</option>
                                      <option value="m2">M.2</option>
                                    </select>
                                  </div>

                                  <div>
                                    <label>Capacidad (GB)</label>
                                    <input type="number" min="1" name="series[__i__][spec_pc][almacenamientos][__j__][capacidad_gb]" placeholder="Ej. 512">
                                  </div>
                                </div>

                                <div style="display:flex; justify-content:flex-end; margin-top:10px;">
                                  <button type="button" class="btn-mini btn-mini-del" data-del-storage>Quitar</button>
                                </div>
                              </div>
                            </template>

                            <div class="hint" style="margin-top:8px;">
                              Si capturas capacidad, selecciona el tipo (SSD/HDD/M.2).
                            </div>
                          </div>
                        </div>

                        <div class="grid-2" style="grid-column:1/-1">
                          <div>
                            <label>Procesador</label>
                            <input name="series[__i__][spec_pc][procesador]" placeholder="Ej. Intel Core i5-1135G7">
                          </div>

                          <div>
                            <label>Fecha de compra</label>
                            <input type="date" name="series[__i__][spec_pc][fecha_compra]">
                          </div>
                        </div>
                      </div>
                    </div>

                    <div class="spec-cel" style="display:none; margin-top:12px;">
                      <div style="font-weight:700;margin:6px 0 8px;">Especificaciones (por serie) - Celular / Teléfono</div>
                      <div class="grid-2">
                        <div>
                          <label>Descripción o Color</label>
                          <input name="series[__i__][spec_cel][color]" placeholder="Ej. Azul / Negro">
                        </div>

                        <div>
                          <label>Almacenamiento (GB)</label>
                          <input type="number" min="1" name="series[__i__][spec_cel][almacenamiento_gb]">
                        </div>

                        <div>
                          <label>RAM (GB) <span class="hint">(opcional)</span></label>
                          <input type="number" min="1" name="series[__i__][spec_cel][ram_gb]">
                        </div>

                        <div>
                          <label>IMEI</label>
                          <input name="series[__i__][spec_cel][imei]" placeholder="Ej. 356xxxxxxxxxxxxx">
                        </div>
                      
                        <div>
                          <label>Número de celular</label>
                          <input name="series[__i__][spec_cel][numero_celular]" placeholder="Ej. 55 1234 5678">
                        </div>

                        <div>
                          <label>Fecha de compra</label>
                          <input type="date" name="series[__i__][spec_cel][fecha_compra]">
                        </div>

                        <div style="grid-column:1/-1; margin-top:6px;">
                          <div style="font-weight:700; margin:4px 0 6px; color:#111827;">Accesorios</div>

                          <div class="acc-grid">
                            <label class="acc-item">
                              <input type="checkbox" name="series[__i__][spec_cel][accesorios][funda]" value="1">
                              <span>Funda</span>
                            </label>

                            <label class="acc-item">
                              <input type="checkbox" name="series[__i__][spec_cel][accesorios][mica_protectora]" value="1">
                              <span>Mica Protectora</span>
                            </label>

                            <label class="acc-item">
                              <input type="checkbox" name="series[__i__][spec_cel][accesorios][cargador]" value="1">
                              <span>Cargador</span>
                            </label>

                            <label class="acc-item">
                              <input type="checkbox" name="series[__i__][spec_cel][accesorios][cable_usb]" value="1">
                              <span>Cable USB</span>
                            </label>
                          </div>
                        </div>

                      </div>
                    </div>

                    <div class="spec-desc" style="display:none; margin-top:12px;">
                      <div style="font-weight:700;margin:6px 0 8px;">Descripción (por serie)</div>
                      <textarea
                        name="series[__i__][descripcion]"
                        rows="3"
                        placeholder="Detalles relevantes de esta pieza/serie…"
                        style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;font-size:14px"
                      ></textarea>

                      <div style="margin-top:10px;">
                        <label>Fecha de compra</label>
                        <input type="date" name="series[__i__][spec_desc][fecha_compra]">
                      </div>
                    </div>

                    <div style="display:flex; justify-content:flex-end; margin-top:12px;">
                      <button type="button" class="btn-cancel btnRemoveRow" style="padding:6px 12px;">
                        Quitar
                      </button>
                    </div>
                  </div>
                </div>
              </template>
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
    const tipo         = document.getElementById('tipo');
    const tracking     = document.getElementById('tracking');
    const trackingWrap = document.getElementById('tracking-wrap');
    const skuWrap      = document.getElementById('sku-wrap');
    const umWrap       = document.getElementById('um-wrap');
    const serialWrap   = document.getElementById('serial-wrap');
    const cantWrap     = document.getElementById('cantidad-wrap');
    const descWrap     = document.getElementById('descripcion-wrap');
    const colorConsumibleWrap = document.getElementById('color-consumible-wrap');

    const rowsWrap = document.getElementById('seriesRows');
    const addBtn   = document.getElementById('addSerieBtn');
    const tpl      = document.getElementById('serieRowTpl');
    let rowIndex   = 0;

    // ---- Accordion libre ----
    function collapseRow(row){
      row.classList.add('is-collapsed');
      const che = row.querySelector('[data-serie-chevron]');
      if (che) che.textContent = '▸';
    }
    function expandRow(row){
      row.classList.remove('is-collapsed');
      const che = row.querySelector('[data-serie-chevron]');
      if (che) che.textContent = '▾';
    }
    function toggleRow(row){
      if (row.classList.contains('is-collapsed')) expandRow(row);
      else collapseRow(row);
    }

    // ✅ Auto-expande tarjetas con errores al intentar enviar
    function clearSerieErrors() {
      document.querySelectorAll('#seriesRows .serie-row.has-error').forEach(r => r.classList.remove('has-error'));
    }

    function expandSerieContaining(el) {
      const row = el?.closest?.('.serie-row');
      if (!row) return;
      row.classList.add('has-error');
      // expandir solo esa tarjeta (modo libre)
      expandRow(row);
    }

    function setupAutoExpandOnInvalid(formEl) {
      if (!formEl) return;

      // Captura TODOS los invalid (aunque el navegador solo muestre el primero)
      formEl.addEventListener('invalid', (e) => {
        const el = e.target;
        // si el inválido está dentro de una serie, la expandimos
        expandSerieContaining(el);
      }, true); // 👈 capture

      formEl.addEventListener('submit', (e) => {
        clearSerieErrors();

        // si hay inválidos, evitamos submit, expandimos tarjetas y enfocamos el primero
        if (!formEl.checkValidity()) {
          e.preventDefault();

          // fuerza a que el navegador dispare invalid por cada campo
          formEl.reportValidity();

          // focus al primer inválido visible
          const firstInvalid = formEl.querySelector(':invalid');
          if (firstInvalid) {
            expandSerieContaining(firstInvalid);
            setTimeout(() => firstInvalid.focus({ preventScroll: false }), 50);
          }
          return false;
        }
      });
    }

    // ✅ inicializa
    setupAutoExpandOnInvalid(document.getElementById('productoForm'));

    function renumberTitles(){
      const all = Array.from(document.querySelectorAll('#seriesRows [data-serie-row]'));
      all.forEach((row, idx) => {
        const n = idx + 1;
        const title = row.querySelector('[data-serie-title]');
        if (title) title.textContent = `Serie ${n}`;
        row.setAttribute('data-serie-number', String(n));
        updateSeriePreview(row);
      });
    }

    function updateSeriePreview(row){
      const input = row.querySelector('[data-serie-input]');
      const val = (input?.value || '').trim();
      const preview = row.querySelector('[data-serie-preview]');
      if (!preview) return;
      preview.textContent = val ? `Serie capturada: ${val}` : 'Captura la información de esta serie';
    }

    function wireAccordion(row){
      row.querySelector('[data-serie-toggle]')?.addEventListener('click', (e) => {
        e.preventDefault();
        toggleRow(row); // ✅ libre: no afecta a las otras
      });

      row.querySelector('[data-serie-input]')?.addEventListener('input', () => updateSeriePreview(row));
    }

    function applySpecsVisibilityToRow(row) {
      const t = (tipo.value || '').toLowerCase();

      const pc = row.querySelector('.spec-pc');
      const cel = row.querySelector('.spec-cel');
      const desc = row.querySelector('.spec-desc');

      if (pc) pc.style.display = (t === 'equipo_pc') ? '' : 'none';
      if (cel) cel.style.display = (t === 'celular') ? '' : 'none';

      const showDescBySerie = (t === 'impresora' || t === 'monitor' || t === 'pantalla' || t === 'periferico' || t === 'otro');
      if (desc) desc.style.display = showDescBySerie ? '' : 'none';
    }

    // ===== Almacenamientos repeater por fila =====
    function initStorageRepeater(row, iIndex) {
      const box = row.querySelector('[data-storage-box]');
      if (!box) return;

      const rows = box.querySelector('[data-storage-rows]');
      const tpl = box.querySelector('template[data-storage-tpl]');
      const btnAdd = box.querySelector('[data-add-storage]');
      if (!rows || !tpl || !btnAdd) return;

      let j = 0;

      function addStorage(values = {}) {
        const html = tpl.innerHTML
          .replaceAll('__i__', String(iIndex))
          .replaceAll('__j__', String(j));

        const temp = document.createElement('div');
        temp.innerHTML = html;
        const node = temp.firstElementChild;

        const selTipo = node.querySelector(`select[name="series[${iIndex}][spec_pc][almacenamientos][${j}][tipo]"]`);
        const inCap   = node.querySelector(`input[name="series[${iIndex}][spec_pc][almacenamientos][${j}][capacidad_gb]"]`);

        if (selTipo && values.tipo) selTipo.value = String(values.tipo);
        if (inCap && values.capacidad_gb) inCap.value = String(values.capacidad_gb);

        node.querySelector('[data-del-storage]')?.addEventListener('click', () => node.remove());

        rows.appendChild(node);
        j++;
      }

      btnAdd.addEventListener('click', () => addStorage());

      if (rows.children.length === 0) addStorage();
    }

    function addSerieRow(values = {}) {
      if (!rowsWrap || !tpl) return;

      const visibleNumber = rowsWrap.querySelectorAll('[data-serie-row]').length + 1;

      const html = tpl.innerHTML
        .replaceAll('__i__', String(rowIndex))
        .replaceAll('__n__', String(visibleNumber));

      const temp = document.createElement('div');
      temp.innerHTML = html;

      const row = temp.firstElementChild;

      const inputSerie = row.querySelector(`input[name="series[${rowIndex}][serie]"]`);
      const selSubs    = row.querySelector(`select[name="series[${rowIndex}][subsidiaria_id]"]`);
      const selUnidad  = row.querySelector(`select[name="series[${rowIndex}][unidad_servicio_id]"]`);

      if (inputSerie && values.serie) inputSerie.value = values.serie;
      if (selSubs && values.subsidiaria_id) selSubs.value = String(values.subsidiaria_id);
      if (selUnidad && values.unidad_servicio_id) selUnidad.value = String(values.unidad_servicio_id);

      row.querySelector('.btnRemoveRow')?.addEventListener('click', () => {
        row.remove();
        renumberTitles();
      });

      rowsWrap.appendChild(row);

      applySpecsVisibilityToRow(row);
      initStorageRepeater(row, rowIndex);

      wireAccordion(row);
      updateSeriePreview(row);
      renumberTitles();

      // ✅ al agregar: la nueva queda abierta (sin colapsar las demás)
      expandRow(row);

      rowIndex++;
    }

    addBtn?.addEventListener('click', () => addSerieRow());

    function ensureOneRowIfSerial() {
      if (serialWrap.style.display !== 'none' && rowsWrap && rowsWrap.children.length === 0) {
        addSerieRow();
      }
    }

    const defaultTracking = {
      consumible: 'cantidad',
      periferico: 'serial',
      equipo_pc:  'serial',
      impresora:  'serial',
      monitor:    'serial',
      pantalla:   'serial',
      celular:    'serial'
    };

    function resetAll() {
      if (tracking) tracking.value = '';
      trackingWrap.style.display = 'none';
      tracking?.removeAttribute('required');
      skuWrap.style.display   = 'none';
      umWrap.style.display    = 'none';
      serialWrap.style.display= 'none';
      cantWrap.style.display  = 'none';
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
        ensureOneRowIfSerial();
        return;
      }
      const isCantidad = tracking.value === 'cantidad';
      umWrap.style.display     = isCantidad ? '' : 'none';
      cantWrap.style.display   = isCantidad ? '' : 'none';
      serialWrap.style.display = isCantidad ? 'none' : '';
      updateSKUVisibility();
      ensureOneRowIfSerial();
    }

    function applyByTipo() {
      const t = (tipo.value || '').toLowerCase();
      if (!t) { resetAll(); return; }

      const showDescGlobal = (t === 'consumible');
      descWrap.style.display = showDescGlobal ? '' : 'none';

      if (t === 'otro') {
        trackingWrap.style.display = '';
        tracking?.setAttribute('required','required');
      } else {
        trackingWrap.style.display = 'none';
        tracking?.removeAttribute('required');
        if (tracking) tracking.value = defaultTracking[t] || '';
      }

      if (t === 'consumible') colorConsumibleWrap.style.display = '';
      else colorConsumibleWrap.style.display = 'none';

      toggleByTracking();

      document.querySelectorAll('#seriesRows .serie-row').forEach(applySpecsVisibilityToRow);
    }

    tipo.addEventListener('change', applyByTipo);
    tracking?.addEventListener('change', toggleByTracking);

    if ('{{ old('tipo') }}') applyByTipo();
    else resetAll();
  })();

  // === Validar almacenamientos[]: si hay capacidad, tipo requerido (POR SERIE PC) ===
  const form = document.getElementById('productoForm');
  const tipo = document.getElementById('tipo');

  form?.addEventListener('submit', (e) => {
    const tipoProd = (tipo.value || '').toLowerCase();
    if (tipoProd !== 'equipo_pc') return;

    const rows = form.querySelectorAll('#seriesRows .serie-row');
    for (const row of rows) {
      const caps = row.querySelectorAll('[name*="[spec_pc][almacenamientos]"][name$="[capacidad_gb]"]');

      for (const capInput of caps) {
        const cap = parseInt(capInput?.value || 0);
        if (!cap || cap <= 0) continue;

        const tipoName = capInput.name.replace('[capacidad_gb]', '[tipo]');
        const tipoSelect = row.querySelector(`[name="${tipoName}"]`);
        const tipoVal = (tipoSelect?.value || '').trim();

        if (!tipoVal) {
          e.preventDefault();
          alert('En una de las series: debes seleccionar el tipo de almacenamiento (SSD, HDD o M.2) si colocas una capacidad.');
          tipoSelect?.focus();
          return false;
        }
      }
    }
  });
  </script>
</x-app-layout>
