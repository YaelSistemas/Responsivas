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

  /* ===== Encabezado / logo ===== */
  .hero .logo-cell{
    width:28%;
    padding:0;
    height:120px;            /* alto fijo del bloque del logo */
    vertical-align:middle;
    text-align:center;
  }
  .hero .logo-box{
    display:table;
    width:100%;
    height:120px;            /* NO cambiar este valor */
    border:0;
  }
  .hero .logo-box .in{
    display:table-cell;
    vertical-align:middle;
    text-align:center;
    padding-top:16px;         /* ← Pequeño “bajón” del logo (ajusta 4–12px a gusto) */
  }
  .hero .logo-cell img{
    display:inline-block;
    max-width:200px;
    max-height:90px;
  }

  .title-row{ text-align:center; }
  .title-main{ font-weight:800; font-size:13px; }
  .title-sub{ font-weight:700; font-size:11px; text-transform:uppercase; letter-spacing:.3px; }

  /* Metadatos compactos */
  .meta td, .meta th{ padding:3px 6px; }
  .meta .label{ font-weight:700; font-size:9px; text-transform:uppercase; line-height:1.1; white-space:nowrap; }
  .meta .val{ font-size:9.8px; line-height:1.1; }
  .meta .label.long{ font-size:8.6px; white-space:nowrap; }
  .xcell{ text-align:center; font-weight:700; }

  .blk{ margin:8px 0; font-size:11px; }
  .blk b{ font-weight:700; }

  .equipos th{ text-align:center; }
  .equipos td{ height:24px; text-align:center; }

  .fecha-entrega{ width:280px; margin-left:auto; }
  .fecha-entrega .label{ width:55%; }
  .fecha-entrega .val{ width:45%; }

  /* ===== Firmas (sin bordes; imágenes centradas; líneas iguales) ===== */
  .tbl.firmas, .tbl.firmas tr, .tbl.firmas td { border:0 !important; }
  .tbl.firmas td{ padding:0 10px; vertical-align:top; }

  .sign-title{ text-align:center; text-transform:uppercase; font-weight:700; margin:6px 0 2px; font-size:12px; }
  .sign-sub{ text-align:center; font-size:9px; text-transform:uppercase; margin:0 0 6px; }

  .sign-space{
    height:54px;
    line-height:54px;
    text-align:center;
  }
  .firma{
    display:inline-block;
    vertical-align:middle;
    max-height:50px;
    max-width:220px;
    margin:0;
  }

  .sign-line{ border-top:1px solid #111; height:1px; margin:6px auto 2px; width:240px; }
  .sign-name{ text-align:center; font-size:10px; text-transform:uppercase; margin-bottom:2px; }
  .sign-caption{ text-align:center; font-size:9px; text-transform:uppercase; margin-top:3px; }

  .hero, .meta, .equipos, .firmas { page-break-inside: avoid; }
</style>


</head>
<body>
@php
  $col = $responsiva->colaborador;

  $areaDepto = $col?->area ?? $col?->departamento ?? $col?->sede ?? '';
  if (is_object($areaDepto)) {
    $areaDepto = $areaDepto->nombre ?? $areaDepto->name ?? $areaDepto->descripcion ?? (string) $areaDepto;
  } elseif (is_array($areaDepto)) {
    $areaDepto = implode(' ', array_filter($areaDepto));
  }

  $unidadServicio = $col?->unidad_servicio
                  ?? $col?->unidadServicio
                  ?? $col?->unidad_de_servicio
                  ?? $col?->unidad
                  ?? $col?->servicio
                  ?? '';
  if (is_object($unidadServicio)) {
    $unidadServicio = $unidadServicio->nombre ?? $unidadServicio->name ?? $unidadServicio->descripcion ?? (string) $unidadServicio;
  } elseif (is_array($unidadServicio)) {
    $unidadServicio = implode(' ', array_filter($unidadServicio));
  }
  if ($unidadServicio === '' && $areaDepto !== '') $unidadServicio = $areaDepto;

  $empresaId     = (int) session('empresa_activa', auth()->user()?->empresa_id);
  $empresaNombre = config('app.name', 'Laravel');
  $logoPath      = public_path('images/logos/default.png');

  if (class_exists(\App\Models\Empresa::class) && $empresaId) {
    $emp = \App\Models\Empresa::find($empresaId);
    if ($emp) {
      $empresaNombre = $emp->nombre ?? $empresaNombre;

      $candidates = [];
      if (!empty($emp->logo_url))  $candidates[] = ltrim(parse_url($emp->logo_url, PHP_URL_PATH) ?? '', '/');
      if (!empty($emp->logo))      $candidates[] = 'images/logos/'.ltrim($emp->logo, '/');
      if (!empty($emp->logo_path)) $candidates[] = ltrim($emp->logo_path, '/');

      $slug = \Illuminate\Support\Str::slug($empresaNombre, '-');
      foreach (['png','jpg','jpeg','webp','svg'] as $ext) {
        $candidates[] = "images/logos/{$slug}.{$ext}";
        $candidates[] = "images/logos/empresa-{$empresaId}.{$ext}";
        $candidates[] = "images/logos/{$empresaId}.{$ext}";
      }
      foreach ($candidates as $rel) {
        if ($rel && file_exists(public_path($rel))) { $logoPath = public_path($rel); break; }
      }
    }
  }

  $motivo         = $responsiva->motivo_entrega;
  $isAsignacion   = $motivo === 'asignacion';
  $isPrestamoProv = $motivo === 'prestamo_provisional';

  $detalles = $responsiva->detalles ?? collect();
  $minRows  = 6;
  $faltan   = max(0, $minRows - $detalles->count());

  $productosLista = $detalles->map(function ($d) {
    $nombre = trim($d->producto->nombre ?? '');
    return $nombre !== '' ? $nombre : null;
  })->filter()->unique()->values()->all();

  $fraseEntrega = $productosLista
    ? ('Se hace entrega de '.implode(', ', $productosLista).'.')
    : 'Se hace entrega de equipo y accesorios.';

  $emisorRazon = $empresaNombre;
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

  $apellidos = $col?->apellido
            ?? $col?->apellidos
            ?? trim(($col?->apellido_paterno ?? $col?->primer_apellido ?? '').' '.($col?->apellido_materno ?? $col?->segundo_apellido ?? ''));
  $nombreCompleto = trim(trim($col?->nombre ?? '').' '.trim($apellidos ?? '')) ?: ($col?->nombre ?? '');

  $entregoNombre = '';
  if (!empty($responsiva->entrego) && !is_string($responsiva->entrego)) {
    $entregoNombre = $responsiva->entrego->name ?? '';
  } elseif (!empty($responsiva->entrego_user_id) && class_exists(\App\Models\User::class)) {
    $entregoNombre = \App\Models\User::find($responsiva->entrego_user_id)?->name ?? '';
  }

  $autorizaNombre = '';
  if (!empty($responsiva->autoriza) && !is_string($responsiva->autoriza)) {
    $autorizaNombre = $responsiva->autoriza->name ?? '';
  } elseif (!empty($responsiva->autoriza_user_id) && class_exists(\App\Models\User::class)) {
    $autorizaNombre = \App\Models\User::find($responsiva->autoriza_user_id)?->name ?? '';
  }

  $fechaSolicitudFmt = '';
  if (!empty($responsiva->fecha_solicitud)) {
    $fs = $responsiva->fecha_solicitud;
    $fechaSolicitudFmt = $fs instanceof \Illuminate\Support\Carbon
        ? $fs->format('d-m-Y')
        : \Illuminate\Support\Carbon::parse($fs)->format('d-m-Y');
  }
  $fechaEntregaFmt = '';
  if (!empty($responsiva->fecha_entrega)) {
    $fe = $responsiva->fecha_entrega;
    $fechaEntregaFmt = $fe instanceof \Illuminate\Support\Carbon
        ? $fe->format('d-m-Y')
        : \Illuminate\Support\Carbon::parse($fe)->format('d-m-Y');
  }

  // archivo -> dataURL base64 (para DomPDF)
  $toB64 = function (?string $fullPath): ?string {
    if (!$fullPath || !is_file($fullPath)) return null;
    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    $mime = match ($ext) { 'png' => 'image/png', 'jpg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'svg' => 'image/svg+xml', default => 'image/png' };
    $data = @file_get_contents($fullPath);
    if ($data === false) return null;
    return 'data:'.$mime.';base64,'.base64_encode($data);
  };

  // Firmas
  $findFirmaPath = function ($user): ?string {
    if (!$user) return null;
    $id   = $user->id ?? null;
    $name = trim($user->name ?? '');
    $cands = [];
    foreach (['png','jpg','jpeg','webp','svg'] as $ext) {
      if ($id)   $cands[] = public_path("storage/firmas/{$id}.{$ext}");
      if ($name !== '') {
        $slug = \Illuminate\Support\Str::slug($name);
        $cands[] = public_path("storage/firmas/{$slug}.{$ext}");
        $cands[] = public_path("storage/firmas/{$name}.{$ext}");
      }
    }
    foreach ($cands as $full) if (is_file($full)) return $full;
    return null;
  };

  $firmaEntregoB64  = $toB64($findFirmaPath($responsiva->entrego ?? null));
  $firmaAutorizaB64 = $toB64($findFirmaPath($responsiva->autoriza ?? null));

  $firmaColabB64 = null;
  if (!empty($responsiva->firma_colaborador_path)) {
    $full = public_path('storage/'.ltrim($responsiva->firma_colaborador_path, '/'));
    $firmaColabB64 = $toB64($full);
  }

  $logoB64 = $toB64($logoPath) ?: null;
@endphp

  {{-- ENCABEZADO --}}
  <table class="tbl hero">
    <colgroup><col style="width:28%"><col></colgroup>
    <tr>
      <td class="logo-cell" rowspan="3">
        <div class="logo-box"><div class="in">
          <img src="{{ $logoB64 ?: $logoPath }}" alt="logo">
        </div></div>
      </td>
      <td class="title-row title-main">Grupo Vysisa</td>
    </tr>
    <tr><td class="title-row title-sub">Departamento de Sistemas</td></tr>
    <tr><td class="title-row title-sub">Formato de Responsiva</td></tr>
  </table>

  {{-- METADATOS 1 --}}
  <table class="tbl meta" style="margin-top:6px">
    <colgroup>
      <col style="width:13%"><col style="width:17%">
      <col style="width:16%"><col style="width:10%">
      <col style="width:18%"><col style="width:26%">
    </colgroup>
    <tr>
      <td class="label">No. de salida</td>
      <td class="val center">{{ $responsiva->folio }}</td>
      <td class="label nowrap">Fecha de solicitud</td>
      <td class="val nowrap center">{{ $fechaSolicitudFmt }}</td>
      <td class="label">Nombre del usuario</td>
      <td class="val center">{{ $nombreCompleto }}</td>
    </tr>
  </table>

  {{-- METADATOS 2 --}}
  <table class="tbl meta" style="border-top:0">
    <colgroup>
      <col style="width:18%"><col style="width:20%">
      <col style="width:16%"><col style="width:14%"><col style="width:4%">
      <col style="width:20%"><col style="width:4%">
    </colgroup>
    <tr>
      <td class="label">ÁREA/DEPARTAMENTO</td>
      <td class="val center">{{ $unidadServicio }}</td>
      <td class="label">Motivo de entrega</td>
      <td class="label">Asignación</td>
      <td class="xcell">{{ $isAsignacion ? 'X' : '' }}</td>
      <td class="label">Préstamo provisional</td>
      <td class="xcell">{{ $isPrestamoProv ? 'X' : '' }}</td>
    </tr>
  </table>

  <br>

  {{-- TEXTOS --}}
  <div class="blk">
    <b>Por medio de la presente hago constar que:</b>
    <span>{{ $fraseEntrega }}</span>
  </div>

  <br>

  <div class="blk">
    <span>Recibí de: </span><b>{{ $emisorRazon }}</b>
    <span> el siguiente equipo para uso exclusivo del desempeño de mis actividades laborales asignadas,
      el cual se reserva el derecho de retirar cuando así lo considere necesario la empresa.</span>
  </div>

  <br>

  <div class="blk"><span>Consta de las siguientes características</span></div>

  {{-- TABLA DE EQUIPOS --}}
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
      @foreach($detalles as $d)
        @php
          $p = $d->producto;
          $s = $d->serie;

          $specS = $s->specs ?? $s->especificaciones ?? null;
          if (is_string($specS)) { $tmp = json_decode($specS, true); if (json_last_error() === JSON_ERROR_NONE) $specS = $tmp; }
          $specP = $p->specs ?? $p->especificaciones ?? null;
          if (is_string($specP)) { $tmp = json_decode($specP, true); if (json_last_error() === JSON_ERROR_NONE) $specP = $tmp; }

          $des = '';
          if (($p->tipo ?? null) === 'equipo_pc') {
              $color = '';
              if (is_array($specS)) $color = $specS['color'] ?? '';
              if (!$color && is_array($specP)) $color = $specP['color'] ?? '';
              $des = $color !== '' ? $color : ($p->descripcion ?? '');
          } else {
              $des = $p->descripcion ?? '';
          }
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

  <div class="blk" style="margin-top:10px">
    Los daños ocasionados por el mal manejo o imprudencia, así como el robo o pérdida total o parcial a causa de
    negligencia o descuido, serán mi responsabilidad y asumo las consecuencias que de esto deriven.
  </div>

  <br>

  {{-- FECHA DE ENTREGA --}}
  <table class="tbl meta fecha-entrega" style="margin-top:12px">
    <colgroup><col class="label"><col class="val"></colgroup>
    <tr>
      <td class="label nowrap">Fecha de entrega</td>
      <td class="val nowrap center">{{ $fechaEntregaFmt }}</td>
    </tr>
  </table>

  <br>
  
  {{-- FIRMAS (sin bordes; imágenes centradas; líneas iguales) --}}
  <table class="tbl firmas" style="margin-top:10px">
    <tr>
      <td style="width:50%">
        <div class="sign-title">ENTREGÓ</div>
        <div class="sign-sub">Departamento de Sistemas</div>
        <div class="sign-space">
          @if($firmaEntregoB64)
            <img class="firma" src="{{ $firmaEntregoB64 }}" alt="Firma entregó">
          @endif
        </div>
        <div class="sign-name">{{ $entregoNombre }}</div>
        <div class="sign-line"></div>
        <div class="sign-caption">Nombre y firma</div>
      </td>

      <td style="width:50%">
        <div class="sign-title">RECIBIÓ</div>
        <div class="sign-sub">Recibí de conformidad Usuario</div>
        <div class="sign-space">
          @if($firmaColabB64)
            <img class="firma" src="{{ $firmaColabB64 }}" alt="Firma colaborador">
          @endif
        </div>
        <div class="sign-name">{{ $nombreCompleto }}</div>
        <div class="sign-line"></div>
        <div class="sign-caption">Nombre y firma</div>
      </td>
    </tr>

    <tr>
      <td colspan="2" style="padding-top:10px;">
        <div class="sign-title">AUTORIZÓ</div>
        <div class="sign-space">
          @if($firmaAutorizaB64)
            <img class="firma" src="{{ $firmaAutorizaB64 }}" alt="Firma autorizó">
          @endif
        </div>
        <div class="sign-name">{{ $autorizaNombre }}</div>
        <div class="sign-line"></div>
        <div class="sign-caption">Nombre y firma</div>
      </td>
    </tr>
  </table>

</body>
</html>
