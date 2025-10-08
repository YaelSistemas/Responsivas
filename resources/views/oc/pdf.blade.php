{{-- resources/views/oc/pdf.blade.php --}}
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>OC {{ $oc->numero_orden ?? '' }}</title>
  <style>
    /* Hoja A4, sin “marco” alrededor */
    @page { size: A4 portrait; margin: 8mm; }
    html, body { margin:0; padding:0; font-family: DejaVu Sans, sans-serif; }

    /* Tabla base */
    .tbl{ width:100%; border-collapse:collapse; table-layout:fixed; }
    .tbl th, .tbl td{
      border:1px solid #111; padding:6px 8px;
      font-size:11px; line-height:1.15; vertical-align:middle;
      word-break:normal; box-sizing:border-box;
    }

    /* ===== Encabezado (idéntico al show) ===== */
    .hero { --h: 34px; }                    /* alto por fila */
    .hero tr { height: var(--h); }
    .hero .logo-cell{ width:28%; padding:0; }
    .hero .logo-box{
      display:flex; align-items:center; justify-content:center;
      height: calc(var(--h) * 4);
    }
    .hero .logo{ max-width:220px; max-height:105px; display:block; }

    .title-row{ text-align:center; }
    .title-main{
      font-weight:800; font-size:13px;
      white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    }
    .title-sub{
      font-weight:700; font-size:11px; text-transform:uppercase; letter-spacing:.3px;
    }

    .rightcell{ font-size:11px; line-height:1.2; text-align:left; padding:6px 8px; }
    .rightcell b{ font-weight:700; }
  </style>
</head>
<body>
@php
  /* ===== Logo en base64 (DomPDF friendly) ===== */
  $empresaId = (int) (
      session('empresa_activa')
      ?? auth()->user()?->empresa_id
      ?? $oc->empresa_tenant_id
      ?? 0
  );
  $empresaNombre = config('app.name','Laravel');
  $logoPath      = public_path('images/logos/default.png');

  if (class_exists(\App\Models\Empresa::class) && $empresaId) {
    $emp = \App\Models\Empresa::find($empresaId);
    if ($emp) {
      $empresaNombre = $emp->nombre ?? $empresaNombre;
      $cands = [];
      if (!empty($emp->logo_url))  $cands[] = ltrim(parse_url($emp->logo_url, PHP_URL_PATH) ?? '', '/');
      if (!empty($emp->logo))      $cands[] = 'images/logos/'.ltrim($emp->logo,'/');
      if (!empty($emp->logo_path)) $cands[] = ltrim($emp->logo_path,'/');

      $slug = \Illuminate\Support\Str::slug($empresaNombre,'-');
      foreach (['png','jpg','jpeg','webp','svg'] as $ext) {
        $cands[] = "images/logos/{$slug}.{$ext}";
        $cands[] = "images/logos/empresa-{$empresaId}.{$ext}";
        $cands[] = "images/logos/{$empresaId}.{$ext}";
      }
      foreach ($cands as $rel) {
        if ($rel && file_exists(public_path($rel))) { $logoPath = public_path($rel); break; }
      }
    }
  }

  $toB64 = function (?string $fullPath): ?string {
    if (!$fullPath || !is_file($fullPath)) return null;
    $ext  = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    $mime = match ($ext) { 'png'=>'image/png','jpg','jpeg'=>'image/jpeg','webp'=>'image/webp','svg'=>'image/svg+xml', default=>'image/png' };
    $bin  = @file_get_contents($fullPath);
    return $bin===false ? null : 'data:'.$mime.';base64,'.base64_encode($bin);
  };
  $logoB64 = $toB64($logoPath) ?: null;

  /* Fecha */
  $fechaFmt = '';
  if (!empty($oc->fecha)) {
    $fechaFmt = $oc->fecha instanceof \Illuminate\Support\Carbon
      ? $oc->fecha->format('d/m/Y')
      : \Illuminate\Support\Carbon::parse($oc->fecha)->format('d/m/Y');
  }
@endphp

{{-- ========= ENCABEZADO ========= --}}
<table class="tbl hero">
  <colgroup>
    <col style="width:28%">
    <col style="width:52%">
    <col style="width:20%">
  </colgroup>

  <tr>
    <td class="logo-cell" rowspan="4">
      <div class="logo-box">
        <img class="logo" src="{{ $logoB64 ?: $logoPath }}" alt="Logo">
      </div>
    </td>

    {{-- Título principal UNA SOLA LÍNEA --}}
    <td class="title-row title-main" rowspan="2">
      RECUBRIMIENTOS, PRODUCTOS Y SERVICIOS INDUSTRIALES, S.A. DE C.V.
    </td>

    <td class="rightcell">Página 1 de 1</td>
  </tr>

  <tr>
    <td class="rightcell"><b>CODIFICACIÓN:</b><br>SGC-PO-CO-01-FO-01</td>
  </tr>

  <tr>
    <td class="title-row title-sub">SISTEMA DE GESTION DE CALIDAD</td>
    <td class="rightcell"><b>FECHA DE EMISIÓN:</b><br>{{ $fechaFmt }}</td>
  </tr>

  <tr>
    <td class="title-row title-sub">ORDEN DE COMPRA</td>
    <td class="rightcell"><b>NÚMERO DE REVISIÓN:</b><br>01</td>
  </tr>
</table>

</body>
</html>
