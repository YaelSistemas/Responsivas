{{-- resources/views/cartuchos/pdf.blade.php --}}
@php
  use Illuminate\Support\Str;
  use Illuminate\Support\Carbon;

  $empresaNombre = 'GRUPO VYSISA';

  /* ===== LOGO (igual que responsivas PDF) ===== */
  $empresaId = (int) (
      session('empresa_activa')
      ?? auth()->user()?->empresa_id
      ?? ($cartucho->empresa_tenant_id ?? 0)
      ?? 0
  );

  $logoFile = public_path('images/logos/default.png');

  if (class_exists(\App\Models\Empresa::class) && $empresaId) {
    $emp = \App\Models\Empresa::find($empresaId);
    if ($emp) {
      // ✅ NO cambies texto del encabezado si no quieres
      // $empresaNombre = strtoupper($emp->nombre ?? $empresaNombre);

      // ✅ Sí usar nombre para buscar logo por slug
      $slug = Str::slug($emp->nombre ?? '', '-');
      foreach (['png','jpg','jpeg','webp','svg'] as $ext) {
        $candidate = public_path("images/logos/{$slug}.{$ext}");
        if (is_file($candidate)) { $logoFile = $candidate; break; }
      }
    }
  }

  $logoB64 = null;
  if (is_file($logoFile)) {
    $ext = strtolower(pathinfo($logoFile, PATHINFO_EXTENSION));
    $mime = match($ext) {
      'jpg','jpeg' => 'image/jpeg',
      'png' => 'image/png',
      'webp' => 'image/webp',
      'svg' => 'image/svg+xml',
      default => 'image/png'
    };
    $logoB64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoFile));
  }

  /* ===== DATOS BASE (como tu show) ===== */
  $col = $cartucho->colaborador ?? null;

  $apellidos = $col?->apellido
            ?? $col?->apellidos
            ?? trim(($col?->apellido_paterno ?? $col?->primer_apellido ?? '').' '.($col?->apellido_materno ?? $col?->segundo_apellido ?? ''));
  $nombreCompleto = trim(trim($col?->nombre ?? '').' '.trim($apellidos ?? '')) ?: '—';

  // Unidad de servicio (compatible)
  $unidadServicio = $col?->unidadServicio
                  ?? $col?->unidad_servicio
                  ?? $col?->unidad_de_servicio
                  ?? $col?->unidad
                  ?? $col?->servicio
                  ?? '—';
  if (is_object($unidadServicio)) {
    $unidadServicio = $unidadServicio->nombre ?? $unidadServicio->name ?? $unidadServicio->descripcion ?? (string) $unidadServicio;
  } elseif (is_array($unidadServicio)) {
    $unidadServicio = implode(' ', array_filter($unidadServicio));
  }
  $unidadServicio = $unidadServicio ?: '—';

  // Equipo (impresora/multi) = producto del cartucho (como show)
  $equipoTxt = trim(
    ($cartucho->producto?->nombre ?? '')
    .' '.($cartucho->producto?->marca ?? '')
    .' '.($cartucho->producto?->modelo ?? '')
  );
  $equipoTxt = $equipoTxt !== '' ? $equipoTxt : '—';

  // Realizado por (usuario)
  $realizoTxt = $cartucho->realizadoPor?->name
             ?? $cartucho->firmaRealizoUser?->name
             ?? '—';

  // Fecha solicitud
  $fechaSolicitudFmt = !empty($cartucho->fecha_solicitud)
      ? ( ($cartucho->fecha_solicitud instanceof Carbon)
          ? $cartucho->fecha_solicitud->format('d-m-Y')
          : Carbon::parse($cartucho->fecha_solicitud)->format('d-m-Y')
        )
      : '—';

  /* ===== Helpers: archivo -> dataURL base64 (DomPDF) ===== */
  $toB64 = function (?string $fullPath): ?string {
    if (!$fullPath || !is_file($fullPath)) return null;
    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    $mime = match ($ext) {
      'png' => 'image/png',
      'jpg', 'jpeg' => 'image/jpeg',
      'webp' => 'image/webp',
      'svg' => 'image/svg+xml',
      default => 'image/png',
    };
    $data = @file_get_contents($fullPath);
    if ($data === false) return null;
    return 'data:' . $mime . ';base64,' . base64_encode($data);
  };

  /* ===== Firmas globales por usuario (storage/firmas) ===== */
  $findFirmaPath = function ($user): ?string {
    if (!$user) return null;

    $id   = $user->id ?? null;
    $name = trim($user->name ?? ($user->nombre ?? ''));
    $cands = [];

    foreach (['png','jpg','jpeg','webp','svg'] as $ext) {
      if ($id)   $cands[] = public_path("storage/firmas/{$id}.{$ext}");
      if ($name !== '') {
        $slug = Str::slug($name);
        $cands[] = public_path("storage/firmas/{$slug}.{$ext}");
        $cands[] = public_path("storage/firmas/{$name}.{$ext}");
      }
    }

    foreach ($cands as $full) {
      if (is_file($full)) return $full;
    }
    return null;
  };

  // ENTREGÓ = realizadoPor / firmaRealizoUser
  $entregoUser = $cartucho->realizadoPor ?? $cartucho->firmaRealizoUser ?? null;
  $entregoNombre = ($entregoUser?->name ?? '') ?: $realizoTxt;

  $firmaEntregoB64 = $toB64($findFirmaPath($entregoUser));

  // RECIBIÓ = firma guardada en cartuchos (si existe)
  $firmaRecibioB64 = null;
  $firmaRecibioRaw = $cartucho->firma_colaborador_url
                  ?? $cartucho->firma_colaborador_path
                  ?? $cartucho->firma_recibio_url
                  ?? $cartucho->firma_recibio_path
                  ?? null;

  if (!empty($firmaRecibioRaw)) {
    // si viene como URL/data: no la podemos volver path fácilmente; si viene como path relativo lo convertimos a public_path(storage/...)
    if (Str::startsWith($firmaRecibioRaw, 'data:')) {
      $firmaRecibioB64 = $firmaRecibioRaw;
    } elseif (Str::startsWith($firmaRecibioRaw, ['http://','https://'])) {
      // DomPDF suele fallar con remotas; aquí lo dejamos en null para no romper
      $firmaRecibioB64 = null;
    } else {
      $rel = ltrim($firmaRecibioRaw, '/');
      // si guardas "firmas/xxx.png" lo normal es storage/firmas/..., pero por compatibilidad:
      $full = public_path($rel);
      if (!is_file($full)) $full = public_path('storage/'.ltrim($rel,'storage/'));
      $firmaRecibioB64 = $toB64($full);
    }
  }

  /* ===== Detalles (productos/consumibles) ===== */
  $detalles = $cartucho->detalles ?? collect();
  $minRows = 5;
  $faltan  = max(0, $minRows - $detalles->count());

  $colorLabel = function ($producto): array {
    $rawSpecs = $producto?->especificaciones ?? null;
    $specArr = [];

    if (is_array($rawSpecs)) {
      $specArr = $rawSpecs;
    } elseif (is_string($rawSpecs) && $rawSpecs !== '') {
      $tmp = json_decode($rawSpecs, true);
      if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) $specArr = $tmp;
    }

    $rawColor = trim((string)($specArr['color'] ?? $specArr['Color'] ?? ''));
    $rawColor = preg_replace('/\s*\([^)]*\)\s*/', '', $rawColor);
    $rawColor = trim($rawColor);

    $key = mb_strtolower($rawColor, 'UTF-8');

    if ($key === '') return ['', ''];
    if ($key === 'yellow')  return ['AMARILLO', 'c-yellow'];
    if ($key === 'magenta') return ['MAGENTA', 'c-magenta'];
    if ($key === 'cian' || $key === 'cyan') return ['AZUL', 'c-cyan'];
    if ($key === 'black')   return ['NEGRO', 'c-black'];

    return [Str::upper($rawColor), ''];
  };

@endphp

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Salida de cartuchos {{ $cartucho->folio ?? ('#'.$cartucho->id) }}</title>

  <style>
    @page { size: letter portrait; margin: 15px 18px 10px 18px; }

    body{
      margin:0; padding:0;
      font-family: DejaVu Sans, Arial, sans-serif;
      font-size: 9.8px;
      color:#000;
    }

    .sheet{
      width:100%;
      transform-origin: top center;
      display:flex;
      justify-content:center;
      position:relative;
    }

    table{
      width:100%;
      margin:0 auto;
      border-collapse:collapse;
      border:1px solid #000;
      page-break-inside: avoid !important;
    }

    td, th{
      border:1px solid #000;
      padding:3px 4px;
      vertical-align:middle;
      text-align:center;
    }

    .logo-cell{ width:28%; text-align:center; }
    .logo-box{
      display:flex;
      align-items:center;
      justify-content:center;
      height:80px;
      width:100%;
    }
    .logo-box img{
      max-width:90%;
      max-height:75px;
      object-fit:contain;
    }

    .title-main{ font-weight:bold; text-transform:uppercase; font-size:13.5px; }
    .title-sub { font-weight:bold; text-transform:uppercase; font-size:11.5px; }

    .label{
      font-weight:bold;
      text-transform:uppercase;
      white-space:nowrap;
      text-align:left;
    }

    html, body{ height:100%; overflow:hidden !important; }
    table, div, p, img{ page-break-inside: avoid !important; }

    /* ===== metadatos (pegados) ===== */
    .meta-1 tr:last-child td{ border-bottom: 1px solid #ccc !important; }
    .meta-2 tr:first-child td{ border-top: none !important; }

    /* ===== texto ===== */
    .texto{
      margin:0 0 6px 0;
      text-align:justify;
      line-height:1.25;
      font-size: 10.4px;
    }

    /* ===== tabla productos ===== */
    .thead th{ background:#f5f5f5; font-weight:bold; }
    .qty{ width:14%; font-weight:bold; }
    .desc{ width:56%; }
    .sku{ width:30%; }

    .c-yellow{ color:#d4a400; font-weight:bold; }
    .c-cyan{ color:#0070c0; font-weight:bold; }
    .c-magenta{ color:#b000b5; font-weight:bold; }
    .c-black{ color:#000; font-weight:bold; }

    /* ===== Firmas fijas al fondo (como responsivas) ===== */
    .firmas-fixed{
      position: absolute;
      bottom: 18px;
      left: 0;
      right: 0;
      width: 100%;
      page-break-inside: avoid !important;
    }

    .firmas-fixed table{
      width: 100%;
      border: none !important;
      border-collapse: collapse;
    }

    .firmas-fixed td{
      border: none !important;
      text-align: center;
      vertical-align: top;
      padding: 0 10px;
    }

    .sign-title{ font-weight: bold; text-transform: uppercase; font-size: 12px; margin: 0; }
    .sign-sub{ font-weight: bold; text-transform: uppercase; font-size: 9px; margin: 2px 0 6px; }

    .sign-col{ width: 50%; border:none !important; vertical-align: top; padding: 0 !important; }
    .sign-col-left  { text-align: left !important; }
    .sign-col-right { text-align: right !important; }

    .sign-box{
      width: 320px;
      display: inline-block;
      text-align: center;
    }

    .sign-space{ height: 65px; display:block; }
    .firma-img{ height: 65px; width:auto; display:block; margin: 0 auto; }

    .sign-name{ text-transform: uppercase; font-size: 9px; margin: 3px 0 0 0; }
    .sign-line{ width: 75%; margin: 4px auto 0 auto; border-top: 1px solid #000; }
    .sign-caption{ font-size: 9px; margin-top: 2px; text-transform: uppercase; }

  </style>
</head>

<body>
  <div class="sheet">

    <!-- ===== ENCABEZADO ===== -->
    <table>
      <tr>
        <td class="logo-cell" rowspan="3">
          <div class="logo-box">
            @if($logoB64)
              <img src="{{ $logoB64 }}" alt="Logo">
            @endif
          </div>
        </td>
        <td class="title-main">{{ $empresaNombre }}</td>
      </tr>
      <tr><td class="title-sub">Departamento de Sistemas</td></tr>
      <tr><td class="title-sub">Formato de Entrega de Cartuchos</td></tr>
    </table>

    <br>

    <!-- ===== METADATOS 1 ===== -->
    <table class="meta-1">
      <tr>
        <td class="label">No. de salida</td>
        <td>{{ $cartucho->folio ?? ('#'.$cartucho->id) }}</td>

        <td class="label">Fecha de solicitud</td>
        <td>{{ $fechaSolicitudFmt }}</td>

        <td class="label">Colaborador</td>
        <td>{{ $nombreCompleto }}</td>
      </tr>
    </table>

    <!-- ===== METADATOS 2 ===== -->
    <table class="meta-2">
      <tr>
        <td class="label">Equipo</td>
        <td>{{ $equipoTxt }}</td>

        <td class="label">Unidad de servicio</td>
        <td>{{ $unidadServicio }}</td>

        <td class="label">Realizado por</td>
        <td>{{ $realizoTxt }}</td>
      </tr>
    </table>

    <br><br>

    <p class="texto">
      Por medio de la presente hago constar que se realiza la entrega de los siguientes consumibles para el desempeño de mis actividades laborales.
    </p>

    <br>

    <!-- ===== TABLA PRODUCTOS ===== -->
    <table style="margin-top:6px;">
      <thead class="thead">
        <tr>
          <th style="width:20%;">CANTIDAD</th>
          <th style="width:55%;">DESCRIPCIÓN</th>
          <th style="width:25%;">SKU</th>
        </tr>
      </thead>

      <tbody>
        @foreach($detalles as $d)
          @php
            $p = $d->producto ?? null;
            $qty = (int) ($d->cantidad ?? 0);
            $sku = trim((string)($p?->sku ?? ''));

            $descBase = trim(
              ($p?->nombre ?? '')
              .' '.($p?->marca ?? '')
              .' '.($p?->modelo ?? '')
            );

            [$colorText, $colorClass] = $colorLabel($p);
          @endphp

          <tr>
            <td class="qty">{{ $qty ?: '—' }}</td>
            <td class="desc">
              {{ $descBase !== '' ? $descBase : '—' }}
              @if($colorText !== '')
                - <span class="{{ $colorClass }}">{{ $colorText }}</span>
              @endif
            </td>
            <td class="sku">{{ $sku !== '' ? $sku : '—' }}</td>
          </tr>
        @endforeach

        @for($i=0; $i<$faltan; $i++)
          <tr>
            <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
          </tr>
        @endfor
      </tbody>
    </table>

    <p class="texto" style="margin-top:8px;">
      El colaborador se compromete a utilizar los consumibles conforme a las políticas internas.
      Cualquier uso indebido o extravío deberá reportarse de inmediato al Departamento de Sistemas.
    </p>

    <!-- ===== FIRMAS FIJAS AL FONDO ===== -->
    <div class="firmas-fixed">
      <table>
        <tr>
          <td class="sign-col sign-col-left">
            <div class="sign-box">
              <p class="sign-title">ENTREGÓ</p>
              <p class="sign-sub">DEPARTAMENTO DE SISTEMAS</p>

              <div class="sign-space">
                @if($firmaEntregoB64)
                  <img class="firma-img" src="{{ $firmaEntregoB64 }}" alt="Firma entregó">
                @else
                  <div style="height:65px;"></div>
                @endif
              </div>

              <p class="sign-name">{{ $entregoNombre ?: '_________________________' }}</p>
              <div class="sign-line"></div>
              <p class="sign-caption">NOMBRE Y FIRMA</p>
            </div>
          </td>

          <td class="sign-col sign-col-right">
            <div class="sign-box">
              <p class="sign-title">RECIBIÓ</p>
              <p class="sign-sub">RECIBÍ DE CONFORMIDAD USUARIO</p>

              <div class="sign-space">
                @if($firmaRecibioB64)
                  <img class="firma-img" src="{{ $firmaRecibioB64 }}" alt="Firma recibió">
                @else
                  <div style="height:65px;"></div>
                @endif
              </div>

              <p class="sign-name">{{ $nombreCompleto ?: '_________________________' }}</p>
              <div class="sign-line"></div>
              <p class="sign-caption">NOMBRE Y FIRMA</p>
            </div>
          </td>
        </tr>
      </table>
    </div>

  </div>
</body>
</html>
