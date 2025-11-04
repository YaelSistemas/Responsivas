{{-- resources/views/devoluciones/show.blade.php --}}
<x-app-layout title="Devolución {{ $devolucion->folio }}">
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight text-center">
      Devolución {{ $devolucion->folio }}
    </h2>
  </x-slot>

  @php
    // ======= Variables base =======
    $col = $devolucion->entregoColaborador ?? null;
    $recibio = $devolucion->recibidoPor ?? null;
    $psitio  = $devolucion->psitioColaborador ?? null;
    $productos = $devolucion->productos ?? collect();

    // Empresa / logo
    $empresaId     = (int) session('empresa_activa', auth()->user()?->empresa_id);
    $empresaNombre = config('app.name', 'Laravel');
    $logo = asset('images/logos/default.png');
    if (class_exists(\App\Models\Empresa::class) && $empresaId) {
      $emp = \App\Models\Empresa::find($empresaId);
      if ($emp) {
        $empresaNombre = $emp->nombre ?? $empresaNombre;
        if (!empty($emp->logo_url) && filter_var($emp->logo_url, FILTER_VALIDATE_URL)) {
          $logo = $emp->logo_url;
        } else {
          $candidates = [];
          if (!empty($emp->logo_url))  $candidates[] = ltrim($emp->logo_url, '/');
          if (!empty($emp->logo))      $candidates[] = 'images/logos/'.ltrim($emp->logo, '/');
          $slug = \Illuminate\Support\Str::slug($empresaNombre, '-');
          foreach (['png','jpg','jpeg','webp','svg'] as $ext) {
            $candidates[] = "images/logos/{$slug}.{$ext}";
            $candidates[] = "images/logos/empresa-{$empresaId}.{$ext}";
          }
          foreach ($candidates as $rel) {
            if (file_exists(public_path($rel))) { $logo = asset($rel); break; }
          }
        }
      }
    }

    /// Razón social del emisor (subsidiaria o empresa del colaborador)
    $emisorRazon = $empresaNombre;

    // Verifica primero si hay una relación de responsiva
    $responsiva = $devolucion->responsiva ?? null;

    // Si no hay colaborador definido aún, intenta tomarlo de la responsiva
    if (!$col && $responsiva && $responsiva->colaborador) {
        $col = $responsiva->colaborador;
    }

    // Si hay colaborador, intenta determinar su subsidiaria o empresa
    if ($col) {
        $sub = $col->subsidiaria ?? $col?->subsidiary ?? null;
        if (!$sub && !empty($col->subsidiaria_id) && class_exists(\App\Models\Subsidiaria::class)) {
            $sub = \App\Models\Subsidiaria::find($col->subsidiaria_id);
        }

        if ($sub) {
            $emisorRazon = $sub->razon_social
                        ?? $sub->descripcion
                        ?? $sub->nombre_fiscal
                        ?? $sub->razon
                        ?? $sub->nombre
                        ?? $emisorRazon;
        } elseif (isset($col->empresa)) {
            $emisorRazon = $col->empresa->razon_social
                        ?? $col->empresa->descripcion
                        ?? $col->empresa->nombre_fiscal
                        ?? $col->empresa->nombre
                        ?? $emisorRazon;
        }
    }

    // Fechas
    $fechaDevolucionFmt = $devolucion->fecha_devolucion
        ? (\Illuminate\Support\Carbon::parse($devolucion->fecha_devolucion)->format('d-m-Y'))
        : '';

    // Colaborador desde la responsiva (para unidad)
    $responsiva = $devolucion->responsiva ?? null;
    $colResp = $responsiva?->colaborador;
    $colNombreUsuario = $colResp
        ? trim(($colResp->nombre ?? '').' '.($colResp->apellidos ?? ''))
        : '-';

    $colNombreEntrego = $col
        ? trim(($col->nombre ?? '').' '.($col->apellidos ?? ''))
        : '-';

    $unidadServicio = $colResp?->unidad_servicio?->nombre
                    ?? $colResp?->unidadServicio?->nombre
                    ?? $colResp?->unidad
                    ?? '—';

    $recibioNombre = $recibio
        ? trim(($recibio->nombre ?? '').' '.($recibio->apellidos ?? ''))
          ?: ($recibio->name ?? '')
        : '';

    $psitioNombre = $psitio
        ? trim(($psitio->nombre ?? '').' '.($psitio->apellidos ?? ''))
          ?: ($psitio->name ?? '')
        : '';

    // Firmas
    $firmaUrlFor = function ($user) {
      if (!$user) return null;
      $id = $user->id ?? null;
      $name = trim($user->name ?? ($user->nombre ?? ''));
      foreach (['png','jpg','jpeg','webp','svg'] as $ext) {
        if ($id && file_exists(public_path("storage/firmas/{$id}.{$ext}"))) return asset("storage/firmas/{$id}.{$ext}");
        if ($name && file_exists(public_path("storage/firmas/".\Illuminate\Support\Str::slug($name).".{$ext}"))) return asset("storage/firmas/".\Illuminate\Support\Str::slug($name).".{$ext}");
      }
      return null;
    };
    $firmaEntrego = $firmaUrlFor($col);
    $firmaRecibio = $firmaUrlFor($recibio);
    $firmaPsitio  = $firmaUrlFor($psitio);
  @endphp

  <style>
  .zoom-outer{ overflow-x:hidden; }
  .zoom-inner{ --zoom: 1; transform: scale(var(--zoom)); transform-origin: top left; width: calc(100% / var(--zoom)); }
  @media (max-width:1024px){ .zoom-inner{ --zoom:.95; } }
  @media (max-width:768px){  .zoom-inner{ --zoom:.90; } }
  @media (max-width:640px){  .zoom-inner{ --zoom:.70; } }
  @media (max-width:400px){  .zoom-inner{ --zoom:.55; } }

  .sheet { max-width: 940px; margin: 0 auto; }
  .doc { background:#fff; border:1px solid #111; border-radius:6px; padding:18px; box-shadow:0 2px 6px rgba(0,0,0,.08); }

  .actions{ display:flex; gap:10px; margin-bottom:14px; flex-wrap:wrap; align-items:center; }
  .actions .spacer{ flex:1 1 auto; }

  .btn { padding:8px 12px; border-radius:6px; font-weight:600; border:1px solid transparent; }
  .btn-primary { background:#2563eb; color:#fff; }
  .btn-secondary { background:#f3f4f6; color:#111; border-color:#d1d5db; }
  .btn-danger{ background:#dc2626; color:#fff; border-color:#b91c1c; }
  .btn:hover { filter:brightness(.97); }

  .tbl { width:100%; border-collapse:collapse; table-layout:fixed; }
  .tbl th, .tbl td {
    border:1px solid #111;
    padding:6px 8px;
    font-size:12px;
    line-height:1.15;
    vertical-align:middle;
    text-align:center;
  }
  .tbl th{ font-weight:700; text-transform:uppercase; background:#f8fafc; }

  .meta .label{
    font-weight:700;
    font-size:11px;
    text-transform:uppercase;
    white-space:nowrap;
    padding:4px 6px;
    text-align: left !important;
  }

  .meta .val{ font-size:12px; }
  .mark-x { font-weight:700; }
  .meta .label.center { text-align:center !important; }

  .tbl.meta + .tbl.meta{ margin-top:0; }
  .tbl.meta.no-border-top tr:first-child > td,
  .tbl.meta.no-border-top tr:first-child > th{ border-top:0 !important; }

  .hero .logo-cell{ width:28%; }
  .hero .logo-box{ height:120px; display:flex; align-items:center; justify-content:center; }
  .hero .logo-cell img{ max-width:200px; max-height:90px; display:block; }
  .hero .title-row{ text-align:center; }
  .title-main{ font-weight:800; font-size:14px; }
  .title-sub{ font-weight:700; font-size:12px; text-transform:uppercase; }

  .section-title{
    font-weight:800;
    text-align:left;
    margin-top:8px;
    letter-spacing:.3px;
    font-size:12px;
  }

  .section-consta{
    font-weight:800;
    margin-top:8px;
    letter-spacing:.3px;
    font-size:13px;
  }

  .blk{
    margin-top:10px;
    font-size:12px;
    line-height:1.35;
  }

  .fecha-entrega { width: 280px; margin-left: auto; }
  .fecha-entrega .label { width: 55%; }
  .fecha-entrega .val { width: 45%; }


  .firmas-wrap{ margin-top:16px; }
  .firma-row{ display:flex; gap:32px; justify-content:space-between; align-items:flex-start; }
  .sign{ flex:1; }
  .sign-title{ text-align:center; text-transform:uppercase; font-weight:700; margin-bottom:6px; }
  .sign-sub{ text-align:center; font-size:10px; text-transform:uppercase; margin:-2px 0 8px; }
  .sign-space{ height:56px; display:flex; align-items:center; justify-content:center; }
  .firma-img{ max-height:56px; max-width:100%; display:block; margin:0 auto; mix-blend-mode:multiply; }
  .sign-inner{ width:55%; margin:0 auto; }
  .sign-name{ text-align:center; font-size:11px; text-transform:uppercase; margin-bottom:2px; }
  .sign-line{ border-top:1px solid #111; height:1px; }
  .sign-caption{ text-align:center; font-size:10px; text-transform:uppercase; margin-top:4px; }
  </style>

  <div class="zoom-outer">
    <div class="zoom-inner">
      <div class="py-6 sheet">
        <div class="actions">
          <a href="{{ route('devoluciones.index') }}" class="btn btn-secondary">← Devoluciones</a>
          <a href="{{ route('devoluciones.pdf', $devolucion) }}" class="btn btn-primary" target="_blank" rel="noopener">Descargar PDF</a>
        </div>

        <div id="printable" class="doc">
          {{-- ENCABEZADO --}}
          <table class="tbl hero">
            <colgroup><col style="width:28%"><col></colgroup>
            <tr>
              <td class="logo-cell" rowspan="3">
                <div class="logo-box"><img src="{{ $logo }}" alt=""></div>
              </td>
              <td class="title-row title-main">Grupo Vysisa</td>
            </tr>
            <tr><td class="title-row title-sub">Departamento de Sistemas</td></tr>
            <tr><td class="title-row title-sub">Formato de Devolución</td></tr>
          </table>

          {{-- METADATOS 1 --}}
          @php
            $fechaEntregaFmt = $responsiva && $responsiva->fecha_entrega
                ? \Illuminate\Support\Carbon::parse($responsiva->fecha_entrega)->format('d-m-Y')
                : '—';
          @endphp

          <table class="tbl meta" style="margin-top:6px">
            <colgroup>
              <col style="width:14%"><col style="width:14%">
              <col style="width:14%"><col style="width:13%">
              <col style="width:14%"><col style="width:31%">
            </colgroup>
            <tr>
              <td class="label">No. de devolución</td>
              <td class="val center">{{ $devolucion->folio }}</td>
              <td class="label nowrap">Fecha de entrega</td>
              <td class="val nowrap center">{{ $fechaEntregaFmt }}</td>
              <td class="label">Nombre de Usuario</td>
              <td class="val center">{{ $colNombreUsuario  }}</td>
            </tr>
          </table>

          {{-- METADATOS 2 --}}
          <table class="tbl meta no-border-top">
            <colgroup>
              <col style="width:18%"><col style="width:20%">
              <col style="width:16%"><col style="width:20%"><col style="width:4%">
              <col style="width:14%"><col style="width:4%">
            </colgroup>
            @php
              $isRenovacion = strtolower($devolucion->motivo) === 'renovacion';
              $isBaja       = strtolower($devolucion->motivo) === 'baja_colaborador';
            @endphp
            <tr>
              <td class="label">ÁREA/DEPARTAMENTO</td>
              <td class="val center">{{ $unidadServicio ?? '—' }}</td>
              <td class="label center">MOTIVO DE DEVOLUCIÓN</td>
              <td class="label center">BAJA DE COLABORADOR</td>
              <td class="val center mark-x">{{ $isBaja ? 'X' : '' }}</td>
              <td class="label center">RENOVACIÓN</td>
              <td class="val center mark-x">{{ $isRenovacion ? 'X' : '' }}</td>
            </tr>
          </table>
          
          <br>
          
          {{-- Texto de Declaración --}}
          @php
            // Catálogo de tipos legibles
            $tipos = [
                'equipo_pc'  => 'Equipo de Cómputo',
                'impresora'  => 'Impresora/Multifuncional',
                'monitor'    => 'Monitor',
                'pantalla'   => 'Pantalla/TV',
                'periferico' => 'Periférico',
                'consumible' => 'Consumible',
                'otro'       => 'Otro',
            ];

            // Construimos la lista de productos en formato "tipo (nombre)"
            $productosTexto = $productos->map(function ($p) use ($tipos) {
                $tipoClave = $p->tipo ?? 'otro';
                $tipoProducto = $tipos[$tipoClave] ?? ucfirst(str_replace('_', ' ', $tipoClave));
                $nombreProducto = $p->nombre ?? 'producto';
                return strtolower($tipoProducto) . ' (<b>' . e($nombreProducto) . '</b>)';
            })->implode(', ');
          @endphp

          <div class="section-title">DECLARACIÓN DE DEVOLUCIÓN</div>
          <br>
          <div class="blk">
            <span class="section-consta">Por medio de la presente hago constar que: </span>
            <span>Se hace entrega a </span><b>{{ $emisorRazon }}</b>
            <span> del {!! $productosTexto !!} en óptimas condiciones físicas y operativas.</span>
          </div>

          <div class="blk">Consta de las siguientes características</div>

          {{-- TABLA DE PRODUCTOS --}}
          @php
            $totalProductos = $productos->count();
            $filasVacias = max(0, 4 - $totalProductos); // Calcula cuántas filas vacías faltan para llegar a 4
          @endphp

          <table class="tbl" style="margin-top:10px">
            <thead>
                <tr>
                <th>Equipo</th>
                <th>Descripción</th>
                <th>Marca</th>
                <th>Modelo</th>
                <th>Serie</th>
                </tr>
            </thead>
            <tbody>
                {{-- Productos reales --}}
                @foreach ($productos as $p)
                @php
                    $pivot = $p->pivot ?? null;
                    $serie = \App\Models\ProductoSerie::find($pivot?->producto_serie_id);
                @endphp
                <tr>
                    <td>{{ $p->nombre }}</td>
                    <td>{{ $p->descripcion ?? '-' }}</td>
                    <td>{{ $p->marca ?? '-' }}</td>
                    <td>{{ $p->modelo ?? '-' }}</td>
                    <td>{{ $serie?->serie ?? '-' }}</td>
                </tr>
                @endforeach

                {{-- Filas vacías (si hay menos de 4 productos) --}}
                @for ($i = 0; $i < $filasVacias; $i++)
                <tr>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>
                @endfor
            </tbody>
          </table>

          <br>

          {{-- TEXTO ENTERADO --}}
          <div class="blk">
            <span>El usuario realiza la devolución del equipo en óptimas condiciones. La entrega se hizo llegar al departamento de sistemas.</span> 
            <span>Asimismo, confirmo que estoy enterado de que se realizará un respaldo y la posterior baja de la 
                cuenta de correo electrónico asignada a mi usuario.</span>
          </div>

          <br>

          {{-- FECHA DE DEVOLUCIÓN --}}
          <table class="tbl meta fecha-entrega" style="margin-top:12px">
            <colgroup><col class="label"><col class="val"></colgroup>
            <tr>
                <td class="label nowrap">Fecha de devolución</td>
                <td class="val nowrap center">{{ $fechaDevolucionFmt ?: '—' }}</td>
            </tr>
          </table>

          <br>

          {{-- FIRMAS --}}
          <div class="firmas-wrap">
            
            {{-- 🔹 Primera fila: Recibió (admin) / Entregó (colaborador) --}}
            <div class="firma-row">
                {{-- 1️⃣ RECIBIÓ (usuario admin) --}}
                <div class="sign">
                    <div class="sign-title">RECIBIÓ</div>
                    <div class="sign-sub">Departamento de Sistemas</div>
                    <div class="sign-space">
                        @if($firmaRecibio)
                            <img class="firma-img" src="{{ $firmaRecibio }}" alt="Firma recibió (admin)">
                        @endif
                    </div>
                    <div class="sign-inner">
                        <div class="sign-name">{{ $recibioNombre }}</div>
                        <div class="sign-line"></div>
                        <div class="sign-caption">Nombre y firma</div>
                    </div>
                </div>
                {{-- 2️⃣ ENTREGÓ (colaborador) --}}
                <div class="sign">
                    <div class="sign-title">ENTREGÓ</div>
                    <div class="sign-sub"> CONFORMIDAD USUARIO</div>
                    <div class="sign-space">
                        @if($firmaEntrego)
                            <img class="firma-img" src="{{ $firmaEntrego }}" alt="Firma entregó (colaborador)">
                        @endif
                    </div>
                    <div class="sign-inner">
                        <div class="sign-name">{{ $colNombreEntrego  }}</div>
                        <div class="sign-line"></div>
                        <div class="sign-caption">Nombre y firma</div>
                    </div>
                </div>
            </div>
            
            {{-- 🔹 Segunda fila centrada: Psitio (colaborador auxiliar) --}}
            <div class="firma-row" style="justify-content:center; margin-top:16px;">
                <div class="sign" style="flex:0 0 60%; max-width:60%;">
                    <div class="sign-title">RECIBIO</div>
                    <div class="sign-sub">PERSONA EN SITIO</div>
                    <div class="sign-space">
                        @if($firmaPsitio)
                            <img class="firma-img" src="{{ $firmaPsitio }}" alt="Firma psitio">
                        @endif
                    </div>
                    <div class="sign-inner sm">
                        <div class="sign-name">{{ $psitioNombre }}</div>
                        <div class="sign-line"></div>
                        <div class="sign-caption">Nombre y firma</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

        </div>
      </div>
    </div>
  </div>
</x-app-layout>
