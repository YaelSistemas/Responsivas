{{-- resources/views/responsivas/pdf.blade.php --}}
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Responsiva {{ $responsiva->folio }}</title>
  <style>
    @page { size: A4 portrait; margin: 0; }
    html, body { margin:0; padding:0; font-family: DejaVu Sans, sans-serif; }
    .page{ width:100%; margin:0; }

    .tbl{ width:100%; border-collapse:collapse; table-layout:fixed; }
    .tbl.meta{ table-layout:auto; }

    .tbl th,.tbl td{
      border:1px solid #111; padding:6px 8px;
      font-size:11px; line-height:1.15; vertical-align:middle;
      word-break:normal; box-sizing:border-box;
    }
    .tbl th{ font-weight:700; text-transform:uppercase; background:#f8fafc; }
    .center{ text-align:center; }

    /* utilidades */
    .nowrap{ white-space:nowrap; }
    .nowrap-clip{ white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

    /* Encabezado / logo */
    .hero .logo-cell{
      width:28%;
      padding:0;
      height:120px;                /* alto del bloque del logo */
      position:relative;           /* para que .logo-box se ancle a la celda */
      vertical-align:middle;
      text-align:center;
    }
    /* Tabla interna que ocupa TODO el alto de la celda -> centra vertical y horizontal */
    .hero .logo-box{
      position:absolute; inset:0;  /* llena por completo la celda */
      display:table; width:100%; height:100%;
    }
    .hero .logo-box .in{
      display:table-cell;
      vertical-align:middle;       /* centra vertical */
      text-align:center;           /* centra horizontal */
    }
    .hero .logo-cell img{ max-width:200px; max-height:90px; display:inline-block; }

    .title-row{ text-align:center; }
    .title-main{ font-weight:800; font-size:13px; }
    .title-sub{ font-weight:700; font-size:11px; text-transform:uppercase; letter-spacing:.3px; }

    /* Metadatos compactos */
    .meta td, .meta th{ padding:3px 6px; }
    .meta .label{ font-weight:700; font-size:9px; text-transform:uppercase; line-height:1.1; white-space:nowrap; }
    .meta .val{ font-size:9.8px; line-height:1.1; }
    .meta .label.long{ font-size:8.6px; white-space:nowrap; } /* PRÉSTAMO PROVISIONAL */
    .xcell{ text-align:center; font-weight:700; }

    /* auto-shrink */
    .sm  { font-size:9.2px; }
    .xs  { font-size:8.8px; }
    .xxs { font-size:8.2px; }
    .xxxs{ font-size:7.8px; }

    .blk{ margin:8px 0; font-size:11px; }
    .blk b{ font-weight:700; }

    .equipos th{ text-align:center; }
    .equipos td{ height:24px; text-align:center; }

    .fecha-entrega{ width:280px; margin-left:auto; }
    .fecha-entrega .label{ width:55%; }
    .fecha-entrega .val{ width:45%; }

    .firmas-table{ width:100%; border-collapse:collapse; table-layout:fixed; margin-top:10px; }
    .firmas-table td{ border:0; vertical-align:top; padding:0 10px; }
    .sign-title{ text-align:center; text-transform:uppercase; font-weight:700; margin:6px 0; font-size:12px; }
    .sign-sub{ text-align:center; font-size:9px; text-transform:uppercase; margin:-2px 0 10px; }
    .sign-space{ height:44px; }
    .sign-inner{ width:240px; margin:0 auto; }
    .sign-inner.sm{ width:260px; }
    .sign-name{ text-align:center; font-size:10px; text-transform:uppercase; margin-bottom:2px; }
    .sign-line{ border-top:1px solid #111; height:1px; }
    .sign-caption{ text-align:center; font-size:9px; text-transform:uppercase; margin-top:3px; }

    .hero, .meta, .equipos, .firmas-table { page-break-inside: avoid; }
  </style>
</head>
<body>
@php
  use Illuminate\Support\Carbon;

  $col = $responsiva->colaborador;

  $areaDepto = $col?->area ?? $col?->departamento ?? $col?->sede ?? '';
  if (is_object($areaDepto))      $areaDepto = $areaDepto->nombre ?? $areaDepto->name ?? $areaDepto->descripcion ?? (string)$areaDepto;
  elseif (is_array($areaDepto))   $areaDepto = implode(' ', array_filter($areaDepto));

  $unidadServicio = $col?->unidad_servicio ?? $col?->unidadServicio ?? $col?->unidad_de_servicio ?? $col?->unidad ?? $col?->servicio ?? '';
  if (is_object($unidadServicio)) $unidadServicio = $unidadServicio->nombre ?? $unidadServicio->name ?? $unidadServicio->descripcion ?? (string)$unidadServicio;
  elseif (is_array($unidadServicio)) $unidadServicio = implode(' ', array_filter($unidadServicio));
  if ($unidadServicio === '' && $areaDepto !== '') $unidadServicio = $areaDepto;

  $empresaNombre = config('app.name', 'Laravel');
  $logoPath = public_path('images/logos/default.png');
  if (class_exists(\App\Models\Empresa::class)) {
    $emp = $responsiva->empresa ?? (\App\Models\Empresa::find($responsiva->empresa_id ?? (int) session('empresa_activa', auth()->user()?->empresa_id)));
    if ($emp) {
      $empresaNombre = $emp->nombre ?? $empresaNombre;
      $candidates = [];
      if (!empty($emp->logo))      $candidates[] = public_path('images/logos/'.ltrim($emp->logo,'/'));
      if (!empty($emp->logo_path)) $candidates[] = public_path(ltrim($emp->logo_path,'/'));
      $slug = \Illuminate\Support\Str::slug($empresaNombre, '-');
      foreach (['png','jpg','jpeg','svg','webp'] as $ext) {
        $candidates[] = public_path("images/logos/{$slug}.{$ext}");
        $candidates[] = public_path("images/logos/empresa-{$emp->id}.{$ext}");
        $candidates[] = public_path("images/logos/{$emp->id}.{$ext}");
      }
      foreach ($candidates as $abs) if (is_file($abs)) { $logoPath = $abs; break; }
    }
  }
  $logoSrc = is_file($logoPath)
    ? ('data:'.(@mime_content_type($logoPath) ?: 'image/png').';base64,'.base64_encode(file_get_contents($logoPath)))
    : '';

  $motivo         = $responsiva->motivo_entrega;
  $isAsignacion   = $motivo === 'asignacion';
  $isPrestamoProv = $motivo === 'prestamo_provisional';

  $detalles = $responsiva->detalles ?? collect();
  $minRows  = 6;
  $faltan   = max(0, $minRows - $detalles->count());
  $productosLista = $detalles->map(fn($d)=> trim($d->producto->nombre ?? '') ?: null)->filter()->unique()->values()->all();
  $fraseEntrega   = $productosLista ? ('Se hace entrega de '.implode(', ', $productosLista).'.') : 'Se hace entrega de equipo y accesorios.';

  $emisorNombre = $empresaNombre;
  if ($col) {
    $sub = $col->subsidiaria ?? $col->subsidiary ?? null;
    $emisorNombre = $sub?->razon_social ?? $sub?->razon ?? $sub?->nombre_fiscal ?? $sub?->nombre ?? $emisorNombre;
    if (!$sub && isset($col->empresa)) {
      $emisorNombre = $col->empresa->razon_social ?? $col->empresa->nombre_fiscal ?? $col->empresa->nombre ?? $emisorNombre;
    }
  }

  $apellidos = $col?->apellido ?? $col?->apellidos
            ?? trim(($col?->apellido_paterno ?? $col?->primer_apellido ?? '').' '.($col?->apellido_materno ?? $col?->segundo_apellido ?? ''));
  $nombreCompleto = trim(trim($col?->nombre ?? '').' '.trim($apellidos ?? '')) ?: ($col?->nombre ?? '');

  $entregoNombre = '';
  if (!empty($responsiva->entrego) && !is_string($responsiva->entrego)) {
    $entregoNombre = $responsiva->entrego->name ?? '';
  } elseif (!empty($responsiva->entrego_user_id)) {
    $entregoNombre = \App\Models\User::find($responsiva->entrego_user_id)?->name ?? '';
  }

  $autorizaNombre = '';
  if (!empty($responsiva->autoriza) && !is_string($responsiva->autoriza)) {
    $autorizaNombre = $responsiva->autoriza->name ?? '';
  } elseif (!empty($responsiva->autoriza_user_id)) {
    $autorizaNombre = \App\Models\User::find($responsiva->autoriza_user_id)?->name ?? '';
  }

  $fechaSolicitudFmt = $responsiva->fecha_solicitud
      ? ($responsiva->fecha_solicitud instanceof \Illuminate\Support\Carbon
          ? $responsiva->fecha_solicitud->format('d-m-Y')
          : Carbon::parse($responsiva->fecha_solicitud)->format('d-m-Y'))
      : '';

  $fechaEntregaFmt = $responsiva->fecha_entrega
      ? ($responsiva->fecha_entrega instanceof \Illuminate\Support\Carbon
          ? $responsiva->fecha_entrega->format('d-m-Y')
          : Carbon::parse($responsiva->fecha_entrega)->format('d-m-Y'))
      : '';

  $lenNombre  = mb_strlen($nombreCompleto ?? '');
  $lenUnidad  = mb_strlen($unidadServicio ?? '');
  $nombreClass = $lenNombre > 58 ? 'xxxs' : ($lenNombre > 46 ? 'xxs' : ($lenNombre > 34 ? 'xs' : ($lenNombre > 26 ? 'sm' : '')));
  $unidadClass = $lenUnidad > 58 ? 'xxxs' : ($lenUnidad > 46 ? 'xxs' : ($lenUnidad > 34 ? 'xs' : ($lenUnidad > 26 ? 'sm' : '')));
@endphp

<div class="page">
  <!-- HERO -->
  <table class="tbl hero">
    <colgroup><col style="width:28%"><col></colgroup>
    <tr>
      <td class="logo-cell" rowspan="3">
        <div class="logo-box">
          <div class="in">
            @if($logoSrc) <img src="{{ $logoSrc }}" alt="Logo"> @endif
          </div>
        </div>
      </td>
      <td class="title-row title-main">{{ $empresaNombre }}</td>
    </tr>
    <tr><td class="title-row title-sub">Departamento de Sistemas</td></tr>
    <tr><td class="title-row title-sub">Formato de Responsiva</td></tr>
  </table>

  <!-- META UNIFICADA (2 filas, 7 columnas) -->
  <table class="tbl meta" style="margin-top:6px">
    <colgroup>
      <col style="width:112px"><!-- No. salida (label) -->
      <col style="width:92px"><!-- No. salida (valor) -->
      <col style="width:105px"><!-- Fecha (label) -->
      <col style="width:82px"><!-- Fecha (valor) -->
      <col style="width:130px"><!-- Nombre (label) -->
      <col style="width:40.5%"><!-- Nombre / Préstamo provisional -->
      <col style="width:2%"><!-- X préstamo -->
    </colgroup>
    <tr>
      <td class="label">No. de salida</td>
      <td class="val center">{{ $responsiva->folio }}</td>
      <td class="label">Fecha de solicitud</td>
      <td class="val center">{{ $fechaSolicitudFmt }}</td>
      <td class="label">Nombre del usuario</td>
      <td class="val nowrap-clip center {{ $nombreClass }}" colspan="2">{{ $nombreCompleto }}</td>
    </tr>
    <tr>
      <td class="label">ÁREA/DEPARTAMENTO</td>
      <td class="val center {{ $unidadClass }}">{{ $unidadServicio }}</td>
      <td class="label">Motivo de entrega</td>
      <td class="label">Asignación</td>
      <td class="xcell">{{ $isAsignacion ? 'X' : '' }}</td>
      <td class="label long">Préstamo provisional</td>
      <td class="xcell">{{ $isPrestamoProv ? 'X' : '' }}</td>
    </tr>
  </table>

  <br>

  <div class="blk">
    <b>Por medio de la presente hago constar que:</b>
    <span>{{ $fraseEntrega }}</span>
  </div>

  <br>

  <div class="blk">
    <span>Recibí de: </span><b>{{ $emisorNombre }}</b>
    <span> el siguiente equipo para uso exclusivo del desempeño de mis actividades laborales asignadas,
      el cual se reserva el derecho de retirar cuando así lo considere necesario la empresa.</span>
  </div>

  <div class="blk"><span>Consta de las siguientes características</span></div>

  <table class="tbl equipos" style="margin-top:8px">
    <thead>
      <tr>
        <th style="width:20%">Equipo</th>
        <th style="width:28%">Descripción</th>
        <th style="width:16%">Marca</th>
        <th style="width:16%">Modelo</th>
        <th>Número de serie</th>
      </tr>
    </thead>
    <tbody>
      @foreach(($responsiva->detalles ?? []) as $d)
        @php
          $p   = $d->producto;
          $s   = $d->serie;
          $des = ($p?->tipo === 'impresora') ? ($p?->descripcion ?? '') : '';
        @endphp
        <tr>
          <td style="text-transform:uppercase">{{ $p?->nombre }}</td>
          <td>{{ $des }}</td>
          <td>{{ $p?->marca }}</td>
          <td>{{ $p?->modelo }}</td>
          <td>{{ $s?->serie }}</td>
        </tr>
      @endforeach
      @for($i=0; $i<$faltan; $i++)
        <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td></tr>
      @endfor
    </tbody>
  </table>

  <div class="blk" style="margin-top:8px">
    Los daños ocasionados por el mal manejo o imprudencia, así como el robo o pérdida total o parcial a causa de
    negligencia o descuido, serán mi responsabilidad y asumo las consecuencias que de esto deriven.
  </div>

  <br>

  <table class="tbl meta fecha-entrega" style="margin-top:8px">
    <colgroup><col class="label"><col class="val"></colgroup>
    <tr>
      <td class="label">Fecha de entrega</td>
      <td class="val center">{{ $fechaEntregaFmt }}</td>
    </tr>
  </table>

  <br><br>

  <table class="firmas-table">
    <tr>
      <td>
        <div class="sign-title">ENTREGO</div>
        <div class="sign-sub">Departamento de Sistemas</div>
        <div class="sign-space"></div>
        <div class="sign-inner">
          <div class="sign-name">{{ $entregoNombre }}</div>
          <div class="sign-line"></div>
          <div class="sign-caption">Nombre y firma</div>
        </div>
      </td>
      <td>
        <div class="sign-title">RECIBIO</div>
        <div class="sign-sub">Recibí de conformidad Usuario</div>
        <div class="sign-space"></div>
        <div class="sign-inner">
          <div class="sign-name">{{ $nombreCompleto }}</div>
          <div class="sign-line"></div>
          <div class="sign-caption">Nombre y firma</div>
        </div>
      </td>
    </tr>
  </table>

  <br>

  <table class="firmas-table" style="margin-top:14px;">
    <tr>
      <td style="text-align:center;">
        <div class="sign-title">AUTORIZÓ</div>
        <div class="sign-space"></div>
        <div class="sign-inner sm">
          <div class="sign-name">{{ $autorizaNombre }}</div>
          <div class="sign-line"></div>
          <div class="sign-caption">Nombre y firma</div>
        </div>
      </td>
    </tr>
  </table>
</div>
</body>
</html>
