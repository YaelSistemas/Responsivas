<x-app-layout title="Nueva responsiva">
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight text-center">Crear responsiva</h2>
  </x-slot>

  <style>
    /* ====== Zoom responsivo: MISMA VISTA, solo más “pequeña” en móvil ====== */
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

    /* iOS: evita auto-zoom al enfocar inputs */
    @media (max-width:768px){
      input, select, textarea{ font-size:16px; }
    }

    /* ====== Estilos propios ====== */
    .wrap{max-width:880px;margin:0 auto;background:#fff;padding:24px;border-radius:10px;box-shadow:0 2px 6px rgba(0,0,0,.08)}
    .row{margin-bottom:16px}
    select,textarea,input{width:100%;padding:8px;border:1px solid #d1d5db;border-radius:8px}
    .err{color:#dc2626;font-size:12px;margin-top:6px}
    .hint{font-size:12px;color:#6b7280}
    .btn{background:#16a34a;color:#fff;padding:10px 16px;border-radius:8px;font-weight:600;border:none}
    .btn-cancel{background:#dc2626;color:#fff;padding:10px 16px;border-radius:8px;text-decoration:none}
    .grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
    .grid3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px}
    .toolrow{display:flex;gap:8px;align-items:center}
    .btn-gray{background:#f3f4f6;color:#111827;border:1px solid #e5e7eb}
    .btn-gray:hover{background:#e5e7eb}
    .sep-option{color:#9ca3af}
    .toolbar-right{margin-left:auto;display:flex;gap:8px}
    #seriesSelect optgroup{font-weight:700;color:#111827}
    .section-sep{display:flex;align-items:center;margin:22px 0 14px}
    .section-sep .line{flex:1;height:1px;background:#e5e7eb}
    .section-sep .label{margin:0 10px;font-size:12px;color:#6b7280;letter-spacing:.06em;text-transform:uppercase;font-weight:700;white-space:nowrap}

    /* === Tom Select: dropdown con fondo blanco y scroll interno === */
    .ts-dropdown { z-index: 9999 !important; max-height: none; overflow: visible; background: #ffffff; 
      border: 1px solid #d1d5db; box-shadow: 0 10px 15px -3px rgba(0,0,0,.1); }
    .ts-dropdown .ts-dropdown-content { max-height: 220px; overflow-y: auto; background: #ffffff; }
    
    /* ✅ MISMO DISEÑO QUE "Motivo de entrega" (vista normal) */
    .form-control { width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 8px; background: #fff; color: #111827; outline: none; }
    .form-control:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.15); }

    /* disabled (para CEL motivo fijo) */
    .form-control[disabled],
    .form-control:disabled { background: #f9fafb; color: #111827; cursor: not-allowed; opacity: 1; }

    /* === "fake TomSelect" para readonly === */
    .ts-like{ border-radius: 8px; border: 1px solid #d1d5db; padding: 6px 8px; min-height: 38px; width: 100%; 
      background: #ffffff; box-shadow: none; display:flex; align-items:center; }
    .ts-like.is-readonly{ cursor:default; }
    .ts-like.is-readonly span{ color:#9ca3af; font-weight:400; }
    .ts-like.small-text span{ font-size:13px; line-height:1.2; }
  </style>

  @php
    $groups = $series->groupBy('producto_id');

    $fmtSerie = function($s) {
      $p   = $s->producto;
      $lbl = "{$s->serie} — {$p->nombre}".($p->marca ? " {$p->marca}" : "").($p->modelo ? " {$p->modelo}" : "");
      $spec = method_exists($s,'getSpecsAttribute') ? ($s->specs ?? []) : ($s->especificaciones ?? []);
      $isPc = ($p->tipo ?? '') === 'equipo_pc';
      if ($isPc && is_array($spec)) {
        $chips = [];
        if (!empty($spec['ram_gb'])) $chips[] = $spec['ram_gb'].' GB RAM';
        if (!empty($spec['almacenamiento']['tipo']) || !empty($spec['almacenamiento']['capacidad_gb'])) {
          $st = [];
          if (!empty($spec['almacenamiento']['tipo'])) $st[] = strtoupper($spec['almacenamiento']['tipo']);
          if (!empty($spec['almacenamiento']['capacidad_gb'])) $st[] = $spec['almacenamiento']['capacidad_gb'].' GB';
          if ($st) $chips[] = implode(' ', $st);
        }
        if (!empty($spec['color'])) $chips[] = 'Color: '.$spec['color'];
        if (!empty($spec['procesador'])) $chips[] = $spec['procesador'];
        if ($chips) $lbl .= ' — '.implode(' · ', $chips);
      } elseif (($p->tipo ?? '') === 'impresora' && !empty($p->descripcion)) {
        $lbl .= ' — '.$p->descripcion;
      }
      return $lbl;
    };

    $data = [];
    foreach ($groups as $pid => $items) {
      $p = $items->first()->producto;
      $titulo = trim($p->nombre.(($p->marca||$p->modelo) ? (' — '.trim($p->marca.' '.$p->modelo)) : ''));
      $entry = [
        'producto_id' => $pid,
        'label'       => $titulo,
        'tipo'        => $p->tipo,
        'producto'    => trim($p->nombre.' '.$p->marca.' '.$p->modelo),
        'options'     => [],
      ];
      foreach ($items as $s) {
        $entry['options'][] = [
          'id'    => $s->id,
          'text'  => $fmtSerie($s),
          'serie' => $s->serie,
        ];
      }
      $data[] = $entry;
    }

    $entregoDefaultId = old('entrego_user_id', $entregoDefaultId ?? null);

    $recibiDefaultId   = old('recibi_colaborador_id', old('colaborador_id'));
    // ← si el controlador envía $autorizaDefaultId (Erasto admin), úsalo; si no, queda vacío
    $autorizaDefaultId = old('autoriza_user_id', isset($autorizaDefaultId) ? $autorizaDefaultId : '');
    // ← sin default: obligamos a elegir
    $motivoDefault     = old('motivo_entrega');
    $hoy               = now()->toDateString();
  @endphp

  <!-- Envoltura de zoom -->
  <div class="zoom-outer">
    <div class="zoom-inner">
      @can('responsivas.create')
        <div class="py-6">
          <div class="wrap">

            @if ($errors->any())
              <div style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:8px;padding:12px;margin-bottom:14px;">
                <b>Revisa los campos:</b>
                <ul style="margin-left:18px;list-style:disc;">
                  @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
                </ul>
              </div>
            @endif

            <form method="POST" action="{{ route('responsivas.store') }}">
              @csrf
              @php $isCel = request('tipo_documento') === 'CEL'; @endphp

              {{-- Ajuste para Mandar a CEL desde Celulares y OES desde Responsivas--}}
              <input type="hidden" name="tipo_documento" value="{{ $isCel ? 'CEL' : 'OES' }}">

              {{-- ======= Datos ======= --}}
              <div class="section-sep"><div class="line"></div><div class="label">Datos</div><div class="line"></div></div>

              <div class="grid2 row">
                <div>
                  <label>Motivo de entrega</label>

                  @if($isCel)
                    {{-- ✅ Celulares: fijo y no editable (pero con mismo diseño) --}}
                    <input type="hidden" name="motivo_entrega" value="prestamo_provisional">
                    <input type="text" class="form-control" value="Préstamo provisional" disabled>
                    <div class="hint">Motivo asignado automáticamente para celulares.</div>
                  @else
                    {{-- ✅ Responsivas: mismo diseño --}}
                    <select name="motivo_entrega" class="form-control" required>
                      <option value="" disabled {{ $motivoDefault ? '' : 'selected' }}>— Selecciona —</option>
                      <option value="asignacion"           @selected($motivoDefault==='asignacion')>Asignación</option>
                      <option value="prestamo_provisional" @selected($motivoDefault==='prestamo_provisional')>Préstamo provisional</option>
                    </select>
                    <div class="hint">No se asume por defecto: elige el motivo.</div>
                  @endif

                  @error('motivo_entrega') <div class="err">{{ $message }}</div> @enderror
                </div>

                <div>
                  <label>Colaborador</label>
                  {{-- ⚠️ NO aplicar form-control aquí por TomSelect --}}
                  <select name="colaborador_id" id="colaborador_id" required>
                    <option value="" disabled {{ old('colaborador_id') ? '' : 'selected' }}>Selecciona colaborador…</option>
                    @foreach($colaboradores as $c)
                      <option value="{{ $c->id }}" @selected(old('colaborador_id')==$c->id)>{{ $c->nombre_completo }}</option>
                    @endforeach
                  </select>
                  @error('colaborador_id') <div class="err">{{ $message }}</div> @enderror
                </div>
              </div>

              <div class="grid2 row">
                <div>
                  <label>{{ $isCel ? 'Fecha de salida' : 'Fecha de solicitud' }}</label>
                  <input type="date" class="form-control" name="fecha_solicitud" value="{{ old('fecha_solicitud') }}" required>
                  @error('fecha_solicitud') <div class="err">{{ $message }}</div> @enderror
                </div>

                <div>
                  <label>Fecha de entrega <span class="hint">(requerida)</span></label>
                  <input type="date" class="form-control" name="fecha_entrega" value="{{ old('fecha_entrega') }}" required>
                  @error('fecha_entrega') <div class="err">{{ $message }}</div> @enderror
                </div>
              </div>

              @if($isCel)
                <div class="row">
                  <label>Observaciones</label>
                  {{-- (No lo pediste aquí, lo dejamos como estaba para no afectar otros campos) --}}
                  <textarea name="observaciones" rows="4" placeholder="Escribe observaciones...">{{ old('observaciones') }}</textarea>
                  @error('observaciones') <div class="err">{{ $message }}</div> @enderror
                </div>
              @endif

              {{-- ======= Productos ======= --}}
              <div class="section-sep"><div class="line"></div><div class="label">Productos</div><div class="line"></div></div>

              <div class="row toolrow">
                <input id="searchBox" placeholder="Buscar por serie / producto…"/>
                <div class="toolbar-right">
                  <button type="button" class="btn-gray" id="btnSelectVisible">Seleccionar visibles</button>
                  <button type="button" class="btn-gray" id="btnClearSel">Limpiar selección</button>
                </div>
              </div>

              <div class="row">
                <label>Series disponibles (puedes seleccionar varias)</label>
                <select id="seriesSelect" name="series_ids[]" multiple size="12" required></select>
                <div class="hint">Mantén presionado Ctrl/Cmd para seleccionar varias.</div>
                @error('series_ids') <div class="err">{{ $message }}</div> @enderror
                @error('series_ids.*') <div class="err">{{ $message }}</div> @enderror
              </div>

              {{-- ======= Firmas ======= --}}
              <div class="section-sep"><div class="line"></div><div class="label">Firmas</div><div class="line"></div></div>

              <div class="{{ $isCel ? 'grid2' : 'grid3' }} row">
                <div>
                  <label>Entregó (solo admin)</label>

                    @if(!empty($lockEntrego) && $lockEntrego)
                      {{-- ✅ SOLO CEL: Isidro logueado (no admin) => fijo, sin poder cambiar --}}
                      <div class="ts-like is-readonly small-text">
                        <span>{{ auth()->user()->name }}</span>
                      </div>
                      <input type="hidden" name="entrego_user_id" value="{{ auth()->id() }}">
                    @else
                      {{-- ✅ Admin: seleccionable (admins + Isidro en CEL) --}}
                      <select name="entrego_user_id" id="entrego_user_id" required>
                        <option value="" disabled {{ $entregoDefaultId ? '' : 'selected' }}>— Selecciona —</option>
                        @foreach($admins as $u)
                          <option value="{{ $u->id }}" @selected((string)$entregoDefaultId === (string)$u->id)>{{ $u->name }}</option>
                        @endforeach
                      </select>
                      <div class="hint">Por defecto: el usuario actual si tiene rol Administrador.</div>
                    @endif

                    @error('entrego_user_id') <div class="err">{{ $message }}</div> @enderror
                </div>

                <div>
                  <label>Recibí (colaborador)</label>
                  <select name="recibi_colaborador_id" id="recibi_colaborador_id" required>
                    <option value="" disabled {{ $recibiDefaultId ? '' : 'selected' }}>— Selecciona —</option>
                    @foreach($colaboradores as $c)
                      <option value="{{ $c->id }}" @selected((string)$recibiDefaultId===(string)$c->id)>{{ $c->nombre_completo }}</option>
                    @endforeach
                  </select>
                  <div class="hint">Se sincroniza con “Colaborador”, pero puedes elegir otro.</div>
                  @error('recibi_colaborador_id') <div class="err">{{ $message }}</div> @enderror
                </div>

                @unless($isCel)
                  <div>
                    <label>Autorizó (solo admin)</label>
                    <select name="autoriza_user_id" id="autoriza_user_id" required>
                      <option value="" disabled {{ $autorizaDefaultId ? '' : 'selected' }}>— Selecciona —</option>
                      @foreach($admins as $u)
                        <option value="{{ $u->id }}" @selected((string)$autorizaDefaultId===(string)$u->id)>{{ $u->name }}</option>
                      @endforeach
                    </select>
                    @error('autoriza_user_id') <div class="err">{{ $message }}</div> @enderror
                  </div>
                @endunless
              </div>

              <div class="grid2">
                <a href="{{ $isCel ? route('celulares.responsivas.index') : route('responsivas.index') }}" class="btn-cancel">
                  Cancelar
                </a>
                <button type="submit" class="btn">Crear responsiva</button>
              </div>
            </form>
          </div>
        </div>

        <script>
          (function(){
            const DATA   = @json($data);
            const select = document.getElementById('seriesSelect');
            const search = document.getElementById('searchBox');
            const btnAll = document.getElementById('btnSelectVisible');
            const btnClr = document.getElementById('btnClearSel');

            function render(filterText='') {
              const q = (filterText || '').toLowerCase().trim();
              const selected = new Set(Array.from(select.selectedOptions).map(o => o.value));
              select.innerHTML = '';
              let groupsRendered = 0;

              DATA.forEach(g => {
                const groupMatches = g.label.toLowerCase().includes(q) || g.producto.toLowerCase().includes(q);
                const opts = [];
                g.options.forEach(o => {
                  const text = (o.text || '').toLowerCase();
                  const match = groupMatches || text.includes(q) || (o.serie || '').toLowerCase().includes(q);
                  if (q === '' || match) opts.push(o);
                });

                if (opts.length) {
                  groupsRendered++;
                  const og = document.createElement('optgroup');
                  og.label = g.label;

                  opts.forEach(o => {
                    const opt = document.createElement('option');
                    opt.value = String(o.id);
                    opt.textContent = o.text;
                    if (selected.has(String(o.id))) opt.selected = true;
                    og.appendChild(opt);
                  });

                  const sep = document.createElement('option');
                  sep.textContent = '────────────────────────';
                  sep.disabled = true;
                  sep.className = 'sep-option';
                  og.appendChild(sep);

                  select.appendChild(og);
                }
              });

              if (!groupsRendered) {
                const og = document.createElement('optgroup');
                og.label = 'Sin coincidencias';
                const opt = document.createElement('option');
                opt.textContent = 'No hay series que coincidan con la búsqueda.';
                opt.disabled = true;
                og.appendChild(opt);
                select.appendChild(og);
              }
            }

            search.addEventListener('input', () => render(search.value));
            btnAll.addEventListener('click', () => {
              Array.from(select.options).forEach(o => { if (!o.disabled) o.selected = true; });
              select.focus();
            });
            btnClr.addEventListener('click', () => {
              Array.from(select.options).forEach(o => { o.selected = false; });
              select.focus();
            });

            render('');
          })();
        </script>
      @else
        <div class="py-6">
          <div class="wrap">
            <p>No tienes permiso para <b>crear</b> responsivas.</p>
            <div style="margin-top:10px">
              <a href="{{ route('responsivas.index') }}" class="btn-cancel" style="text-decoration:none">← Volver al listado</a>
            </div>
          </div>
        </div>
      @endcan
    </div>
  </div>

  @push('styles')
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css">
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const baseConfig = {
                allowEmptyOption: true,
                maxOptions: 5000,
                sortField: [
                    { field: '$order', direction: 'asc' },
                    { field: 'text',   direction: 'asc' },
                ],
                plugins: ['dropdown_input'],
                dropdownParent: 'body',
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

            // Colaborador (Datos)
            const tsColaborador = document.getElementById('colaborador_id')
                ? new TomSelect('#colaborador_id', {
                    ...baseConfig,
                    placeholder: 'Selecciona colaborador…',
                })
                : null;

            // Entregó (solo admin)
            const tsEntrego = document.getElementById('entrego_user_id')
                ? new TomSelect('#entrego_user_id', {
                    ...baseConfig,
                    placeholder: 'Selecciona quién entrega…',
                })
                : null;

            // Recibí (colaborador)
            const tsRecibi = document.getElementById('recibi_colaborador_id')
                ? new TomSelect('#recibi_colaborador_id', {
                    ...baseConfig,
                    placeholder: 'Selecciona quién recibe…',
                })
                : null;

            // Autorizó (solo admin)
            const autorizaEl = document.getElementById('autoriza_user_id');
            const tsAutoriza = autorizaEl
              ? new TomSelect('#autoriza_user_id', {
                  ...baseConfig,
                  placeholder: 'Selecciona quién autoriza…',
                })
              : null;

            // 🔄 Sincronizar "Recibí" con "Colaborador"
            if (tsColaborador && tsRecibi) {
                // Cuando cambie el colaborador → actualizar "Recibí"
                tsColaborador.on('change', (value) => {
                    if (value && tsRecibi.options[value]) {
                        tsRecibi.setValue(value, true);
                    }
                });

                // Al cargar la página: si "Recibí" está vacío pero hay colaborador, copiarlo
                const colValue = tsColaborador.getValue();
                if (!tsRecibi.getValue() && colValue && tsRecibi.options[colValue]) {
                    tsRecibi.setValue(colValue, true);
                }
            }
        });
    </script>
@endpush

</x-app-layout>