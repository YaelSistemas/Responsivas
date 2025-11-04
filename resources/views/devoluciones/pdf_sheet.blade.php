@php
  use Illuminate\Support\Str;
  use Illuminate\Support\Carbon;

  $empresaNombre = 'GRUPO VYSISSA';

  /* ===== LOGO ===== */
  $logoFile = public_path('images/logos/default.png');
  if (class_exists(\App\Models\Empresa::class)) {
    $emp = \App\Models\Empresa::find((int) session('empresa_activa', auth()->user()?->empresa_id));
    if ($emp) {
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

  /* ===== DATOS BASE ===== */
  $colaborador = $devolucion->usuario
      ?? $devolucion->colaborador
      ?? $devolucion->responsiva?->colaborador
      ?? $devolucion->entregoColaborador;

  $usuarioNombre = $colaborador
      ? trim(($colaborador->nombre ?? '').' '.($colaborador->apellidos ?? '').' '.($colaborador->name ?? ''))
      : '—';

  $fechaEntrega = $devolucion->fecha_entrega
      ?? $devolucion->responsiva?->fecha_entrega
      ?? null;

  $fechaEntregaFmt = $fechaEntrega
      ? Carbon::parse($fechaEntrega)->format('d-m-Y')
      : '—';

  $unidadServicio = $colaborador?->unidad_servicio?->nombre
      ?? $colaborador?->unidadServicio?->nombre
      ?? $colaborador?->unidad
      ?? '—';

  /* ===== RAZÓN SOCIAL ===== */
  $razonSocial = null;
  if ($colaborador?->subsidiaria) {
      $razonSocial = $colaborador->subsidiaria->descripcion ?: $colaborador->subsidiaria->nombre;
  }
  if (!$razonSocial && $devolucion->responsiva?->colaborador?->subsidiaria) {
      $razonSocial = $devolucion->responsiva->colaborador->subsidiaria->descripcion
          ?: $devolucion->responsiva->colaborador->subsidiaria->nombre;
  }
  if (!$razonSocial && $devolucion->empresa) {
      $razonSocial = $devolucion->empresa->descripcion ?: $devolucion->empresa->nombre;
  }
  $razonSocial = $razonSocial ?: '—';

  $motivo = strtolower(trim($devolucion->motivo ?? ''));
  $isBaja = $motivo === 'baja_colaborador' || $motivo === 'baja de colaborador';
  $isRenovacion = $motivo === 'renovacion';

  $fechaDevolucion = $devolucion->fecha_devolucion ?? null;
  $fechaDevolucionFmt = $fechaDevolucion
      ? \Illuminate\Support\Carbon::parse($fechaDevolucion)->format('d-m-Y')
      : '—';

  /* ===== FIRMAS ===== */
  $col = $devolucion->entregoColaborador ?? null;
  $recibio = $devolucion->recibidoPor ?? null;
  $psitio  = $devolucion->psitioColaborador ?? null;

  $recibioNombre = $recibio
      ? trim(($recibio->nombre ?? '').' '.($recibio->apellidos ?? ''))
        ?: ($recibio->name ?? '')
      : '_________________________';

  $colNombreEntrego = $col
      ? trim(($col->nombre ?? '').' '.($col->apellidos ?? ''))
      : '_________________________';

  $psitioNombre = $psitio
      ? trim(($psitio->nombre ?? '').' '.($psitio->apellidos ?? ''))
      : '_________________________';

  $firmaUrlFor = function ($user) {
    if (!$user) return null;
    $id = $user->id ?? null;
    $name = trim($user->name ?? ($user->nombre ?? ''));
    foreach (['png','jpg','jpeg','webp','svg'] as $ext) {
      if ($id && file_exists(public_path("storage/firmas/{$id}.{$ext}"))) return public_path("storage/firmas/{$id}.{$ext}");
      if ($name && file_exists(public_path("storage/firmas/".Str::slug($name).".{$ext}"))) return public_path("storage/firmas/".Str::slug($name).".{$ext}");
    }
    return null;
  };

  $firmaRecibio = $firmaUrlFor($recibio);
  $firmaEntrego = $firmaUrlFor($col);
  $firmaPsitio  = $firmaUrlFor($psitio);
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Formato de Devolución</title>
  <style>
  @page {
    size: letter portrait;
    margin: 15px 18px 10px 18px;
  }

  body {
    margin: 0;
    padding: 0;
    font-family: DejaVu Sans, Arial, sans-serif;
    font-size: 9.8px;
    color: #000;
  }

  .sheet {
    width: 100%;
    transform-origin: top center;
    display: flex;
    justify-content: center;
    position: relative;
  }

  table {
    width: 100%;
    margin: 0 auto;
    border-collapse: collapse;
    border: 1px solid #000;
    page-break-inside: avoid !important;
  }

  td, th {
    border: 1px solid #000;
    padding: 3px 4px;
    vertical-align: middle;
    text-align: center;
  }

  .logo-cell { width: 28%; text-align: center; }

  .logo-box {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 80px;
    width: 100%;
  }

  .logo-box img {
    max-width: 90%;
    max-height: 75px;
    object-fit: contain;
  }

  .title-main { font-weight: bold; text-transform: uppercase; font-size: 13.5px; }
  .title-sub  { font-weight: bold; text-transform: uppercase; font-size: 11.5px; }

  .label { font-weight: bold; text-transform: uppercase; white-space: nowrap; text-align: left; }
  .mark-cell { width: 18px; text-align: center; font-weight: bold; }

  .meta-1 tr:last-child td { border-bottom: 1px solid #ccc !important; }
  .meta-2 tr:first-child td { border-top: none !important; }

  .section-title {
    font-weight: bold;
    text-transform: uppercase;
    margin-top: 5px;
    margin-bottom: 2px;
  }

  .paragraph {
    text-align: justify;
    line-height: 1.25;
    margin-bottom: 5px;
  }

  table.productos td, table.productos th {
    padding: 3px 3px;
    font-size: 9.5px;
  }

  @php $count = count($devolucion->productos ?? []); @endphp
  @if ($count <= 4)
    table.productos td, table.productos th { padding: 3px 3px; font-size: 9.5px; }
  @elseif ($count <= 6)
    table.productos td, table.productos th { padding: 2.5px 3px; font-size: 9px; }
  @elseif ($count <= 8)
    table.productos td, table.productos th { padding: 2px 2px; font-size: 8.7px; }
  @elseif ($count <= 10)
    table.productos td, table.productos th { padding: 1.8px 2px; font-size: 8.5px; }
  @else
    table.productos td, table.productos th { padding: 1.6px 1.8px; font-size: 8.3px; }
  @endif

  html, body { height: 100%; overflow: hidden !important; }
  table, div, p, img { page-break-inside: avoid !important; }

  /* Firmas fijas al fondo */
  .firmas-fixed {
    position: absolute;
    bottom: 65px;
    left: 0;
    right: 0;
    width: 100%;
    text-align: center;
    page-break-inside: avoid !important;
  }

  .firmas-fixed table td { border: none !important; }
  .firmas-fixed img { height: 65px; margin: 3px 0; }
  .firmas-fixed p { margin: 1px 0; font-size: 9px; }
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
      <tr><td class="title-sub">Formato de Devolución</td></tr>
    </table>

    <br>

    <!-- ===== METADATOS ===== -->
    <table class="meta-1">
      <tr>
        <td class="label">No. de Devolución</td>
        <td>{{ $devolucion->folio ?? '' }}</td>
        <td class="label">Fecha de Entrega</td>
        <td>{{ $fechaEntregaFmt }}</td>
        <td class="label">Nombre de Usuario</td>
        <td>{{ $usuarioNombre }}</td>
      </tr>
    </table>

    <table class="meta-2">
      <tr>
        <td class="label">Área/Departamento</td>
        <td>{{ $unidadServicio }}</td>
        <td class="label">Motivo de Devolución</td>
        <td class="label">Baja de Colaborador</td>
        <td class="mark-cell">{!! $isBaja ? 'X' : '&#160;' !!}</td>
        <td class="label">Renovación</td>
        <td class="mark-cell">{!! $isRenovacion ? 'X' : '&#160;' !!}</td>
      </tr>
    </table>

    <br>

    <!-- ===== DECLARACIÓN DE DEVOLUCIÓN ===== -->
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

      // Construir lista dinámica como en show.blade.php
      $productos = $devolucion->productos ?? collect();
      $productosTexto = $productos->map(function ($p) use ($tipos) {
          $tipoClave = $p->tipo ?? 'otro';
          $tipoProducto = $tipos[$tipoClave] ?? ucfirst(str_replace('_', ' ', $tipoClave));
          $nombreProducto = $p->nombre ?? 'producto';
          return strtolower($tipoProducto) . ' (<b>' . e($nombreProducto) . '</b>)';
      })->implode(', ');
    @endphp

    <p class="section-title">Declaración de Devolución</p>
    <p class="paragraph">
      <strong>Por medio de la presente hago constar que:</strong>
      Se hace entrega a <strong>{{ $razonSocial }}</strong> del {!! $productosTexto !!}
      en óptimas condiciones físicas y operativas.
    </p>
    <p class="paragraph">Consta de las siguientes características</p>

    @php
      $productos = $devolucion->productos ?? collect();
      $totalProductos = $productos->count();
      $filasVacias = max(0, 4 - $totalProductos);
    @endphp

    <table style="margin-top: 10px; border-collapse: collapse; width: 100%; text-align: center;">
      <thead>
        <tr style="background-color: #f5f5f5; font-weight: bold;">
          <th>PRODUCTO</th><th>DESCRIPCIÓN</th><th>MARCA</th><th>MODELO</th><th>N° DE SERIE</th>
        </tr>
      </thead>
      <tbody>
        @foreach($productos as $prod)
          @php
            $serieSeleccionada = null;
            if ($prod->pivot && $prod->pivot->producto_serie_id) {
                $serieSeleccionada = \App\Models\ProductoSerie::find($prod->pivot->producto_serie_id)?->serie;
            }
            if (!$serieSeleccionada) {
                $serieSeleccionada = $prod->series?->pluck('serie')->filter()->join(', ');
            }
          @endphp
          <tr>
            <td>{{ $prod->nombre ?? '—' }}</td>
            <td>{{ $prod->descripcion ?? '—' }}</td>
            <td>{{ $prod->marca ?? '—' }}</td>
            <td>{{ $prod->modelo ?? '—' }}</td>
            <td>{{ $serieSeleccionada ?: '—' }}</td>
          </tr>
        @endforeach

        @for($i = 0; $i < $filasVacias; $i++)
          <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
        @endfor
      </tbody>
    </table>

    <p class="paragraph" style="margin-top: 12px;">
      El usuario realiza la devolución del equipo en óptimas condiciones. La entrega se hizo llegar al departamento de sistemas.
      Asimismo, confirmo que estoy enterado de que se realizará un respaldo y la posterior baja de la cuenta de correo electrónico
      asignada a mi usuario.
    </p>

    <!-- ===== FECHA DE DEVOLUCIÓN (mantiene posición original) ===== -->
    <table style="width: 100%; border: none; margin-top: 10px; margin-bottom: 65px;"> {{-- margen inferior para dejar espacio a las firmas --}}
      <tr>
        <td style="border: none; text-align: right; padding-right: 0;">
          <table style="border-collapse: collapse; width: 230px; text-align: center; margin-right: 0;">
            <tr>
              <td style="font-weight: bold; border: 1px solid #000; padding: 5px; text-align: center;">
                FECHA DE DEVOLUCIÓN
              </td>
              <td style="border: 1px solid #000; padding: 5px; text-align: center;">
                {{ $fechaDevolucionFmt }}
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>

    <!-- ===== FIRMAS (fijas al fondo, sin mover la fecha) ===== -->
    <div class="firmas-fixed">
      <!-- FILA SUPERIOR -->
      <table style="width: 100%; border: none; text-align: center; margin-bottom: 35px;">
        <tr>
          <td style="width: 50%; border: none;">
            <p><strong>RECIBIÓ</strong><br>DEPARTAMENTO DE SISTEMAS</p>
            @if($firmaRecibio)
              <img src="data:image/png;base64,{{ base64_encode(file_get_contents($firmaRecibio)) }}" style="height:65px;margin:5px 0;">
            @else <div style="height:65px;"></div> @endif
            <p style="text-transform:uppercase;">{{ $recibioNombre }}</p>
            <hr style="width:55%;margin:4px auto;border-top:1px solid #000;">
            <p>NOMBRE Y FIRMA</p>
          </td>

          <td style="width: 50%; border: none;">
            <p><strong>ENTREGÓ</strong><br>CONFORMIDAD USUARIO</p>
            @if($firmaEntrego)
              <img src="data:image/png;base64,{{ base64_encode(file_get_contents($firmaEntrego)) }}" style="height:65px;margin:5px 0;">
            @else <div style="height:65px;"></div> @endif
            <p style="text-transform:uppercase;">{{ $colNombreEntrego }}</p>
            <hr style="width:55%;margin:4px auto;border-top:1px solid #000;">
            <p>NOMBRE Y FIRMA</p>
          </td>
        </tr>
      </table>

      <!-- FIRMA INFERIOR -->
      <div style="width:100%;">
        <p><strong>RECIBIÓ</strong><br>PERSONA EN SITIO</p>
        @if($firmaPsitio)
          <img src="data:image/png;base64,{{ base64_encode(file_get_contents($firmaPsitio)) }}" style="height:65px;margin:5px 0;">
        @else <div style="height:65px;"></div> @endif
        <p style="text-transform:uppercase;">{{ $psitioNombre }}</p>
        <hr style="width:35%;margin:4px auto;border-top:1px solid #000;">
        <p>NOMBRE Y FIRMA</p>
      </div>
    </div>
  </div>
</body>
</html>
