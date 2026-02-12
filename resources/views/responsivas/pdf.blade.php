@php
  use Illuminate\Support\Str;
  use Illuminate\Support\Carbon;

  $empresaNombre = 'GRUPO VYSISA';

  /* ===== LOGO (igual que devoluciones) ===== */
  $empresaId = (int) (
      session('empresa_activa')
      ?? auth()->user()?->empresa_id
      ?? $responsiva->empresa_tenant_id
      ?? 0
  );

  $logoFile = public_path('images/logos/default.png');

  if (class_exists(\App\Models\Empresa::class) && $empresaId) {
    $emp = \App\Models\Empresa::find($empresaId);
    if ($emp) {
      // ✅ NO cambiar el texto del encabezado
      // $empresaNombre = strtoupper($emp->nombre ?? $empresaNombre);

      // ✅ PERO sí usamos el nombre para buscar el logo
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

  /* ===== DATOS BASE RESPONSIVA ===== */
  $col = $responsiva->colaborador ?? null;

  $apellidos = $col?->apellido
            ?? $col?->apellidos
            ?? trim(($col?->apellido_paterno ?? $col?->primer_apellido ?? '').' '.($col?->apellido_materno ?? $col?->segundo_apellido ?? ''));
  $usuarioNombre = trim(trim($col?->nombre ?? '').' '.trim($apellidos ?? '')) ?: '—';

  $fechaSolicitudFmt = !empty($responsiva->fecha_solicitud)
      ? Carbon::parse($responsiva->fecha_solicitud)->format('d-m-Y')
      : '—';

  $unidadServicio = $col?->unidad_servicio?->nombre
      ?? $col?->unidadServicio?->nombre
      ?? $col?->unidad
      ?? $col?->servicio
      ?? '—';

  $motivo = strtolower(trim($responsiva->motivo_entrega ?? ''));
  $isAsignacion   = $motivo === 'asignacion';
  $isPrestamoProv = $motivo === 'prestamo_provisional';

  // ✅ SOLO para Celulares (no afecta responsiva normal)
  $isCel = (($responsiva->tipo_documento ?? null) === 'CEL');
  $obs   = trim((string)($responsiva->observaciones ?? ''));

  // Motivo / frases
  $detalles = $responsiva->detalles ?? collect();

  $productosLista = $detalles->map(function ($d) {
    $nombre = trim($d->producto->nombre ?? '');
    return $nombre !== '' ? $nombre : null;
  })->filter()->unique()->values()->all();

  $fraseEntrega = $productosLista
    ? ('Se hace entrega de '.implode(', ', $productosLista).'.')
    : 'Se hace entrega de equipo y accesorios.';

    // ✅ SOLO si es CEL: agrega producto(s) y luego observaciones
    if ($isCel) {
      // 1) Nombre(s) de producto(s) (limpia "resguardo")
      $productoCel = $productosLista
        ? implode(', ', array_map(function ($n) {
            $n = trim((string)$n);
            $n = preg_replace('/\bresguardo\b/i', '', $n);
            $n = trim(preg_replace('/\s+/', ' ', $n));
            return $n !== '' ? $n : 'Celular';
          }, $productosLista))
        : 'Celular';

      // 2) Num celular desde especificaciones de SERIE (primer match)
      $numCel = null;
      foreach ($detalles as $d) {
        $s = $d->serie;
        if (!$s) continue;

        $specS = $s->especificaciones ?? $s->specs ?? null;
        if (is_string($specS)) {
          $tmp = json_decode($specS, true);
          if (json_last_error() === JSON_ERROR_NONE) $specS = $tmp;
        }

        $cand = data_get($specS, 'num_celular')
            ?? data_get($specS, 'numero_celular')
            ?? data_get($specS, 'telefono')
            ?? data_get($specS, 'numero_telefono')
            ?? data_get($specS, 'linea')
            ?? data_get($specS, 'numero_linea');

        $cand = trim((string)$cand);
        if ($cand !== '') { $numCel = $cand; break; }
      }

      // 3) Frase final
      $fraseEntrega = 'Se hace entrega de equipo '.$productoCel;

      if (!empty($numCel)) {
        $fraseEntrega .= ' (Num. Celular: '.$numCel.')';
      }

      $fraseEntrega .= ','; // coma obligatoria

      $obsCel = trim((string)($responsiva->observaciones ?? ''));
      if ($obsCel === '') {
        $fraseEntrega .= ' para actividades.';
      } else {
        $fraseEntrega .= ' para '.$obsCel.'.';
      }
    }
  
  // ✅ NUEVO: si en la responsiva viene al menos 1 producto tipo celular/telefono,
  // mostramos columna "Accesorios" entre Modelo y Número de serie.
  $hasCelularRow = $detalles->contains(function($d){
    $tipo = (string) ($d->producto->tipo ?? '');
    return in_array($tipo, ['celular','telefono'], true);
  }); 
  
  // Razón social emisor (igual que tu show/pdf anterior)
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

  // filas mínimas como tu responsiva actual
  $minRows = 6;
  $faltan  = max(0, $minRows - $detalles->count());

  $fechaEntregaFmt = !empty($responsiva->fecha_entrega)
  ? \Illuminate\Support\Carbon::parse($responsiva->fecha_entrega)->format('d-m-Y')
  : '—';

  /* ===== Helpers: archivo -> dataURL base64 (para DomPDF) ===== */
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
        $slug = \Illuminate\Support\Str::slug($name);
        $cands[] = public_path("storage/firmas/{$slug}.{$ext}");
        $cands[] = public_path("storage/firmas/{$name}.{$ext}");
      }
    }

    foreach ($cands as $full) {
      if (is_file($full)) return $full;
    }
    return null;
  };

  // Firmas (ENTREGÓ / AUTORIZÓ) por usuario
  $firmaEntregoB64  = $toB64($findFirmaPath($responsiva->entrego ?? null));
  $firmaAutorizaB64 = $toB64($findFirmaPath($responsiva->autoriza ?? null));

  // Firma del colaborador (RECIBIÓ) desde archivo guardado en responsiva
  $firmaColabB64 = null;
  if (!empty($responsiva->firma_colaborador_path)) {
    $full = public_path('storage/'.ltrim($responsiva->firma_colaborador_path, '/'));
    $firmaColabB64 = $toB64($full);
  }

    // Alias para no cambiar el HTML
  $nombreCompleto = $usuarioNombre;

  // Nombres de firmantes (como en tu PDF anterior)
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

@endphp

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Responsiva {{ $responsiva->folio }}</title>
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
    .mark-cell{ width:18px; text-align:center; font-weight:bold; }

    .meta-1 tr:last-child td{ border-bottom: 1px solid #ccc !important; }
    .meta-2 tr:first-child td{ border-top: none !important; }

    html, body{ height:100%; overflow:hidden !important; }
    table, div, p, img{ page-break-inside: avoid !important; }

    /* ===== Cuadro Fecha (derecha) ===== */
    .fecha-box-wrap{
      width:100%;
      border:none !important;
      margin-top:10px;
      margin-bottom:65px; /* deja espacio para firmas */
      text-align:right;   /* ✅ fuerza derecha */
    }

    .fecha-box-wrap td{
      border:none !important;
      padding:0 !important;
    }

    .fecha-box{
      width:230px;
      border-collapse:collapse;
      display:inline-table; /* ✅ clave: lo trata como inline y respeta text-align:right */
      margin:0 !important;  /* ✅ evita el margin:0 auto global */
    }

    .fecha-box td{
      border:1px solid #000 !important;
      padding:5px 6px;
      text-align:center;
      font-size:9.8px;
    }

    /* ===== Firmas fijas al fondo (estilo devoluciones) ===== */
    .firmas-fixed{
      position: absolute;
      bottom: 20px;   /* ajustable */
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

    .sign-space{ height: 65px; margin: 0 auto; }
    .firma-img{ height: 65px; margin: 5px 0; }

    .sign-name{ text-transform: uppercase; font-size: 9px; margin: 2px 0; }
    .sign-line{ width: 55%; margin: 4px auto; border-top: 1px solid #000; }
    .sign-caption{ font-size: 9px; margin-top: 2px; text-transform: uppercase; }

    /* ===== Alineación izquierda/derecha en la fila superior ===== */
    .sign-col{
      width: 50%;
      border: none !important;
      vertical-align: top;
      padding: 0 !important;
    }

    .sign-col-left  { text-align: left !important; }
    .sign-col-right { text-align: right !important; }

    .sign-box{
      width: 320px;           /* ancho fijo para que ambos bloques queden iguales */
      display: inline-block;  /* permite pegar el bloque al lado izq/der */
      text-align: center;     /* dentro del bloque todo centrado */
    }

    .sign-space{
      height: 65px;           /* mismo alto en ambos */
      display: block;
    }

    .firma-img{
      height: 65px;
      width: auto;
      display: block;
      margin: 0 auto;         /* centra la firma */
    }

    .sign-name{ margin: 3px 0 0 0; }
    .sign-line{ margin: 4px auto 0 auto; }
    .sign-caption{ margin-top: 2px; }

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
      <tr><td class="title-sub">{{ $isCel ? 'Formato de Préstamo' : 'Formato de Responsiva' }}</td></tr>
    </table>

    <br>

    <!-- ===== METADATOS 1 ===== -->
    <table class="meta-1">
      <tr>
        <td class="label">No. de salida</td>
        <td>{{ $responsiva->folio ?? '—' }}</td>
        <td class="label">{{ $isCel ? 'Fecha de salida' : 'Fecha de solicitud' }}</td>
        <td>{{ $fechaSolicitudFmt }}</td>
        <td class="label">Nombre del usuario</td>
        <td>{{ $usuarioNombre }}</td>
      </tr>
    </table>

    <!-- ===== METADATOS 2 ===== -->
    <table class="meta-2">
      <tr>
        <td class="label">Área/Departamento</td>
        <td>{{ $unidadServicio }}</td>
        <td class="label">Motivo de entrega</td>
        <td class="label">Asignación</td>
        <td class="mark-cell">{!! $isAsignacion ? 'X' : '&#160;' !!}</td>
        <td class="label">Préstamo provisional</td>
        <td class="mark-cell">{!! $isPrestamoProv ? 'X' : '&#160;' !!}</td>
      </tr>
    </table>

    <br><br>

    <p style="margin:0 0 5px 0; text-align:justify; line-height:1.25;">
      <strong>Por medio de la presente hago constar que:</strong>
      {{ $fraseEntrega }}
    </p>

    <br><br>

    <p style="margin:0 0 5px 0; text-align:justify; line-height:1.25;">
      Recibí de: <strong>{{ $emisorRazon }}</strong> el siguiente equipo para uso exclusivo del desempeño de mis actividades laborales asignadas,
      el cual se reserva el derecho de retirar cuando así lo considere necesario la empresa.
    </p>

    <p style="margin:0 0 6px 0; text-align:justify; line-height:1.25;">
      Consta de las siguientes características
    </p>

    <table style="margin-top:6px;">
      <thead>
        <tr style="background:#f5f5f5; font-weight:bold;">
          <th style="width:20%;">EQUIPO</th>
          <th style="width:28%;">DESCRIPCIÓN</th>

          @if($hasCelularRow)
            <th style="width:22%;">MARCA Y MODELO</th>
            <th style="width:14%;">ACCESORIOS</th>
            <th>NÚMERO DE SERIE</th>
          @else
            <th style="width:16%;">MARCA</th>
            <th style="width:16%;">MODELO</th>
            <th>NÚMERO DE SERIE</th>
          @endif
        </tr>
      </thead>
      <tbody>
        @foreach($detalles as $d)
          @php
            $p = $d->producto;
            $s = $d->serie;

            // specs (serie y producto) como en tu show/pdf actual
            $specS = $s->especificaciones ?? $s->specs ?? null;
            if (is_string($specS)) { $tmp = json_decode($specS, true); if (json_last_error() === JSON_ERROR_NONE) $specS = $tmp; }

            $specP = $p->especificaciones ?? $p->specs ?? null;
            if (is_string($specP)) { $tmp = json_decode($specP, true); if (json_last_error() === JSON_ERROR_NONE) $specP = $tmp; }

            // ✅ Descripción universal (PDF):
            // 1) color (serie)
            // 2) descripcion (serie)
            // 3) color (producto)
            // 4) descripcion del producto
            $colorSerie = data_get($specS, 'color');
            $descSerie  = data_get($specS, 'descripcion');

            $colorProd  = data_get($specP, 'color');
            $descProd   = $p->descripcion ?? '';

            $des = '';
            if (filled($colorSerie))      $des = $colorSerie;
            elseif (filled($descSerie))   $des = $descSerie;
            elseif (filled($colorProd))   $des = $colorProd;
            else                          $des = $descProd;

            $des = trim((string)$des);
            if ($des === '') $des = '—';

            $tipoRow = (string) ($p->tipo ?? '');

            // accesorios (solo para celular/telefono, por serie)
            $acc = data_get($specS, 'accesorios');
            if (is_string($acc)) { $tmp = json_decode($acc, true); if (json_last_error() === JSON_ERROR_NONE) $acc = $tmp; }
            $acc = is_array($acc) ? $acc : [];

            $accLabels = [
              'mica_protectora' => 'Mica',
              'funda'           => 'Funda',
              'cargador'        => 'Cargador',
              'cable_usb'       => 'Cable USB',
            ];

            $accList = [];
            foreach ($accLabels as $k => $label) {
              if (!empty($acc[$k])) $accList[] = $label;
            }

            $marcaModelo = trim(trim((string)($p?->marca ?? '')).' '.trim((string)($p?->modelo ?? '')));
            if ($marcaModelo === '') $marcaModelo = '—';
          @endphp

          <tr>
            <td style="text-transform:uppercase;">{{ $p?->nombre ?? '—' }}</td>
            <td>{{ $des ?: '—' }}</td>

            @if($hasCelularRow)
              <td>{{ $marcaModelo }}</td>

              <td>
                @if(in_array($tipoRow, ['celular','telefono'], true))
                  @if(count($accList))
                    {{ implode(', ', $accList) }}
                  @else
                    —
                  @endif
                @else
                  —
                @endif
              </td>

              <td>{{ $s?->serie ?? '—' }}</td>
            @else
              <td>{{ $p?->marca ?? '—' }}</td>
              <td>{{ $p?->modelo ?? '—' }}</td>
              <td>{{ $s?->serie ?? '—' }}</td>
            @endif
          </tr>
        @endforeach

        @for($i=0; $i<$faltan; $i++)
          @if($hasCelularRow)
            <tr>
              <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
            </tr>
          @else
            <tr>
              <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
            </tr>
          @endif
        @endfor
      </tbody>
    </table>

    <p style="margin:8px 0 0 0; text-align:justify; line-height:1.25;">
      Los daños ocasionados por el mal manejo o imprudencia, así como el robo o pérdida total o parcial a causa de negligencia o descuido,
      serán mi responsabilidad y asumo las consecuencias que de esto deriven.
    </p>

    <br>

    <table class="fecha-box-wrap">
      <tr>
        <td style="text-align:right;">
          <table class="fecha-box">
            <tr>
              <td class="label">FECHA DE ENTREGA</td>
              <td>{{ $fechaEntregaFmt }}</td>
            </tr>
          </table>
        </td>
      </tr>
    </table>

    <div class="firmas-fixed">
      <!-- FILA SUPERIOR (ENTREGÓ / RECIBIÓ) -->
      <table style="margin-bottom: 28px;">
        <tr>
          <td class="sign-col sign-col-left">
            <div class="sign-box">
              <p class="sign-title">ENTREGÓ</p>
              <p>DEPARTAMENTO DE SISTEMAS</p>

              <div class="sign-space">
                @if($firmaEntregoB64)
                  <img class="firma-img" src="{{ $firmaEntregoB64 }}" alt="Firma entregó">
                @else
                  <div style="height:65px;"></div>
                @endif
              </div>

              <p class="sign-name">{{ $entregoNombre ?: '_________________________' }}</p>
              <div class="sign-line" style="width:75%;"></div>
              <p class="sign-caption">NOMBRE Y FIRMA</p>
            </div>
          </td>

          <td class="sign-col sign-col-right">
            <div class="sign-box">
              <p class="sign-title">RECIBIÓ</p>
              <p>RECIBÍ DE CONFORMIDAD USUARIO</p>

              <div class="sign-space">
                @if($firmaColabB64)
                  <img class="firma-img" src="{{ $firmaColabB64 }}" alt="Firma colaborador">
                @else
                  <div style="height:65px;"></div>
                @endif
              </div>

              <p class="sign-name">{{ $nombreCompleto ?: '_________________________' }}</p>
              <div class="sign-line" style="width:75%;"></div>
              <p class="sign-caption">NOMBRE Y FIRMA</p>
            </div>
          </td>
        </tr>
      </table>

      @if(!$isCel)
        <!-- FILA INFERIOR (AUTORIZÓ) -->
        <table>
          <tr>
            <td style="width:100%;">
              <p class="sign-title">AUTORIZÓ</p>

              <div class="sign-space">
                @if($firmaAutorizaB64)
                  <img class="firma-img" src="{{ $firmaAutorizaB64 }}" alt="Firma autorizó">
                @else
                  <div style="height:65px;"></div>
                @endif
              </div>

              <p class="sign-name">{{ $autorizaNombre ?: '_________________________' }}</p>
              <div class="sign-line" style="width:35%;"></div>
              <p class="sign-caption">NOMBRE Y FIRMA</p>
            </td>
          </tr>
        </table>
      @endif
    </div>


  </div>
</body>
</html>
