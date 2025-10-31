{{-- resources/views/devoluciones/pdf_sheet.blade.php --}}
@php
  use Illuminate\Support\Str;
  use Illuminate\Support\Carbon;

  /* ========== Empresa / Logo ========== */
  $empresaNombre = 'Grupo Vysisa';
  $logoFile      = public_path('images/logos/default.png');

  if (class_exists(\App\Models\Empresa::class)) {
    $emp = \App\Models\Empresa::find((int) session('empresa_activa', auth()->user()?->empresa_id));
    if ($emp) {
      $empresaNombre = $emp->nombre ?: $empresaNombre;
      $candidates = [];
      if (!empty($emp->logo_url))  $candidates[] = public_path(ltrim($emp->logo_url, '/'));
      if (!empty($emp->logo))      $candidates[] = public_path('images/logos/'.ltrim($emp->logo, '/'));
      $slug = Str::slug($empresaNombre, '-');
      foreach (['png','jpg','jpeg','webp','svg'] as $ext) {
        $candidates[] = public_path("images/logos/{$slug}.{$ext}");
        $candidates[] = public_path("images/logos/empresa-{$emp->id}.{$ext}");
      }
      foreach ($candidates as $abs) { if (is_file($abs)) { $logoFile = $abs; break; } }
    }
  }

  $toB64 = function ($path) {
    if (!is_file($path)) return null;
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mime = match($ext){'jpg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','svg'=>'image/svg+xml',default=>'image/png'};
    return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($path));
  };
  $logoB64 = $toB64($logoFile);

  /* ========== Datos base ========== */
  $responsiva = $devolucion->responsiva ?? null;
  $colResp = $responsiva?->colaborador;

  $folio = $devolucion->folio ?? '—';
  $fechaEntrega = $responsiva && $responsiva->fecha_entrega
      ? Carbon::parse($responsiva->fecha_entrega)->format('d-m-Y')
      : '—';
  $colNombreUsuario = $colResp ? trim(($colResp->nombre ?? '').' '.($colResp->apellidos ?? '')) : '—';

  $unidadServicio = $colResp?->unidad_servicio?->nombre
                  ?? $colResp?->unidadServicio?->nombre
                  ?? $colResp?->unidad
                  ?? '—';

  $isRenovacion = strtolower($devolucion->motivo ?? '') === 'renovacion';
  $isBaja       = strtolower($devolucion->motivo ?? '') === 'baja_colaborador';

  $fechaDevolucionFmt = $devolucion->fecha_devolucion
      ? Carbon::parse($devolucion->fecha_devolucion)->format('d-m-Y')
      : '';

  // Firmas (como en show)
  $col      = $devolucion->entregoColaborador ?? null;
  $recibio  = $devolucion->recibidoPor ?? null;
  $psitio   = $devolucion->psitioColaborador ?? null;

  $firmaUrlFor = function ($user) {
    if (!$user) return null;
    $id = $user->id ?? null;
    $name = trim($user->name ?? ($user->nombre ?? ''));
    foreach (['png','jpg','jpeg','webp','svg'] as $ext) {
      if ($id && file_exists(public_path("storage/firmas/{$id}.{$ext}"))) return asset("storage/firmas/{$id}.{$ext}");
      if ($name && file_exists(public_path("storage/firmas/".Str::slug($name).".{$ext}"))) return asset("storage/firmas/".Str::slug($name).".{$ext}");
    }
    return null;
  };
  $firmaEntrego = $firmaUrlFor($col);
  $firmaRecibio = $firmaUrlFor($recibio);
  $firmaPsitio  = $firmaUrlFor($psitio);

  $colNombreEntrego = $col ? trim(($col->nombre ?? '').' '.($col->apellidos ?? '')) : '—';
  $recibioNombre    = $recibio ? trim(($recibio->nombre ?? '').' '.($recibio->apellidos ?? '')) ?: ($recibio->name ?? '') : '';
  $psitioNombre     = $psitio ? trim(($psitio->nombre ?? '').' '.($psitio->apellidos ?? '')) ?: ($psitio->name ?? '') : '';

  $productos = $devolucion->productos ?? collect();
  $tipos = [
    'equipo_pc'  => 'Equipo de Cómputo',
    'impresora'  => 'Impresora/Multifuncional',
    'monitor'    => 'Monitor',
    'pantalla'   => 'Pantalla/TV',
    'periferico' => 'Periférico',
    'consumible' => 'Consumible',
    'otro'       => 'Otro',
    'pc_de_escritorio' => 'PC de Escritorio'
  ];
  $productosTexto = $productos->map(function ($p) use ($tipos) {
    $tipoClave = $p->tipo ?? 'otro';
    $tipoProducto = $tipos[$tipoClave] ?? ucfirst(str_replace('_', ' ', $tipoClave));
    $nombreProducto = $p->nombre ?? 'producto';
    return strtolower($tipoProducto).' (<b>'.e($nombreProducto).'</b>)';
  })->implode(', ');
@endphp

<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Devolución {{ $folio }}</title>
  <style>
    @page { size: A4 portrait; margin: 6mm; }
    html, body { margin:0; padding:0; font-family: DejaVu Sans, Arial, sans-serif; color:#000; }

    .sheet { width: 98%; margin: 0 auto; }
    .tbl{ width:100%; border-collapse:collapse; table-layout:fixed; }
    .tbl th,.tbl td{ border:1px solid #111; padding:6px 8px; font-size:12px; line-height:1.15; vertical-align:middle; text-align:center; white-space:nowrap; }
    .tbl th{ font-weight:700; text-transform:uppercase; background:#f8fafc; }

    /* ===== Encabezado ===== */
    .hero{ table-layout:fixed; }
    .hero .logo-cell{ width:28%; }
    .hero .logo-box{ height:120px; display:flex; align-items:center; justify-content:center; }
    .hero img{ max-width:200px; max-height:90px; display:block; }
    .title-row{ text-align:center; }
    .title-main{ font-weight:800; font-size:14px; }
    .title-sub{ font-weight:700; font-size:12px; text-transform:uppercase; }

    /* ===== Tablas de metadatos ===== */
.tbl.meta td, .tbl.meta th {
  font-size: 10px;
  padding: 5px 6px;
  line-height: 1.3;
  vertical-align: middle;
  white-space: nowrap;
}

.tbl.meta .label {
  font-weight: 700;
  text-transform: uppercase;
  text-align: left;
  background: none;       /* 🔹 Quita sombreado */
  border-right: 1px solid #000;
}

.tbl.meta .val {
  text-align: center;
  font-size: 10px;
}

.tbl.meta .mark-x {
  width: 22px;
  font-weight: bold;
  text-align: center;
  font-size: 10px;
}



    .mark-x{ width:20px; text-align:center; font-weight:700; display:inline-block; }

    /* ===== Secciones ===== */
    .section-title{ font-weight:800; text-align:left; margin:10px 0 4px; letter-spacing:.3px; font-size:12px; }
    .blk{ margin-top:6px; font-size:12px; line-height:1.35; }

    /* ===== Fecha ===== */
    .fecha-wrap{ width: 340px; margin-left: auto; margin-top:10px; }
    .fecha-wrap .label{ width: 60%; }
    .fecha-wrap .val{ width: 40%; }

    /* ===== Firmas ===== */
    .firmas-wrap{ margin-top:30px; }
    .firma-row{ display:flex; gap:32px; justify-content:space-between; align-items:flex-start; }
    .sign{ flex:1; text-align:center; }
    .sign-title{ text-transform:uppercase; font-weight:700; margin-bottom:6px; }
    .sign-sub{ font-size:10px; text-transform:uppercase; margin:-2px 0 8px; }
    .sign-space{ height:56px; display:flex; align-items:center; justify-content:center; }
    .firma-img{ max-height:56px; max-width:100%; display:block; margin:0 auto; mix-blend-mode:multiply; }
    .sign-inner{ width:55%; margin:0 auto; }
    .sign-name{ font-size:11px; text-transform:uppercase; margin-bottom:2px; }
    .sign-line{ border-top:1px solid #111; height:1px; }
    .sign-caption{ font-size:10px; text-transform:uppercase; margin-top:4px; }

    .mt-6{ margin-top:6px; }
    .mt-10{ margin-top:10px; }
  </style>
</head>
<body>
  <div class="sheet">

    {{-- ====== ENCABEZADO ====== --}}
    <table class="tbl hero">
      <tr>
        <td class="logo-cell" rowspan="3">
          <div class="logo-box">@if($logoB64)<img src="{{ $logoB64 }}" alt="Logo">@endif</div>
        </td>
        <td class="title-row title-main">{{ $empresaNombre }}</td>
      </tr>
      <tr><td class="title-row title-sub">Departamento de Sistemas</td></tr>
      <tr><td class="title-row title-sub">Formato de Devolución</td></tr>
    </table>

    {{-- ====== METADATOS 1 ====== --}}
<table class="tbl meta mt-10">
  <colgroup>
    <col style="width:20%">
    <col style="width:15%">
    <col style="width:20%">
    <col style="width:15%">
    <col style="width:15%">
    <col style="width:15%">
  </colgroup>
  <tr>
    <td class="label">No. de Devolución</td>
    <td class="val center">{{ $folio }}</td>
    <td class="label">Fecha de Entrega</td>
    <td class="val center">{{ $fechaEntrega }}</td>
    <td class="label">Nombre de Usuario</td>
    <td class="val center">{{ $colNombreUsuario }}</td>
  </tr>
</table>

{{-- ====== METADATOS 2 ====== --}}
<table class="tbl meta mt-6">
  <colgroup>
    <col style="width:22%">
    <col style="width:20%">
    <col style="width:16%">
    <col style="width:18%">
    <col style="width:6%">
    <col style="width:12%">
    <col style="width:6%">
  </colgroup>
  <tr>
    <td class="label">Área/Departamento</td>
    <td class="val center">{{ $unidadServicio }}</td>
    <td class="label center">Motivo de Devolución</td>
    <td class="label center">Baja de Colaborador</td>
    <td class="val center">
      @if($isBaja)
        X
      @else
        &nbsp;
      @endif
    </td>
    <td class="label center">Renovación</td>
    <td class="val center">
      @if($isRenovacion)
        X
      @else
        &nbsp;
      @endif
    </td>
  </tr>
</table>



    {{-- ====== DECLARACIÓN ====== --}}
    <div class="section-title">Declaración de Devolución</div>
    <div class="blk">
      <b>Por medio de la presente hago constar que:</b>
      Se hace entrega a <b>{{ $empresaNombre }}</b> del {!! $productosTexto !!} en óptimas condiciones físicas y operativas.
    </div>

    <div class="blk">Consta de las siguientes características</div>

    {{-- ====== TABLA PRODUCTOS ====== --}}
    @php
      $totalProductos = $productos->count();
      $filasVacias = max(0, 4 - $totalProductos);
    @endphp
    <table class="tbl mt-10">
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
        @for ($i = 0; $i < $filasVacias; $i++)
          <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
        @endfor
      </tbody>
    </table>

    {{-- ====== TEXTO FINAL ====== --}}
    <div class="blk">
      El usuario realiza la devolución del equipo en óptimas condiciones. La entrega se hizo llegar al departamento de sistemas. Asimismo, confirmo que estoy enterado de que se realizará un respaldo y la posterior baja de la cuenta de correo electrónico asignada a mi usuario.
    </div>

    {{-- ====== FECHA DEVOLUCIÓN ====== --}}
    <table class="tbl meta fecha-wrap">
      <tr>
        <td class="label nowrap">Fecha de devolución</td>
        <td class="val nowrap center">{{ $fechaDevolucionFmt ?: '—' }}</td>
      </tr>
    </table>

    {{-- ====== FIRMAS ====== --}}
    <div class="firmas-wrap">
      <div class="firma-row">
        <div class="sign">
          <div class="sign-title">Recibió</div>
          <div class="sign-sub">Departamento de Sistemas</div>
          <div class="sign-space">
            @if($firmaRecibio)<img class="firma-img" src="{{ $firmaRecibio }}" alt="Firma recibió">@endif
          </div>
          <div class="sign-inner">
            <div class="sign-name">{{ $recibioNombre }}</div>
            <div class="sign-line"></div>
            <div class="sign-caption">Nombre y firma</div>
          </div>
        </div>

        <div class="sign">
          <div class="sign-title">Entregó</div>
          <div class="sign-sub">Conformidad Usuario</div>
          <div class="sign-space">
            @if($firmaEntrego)<img class="firma-img" src="{{ $firmaEntrego }}" alt="Firma entregó">@endif
          </div>
          <div class="sign-inner">
            <div class="sign-name">{{ $colNombreEntrego }}</div>
            <div class="sign-line"></div>
            <div class="sign-caption">Nombre y firma</div>
          </div>
        </div>
      </div>

      <div class="firma-row" style="justify-content:center; margin-top:20px;">
        <div class="sign" style="flex:0 0 60%; max-width:60%;">
          <div class="sign-title">Recibió</div>
          <div class="sign-sub">Persona en Sitio</div>
          <div class="sign-space">
            @if($firmaPsitio)<img class="firma-img" src="{{ $firmaPsitio }}" alt="Firma psitio">@endif
          </div>
          <div class="sign-inner">
            <div class="sign-name">{{ $psitioNombre }}</div>
            <div class="sign-line"></div>
            <div class="sign-caption">Nombre y firma</div>
          </div>
        </div>
      </div>
    </div>

  </div>
</body>
</html>
