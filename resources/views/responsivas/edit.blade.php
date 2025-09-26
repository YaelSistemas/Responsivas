<x-app-layout title="Editar responsiva {{ $responsiva->folio }}">
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight text-center">
      Editar responsiva — {{ $responsiva->folio }}
    </h2>
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
    .btn{background:#2563eb;color:#fff;padding:10px 16px;border-radius:8px;font-weight:600;border:none}
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
  </style>

  @php
    // ====== preparar dataset para el selector de series ======
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

    $motivoDefault     = old('motivo_entrega', $responsiva->motivo_entrega ?? 'asignacion');
    $colDefault        = old('colaborador_id', $responsiva->colaborador_id);
    $recibiDefaultId   = old('recibi_colaborador_id', $responsiva->recibi_colaborador_id ?: $responsiva->colaborador_id);
    $entregoDefaultId  = old('entrego_user_id', $responsiva->user_id);
    $autorizaDefaultId = old('autoriza_user_id', $responsiva->autoriza_user_id);
    $fsolDefault       = old('fecha_solicitud', $responsiva->fecha_solicitud?->toDateString());
    $fentDefault       = old('fecha_entrega',   $responsiva->fecha_entrega?->toDateString());
    $selSeries         = collect(old('series_ids', $selectedSeries ?? []))->map(fn($v)=> (string)$v)->all();
  @endphp

  <!-- Envoltura de zoom -->
  <div class="zoom-outer">
    <div class="zoom-inner">
      @can('responsivas.edit')
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

            <form method="POST" action="{{ route('responsivas.update', $responsiva) }}">
              @csrf @method('PUT')

              {{-- ======= Datos ======= --}}
              <div class="section-sep">
                <div class="line"></div>
                <div class="label">Datos</div>
                <div class="line"></div>
              </div>

              <div class="grid2 row">
                <div>
                  <label>Motivo de entrega</label>
                  <select name="motivo_entrega">
                    <option value="asignacion"           @selected($motivoDefault==='asignacion')>Asignación</option>
                    <option value="prestamo_provisional" @selected($motivoDefault==='prestamo_provisional')>Préstamo provisional</option>
                  </select>
                </div>
                <div>
                  <label>Colaborador</label>
                  <select name="colaborador_id" id="colaborador_id" required>
                    <option value="" disabled>Selecciona colaborador…</option>
                    @foreach($colaboradores as $c)
                      <option value="{{ $c->id }}" @selected((string)$colDefault===(string)$c->id)>{{ $c->nombre }}</option>
                    @endforeach
                  </select>
                  @error('colaborador_id') <div class="err">{{ $message }}</div> @enderror
                </div>
              </div>

              <div class="grid2 row">
                <div>
                  <label>Fecha de solicitud</label>
                  <input type="date" name="fecha_solicitud" value="{{ $fsolDefault }}">
                  @error('fecha_solicitud') <div class="err">{{ $message }}</div> @enderror
                </div>
                <div>
                  <label>Fecha de entrega <span class="hint">(requerida)</span></label>
                  <input type="date" name="fecha_entrega" value="{{ $fentDefault }}" required>
                  @error('fecha_entrega') <div class="err">{{ $message }}</div> @enderror
                </div>
              </div>

              {{-- ======= Productos ======= --}}
              <div class="section-sep">
                <div class="line"></div>
                <div class="label">Productos</div>
                <div class="line"></div>
              </div>

              <div class="row toolrow">
                <input id="searchBox" placeholder="Buscar por serie / producto…" />
                <div class="toolbar-right">
                  <button type="button" class="btn-gray" id="btnSelectVisible">Seleccionar visibles</button>
                  <button type="button" class="btn-gray" id="btnClearSel">Limpiar selección</button>
                </div>
              </div>

              <div class="row">
                <label>Series (selecciona una o varias)</label>
                <select id="seriesSelect" name="series_ids[]" multiple size="12" required></select>
                <div class="hint">Incluye las series actuales + disponibles. Quitar una serie la libera.</div>
                @error('series_ids') <div class="err">{{ $message }}</div> @enderror
                @error('series_ids.*') <div class="err">{{ $message }}</div> @enderror
              </div>

              {{-- ======= Firmas ======= --}}
              <div class="section-sep">
                <div class="line"></div>
                <div class="label">Firmas</div>
                <div class="line"></div>
              </div>

              <div class="grid3 row">
                <div>
                  <label>Entregó (solo admin)</label>
                  <select name="entrego_user_id" id="entrego_user_id">
                    <option value="">— Selecciona —</option>
                    @foreach($admins as $u)
                      <option value="{{ $u->id }}" @selected((string)$entregoDefaultId===(string)$u->id)>{{ $u->name }}</option>
                    @endforeach
                  </select>
                </div>
                <div>
                  <label>Recibí (colaborador)</label>
                  <select name="recibi_colaborador_id" id="recibi_colaborador_id">
                    <option value="">— Selecciona —</option>
                    @foreach($colaboradores as $c)
                      <option value="{{ $c->id }}" @selected((string)$recibiDefaultId===(string)$c->id)>{{ $c->nombre }}</option>
                    @endforeach
                  </select>
                  <div class="hint">Se sincroniza con “Colaborador”, puedes elegir otro.</div>
                </div>
                <div>
                  <label>Autorizó (solo admin)</label>
                  <select name="autoriza_user_id" id="autoriza_user_id">
                    <option value="">— Selecciona —</option>
                    @foreach($admins as $u)
                      <option value="{{ $u->id }}" @selected((string)$autorizaDefaultId===(string)$u->id)>{{ $u->name }}</option>
                    @endforeach
                  </select>
                </div>
              </div>

              <div class="grid2">
                <a href="{{ route('responsivas.show', $responsiva) }}" class="btn-cancel">Cancelar</a>
                <button type="submit" class="btn">Actualizar responsiva</button>
              </div>
            </form>
          </div>
        </div>

        <script>
          (function(){
            const DATA   = @json($data);
            const PRESEL = new Set(@json($selSeries));
            const select = document.getElementById('seriesSelect');
            const search = document.getElementById('searchBox');
            const btnAll = document.getElementById('btnSelectVisible');
            const btnClr = document.getElementById('btnClearSel');

            // Sincroniza "Recibí" con "Colaborador"
            const colSel = document.getElementById('colaborador_id');
            const recibi = document.getElementById('recibi_colaborador_id');
            if (colSel && recibi) {
              colSel.addEventListener('change', () => {
                const v = colSel.value;
                if (v && recibi.querySelector(`option[value="${v}"]`)) recibi.value = v;
              });
              if (!recibi.value && colSel.value && recibi.querySelector(`option[value="${colSel.value}"]`)) {
                recibi.value = colSel.value;
              }
            }

            function render(filterText='') {
              const q = (filterText || '').toLowerCase().trim();
              const selected = new Set(Array.from(select.selectedOptions).map(o => String(o.value)));
              // incluir preseleccionadas (primer render)
              PRESEL.forEach(v => selected.add(String(v)));

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
              Array.from(select.options).forEach(o => o.selected = false);
              select.focus();
            });

            render('');
          })();
        </script>
      @else
        <div class="py-6">
          <div class="wrap">
            <p>No tienes permiso para <b>editar</b> responsivas.</p>
            <div style="margin-top:10px;display:flex;gap:8px;">
              <a href="{{ route('responsivas.show', $responsiva) }}" class="btn-cancel" style="text-decoration:none">← Volver a la responsiva</a>
              <a href="{{ route('responsivas.index') }}" class="btn" style="text-decoration:none;background:#2563eb">Ir al listado</a>
            </div>
          </div>
        </div>
      @endcan
    </div>
  </div>
</x-app-layout>
