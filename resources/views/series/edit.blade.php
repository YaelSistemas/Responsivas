<x-app-layout title="Editar serie {{ $serie->serie }}">
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight text-center">
      Editar serie – {{ $producto->nombre }} (Serie: {{ $serie->serie }})
    </h2>
  </x-slot>

  @php
    $tipo = (string) ($producto->tipo ?? '');

    // Overrides guardados en serie
    $over = (array) ($serie->especificaciones ?? []);

    // Para mostrar placeholders (valores “efectivos” si tienes accessor $serie->specs)
    $eff  = (array) ($serie->specs ?? []);

    // Descripción por serie: en tu store la guardas en "observaciones"
    // fallback por si vienes de algo anterior
    $descActual = old('descripcion', $serie->observaciones ?? data_get($over, 'descripcion'));

    // Fecha compra (siempre debe existir en UI en el mismo lugar que create)
    $fechaCompraActual = old('fecha_compra', data_get($over,'fecha_compra')); // guardada en especificaciones[fecha_compra]

    // Cel extras
    $numeroCelActual = old('spec_cel.numero_celular', data_get($over,'numero_celular'));

    $acc = old('spec_cel.accesorios', data_get($over,'accesorios', []));
    if (!is_array($acc)) $acc = [];

    // Almacenamientos por serie (PC) (overrides)
    $overStor = old('spec_pc.almacenamientos', data_get($over, 'almacenamientos', []));
    if (!is_array($overStor)) $overStor = [];
    if (count($overStor) === 0) $overStor = [['tipo'=>'','capacidad_gb'=>'']];
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
    label{display:block;margin-bottom:6px;color:#111827;font-weight:700}
    .inp{width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px}
    .hint{font-size:12px;color:#6b7280}
    .err{color:#dc2626;font-size:12px;margin-top:6px}
    .actions{display:flex;justify-content:space-between;gap:10px;margin-top:14px;flex-wrap:wrap}
    .btn-save{background:#16a34a;color:#fff;padding:10px 16px;border:none;border-radius:8px;font-weight:800;cursor:pointer}
    .btn-save:hover{background:#15803d}
    .btn-cancel{background:#f3f4f6;border:1px solid #e5e7eb;color:#374151;padding:10px 16px;border-radius:8px;font-weight:800;text-decoration:none}
    .btn-cancel:hover{background:#e5e7eb}

    /* storage repeater (igual feeling create) */
    .storage-box{ border:1px solid #e5e7eb; border-radius:10px; padding:10px; margin-top:10px; background:#fafafa; }
    .storage-head{ display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:8px; }
    .storage-title{ font-weight:800; color:#111827; font-size:13px; }
    .btn-mini{ padding:6px 10px; font-size:12px; border-radius:8px; border:0; cursor:pointer; font-weight:800; }
    .btn-mini-add{ background:#e5f9ef; color:#166534; }
    .btn-mini-add:hover{ background:#d1fae5; }
    .btn-mini-del{ background:#fee2e2; color:#991b1b; }
    .btn-mini-del:hover{ background:#fecaca; }
    .storage-row{ border:1px dashed #e5e7eb; border-radius:10px; padding:10px; margin-bottom:10px; background:#fff; }
    .storage-row:last-child{ margin-bottom:0; }

    /* accesorios (mejor visual) */
    .acc-wrap{ display:flex; gap:16px; flex-wrap:wrap; align-items:center; margin-top:6px; }
    .acc-item{ display:flex; gap:8px; align-items:center; margin:0; }
    .acc-item input[type="checkbox"]{ width:18px; height:18px; accent-color:#16a34a; }
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

          <form id="serieUnifiedForm" method="POST" action="{{ route('productos.series.update', [$producto, $serie]) }}">
            @csrf
            @method('PUT')

            <p class="hint">
              Solo cambia lo que <b>difiera</b> del producto base. Si dejas un campo vacío, se usará el valor del producto (o quedará sin override).
            </p>

            {{-- ✅ Subsidiaria + Unidad de servicio (MISMA FILA) --}}
            <div class="grid2">
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
              </div>

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
              </div>
            </div>

            {{-- ===================== PC ===================== --}}
            <div id="block-pc" style="{{ $tipo === 'equipo_pc' ? '' : 'display:none' }}">
              <div style="font-weight:900;color:#111827;margin-top:10px;">Especificaciones – Equipo de cómputo</div>

              <div class="grid2">
                <div>
                  <label>Descripción o Color</label>
                  <input class="inp" name="spec_pc[color]"
                         value="{{ old('spec_pc.color', data_get($over,'color')) }}"
                         placeholder="{{ data_get($eff,'color') }}">
                  @error('spec_pc.color') <div class="err">{{ $message }}</div> @enderror
                </div>

                <div>
                  <label>RAM (GB)</label>
                  <input class="inp" type="number" min="1" step="1" name="spec_pc[ram_gb]"
                         value="{{ old('spec_pc.ram_gb', data_get($over,'ram_gb')) }}"
                         placeholder="{{ data_get($eff,'ram_gb') }}">
                  @error('spec_pc.ram_gb') <div class="err">{{ $message }}</div> @enderror
                </div>

                <div style="grid-column:1/-1">
                  <div class="storage-box" data-storage-box>
                    <div class="storage-head">
                      <div class="storage-title">Almacenamientos (puedes agregar más de uno)</div>
                      <button type="button" class="btn-mini btn-mini-add" data-add-storage>+ Agregar almacenamiento</button>
                    </div>

                    <div data-storage-rows>
                      @foreach($overStor as $j => $st)
                        <div class="storage-row" data-storage-row>
                          <div class="grid2" style="align-items:end;">
                            <div>
                              <label>Tipo</label>
                              @php $t = (string) data_get($st,'tipo'); @endphp
                              <select class="inp" name="spec_pc[almacenamientos][{{ $j }}][tipo]">
                                <option value="">Selecciona…</option>
                                <option value="ssd" @selected($t==='ssd')>SSD</option>
                                <option value="hdd" @selected($t==='hdd')>HDD</option>
                                <option value="m2"  @selected($t==='m2')>M.2</option>
                              </select>
                            </div>

                            <div>
                              <label>Capacidad (GB)</label>
                              <input class="inp" type="number" min="1"
                                     name="spec_pc[almacenamientos][{{ $j }}][capacidad_gb]"
                                     value="{{ data_get($st,'capacidad_gb') }}">
                            </div>
                          </div>

                          <div style="display:flex; justify-content:flex-end; margin-top:10px;">
                            <button type="button" class="btn-mini btn-mini-del" data-del-storage>Quitar</button>
                          </div>
                        </div>
                      @endforeach
                    </div>

                    <template data-storage-tpl>
                      <div class="storage-row" data-storage-row>
                        <div class="grid2" style="align-items:end;">
                          <div>
                            <label>Tipo</label>
                            <select class="inp" name="spec_pc[almacenamientos][__j__][tipo]">
                              <option value="">Selecciona…</option>
                              <option value="ssd">SSD</option>
                              <option value="hdd">HDD</option>
                              <option value="m2">M.2</option>
                            </select>
                          </div>

                          <div>
                            <label>Capacidad (GB)</label>
                            <input class="inp" type="number" min="1" name="spec_pc[almacenamientos][__j__][capacidad_gb]" placeholder="Ej. 512">
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

                <div style="grid-column:1/-1">
                  <label>Procesador</label>
                  <input class="inp" name="spec_pc[procesador]"
                         value="{{ old('spec_pc.procesador', data_get($over,'procesador')) }}"
                         placeholder="{{ data_get($eff,'procesador') }}">
                  @error('spec_pc.procesador') <div class="err">{{ $message }}</div> @enderror
                </div>

                {{-- ✅ SIEMPRE: fecha de compra (mismo lugar que create: al final del bloque) --}}
                <div style="grid-column:1/-1">
                  <label>Fecha de compra</label>
                  <input class="inp" type="date" name="fecha_compra" value="{{ $fechaCompraActual }}">
                  @error('fecha_compra') <div class="err">{{ $message }}</div> @enderror
                </div>
              </div>
            </div>

            {{-- ===================== CELULAR ===================== --}}
            <div id="block-cel" style="{{ $tipo === 'celular' ? '' : 'display:none' }}">
              <div style="font-weight:900;color:#111827;margin-top:10px;">Especificaciones – Celular / Teléfono</div>

              <div class="grid2">
                <div>
                  <label>Descripción o Color</label>
                  <input class="inp" name="spec_cel[color]"
                         value="{{ old('spec_cel.color', data_get($over,'color')) }}">
                  @error('spec_cel.color') <div class="err">{{ $message }}</div> @enderror
                </div>

                <div>
                  <label>Almacenamiento (GB)</label>
                  <input class="inp" type="number" min="1" name="spec_cel[almacenamiento_gb]"
                         value="{{ old('spec_cel.almacenamiento_gb', data_get($over,'almacenamiento_gb')) }}">
                  @error('spec_cel.almacenamiento_gb') <div class="err">{{ $message }}</div> @enderror
                </div>

                <div>
                  <label>RAM (GB) <span class="hint">(opcional)</span></label>
                  <input class="inp" type="number" min="1" name="spec_cel[ram_gb]"
                         value="{{ old('spec_cel.ram_gb', data_get($over,'ram_gb')) }}">
                  @error('spec_cel.ram_gb') <div class="err">{{ $message }}</div> @enderror
                </div>

                <div>
                  <label>IMEI</label>
                  <input class="inp" name="spec_cel[imei]"
                         value="{{ old('spec_cel.imei', data_get($over,'imei')) }}">
                  @error('spec_cel.imei') <div class="err">{{ $message }}</div> @enderror
                </div>

                {{-- ✅ NUEVO: número de celular (mismo lugar que create: después de IMEI y antes de fecha) --}}
                <div>
                  <label>Número de celular</label>
                  <input class="inp" name="spec_cel[numero_celular]" value="{{ $numeroCelActual }}" placeholder="Ej. 55 1234 5678">
                  @error('spec_cel.numero_celular') <div class="err">{{ $message }}</div> @enderror
                </div>

                {{-- ✅ SIEMPRE: fecha de compra (mismo lugar que create) --}}
                <div>
                  <label>Fecha de compra</label>
                  <input class="inp" type="date" name="fecha_compra" value="{{ $fechaCompraActual }}">
                  @error('fecha_compra') <div class="err">{{ $message }}</div> @enderror
                </div>

                {{-- ✅ NUEVO: Accesorios (mismo feeling del create) --}}
                <div style="grid-column:1/-1; margin-top:6px;">
                  <div style="font-weight:900;color:#111827;margin-top:2px;">Accesorios</div>

                  <div class="acc-wrap">
                    <label class="acc-item">
                      <input type="checkbox" name="spec_cel[accesorios][funda]" value="1" @checked(data_get($acc,'funda'))>
                      <span>Funda</span>
                    </label>

                    <label class="acc-item">
                      <input type="checkbox" name="spec_cel[accesorios][mica_protectora]" value="1" @checked(data_get($acc,'mica_protectora'))>
                      <span>Mica Protectora</span>
                    </label>

                    <label class="acc-item">
                      <input type="checkbox" name="spec_cel[accesorios][cargador]" value="1" @checked(data_get($acc,'cargador'))>
                      <span>Cargador</span>
                    </label>

                    <label class="acc-item">
                      <input type="checkbox" name="spec_cel[accesorios][cable_usb]" value="1" @checked(data_get($acc,'cable_usb'))>
                      <span>Cable USB</span>
                    </label>
                  </div>
                </div>
              </div>
            </div>

            {{-- ===================== DESCRIPCIÓN POR SERIE (otros tipos) ===================== --}}
            @php
              $needsDesc = in_array($tipo, ['impresora','monitor','pantalla','periferico','otro'], true);
            @endphp
            <div id="block-desc" style="{{ $needsDesc ? '' : 'display:none' }}">
              <div style="font-weight:900;color:#111827;margin-top:10px;">Descripción / notas</div>
              <textarea class="inp" name="descripcion" id="descripcion" rows="6"
                        placeholder="{{ $producto->descripcion ?? 'Escribe la descripción o notas…' }}">{{ $descActual }}</textarea>
              @error('descripcion') <div class="err">{{ $message }}</div> @enderror

              {{-- ✅ SIEMPRE: fecha de compra (mismo lugar que create: debajo de la descripción) --}}
              <div style="margin-top:10px;">
                <label>Fecha de compra</label>
                <input class="inp" type="date" name="fecha_compra" value="{{ $fechaCompraActual }}">
                @error('fecha_compra') <div class="err">{{ $message }}</div> @enderror
              </div>
            </div>

            <div class="actions">
              <a href="{{ route('productos.series', $producto) }}" class="btn-cancel">Cancelar</a>

              <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <button type="button" class="btn-cancel" id="btn-clear">Limpiar</button>
                <button class="btn-save" type="submit" id="btn-submit">Guardar</button>
              </div>
            </div>
          </form>

          {{-- Informativo --}}
          <div class="hint">
            <b>Tipo detectado:</b> {{ $tipo }}
          </div>

        </div>
      </div>
    </div>
  </div>

  <script>
    (function(){
      const tipo = @json($tipo);

      const form = document.getElementById('serieUnifiedForm');
      const btnClear = document.getElementById('btn-clear');
      const btnSubmit = document.getElementById('btn-submit');

      // ===== Storage repeater (PC) =====
      function initStorageRepeater(){
        const box = document.querySelector('[data-storage-box]');
        if (!box) return;

        const rowsWrap = box.querySelector('[data-storage-rows]');
        const tpl = box.querySelector('template[data-storage-tpl]');
        const btnAdd = box.querySelector('[data-add-storage]');
        if (!rowsWrap || !tpl || !btnAdd) return;

        function getNextIndex(){
          const inputs = rowsWrap.querySelectorAll('select[name^="spec_pc[almacenamientos]"], input[name^="spec_pc[almacenamientos]"]');
          let max = -1;
          inputs.forEach(el => {
            const m = el.name.match(/spec_pc\[almacenamientos\]\[(\d+)\]/);
            if (m) max = Math.max(max, parseInt(m[1], 10));
          });
          return max + 1;
        }

        function wireDelButtons(){
          rowsWrap.querySelectorAll('[data-del-storage]').forEach(btn => {
            if (btn._wired) return;
            btn._wired = true;
            btn.addEventListener('click', () => {
              const row = btn.closest('[data-storage-row]');
              row?.remove();
              if (rowsWrap.querySelectorAll('[data-storage-row]').length === 0) addStorageRow();
            });
          });
        }

        function addStorageRow(){
          const j = getNextIndex();
          const html = tpl.innerHTML.replaceAll('__j__', String(j));
          const temp = document.createElement('div');
          temp.innerHTML = html;
          const node = temp.firstElementChild;
          rowsWrap.appendChild(node);
          wireDelButtons();
        }

        btnAdd.addEventListener('click', addStorageRow);
        wireDelButtons();

        if (rowsWrap.querySelectorAll('[data-storage-row]').length === 0) addStorageRow();
      }

      initStorageRepeater();

      // ===== Limpiar según sección =====
      function clearPC(){
        document.querySelectorAll('[name="spec_pc[color]"], [name="spec_pc[ram_gb]"], [name="spec_pc[procesador]"]').forEach(el => el.value = '');
        const rowsWrap = document.querySelector('[data-storage-rows]');
        if (rowsWrap){ rowsWrap.innerHTML = ''; }
        initStorageRepeater();

        // fecha compra
        document.querySelectorAll('[name="fecha_compra"]').forEach(el => el.value = '');
      }

      function clearCEL(){
        document.querySelectorAll(
          '[name="spec_cel[color]"], [name="spec_cel[almacenamiento_gb]"], [name="spec_cel[ram_gb]"], [name="spec_cel[imei]"], [name="spec_cel[numero_celular]"]'
        ).forEach(el => el.value = '');

        // accesorios
        document.querySelectorAll('[name^="spec_cel[accesorios]"]').forEach(el => el.checked = false);

        // fecha compra
        document.querySelectorAll('[name="fecha_compra"]').forEach(el => el.value = '');
      }

      function clearDESC(){
        const desc = document.getElementById('descripcion');
        if (desc) desc.value = '';

        // fecha compra
        document.querySelectorAll('[name="fecha_compra"]').forEach(el => el.value = '');
      }

      btnClear?.addEventListener('click', () => {
        if (tipo === 'equipo_pc') clearPC();
        else if (tipo === 'celular') clearCEL();
        else clearDESC();
      });

      // ===== Validación extra PC: si hay capacidad, tipo requerido =====
      form?.addEventListener('submit', (e) => {
        if (tipo !== 'equipo_pc') return;

        const caps = form.querySelectorAll('input[name^="spec_pc[almacenamientos]"][name$="[capacidad_gb]"]');
        for (const capInput of caps){
          const cap = parseInt(capInput.value || 0, 10);
          if (!cap || cap <= 0) continue;

          const tipoName = capInput.name.replace('[capacidad_gb]', '[tipo]');
          const tipoSelect = form.querySelector(`[name="${tipoName}"]`);
          const tipoVal = (tipoSelect?.value || '').trim();

          if (!tipoVal){
            e.preventDefault();
            alert('Debes seleccionar el tipo de almacenamiento (SSD, HDD o M.2) si colocas una capacidad.');
            tipoSelect?.focus();
            return false;
          }
        }
      });

      // ===== Previene doble envío + atajos =====
      let sending = false;
      form?.addEventListener('submit', (e) => {
        if (sending) { e.preventDefault(); return; }
        sending = true;
        if (btnSubmit){
          btnSubmit.disabled = true;
          btnSubmit.style.opacity = .7;
        }
      });

      document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
          e.preventDefault();
          btnSubmit?.click();
        }
        if (e.key === 'Escape') {
          e.preventDefault();
          window.location.href = @json(route('productos.series', $producto));
        }
      });

      setTimeout(() => {
        const first = form?.querySelector('input,select,textarea');
        first?.focus?.();
      }, 50);
    })();
  </script>
</x-app-layout>
