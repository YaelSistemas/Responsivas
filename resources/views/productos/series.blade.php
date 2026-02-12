@php
  $tituloProd = trim("{$producto->nombre} {$producto->marca} {$producto->modelo}");

  // ✅ Historial SOLO por ROL
  $isAdmin = auth()->check() && auth()->user()->hasRole('Administrador');

  // ✅ Acciones: mostrar columna si tiene AL MENOS UNO (edit o delete)
  $canEdit    = auth()->check() && auth()->user()->can('productos.edit');
  $canDelete  = auth()->check() && auth()->user()->can('productos.delete');
  $canActions = $canEdit || $canDelete;
@endphp

<x-app-layout title="Series - {{ $tituloProd }}">
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight text-center">
      Series — {{ $tituloProd }}
    </h2>
  </x-slot>

  <style>
    /* ====== Zoom responsivo: MISMA VISTA, solo “más pequeña” en pantallas chicas ====== */
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

    /* iOS: evita auto-zoom al enfocar inputs en móvil */
    @media (max-width:768px){
      input, select, textarea{ font-size:16px; }
    }

    /* ====== Estilos propios ====== */
    .page-wrap{max-width:1180px;margin:0 auto}
    .card{background:#fff;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,.06)}
    .btn{display:inline-block;padding:.45rem .8rem;border-radius:.5rem;font-weight:600;text-decoration:none}
    .btn-primary{background:#2563eb;color:#fff}.btn-primary:hover{background:#1e4ed8}
    .btn-light{background:#f3f4f6;border:1px solid #e5e7eb;color:#374151}
    .tbl{width:100%;border-collapse:separate;border-spacing:0}
    .tbl th,.tbl td{padding:.6rem .8rem;vertical-align:middle}
    .tbl thead th{font-weight:700;color:#374151;background:#f9fafb;border-bottom:1px solid #e5e7eb}
    .tbl tbody tr+tr td{border-top:1px solid #f1f5f9}
    .err{color:#dc2626;font-size:12px;margin-top:6px}

    .chips{display:flex;gap:.35rem;flex-wrap:wrap;margin-top:.35rem}
    .chip{font-size:.72rem;border:1px solid #e5e7eb;background:#f3f4f6;color:#374151;border-radius:9999px;padding:.12rem .5rem}
    .badge-mod{font-size:.65rem;background:#fef3c7;border:1px solid #fde68a;color:#92400e;border-radius:9999px;padding:.08rem .4rem}

    .state-wrap{position:relative;display:inline-block}
    .state-select{-webkit-appearance:none;appearance:none;background:#fff;border:1px solid #d1d5db;border-radius:8px;
      padding:.4rem 2rem .4rem .6rem;min-width:160px;font-size:.9rem;line-height:1.25;}
    .state-wrap::after{content:'▾';position:absolute;right:.55rem;top:50%;transform:translateY(-50%);color:#6b7280;pointer-events:none;font-size:.85rem;}
    .modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;z-index:50}
    .modal.open{display:flex}
    .modal .backdrop{position:absolute;inset:0;background:rgba(0,0,0,.45)}
    .modal .panel{position:relative;background:#fff;border-radius:10px;box-shadow:0 15px 35px rgba(0,0,0,.25);
      width:min(900px,96vw);max-height:90vh;padding:18px;display:flex;flex-direction:column;overflow:hidden}
    .modal .panel h3{font-weight:700;font-size:18px;margin:2px 0 10px}
    .modal .close{position:absolute;right:10px;top:10px;font-size:22px;line-height:1;cursor:pointer;color:#6b7280}
    .modal textarea{width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px}
    .modal .actions{display:flex;justify-content:flex-end;gap:10px;margin-top:12px}

    .modal .scroll{overflow:auto;padding-right:4px;margin-bottom:10px}
    .gallery{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px}
    .tile{border:1px solid #e5e7eb;border-radius:8px;padding:8px;text-align:center;background:#fff}
    .tile img{width:100%;height:120px;object-fit:cover;border-radius:6px}
    .tile .meta{font-size:12px;color:#374151;margin-top:6px}

    .upload-row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
    .upload-row input[type="file"]{flex:1 1 260px;min-width:220px}
    .upload-row input[type="text"]{flex:1 1 260px;min-width:220px}
    .upload-row .btn{flex:0 0 auto}
    .hint{font-size:12px;color:#6b7280;margin-top:6px}

    /* centrado para: Subsidiaria (2), Unidad (3), Fotos (4), Estado (5), Historial (6), Acciones (7) */
    .tbl th:nth-child(2), .tbl td:nth-child(2),
    .tbl th:nth-child(3), .tbl td:nth-child(3),
    .tbl th:nth-child(4), .tbl td:nth-child(4),
    .tbl th:nth-child(5), .tbl td:nth-child(5),
    .tbl th:nth-child(6), .tbl td:nth-child(6),
    .tbl th:nth-child(7), .tbl td:nth-child(7){ text-align:center; }

    /* antes era nth-child(3) porque Fotos estaba en 3; ahora Fotos es 4 */
    .tbl td:nth-child(4) form{display:inline-block;}

    .badge-estado{display:inline-flex;align-items:center;justify-content:center;padding:.25rem .75rem;border-radius:9999px;
      font-size:.75rem;font-weight:600;border-width:1px;border-style:solid;}
    .badge-disponible{background:#f3f4f6;color:#374151;border-color:#d1d5db;}
    .badge-asignado{background:#dcfce7;color:#166534;border-color:#4ade80;}
    .badge-prestamo{background:#fef9c3;color:#92400e;border-color:#eab308;}
    .badge-devuelto{background:#dbeafe;color:#1d4ed8;border-color:#93c5fd;}
    .badge-baja{background:#fee2e2;color:#b91c1c;border-color:#fecaca;}
    .badge-reparacion{background:#ffedd5;color:#c2410c;border-color:#fed7aa;}
    .loading::after{
      content: '';
      width:14px;height:14px;border:2px solid #cbd5e1;border-top-color:#1d4ed8;border-radius:50%;
      display:inline-block;margin-left:8px;vertical-align:middle;animation:spin .6s linear infinite;
    }
    @keyframes spin{to{transform:rotate(360deg)}}

    /* ====== Subsidiaria pill azul ====== */
    .sub-pill{display:inline-flex;align-items:center;justify-content:center;padding:.28rem .75rem;border-radius:9999px;font-size:.75rem;font-weight:700;line-height:1;
      border:1px solid #93c5fd;background:#dbeafe;color:#1d4ed8;max-width: 180px;white-space: nowrap;overflow: hidden;text-overflow: ellipsis;}
    .sub-pill--none{border-color:#93c5fd;background:#dbeafe;color:#1d4ed8;}

    /* ====== Unidad de servicio pill morado ====== */
    .unit-pill{display:inline-flex;align-items:center;justify-content:center;padding:.28rem .75rem;border-radius:9999px;font-size:.75rem;font-weight:700;line-height:1;
      border:1px solid #c4b5fd;background:#ede9fe;color:#5b21b6;max-width: 180px;white-space: nowrap;overflow: hidden;text-overflow: ellipsis;}
    .unit-pill--none{border-color:#c4b5fd;background:#ede9fe;color:#5b21b6;}

    /* CAMBIO: Bulk modal tipo CREATE (tarjetas/accordion) */
    #bulk-modal .panel{ width:min(1100px,96vw); }

    .bulk-scroll{overflow:auto;padding-right:6px;margin-top:10px;max-height: calc(90vh - 160px);}
    .serie-card{border:1px solid #e5e7eb;border-radius:12px;padding:12px;background:#fff;}
    .serie-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:6px 6px 10px;}
    .serie-title{font-weight:800;font-size:14px;line-height:1.2;}
    .serie-sub{font-size:12px;color:#6b7280;margin-top:2px;}
    .serie-toggle{border:1px solid #e5e7eb;background:#fff;border-radius:10px;padding:6px 10px;cursor:pointer;font-size:14px;line-height:1;}
    .serie-body{ display:none; padding:10px 6px 6px; }
    .serie-card.open .serie-body{ display:block; }

    .grid-3{display:grid;grid-template-columns:repeat(3,minmax(220px,1fr));gap:12px;align-items:end;}
    .grid-2{display:grid;grid-template-columns:repeat(2,minmax(240px,1fr));gap:12px;align-items:end;}
    .grid-1{ display:grid; grid-template-columns:1fr; gap:12px; }

    @media (max-width: 900px){
      .grid-3{ grid-template-columns:1fr; }
      .grid-2{ grid-template-columns:1fr; }
    }

    .field label{ display:block; font-size:12px; color:#374151; font-weight:700; margin-bottom:6px; }
    .inp{width:100%;border:1px solid #d1d5db;border-radius:10px;padding:10px 12px;outline:none;}
    .inp:focus{ border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.12); }

    .section-title{font-weight:800;font-size:13px;margin:14px 0 10px;color:#111827;}
    .muted{ color:#6b7280; font-size:12px; }
    .alm-wrap{border:1px dashed #d1d5db;border-radius:12px;padding:10px;background:#f9fafb;}
    .alm-row{display:grid;grid-template-columns: 1fr 1fr auto;gap:10px;align-items:end;margin-top:10px;}
    @media (max-width: 900px){
      .alm-row{ grid-template-columns:1fr; }
    }

    .btn-green{background:#16a34a;color:#fff;border:none;padding:.45rem .9rem;border-radius:.6rem;font-weight:800;cursor:pointer;}
    .btn-green:hover{ background:#15803d; }
    .btn-red{background:#ef4444;color:#fff;border:none;padding:.45rem .9rem;border-radius:.6rem;font-weight:800;cursor:pointer;}
    .btn-red:hover{ background:#dc2626; }

    .btn-gray{background:#f3f4f6;border:1px solid #e5e7eb;color:#374151;padding:.45rem .9rem;border-radius:.6rem;font-weight:800;cursor:pointer;}

    /* ✅ NUEVO: estilo accesorio checkbox (visual consistente) */
    .acc-grid{
      display:grid;
      grid-template-columns:repeat(2,minmax(220px,1fr));
      gap:10px;
      margin-top:6px;
    }
    @media (max-width: 900px){
      .acc-grid{ grid-template-columns:1fr; }
    }
    .acc-item{
      display:flex;
      gap:10px;
      align-items:center;
      padding:10px 12px;
      border:1px solid #e5e7eb;
      border-radius:10px;
      background:#fff;
      font-size:13px;
      color:#374151;
      font-weight:700;
    }
    .acc-item input{ width:16px; height:16px; }
  </style>

  <!-- Escalamos SOLO el contenido principal.
       Dejamos los modales FUERA del .zoom-inner para que su position:fixed
       siga anclado al viewport y no al contenedor transformado. -->
  <div class="zoom-outer">
    <div class="zoom-inner">
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
              <form id="search-form" method="GET" class="flex items-center gap-2" onsubmit="return false">
                <label for="q" class="text-sm text-gray-600">Buscar:</label>
                <input id="q" name="q" value="{{ $q ?? '' }}" autocomplete="off"
                       class="border rounded px-3 py-1 focus:outline-none" placeholder="Serie...">
              </form>

              @can('productos.create')
                <button id="open-bulk" type="button" class="btn btn-primary">Alta masiva</button>
              @endcan

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
                  <th class="text-center" style="width:130px">Subsidiaria</th>
                  <th class="text-center" style="width:130px">Unidad</th>
                  <th class="text-center" style="width:130px">Fotos</th>
                  <th class="text-center" style="width:200px">Estado</th>

                  {{-- ✅ Historial SOLO Admin --}}
                  @if($isAdmin)
                    <th class="text-center" style="width:120px">Historial</th>
                  @endif

                  {{-- ✅ Acciones SOLO si tiene edit O delete --}}
                  @if($canActions)
                    <th class="text-center" style="width:120px">Acciones</th>
                  @endif
                </tr>
              </thead>

              <tbody id="series-tbody">
                @forelse($series as $s)
                  @php
                    // sp = valores efectivos por serie (producto + overrides) si existe accessor specs
                    $sp = (array) ($s->specs ?? $s->especificaciones ?? []);
                    $tipoProd = (string) ($producto->tipo ?? '');

                    // ✅ NUEVO: fecha compra / numero / accesorios (si existen)
                    $fechaCompra = data_get($sp, 'fecha_compra');
                    $numeroCel   = data_get($sp, 'numero_celular');
                    $acc         = data_get($sp, 'accesorios', []);
                    if (!is_array($acc)) $acc = [];

                    // Descripción por serie (nuevo: observaciones) + fallbacks legacy/producto
                    $descSerieObs = $s->observaciones ?? null;
                    $descSerieJson = data_get($s->especificaciones, 'descripcion');
                    $descProd  = $producto->descripcion ?? null;
                    $descRaw   = $descSerieObs ?? $descSerieJson ?? $descProd;

                    $desc = $descRaw
                      ? preg_replace('/\s+/u', ' ', str_replace(["\r\n","\n","\r"], ' ', $descRaw))
                      : null;
                  @endphp

                  <tr>
                    {{-- Serie + chips --}}
                    <td>
                      @php
                        $overRaw = (array) ($s->especificaciones ?? []);

                        $clean = function ($v) use (&$clean) {
                          if (is_array($v)) {
                            $tmp = [];
                            foreach ($v as $k => $vv) {
                              $cv = $clean($vv);
                              if ($cv !== null) $tmp[$k] = $cv;
                            }
                            return count($tmp) ? $tmp : null;
                          }

                          if ($v === null) return null;
                          if (is_string($v) && trim($v) === '') return null;

                          if (is_numeric($v) && (float)$v <= 0) return null;

                          return $v;
                        };

                        $overClean = $clean($overRaw);
                        $hasOverrides = !empty($overClean);

                        $wasEdited = $s->updated_at && $s->created_at
                          ? $s->updated_at->gt($s->created_at)
                          : false;

                        $showMod = $wasEdited && $hasOverrides;
                      @endphp

                      <div class="flex items-center gap-2">
                        <span class="font-mono">{{ $s->serie }}</span>
                        @if($showMod)
                          <span class="badge-mod">Mod.</span>
                        @endif
                      </div>

                      {{-- ===================== CHIPS POR TIPO ===================== --}}
                      @if($tipoProd === 'equipo_pc')
                        <div class="chips">
                          @if(!empty($sp['color']))
                            <span class="chip">Color: {{ $sp['color'] }}</span>
                          @endif

                          @if(!empty($sp['ram_gb']))
                            <span class="chip">{{ (int)$sp['ram_gb'] }} GB RAM</span>
                          @endif

                          @php
                            $alms = data_get($sp, 'almacenamientos');
                            if (!is_array($alms)) $alms = [];

                            if (empty($alms)) {
                              $old = data_get($sp, 'almacenamiento');
                              if (is_array($old) && (!empty($old['tipo']) || !empty($old['capacidad_gb']))) {
                                $alms = [$old];
                              }
                            }
                          @endphp

                          @foreach($alms as $a)
                            @php
                              $t = strtoupper((string) data_get($a,'tipo',''));
                              $c = data_get($a,'capacidad_gb');
                            @endphp
                            @if($t || $c)
                              <span class="chip">
                                {{ $t ?: 'ALM' }}@if($c) {{ (int)$c }} GB @endif
                              </span>
                            @endif
                          @endforeach

                          @if(!empty($sp['procesador']))
                            <span class="chip">{{ $sp['procesador'] }}</span>
                          @endif

                          {{-- ✅ NUEVO: fecha compra chip (opcional) --}}
                          @if(!empty($fechaCompra))
                            <span class="chip">Compra: {{ \Illuminate\Support\Str::of($fechaCompra)->limit(10,'') }}</span>
                          @endif
                        </div>

                      @elseif($tipoProd === 'celular')
                        <div class="chips">
                          @if(!empty($sp['color']))
                            <span class="chip">Color: {{ $sp['color'] }}</span>
                          @elseif($desc)
                            <span class="chip" title="{{ $desc }}">{{ \Illuminate\Support\Str::limit($desc, 70) }}</span>
                          @endif

                          @if(!empty($sp['almacenamiento_gb']))
                            <span class="chip">{{ (int)$sp['almacenamiento_gb'] }} GB</span>
                          @endif

                          @if(!empty($sp['ram_gb']))
                            <span class="chip">{{ (int)$sp['ram_gb'] }} GB RAM</span>
                          @endif

                          @if(!empty($sp['imei']))
                            <span class="chip">IMEI: {{ $sp['imei'] }}</span>
                          @endif

                          {{-- ✅ NUEVO: número + fecha compra --}}
                          @if(!empty($numeroCel))
                            <span class="chip">Tel: {{ $numeroCel }}</span>
                          @endif
                          @if(!empty($fechaCompra))
                            <span class="chip">Compra: {{ \Illuminate\Support\Str::of($fechaCompra)->limit(10,'') }}</span>
                          @endif

                          {{-- ✅ NUEVO: accesorios (si vienen) --}}
                          @php
                            $accLabels = [];
                            if (!empty($acc['funda'])) $accLabels[] = 'Funda';
                            if (!empty($acc['mica_protectora'])) $accLabels[] = 'Mica';
                            if (!empty($acc['cargador'])) $accLabels[] = 'Cargador';
                            if (!empty($acc['cable_usb'])) $accLabels[] = 'Cable';
                          @endphp
                          @if(!empty($accLabels))
                            <span class="chip" title="{{ implode(', ', $accLabels) }}">
                              Acc: {{ \Illuminate\Support\Str::limit(implode(', ', $accLabels), 40) }}
                            </span>
                          @endif
                        </div>

                      @elseif(in_array($tipoProd, ['impresora','monitor','pantalla','periferico','otro'], true))
                        <div class="chips">
                          @if($desc)
                            <span class="chip" title="{{ $desc }}">
                              {{ \Illuminate\Support\Str::limit($desc, 70) }}
                            </span>
                          @endif

                          {{-- ✅ NUEVO: fecha compra chip (opcional) --}}
                          @if(!empty($fechaCompra))
                            <span class="chip">Compra: {{ \Illuminate\Support\Str::of($fechaCompra)->limit(10,'') }}</span>
                          @endif
                        </div>
                      @endif
                    </td>

                    <td class="text-center">
                      @php
                        $subName = $s->subsidiaria?->nombre ?? 'Sin subsidiaria';
                        $isNone  = empty($s->subsidiaria?->nombre);
                      @endphp

                      <span class="sub-pill {{ $isNone ? 'sub-pill--none' : '' }}" title="{{ $subName }}">
                        {{ $subName }}
                      </span>
                    </td>

                    <td class="text-center">
                      @php
                        $unidadName = $s->unidadServicio?->nombre ?? 'Sin unidad';
                        $unidadNone = empty($s->unidadServicio?->nombre);
                      @endphp

                      <span class="unit-pill {{ $unidadNone ? 'unit-pill--none' : '' }}" title="{{ $unidadName }}">
                        {{ $unidadName }}
                      </span>
                    </td>

                    {{-- Fotos --}}
                    <td>
                      <a href="#" class="text-blue-600 hover:underline" data-open-fotos="{{ $s->id }}">
                        Fotos ({{ $s->fotos->count() }})
                      </a>
                    </td>

                    {{-- Estado (solo lectura, calculado) --}}
                    <td class="text-center">
                      @php
                        $estadoRaw = $s->estado;
                        $estado    = $estadoRaw;

                        if ($estadoRaw === 'asignado') {
                          $ultimoEstado = $s->historial()
                            ->whereIn('accion', ['asignacion', 'edicion_asignacion'])
                            ->orderByDesc('id')
                            ->first();

                          $estadoLogico = $ultimoEstado->estado_nuevo ?? null;

                          if ($estadoLogico === 'prestamo_provisional') {
                            $estado = 'prestamo';
                          } elseif ($estadoLogico === 'asignado') {
                            $estado = 'asignado';
                          }
                        }

                        $labels = [
                          'disponible' => 'Disponible',
                          'asignado'   => 'Asignado',
                          'prestamo'   => 'Préstamo',
                          'devuelto'   => 'Devuelto',
                          'baja'       => 'Baja',
                          'reparacion' => 'Reparación',
                        ];

                        $badgeClass = match($estado) {
                          'disponible' => 'badge-disponible',
                          'asignado'   => 'badge-asignado',
                          'prestamo'   => 'badge-prestamo',
                          'devuelto'   => 'badge-devuelto',
                          'baja'       => 'badge-baja',
                          'reparacion' => 'badge-reparacion',
                          default      => 'badge-disponible',
                        };
                      @endphp

                      <span class="badge-estado {{ $badgeClass }}">
                        {{ $labels[$estado] ?? ucfirst($estado) }}
                      </span>
                    </td>

                    {{-- ✅ HISTORIAL SOLO Admin --}}
                    @if($isAdmin)
                      <td class="text-center">
                        <button type="button"
                                class="text-blue-600 hover:text-blue-800 font-semibold"
                                onclick="openSerieHistorial('{{ $s->id }}')">
                                Historial
                        </button>
                      </td>
                    @endif

                    {{-- ✅ ACCIONES SOLO si tiene edit O delete (y muestra solo lo que tenga) --}}
                    @if($canActions)
                      <td>
                        <div class="flex items-center justify-center gap-3">
                          @if($canEdit)
                            <a href="{{ route('productos.series.edit', [$producto, $s]) }}"
                               class="text-gray-800 hover:text-gray-900" title="Editar">
                              <i class="fa-solid fa-pen"></i>
                            </a>
                          @endif

                          @if($canDelete)
                            @if($s->estado === 'disponible')
                              <form method="POST" action="{{ route('productos.series.destroy', [$producto,$s]) }}"
                                    onsubmit="return confirm('¿Eliminar esta serie?');">
                                @csrf @method('DELETE')
                                <button class="text-red-600 hover:text-red-800" type="submit" title="Eliminar">
                                  <i class="fa-solid fa-trash"></i>
                                </button>
                              </form>
                            @endif
                          @endif
                        </div>
                      </td>
                    @endif
                  </tr>
                @empty
                  @php
                    // Columnas base: Serie, Subsidiaria, Unidad, Fotos, Estado => 5
                    $colspan = 5 + ($isAdmin ? 1 : 0) + ($canActions ? 1 : 0);
                  @endphp
                  <tr>
                    <td colspan="{{ $colspan }}" class="text-center text-gray-500 py-6">Sin series.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>

          <div id="series-pagination" class="mt-4">
            {{ $series->appends(['q' => $q ?? ''])->links() }}
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- ==========================================================
       ✅ MODAL Alta masiva estilo CREATE (accordion)
       Mantiene el POST a la MISMA ruta: productos.series.store
       ✅ CAMBIOS:
         - PC: fecha_compra a la derecha del procesador
         - Otros: fecha_compra debajo de descripcion
         - Cel: numero_celular + fecha_compra (misma fila) + accesorios debajo
     ========================================================== --}}
  @can('productos.create')
    @php
      $tipoProd = (string) ($producto->tipo ?? '');
    @endphp

    <div id="bulk-modal" class="modal" aria-hidden="true" role="dialog" aria-modal="true">
      <div class="backdrop" data-close="1"></div>

      <div class="panel">
        <button class="close" type="button" aria-label="Cerrar" data-close="1">&times;</button>
        <h3>Alta masiva de series</h3>
        <div class="hint" style="margin-bottom:10px">
          Agrega varias series y asigna su subsidiaria/unidad. Se crearán como <b>Disponible</b>.
        </div>

        <form id="bulkForm" method="POST" action="{{ route('productos.series.store', $producto) }}">
          @csrf

          <div class="bulk-scroll">
            <div id="rowsWrap" style="display:flex;flex-direction:column;gap:14px;"></div>

            <div style="margin-top:14px;">
              <button type="button" id="btnAddRow" class="btn-green">+ Agregar serie</button>
            </div>
          </div>

          <div class="actions" style="margin-top:16px;">
            <button type="button" class="btn-gray" data-close="1">Cancelar</button>
            <button type="submit" class="btn btn-primary" id="btnBulkSave">Guardar</button>
          </div>
        </form>
      </div>
    </div>
  @endcan
  {{-- ========================================================== --}}

  {{-- MODALES DE FOTOS (uno por serie) --}}
  @foreach($series as $s)
    <div id="fotos-modal-{{ $s->id }}" class="modal" aria-hidden="true">
      <div class="backdrop" data-close="1"></div>
      <div class="panel">
        <button class="close" type="button" aria-label="Cerrar" data-close="1">&times;</button>
        <h3>Fotos — Serie {{ $s->serie }}</h3>

        <div class="scroll">
          <div class="gallery">
            @forelse($s->fotos as $f)
              <div class="tile">
                <a href="{{ $f->url }}" target="_blank" rel="noopener">
                  <img src="{{ $f->url }}" alt="foto">
                </a>
                <div class="meta">{{ $f->caption }}</div>

                @can('productos.edit')
                  <form method="POST" action="{{ route('productos.series.fotos.destroy', [$producto,$s,$f]) }}"
                        onsubmit="return confirm('¿Eliminar esta foto?')" style="margin-top:6px">
                    @csrf @method('DELETE')
                    <button class="btn btn-light" type="submit">Eliminar</button>
                  </form>
                @endcan
              </div>
            @empty
              <div style="grid-column:1/-1;color:#6b7280;text-align:center">Sin fotos.</div>
            @endforelse
          </div>
        </div>

        @can('productos.edit')
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
        @endcan
      </div>
    </div>
  @endforeach

  {{-- CONTENEDOR GLOBAL PARA MODAL AJAX DE HISTORIAL --}}
  <div id="ajax-modal-container"></div>

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

  <script>
    (function(){
      const input = document.getElementById('q');
      const tbody = document.getElementById('series-tbody');
      const pager = document.getElementById('series-pagination');
      const form  = document.getElementById('search-form');

      form.addEventListener('keydown', (e)=>{ if(e.key === 'Enter') e.preventDefault(); });

      let t=null, controller=null;
      function debounceFetch(){
        clearTimeout(t);
        t = setTimeout(runFetch, 300);
        input.classList.add('loading');
      }

      function buildURL(q, page){
        const url = new URL(window.location.href);
        url.searchParams.set('q', q);
        if(page) url.searchParams.set('page', page); else url.searchParams.delete('page');
        return url;
      }

      async function runFetch(page=null){
        const q = input.value || '';
        const url = buildURL(q, page);

        if(controller) controller.abort();
        controller = new AbortController();

        try{
          const res = await fetch(url.toString(), {
            headers: {'X-Requested-With':'XMLHttpRequest'},
            signal: controller.signal
          });
          const html = await res.text();
          const doc  = new DOMParser().parseFromString(html, 'text/html');

          const newBody = doc.querySelector('#series-tbody');
          const newPager= doc.querySelector('#series-pagination');

          if(newBody) tbody.replaceChildren(...newBody.childNodes);
          if(newPager) pager.replaceChildren(...newPager.childNodes);

          window.history.replaceState({}, '', url.toString());

          rebindFotoModals();
          rebindPaginationLinks();
        }catch(err){
          if(err.name !== 'AbortError'){ console.error(err); }
        }finally{
          input.classList.remove('loading');
        }
      }

      function rebindFotoModals(){
        document.querySelectorAll('[data-open-fotos]').forEach(a=>{
          a.addEventListener('click', (e)=>{
            e.preventDefault();
            const id = a.getAttribute('data-open-fotos');
            document.getElementById('fotos-modal-'+id)?.classList.add('open');
          }, { once:true });
        });
      }

      function rebindPaginationLinks(){
        pager.querySelectorAll('a').forEach(a=>{
          a.addEventListener('click', (e)=>{
            e.preventDefault();
            const u = new URL(a.href);
            const page = u.searchParams.get('page') || null;
            runFetch(page);
          }, { once:true });
        });
      }

      input.addEventListener('input', debounceFetch);
      rebindPaginationLinks();

    })();
  </script>

  {{-- ✅ Historial JS solo para Admin (si no, ni existe el botón) --}}
  @if($isAdmin)
    <script>
    async function openSerieHistorial(id) {
        try {
            const res = await fetch(`/producto-series/${id}/historial`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!res.ok) throw new Error(`HTTP ${res.status}`);

            const html = await res.text();

            const existing = document.querySelector('[data-modal-backdrop]');
            if (existing) existing.remove();

            document.body.insertAdjacentHTML('beforeend', html);
            document.body.classList.add('modal-open');

        } catch (error) {
            console.error("Error al abrir historial:", error);
            alert("No se pudo abrir el historial de la serie.");
        }
    }

    document.addEventListener('click', (e) => {
        const closeBtn = e.target.closest('[data-modal-close]');
        const backdrop = e.target.closest('[data-modal-backdrop]');

        if (closeBtn && backdrop) {
            backdrop.remove();
            document.body.classList.remove('modal-open');
        }

        if (backdrop && !e.target.closest('.colab-modal')) {
            backdrop.remove();
            document.body.classList.remove('modal-open');
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const backdrop = document.querySelector('[data-modal-backdrop]');
            if (backdrop) {
                backdrop.remove();
                document.body.classList.remove('modal-open');
            }
        }
    });
    </script>
  @endif

  {{-- ==========================================================
       ✅ JS Alta masiva estilo CREATE (accordion)
       ✅ CAMBIOS:
         - PC: fecha_compra a la derecha de procesador
         - Otros: fecha_compra debajo de descripcion
         - Cel: numero_celular + fecha_compra y accesorios debajo
     ========================================================== --}}
  @can('productos.create')
  <script>
  (function(){
    const modal   = document.getElementById('bulk-modal');
    const openBtn = document.getElementById('open-bulk');
    const form    = document.getElementById('bulkForm');
    const wrap    = document.getElementById('rowsWrap');
    const addBtn  = document.getElementById('btnAddRow');
    const btnSave = document.getElementById('btnBulkSave');

    const tipoProd = @json((string)($producto->tipo ?? ''));

    const SUBS_OPTS = @json(collect($subsidiarias ?? [])->map(fn($s)=>['id'=>$s->id,'nombre'=>$s->nombre])->values());
    const UNI_OPTS  = @json(collect($unidadesServicio ?? [])->map(fn($u)=>['id'=>$u->id,'nombre'=>$u->nombre])->values());

    const OLD_ROWS = @json(old('series', []));

    function esc(v){
      return String(v ?? '')
        .replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;')
        .replaceAll('"','&quot;').replaceAll("'","&#039;");
    }

    function optHTML(list, selected){
      return (list || []).map(x =>
        `<option value="${x.id}" ${String(selected)===String(x.id) ? 'selected':''}>${esc(x.nombre)}</option>`
      ).join('');
    }

    function openModal(){
      modal.classList.add('open');
      setTimeout(()=> wrap?.querySelector('input[name*="[serie]"]')?.focus(), 30);
    }
    function closeModal(){ modal.classList.remove('open'); }

    openBtn?.addEventListener('click', openModal);
    modal?.addEventListener('click', (e)=>{ if(e.target.dataset.close) closeModal(); });
    document.addEventListener('keydown', (e)=>{ if(e.key==='Escape' && modal?.classList.contains('open')) closeModal(); });

    function almRowHTML(i, j, tipoVal='', capVal=''){
      return `
        <div class="alm-row" data-alm-row="${i}">
          <div class="field">
            <label>Tipo</label>
            <select class="inp" name="series[${i}][specs][almacenamientos][${j}][tipo]">
              <option value="">Selecciona...</option>
              <option value="SSD" ${String(tipoVal).toUpperCase()==='SSD'?'selected':''}>SSD</option>
              <option value="HDD" ${String(tipoVal).toUpperCase()==='HDD'?'selected':''}>HDD</option>
              <option value="M.2" ${String(tipoVal).toUpperCase()==='M.2'?'selected':''}>M.2</option>
            </select>
          </div>

          <div class="field">
            <label>Capacidad (GB)</label>
            <input class="inp" type="number" name="series[${i}][specs][almacenamientos][${j}][capacidad_gb]" value="${esc(capVal)}" placeholder="Ej. 512">
          </div>

          <div style="display:flex;justify-content:flex-end;">
            <button type="button" class="btn-red" data-del-alm="1" style="padding:.45rem .85rem;">Quitar</button>
          </div>
        </div>
      `;
    }

    function accesoriosHTML(i, specs){
      const acc = (specs && typeof specs === 'object' && specs.accesorios && typeof specs.accesorios === 'object')
        ? specs.accesorios
        : {};

      const checked = (v) => (v === true || v === 1 || v === '1' || v === 'on');

      return `
        <div class="section-title">Accesorios</div>
        <div class="acc-grid">
          <label class="acc-item">
            <input type="checkbox" name="series[${i}][specs][accesorios][funda]" value="1" ${checked(acc.funda) ? 'checked':''}>
            Funda
          </label>

          <label class="acc-item">
            <input type="checkbox" name="series[${i}][specs][accesorios][mica_protectora]" value="1" ${checked(acc.mica_protectora) ? 'checked':''}>
            Mica protectora
          </label>

          <label class="acc-item">
            <input type="checkbox" name="series[${i}][specs][accesorios][cargador]" value="1" ${checked(acc.cargador) ? 'checked':''}>
            Cargador
          </label>

          <label class="acc-item">
            <input type="checkbox" name="series[${i}][specs][accesorios][cable_usb]" value="1" ${checked(acc.cable_usb) ? 'checked':''}>
            Cable USB
          </label>
        </div>
      `;
    }

    function buildSpecsHTML(i, data){
      const specs = (data && typeof data === 'object') ? (data.specs || {}) : {};
      const get = (k, fallback='') => (specs?.[k] ?? data?.[k] ?? fallback);

      // ✅ nuevo campo común
      const fechaCompraVal = get('fecha_compra', '');

      if(tipoProd === 'celular'){
        return `
          <div class="section-title">Especificaciones (por serie) - Celular / Teléfono</div>

          <div class="grid-2">
            <div class="field">
              <label>Descripción o Color</label>
              <input class="inp" name="series[${i}][specs][color]" value="${esc(get('color'))}" placeholder="Ej. Azul / Negro">
            </div>
            <div class="field">
              <label>Almacenamiento (GB)</label>
              <input class="inp" type="number" name="series[${i}][specs][almacenamiento_gb]" value="${esc(get('almacenamiento_gb'))}" placeholder="Ej. 128">
            </div>
            <div class="field">
              <label>RAM (GB) (opcional)</label>
              <input class="inp" type="number" name="series[${i}][specs][ram_gb]" value="${esc(get('ram_gb'))}" placeholder="Ej. 6">
            </div>
            <div class="field">
              <label>IMEI</label>
              <input class="inp" name="series[${i}][specs][imei]" value="${esc(get('imei'))}" placeholder="Ej. 356xxxxxxxxxxxxx">
            </div>

            <!-- ✅ NUEVO: número + fecha compra (misma fila) -->
            <div class="field">
              <label>Número de celular</label>
              <input class="inp" name="series[${i}][specs][numero_celular]" value="${esc(get('numero_celular'))}" placeholder="Ej. 2221234567">
            </div>
            <div class="field">
              <label>Fecha de compra</label>
              <input class="inp" type="date" name="series[${i}][specs][fecha_compra]" value="${esc(fechaCompraVal)}">
            </div>
          </div>

          <!-- ✅ NUEVO: accesorios debajo -->
          ${accesoriosHTML(i, specs)}
        `;
      }

      if(tipoProd === 'equipo_pc'){
        const alms = Array.isArray(specs?.almacenamientos) ? specs.almacenamientos : [];
        const rows = (alms.length ? alms : [{}]).map((a, j) => {
          const t = a?.tipo ?? '';
          const c = a?.capacidad_gb ?? '';
          return almRowHTML(i, j, t, c);
        }).join('');

        return `
          <div class="section-title">Especificaciones (por serie) - Equipo de cómputo</div>
          <div class="grid-2">
            <div class="field">
              <label>Descripción o Color</label>
              <input class="inp" name="series[${i}][specs][color]" value="${esc(get('color'))}" placeholder="Ej. Negro / Gris">
            </div>
            <div class="field">
              <label>RAM (GB)</label>
              <input class="inp" type="number" name="series[${i}][specs][ram_gb]" value="${esc(get('ram_gb'))}" placeholder="Ej. 16">
            </div>
          </div>

          <div class="section-title" style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
            <span>Almacenamientos (puedes agregar más de uno)</span>
            <button type="button" class="btn-green" data-add-alm="1" data-serie-index="${i}" style="padding:.35rem .7rem;border-radius:.55rem;">
              + Agregar almacenamiento
            </button>
          </div>

          <div class="alm-wrap" data-alm-wrap="${i}">
            ${rows}
            <div class="muted" style="margin-top:8px;">Si capturas capacidad, selecciona el tipo (SSD/HDD/M.2).</div>
          </div>

          <!-- ✅ CAMBIO: Procesador + Fecha de compra (lado derecho) -->
          <div class="section-title">Procesador</div>
          <div class="grid-2">
            <div class="field">
              <label>Procesador</label>
              <input class="inp" name="series[${i}][specs][procesador]" value="${esc(get('procesador'))}" placeholder="Ej. Intel Core i5-1135G7">
            </div>
            <div class="field">
              <label>Fecha de compra</label>
              <input class="inp" type="date" name="series[${i}][specs][fecha_compra]" value="${esc(fechaCompraVal)}">
            </div>
          </div>
        `;
      }

      // impresora/monitor/pantalla/periferico/otro
      return `
        <div class="section-title">Descripción (por serie)</div>
        <div class="grid-1">
          <div class="field">
            <label>Detalles relevantes de esta pieza/serie</label>
            <textarea class="inp" name="series[${i}][observaciones]" rows="4" placeholder="Detalles relevantes de esta pieza/serie...">${esc(data?.observaciones ?? '')}</textarea>
          </div>

          <!-- ✅ NUEVO: fecha compra debajo -->
          <div class="field">
            <label>Fecha de compra</label>
            <input class="inp" type="date" name="series[${i}][specs][fecha_compra]" value="${esc(fechaCompraVal)}">
          </div>
        </div>
      `;
    }

    function serieCardHTML(i, data){
      const serie = data?.serie ?? '';
      const subId = data?.subsidiaria_id ?? '';
      const uniId = data?.unidad_servicio_id ?? '';

      return `
        <div class="serie-card open" data-serie-card="1">
          <div class="serie-head">
            <div>
              <div class="serie-title">Serie ${i+1}</div>
              <div class="serie-sub">Captura la información de esta serie</div>
            </div>

            <div style="display:flex;gap:10px;align-items:center;">
              <button type="button" class="serie-toggle" data-toggle="1" title="Contraer/expandir">▾</button>
            </div>
          </div>

          <div class="serie-body">
            <div class="section-title">Series + Subsidiaria + Unidad de servicio</div>

            <div class="grid-3">
              <div class="field">
                <label>Serie</label>
                <input class="inp" name="series[${i}][serie]" value="${esc(serie)}" placeholder="Ej. ABC123" autocomplete="off" required>
              </div>

              <div class="field">
                <label>Subsidiaria</label>
                <select class="inp" name="series[${i}][subsidiaria_id]">
                  <option value="">— Sin subsidiaria —</option>
                  ${optHTML(SUBS_OPTS, subId)}
                </select>
              </div>

              <div class="field">
                <label>Unidad de servicio</label>
                <select class="inp" name="series[${i}][unidad_servicio_id]">
                  <option value="">— Sin unidad —</option>
                  ${optHTML(UNI_OPTS, uniId)}
                </select>
              </div>
            </div>

            ${buildSpecsHTML(i, data)}

            <div style="display:flex;justify-content:flex-end;margin-top:14px;">
              <button type="button" class="btn-red" data-remove-serie="1">Quitar</button>
            </div>
          </div>
        </div>
      `;
    }

    function renderAllFromOld(){
      wrap.innerHTML = '';
      const rows = Array.isArray(OLD_ROWS) ? OLD_ROWS : [];
      if(rows.length){
        rows.forEach((r, i)=> wrap.insertAdjacentHTML('beforeend', serieCardHTML(i, r)));
      } else {
        wrap.insertAdjacentHTML('beforeend', serieCardHTML(0, {}));
      }
    }

    function renumberAll(){
      const cards = [...wrap.querySelectorAll('[data-serie-card]')];

      cards.forEach((card, i)=>{
        const title = card.querySelector('.serie-title');
        if(title) title.textContent = `Serie ${i+1}`;

        card.querySelectorAll('[name^="series["]').forEach(el=>{
          el.name = el.name.replace(/^series\[\d+\]/, `series[${i}]`);
        });

        if(tipoProd === 'equipo_pc'){
          const almRows = [...card.querySelectorAll('[data-alm-row]')];
          almRows.forEach((r, j)=>{
            r.querySelectorAll('[name*="[almacenamientos]"]').forEach(el=>{
              el.name = el.name.replace(/\[almacenamientos\]\[\d+\]/, `[almacenamientos][${j}]`);
            });
          });
        }

        const addAlm = card.querySelector('[data-add-alm]');
        if(addAlm) addAlm.setAttribute('data-serie-index', String(i));

        const almWrap = card.querySelector('[data-alm-wrap]');
        if(almWrap) almWrap.setAttribute('data-alm-wrap', String(i));

        card.querySelectorAll('[data-alm-row]').forEach(row=>{
          row.setAttribute('data-alm-row', String(i));
        });
      });
    }

    renderAllFromOld();
    renumberAll();

    addBtn?.addEventListener('click', ()=>{
      const i = wrap.querySelectorAll('[data-serie-card]').length;
      wrap.insertAdjacentHTML('beforeend', serieCardHTML(i, {}));
      renumberAll();
      wrap.querySelector(`input[name="series[${i}][serie]"]`)?.focus();
    });

    wrap.addEventListener('click', (e)=>{
      const tg = e.target.closest('[data-toggle]');
      if(tg){
        const card = tg.closest('.serie-card');
        if(card){
          card.classList.toggle('open');
          tg.textContent = card.classList.contains('open') ? '▾' : '▸';
        }
        return;
      }

      const rm = e.target.closest('[data-remove-serie]');
      if(rm){
        const cards = wrap.querySelectorAll('[data-serie-card]');
        if(cards.length <= 1){
          const card = rm.closest('.serie-card');
          card?.querySelectorAll('input,select,textarea').forEach(el=>{
            if(el.tagName === 'SELECT') el.value = '';
            else el.value = '';
          });

          // limpiar checks accesorios si aplica
          card?.querySelectorAll('input[type="checkbox"]').forEach(ch=> ch.checked = false);
          return;
        }
        rm.closest('.serie-card')?.remove();
        renumberAll();
        return;
      }

      const addAlmBtn = e.target.closest('[data-add-alm]');
      if(addAlmBtn && tipoProd === 'equipo_pc'){
        const card = addAlmBtn.closest('.serie-card');
        const idx  = Number(addAlmBtn.getAttribute('data-serie-index') || 0);
        const almWrap = card?.querySelector(`[data-alm-wrap="${idx}"]`);
        if(!almWrap) return;

        const j = almWrap.querySelectorAll('[data-alm-row]').length;
        almWrap.insertAdjacentHTML('beforeend', almRowHTML(idx, j, '', ''));
        renumberAll();
        return;
      }

      const delAlm = e.target.closest('[data-del-alm]');
      if(delAlm && tipoProd === 'equipo_pc'){
        const row = delAlm.closest('.alm-row');
        const card = delAlm.closest('.serie-card');

        const rows = card?.querySelectorAll('.alm-row') || [];
        if(rows.length <= 1){
          row?.querySelectorAll('input,select').forEach(el=> el.value = '');
          return;
        }

        row?.remove();
        renumberAll();
        return;
      }
    });

    let sending = false;
    form?.addEventListener('submit', (e)=>{
      if(sending){ e.preventDefault(); return; }
      sending = true;
      if(btnSave){
        btnSave.disabled = true;
        btnSave.style.opacity = .7;
      }
    });

    @if ($errors->has('series') || collect($errors->keys())->first(fn($k)=> str_starts_with($k, 'series.')))
      openModal();
    @endif

  })();
  </script>
  @endcan
  {{-- ========================================================== --}}

</x-app-layout>
