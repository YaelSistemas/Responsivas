{{-- resources/views/cartuchos/create.blade.php --}}
<x-app-layout title="Nuevo Cartucho">
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight text-center">Nueva solicitud de Entrega de Cartucho</h2>
  </x-slot>

  <style>
    .zoom-outer{ overflow-x:hidden; }
    .zoom-inner{ --zoom:1; transform:scale(var(--zoom)); transform-origin:top left; width:calc(100%/var(--zoom)); }
    @media (max-width:1024px){ .zoom-inner{ --zoom:.95 } .page-wrap{max-width:94vw;padding:0 4vw} }
    @media (max-width:768px){  .zoom-inner{ --zoom:.90 } .page-wrap{max-width:94vw;padding:0 4vw} }
    @media (max-width:640px){  .zoom-inner{ --zoom:.75 } .page-wrap{max-width:94vw;padding:0 4vw} }
    @media (max-width:400px){  .zoom-inner{ --zoom:.60 } .page-wrap{max-width:94vw;padding:0 4vw} }
    @media (max-width:768px){ input, select, textarea, button { font-size:16px; } }

    .page-wrap{ max-width:880px; margin:0 auto; }
    .wrap{background:#fff;padding:24px;border-radius:10px;box-shadow:0 2px 6px rgba(0,0,0,.08); border:1px solid #e5e7eb}
    .row{margin-bottom:16px}
    select,textarea,input{width:100%;padding:8px;border:1px solid #d1d5db;border-radius:8px}
    label{font-weight:400; display:block; margin-bottom:6px;}
    .err{color:#dc2626;font-size:12px;margin-top:6px}
    .hint{font-size:12px;color:#6b7280}

    .btn{background:#16a34a;color:#fff;padding:10px 16px;border-radius:8px;font-weight:600;border:none}
    .btn:hover{background:#15803d}
    .btn-cancel{background:#dc2626;color:#fff;padding:10px 16px;border-radius:8px;font-weight:600;border:none;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}
    .btn-cancel:hover{background:#b91c1c}

    .grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}

    .section-sep{display:flex;align-items:center;margin:22px 0 14px}
    .section-sep .line{flex:1;height:1px;background:#e5e7eb}
    .section-sep .label{margin:0 10px;font-size:12px;color:#6b7280;letter-spacing:.06em;text-transform:uppercase;font-weight:700;white-space:nowrap}

    /* ===== Productos estilo Responsivas (se queda por ahora, aunque no lo usemos todavía) ===== */
    .toolrow{display:flex;gap:8px;align-items:center}
    .btn-gray{background:#f3f4f6;color:#111827;border:1px solid #e5e7eb;padding:8px 12px;border-radius:8px;cursor:pointer;font-weight:600}
    .btn-gray:hover{background:#e5e7eb}

    /* ===== Botones especiales ===== */
    .btn-blue{background:#2563eb;color:#fff;border:1px solid #1d4ed8;padding:10px 14px;border-radius:8px;cursor:pointer;font-weight:700}
    .btn-blue:hover{background:#1d4ed8}
    .btn-red{background:#dc2626;color:#fff;border:1px solid #b91c1c;padding:10px 14px;border-radius:8px;cursor:pointer;font-weight:700}
    .btn-red:hover{background:#b91c1c}

    /* ===== Igualar diseño del input date con TomSelect ===== */
    input[type="date"]{height:42px;padding:8px 12px;border-radius:8px;border:1px solid #d1d5db;background:#fff;line-height:1.25;}
    input[type="date"]::-webkit-calendar-picker-indicator{opacity:.7;cursor:pointer;}
    input[type="date"]:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.15);}

    /* ===== Igualar altura de TomSelect con ese input ===== */
    .ts-wrapper.single .ts-control{min-height:42px;padding:8px 12px;border-radius:8px;border-color:#d1d5db;}

    /* ===== Input number estilo igual que los demás + focus ===== */
    .input-like{height:42px;padding:8px 12px;border-radius:8px;border:1px solid #d1d5db;background:#fff;line-height:1.25;}
    .input-like:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.15);}

    /* quitar flechitas en number (opcional) */
    input[type=number]::-webkit-inner-spin-button,
    input[type=number]::-webkit-outer-spin-button{ -webkit-appearance:none; margin:0; }
    input[type=number]{ -moz-appearance:textfield; }

    /* items: alinear botón quitar a la altura del input/select */
    .itemActions{display:flex;gap:10px;align-items:center;  }

    /* El label arriba mide aprox. 22-24px, compensamos para bajar el botón a la altura del input */
    .btnRemove{margin-top: 12px; height: 42px; white-space: nowrap;}

    /* ===== SKU en 2da línea dentro de TomSelect ===== */
    .ts-sku{ font-size:12px; margin-top:2px; font-weight:600; }
    .ts-sku.bk{ color:#111827; } /* Negro */
    .ts-sku.m { color:#db2777; } /* Magenta */
    .ts-sku.y { color:#d97706; } /* Amarillo */
    .ts-sku.c { color:#0284c7; } /* Cyan */

    /* ===== SKU en línea (cuando ya está seleccionado) ===== */
    .ts-sku-inline{font-size:12px;font-weight:700;margin-left:6px;}

    .ts-sku-inline.bk{ color:#111827; } /* Negro */
    .ts-sku-inline.m { color:#db2777; } /* Magenta */
    .ts-sku-inline.y { color:#d97706; } /* Amarillo */
    .ts-sku-inline.c { color:#0284c7; } /* Cyan */

    .ts-serie{font-size:12px;margin-top:2px;font-weight:600;color:#6b7280;}
    .ts-serie-inline{font-size:12px;font-weight:700;margin-left:6px;color:#374151;}
  </style>

  @php
    $colaboradores   = $colaboradores ?? collect();
    $productos       = $productos ?? collect();             // (lo dejamos por compatibilidad)
    $equiposOptions  = $equiposOptions ?? collect();         // ✅ NUEVO (1 opción por serie)
    $users           = $users ?? collect();
    $productosCartucho = $productosCartucho ?? collect();

    $yo = auth()->user();
    $firmaRealizoDefault = old('firma_realizo', $yo?->id ?? '');
    $firmaRecibioDefault = old('firma_recibio', old('colaborador_id'));
  @endphp


  <div class="zoom-outer">
    <div class="zoom-inner">
      <div class="py-6 page-wrap">
        <div class="wrap">

          @if ($errors->any())
            <div style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:8px;padding:12px;margin-bottom:14px;">
              <b>Revisa los campos:</b>
              <ul style="margin-left:18px;list-style:disc;">
                @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
              </ul>
            </div>
          @endif

          <form method="POST" action="{{ route('cartuchos.store') }}" id="cartuchosForm">
            @csrf

            {{-- ===== DATOS ===== --}}
            <div class="section-sep"><div class="line"></div><div class="label">Datos</div><div class="line"></div></div>

            {{-- FILA 1: Fecha (izq) / Colaborador (der) --}}
            <div class="grid2 row">
              <div>
                <label>Fecha de solicitud</label>
                <input type="date" name="fecha_solicitud" value="{{ old('fecha_solicitud') }}" required>
                @error('fecha_solicitud') <div class="err">{{ $message }}</div> @enderror
              </div>

              <div>
                <label>Colaborador</label>
                <select name="colaborador_id" id="colaborador_id" required>
                  <option value="" disabled {{ old('colaborador_id') ? '' : 'selected' }}>— Selecciona —</option>
                  @foreach($colaboradores as $c)
                    @php
                      $aps = $c->apellido ?? $c->apellidos
                           ?? trim(($c->apellido_paterno ?? '').' '.($c->apellido_materno ?? ''));
                      $nombre = trim(($c->nombre ?? '').' '.$aps);
                    @endphp
                    <option
                        value="{{ $c->id }}"
                        data-unidad-id="{{ $c->unidad_servicio_id ?? '' }}"
                        @selected((string)old('colaborador_id')===(string)$c->id)
                        >
                        {{ $nombre }}
                    </option>
                  @endforeach
                </select>
                @error('colaborador_id') <div class="err">{{ $message }}</div> @enderror
              </div>
            </div>

            {{-- FILA 2: Equipo (izq) / Realizado por (der) --}}
            <div class="grid2 row">
              <div>
                <label>Equipo</label>
                <select name="producto_key" id="producto_id" required>
                    <option value="" disabled selected>— Selecciona —</option>

                    @foreach($equiposOptions as $op)
                        @php
                        // texto del producto (HP...)
                        $titulo = trim((string)($op['text'] ?? ''));

                        // serie individual
                        $serie  = trim((string)($op['serie'] ?? ''));

                        // para evitar que marque el selected por error si hay repetidos:
                        // armamos un value único interno para TomSelect
                        $valueKey = ($op['producto_id'] ?? '') . '|' . ($op['serie_id'] ?? '') . '|' . $serie;

                        $oldProducto = (string) old('producto_id');
                        // si antes guardabas solo producto_id, se seguirá seleccionando por producto_id (primera coincidencia).
                        // si quieres selección exacta por serie, te digo abajo el extra opcional.
                        $oldSerieId = (string) old('producto_serie_id');
                        $selected = ($oldSerieId !== '' && $oldSerieId === (string)($op['serie_id'] ?? ''));
                        @endphp

                        <option
                        value="{{ $valueKey }}"
                        data-producto-id="{{ $op['producto_id'] }}"
                        data-serie-id="{{ $op['serie_id'] }}"
                        data-serie="{{ $serie }}"
                        data-unidad-id="{{ $op['unidad_servicio_id'] ?? '' }}"
                        @selected($selected)
                        >
                        {{ $titulo }}
                        </option>
                    @endforeach
                </select>

                <input type="hidden" name="producto_id" id="producto_id_real" value="{{ old('producto_id') }}">
                <input type="hidden" name="producto_serie_id" id="producto_serie_id" value="{{ old('producto_serie_id') }}">

                @error('producto_id') <div class="err">{{ $message }}</div> @enderror
              </div>

              <div>
                <label>Realizado por</label>
                <select name="realizado_por" id="realizado_por" required>
                  <option value="" disabled {{ old('realizado_por') ? '' : 'selected' }}>— Selecciona —</option>
                  @foreach($users as $u)
                    <option value="{{ $u->id }}" @selected((string)old('realizado_por')===(string)$u->id)>{{ $u->name }}</option>
                  @endforeach
                </select>
                @error('realizado_por') <div class="err">{{ $message }}</div> @enderror
                <div class="hint">Usuario que realizó la entrega/registro.</div>
              </div>
            </div>

            {{-- ===== PRODUCTOS (Opción B: múltiples productos + cantidad) ===== --}}
            <div class="section-sep"><div class="line"></div><div class="label">Productos</div><div class="line"></div></div>

            <div class="row">
              <div class="hint">Agrega uno o varios cartuchos/consumibles con su cantidad.</div>
            </div>

            <div id="itemsWrap"></div>

            <div class="row" style="display:flex; gap:10px; justify-content:flex-end;">
              <button type="button" class="btn-blue" id="btnAddItem">+ Agregar producto</button>
            </div>

            @error('items') <div class="err">{{ $message }}</div> @enderror
            @error('items.*.producto_id') <div class="err">{{ $message }}</div> @enderror
            @error('items.*.cantidad') <div class="err">{{ $message }}</div> @enderror

            <template id="itemTpl">
              <div class="grid2 row itemRow">
                <div>
                  <label>Cartucho / Consumible</label>
                  <select class="itemProducto" name="">
                    <option value="" disabled selected>— Selecciona —</option>

                    @foreach($productosCartucho as $pc)
                        @php
                            $titulo = trim(($pc->nombre ?? '').' '.($pc->marca ?? '').' '.($pc->modelo ?? ''));
                            $sku    = trim((string)($pc->sku ?? ''));

                            // ✅ Color desde especificaciones->color (NO desde el texto/SKU)
                            $esp = $pc->especificaciones ?? null;

                            // puede venir como string JSON, array, stdClass o collection
                            if (is_string($esp)) {
                            $esp = json_decode($esp, true);
                            } elseif ($esp instanceof \Illuminate\Support\Collection) {
                            $esp = $esp->toArray();
                            } elseif ($esp instanceof \stdClass) {
                            $esp = (array) $esp;
                            }

                            $colorTxt = '';
                            if (is_array($esp)) {
                            $colorTxt = (string)($esp['color'] ?? $esp['Color'] ?? '');
                            }

                            $u = strtoupper($colorTxt);

                            $cc = '';
                            if (str_contains($u, '(Y)') || str_contains($u, 'YELLOW') || str_contains($u, 'AMARIL')) $cc = 'y';
                            elseif (str_contains($u, '(C)') || str_contains($u, 'CIAN') || str_contains($u, 'CYAN')) $cc = 'c';
                            elseif (str_contains($u, '(M)') || str_contains($u, 'MAGENTA')) $cc = 'm';
                            elseif (str_contains($u, '(BK)') || str_contains($u, 'NEGRO') || str_contains($u, 'BLACK')) $cc = 'bk';
                        @endphp

                        <option value="{{ $pc->id }}"
                            data-sku="{{ $sku }}"
                            data-cc="{{ $cc }}"
                        >
                            {{ $titulo }}
                        </option>
                    @endforeach

                  </select>
                </div>

                <div class="itemActions">
                  <div style="flex:1;">
                    <label>Cantidad</label>
                    <input class="itemCantidad input-like" type="number" min="0" step="1" value="0" name="">
                    <div class="hint">Cantidad solicitada.</div>
                  </div>
                  <button type="button" class="btn-red btnRemove">Quitar</button>
                </div>
              </div>
            </template>

            {{-- ===== FIRMAS (solo selects) ===== --}}
            <div class="section-sep"><div class="line"></div><div class="label">Firmas</div><div class="line"></div></div>

            <div class="grid2 row">
              <div>
                <label>Firma realizó</label>
                <select name="firma_realizo" id="firma_realizo" required>
                  <option value="" disabled {{ $firmaRealizoDefault ? '' : 'selected' }}>— Selecciona —</option>
                  @foreach($users as $u)
                    <option value="{{ $u->id }}" @selected((string)$firmaRealizoDefault===(string)$u->id)>{{ $u->name }}</option>
                  @endforeach
                </select>
                @error('firma_realizo') <div class="err">{{ $message }}</div> @enderror
                <div class="hint">Quién realizó la entrega/registro.</div>
              </div>

              <div>
                <label>Firma recibió</label>
                <select name="firma_recibio" id="firma_recibio" required>
                  <option value="" disabled {{ $firmaRecibioDefault ? '' : 'selected' }}>— Selecciona —</option>
                  @foreach($colaboradores as $c)
                    @php
                      $aps = $c->apellido ?? $c->apellidos
                           ?? trim(($c->apellido_paterno ?? '').' '.($c->apellido_materno ?? ''));
                      $nombre = trim(($c->nombre ?? '').' '.$aps);
                    @endphp
                    <option value="{{ $c->id }}" @selected((string)$firmaRecibioDefault===(string)$c->id)>{{ $nombre }}</option>
                  @endforeach
                </select>
                @error('firma_recibio') <div class="err">{{ $message }}</div> @enderror
                <div class="hint">Quién recibió los cartuchos.</div>
              </div>
            </div>

            <div class="grid2" style="margin-top:18px">
              <a href="{{ route('cartuchos.index') }}" class="btn-cancel">Cancelar</a>
              <button type="submit" class="btn">Guardar</button>
            </div>

          </form>
        </div>
      </div>
    </div>
  </div>

  {{-- ===== Tom Select (buscar escribiendo en selects) ===== --}}
  @push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css">
    <style>
      .ts-dropdown{
        max-height:none; overflow:visible; z-index:9999 !important;
        background:#fff; border:1px solid #d1d5db; box-shadow:0 4px 10px rgba(0,0,0,.08);
      }
      .ts-dropdown .ts-dropdown-content{ max-height:260px; overflow-y:auto; background:#fff; }
      .ts-dropdown .option:hover, .ts-dropdown .option.active{ background:#f3f4f6; }
    </style>
  @endpush

  @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

    <script>
      document.addEventListener('DOMContentLoaded', () => {
        const baseConfig = {
          allowEmptyOption: true,
          maxOptions: 5000,
          plugins: ['dropdown_input'],
          searchField: ['text','sku'],
          openOnFocus: true,
          dropdownParent: 'body',
          sortField: [
            { field: '$order', direction: 'asc' },
            { field: 'text',   direction: 'asc' },
          ],
          onDropdownOpen: function () {
            const rect = this.control.getBoundingClientRect();
            const espacioAbajo = window.innerHeight - rect.bottom - 10;
            const dropdown = this.dropdown;
            if (dropdown) {
              const minimo = 160;
              const maximo = 260;
              let alto = Math.max(minimo, Math.min(espacioAbajo, maximo));
              dropdown.style.maxHeight = alto + 'px';
            }
          }
        };

        // ✅ Productos (cartucho/consumible): 2 líneas + SKU con color
        const productosConfig = {
  ...baseConfig,
  onInitialize: function () {
    // ✅ Copiar data-sku / data-cc desde el <option> real al objeto option de TomSelect
    const selectEl = this.input; // el <select> original
    Object.keys(this.options).forEach((val) => {
      const domOpt = selectEl.querySelector(`option[value="${CSS.escape(val)}"]`);
      if (!domOpt) return;

      this.options[val].sku = domOpt.getAttribute("data-sku") || "";
      this.options[val].cc  = domOpt.getAttribute("data-cc")  || "";
    });
  },
  render: {
    option: function (data, escape) {
      const sku = data.sku ? String(data.sku) : "";
      const cc  = data.cc  ? String(data.cc)  : "";
      const skuHtml = sku
        ? `<div class="ts-sku ${cc}">SKU: ${escape(sku)}</div>`
        : "";

      return `<div>
                <div>${escape(data.text)}</div>
                ${skuHtml}
              </div>`;
    },
    item: function (data, escape) {
      const sku = data.sku ? String(data.sku) : "";
      const cc  = data.cc  ? String(data.cc)  : "";

      return sku
        ? `<div>${escape(data.text)} <span class="ts-sku-inline ${cc}">(${escape(sku)})</span></div>`
        : `<div>${escape(data.text)}</div>`;
    }
  }
};


        const equipoConfig = {
            ...baseConfig,
            searchField: ['text','serie'],
            dataAttr: {
                serie: 'data-serie',
            },
            render: {
                option: function (data, escape) {
                const serie = data.serie ? String(data.serie) : "";
                const serieHtml = serie
                    ? `<div class="ts-serie">Serie: ${escape(serie)}</div>`
                    : "";
                return `<div>
                            <div>${escape(data.text)}</div>
                            ${serieHtml}
                        </div>`;
                },
                item: function (data, escape) {
                const serie = data.serie ? String(data.serie) : "";
                return serie
                    ? `<div>${escape(data.text)} <span class="ts-serie-inline">(${escape(serie)})</span></div>`
                    : `<div>${escape(data.text)}</div>`;
                }
            }
        };

        // ✅ SOLO firmas: abrir hacia arriba sin romper el script
        const firmasConfig = {
          ...baseConfig,
          dropdownParent: 'body',
          onDropdownOpen: function () {
            const control = this.control;
            const dropdown = this.dropdown;
            if (!control || !dropdown) return;

            const rect = control.getBoundingClientRect();
            const espacioArriba = rect.top - 10;
            const minimo = 160;
            const maximo = 260;
            const alto = Math.max(minimo, Math.min(espacioArriba, maximo));

            dropdown.style.maxHeight = alto + "px";
            dropdown.style.overflowY = "auto";

            requestAnimationFrame(() => {
              const h = dropdown.offsetHeight || alto;

              dropdown.style.position = "fixed";
              dropdown.style.left = rect.left + "px";
              dropdown.style.width = rect.width + "px";
              dropdown.style.top = Math.max(10, rect.top - h - 6) + "px";
              dropdown.style.bottom = "auto";
            });
          },
          onDropdownClose: function () {
            const dropdown = this.dropdown;
            if (!dropdown) return;
            dropdown.style.position = "";
            dropdown.style.left = "";
            dropdown.style.top = "";
            dropdown.style.bottom = "";
            dropdown.style.width = "";
          }
        };

        // selects principales
        const tsColaborador = document.getElementById('colaborador_id')
          ? new TomSelect('#colaborador_id', { ...baseConfig, placeholder: 'Escribe para buscar…' })
          : null;

        const productoSelectEl = document.getElementById('producto_id');

        if (productoSelectEl) {
        const tsEquipo = new TomSelect('#producto_id', { ...equipoConfig, placeholder: 'Escribe para buscar…' });

        const hiddenProducto = document.getElementById('producto_id_real');
        const hiddenSerieId  = document.getElementById('producto_serie_id');

        // ====== 1) Guardar opciones originales del select (con data-unidad-id)
        const allEquipoOptions = Array.from(productoSelectEl.querySelectorAll('option'))
        .filter(o => o.value && o.value !== "")
        .map(o => ({
            value: o.value,
            text: o.textContent.trim(),
            serie: o.getAttribute('data-serie') || '',
            unidadId: String(o.getAttribute('data-unidad-id') || ''),
            productoId: String(o.getAttribute('data-producto-id') || ''),
            serieId: String(o.getAttribute('data-serie-id') || ''),
        }));

        function clearEquipoSelection() {
        tsEquipo.clear(true);
        hiddenProducto.value = '';
        hiddenSerieId.value  = '';
        }

        // ====== 2) Reconstruir el TomSelect según unidad
        function filtrarEquipoPorUnidad(unidadId) {
        const u = String(unidadId || '');

        // si no hay unidad/colaborador, NO mostrar nada
        const filtered = u
            ? allEquipoOptions.filter(op => op.unidadId === u)
            : [];

        tsEquipo.clearOptions();
        tsEquipo.addOptions(filtered);
        tsEquipo.refreshOptions(false);

        clearEquipoSelection();
        }

        // ====== 3) Obtener unidad desde el colaborador seleccionado
        const colabEl = document.getElementById('colaborador_id');

        function getUnidadFromColaborador() {
        const v = colabEl?.value;
        if (!v) return '';
        const opt = colabEl.querySelector(`option[value="${CSS.escape(v)}"]`);
        return opt ? (opt.getAttribute('data-unidad-id') || '') : '';
        }

        // Al cargar: si no hay colaborador, deja el select de equipo SIN opciones
        filtrarEquipoPorUnidad(getUnidadFromColaborador());

        // ====== 4) Cuando cambie colaborador, filtrar
        if (tsColaborador) {
        tsColaborador.on('change', () => {
            const unidadId = getUnidadFromColaborador();
            filtrarEquipoPorUnidad(unidadId);
        });

        // Al cargar, si ya hay colaborador (old), filtra de una vez
        const initialUnidad = getUnidadFromColaborador();
        if (initialUnidad) filtrarEquipoPorUnidad(initialUnidad);
        }

        function syncHiddenFromSelected(valueKey) {
            const opt = tsEquipo.options[valueKey];
            if (!opt) return;

            // TomSelect guarda data-* como propiedades en opt (serie, serieId, productoId)
            // pero como pusimos data-producto-id y data-serie-id, lo más seguro es leer del DOM:
            const domOpt = productoSelectEl.querySelector(`option[value="${CSS.escape(valueKey)}"]`);
            if (!domOpt) return;

            hiddenProducto.value = domOpt.getAttribute('data-producto-id') || '';
            hiddenSerieId.value  = domOpt.getAttribute('data-serie-id') || '';
        }

        tsEquipo.on('change', (valueKey) => {
            syncHiddenFromSelected(valueKey);
        });

        // al cargar, si ya hay valor seleccionado
        const current = tsEquipo.getValue();
        if (current) syncHiddenFromSelected(current);
        }

        if (document.getElementById('realizado_por')) new TomSelect('#realizado_por', { ...baseConfig, placeholder: 'Escribe para buscar…' });

        // firmas (arriba)
        const tsFirmaRealizo = document.getElementById('firma_realizo')
          ? new TomSelect('#firma_realizo', { ...firmasConfig, placeholder: 'Escribe para buscar…' })
          : null;

        const tsFirmaRecibio = document.getElementById('firma_recibio')
          ? new TomSelect('#firma_recibio', { ...firmasConfig, placeholder: 'Escribe para buscar…' })
          : null;

        // (sincronización opcional)
        if (tsColaborador && tsFirmaRecibio) {
          tsColaborador.on('change', (value) => {
            if (value && tsFirmaRecibio.options[value]) tsFirmaRecibio.setValue(value, true);
          });

          const colValue = tsColaborador.getValue();
          if (!tsFirmaRecibio.getValue() && colValue && tsFirmaRecibio.options[colValue]) {
            tsFirmaRecibio.setValue(colValue, true);
          }
        }

        // ===== Items dinámicos
        const wrap = document.getElementById('itemsWrap');
        const tpl  = document.getElementById('itemTpl');
        const btn  = document.getElementById('btnAddItem');

        function renumber() {
          const rows = wrap.querySelectorAll('.itemRow');
          rows.forEach((row, i) => {
            const sel = row.querySelector('.itemProducto');
            const qty = row.querySelector('.itemCantidad');
            sel.name = `items[${i}][producto_id]`;
            qty.name = `items[${i}][cantidad]`;
          });
        }

        function makeTomSelect(selectEl) {
          if (!selectEl) return null;
          return new TomSelect(selectEl, { ...productosConfig, placeholder: 'Escribe para buscar…' });
        }

        function addRow(prefill = null) {
          const node = document.importNode(tpl.content, true);
          wrap.appendChild(node);

          const row = wrap.querySelector('.itemRow:last-child');
          const sel = row.querySelector('.itemProducto');
          const qty = row.querySelector('.itemCantidad');
          const rm  = row.querySelector('.btnRemove');

          const ts = makeTomSelect(sel);

          // por defecto 0
          if (!prefill) qty.value = 0;

          if (prefill?.producto_id && ts) ts.setValue(String(prefill.producto_id), true);
          if (prefill?.cantidad !== undefined && prefill?.cantidad !== null) qty.value = prefill.cantidad;

          rm.addEventListener('click', () => {
            if (sel.tomselect) sel.tomselect.destroy();
            row.remove();
            renumber();
          });

          renumber();
        }

        btn?.addEventListener('click', () => addRow());

        const oldItems = @json(old('items', []));
        if (oldItems && oldItems.length) oldItems.forEach(i => addRow(i));
        else addRow(); // 1 fila por defecto
      });
    </script>
  @endpush

</x-app-layout>
