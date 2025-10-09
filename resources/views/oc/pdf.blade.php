{{-- resources/views/oc/pdf.blade.php --}}
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>OC {{ $oc->numero_orden ?? '' }}</title>

  {{-- Reglas específicas para PDF (A4) --}}
  <style>
    /* Página A4 sin márgenes extra (los márgenes reales se controlan desde @page) */
    @page { size: A4 portrait; margin: 4mm; }

    /* Reset básico para que el PDF sea idéntico al show (pero sin card/shadow) */
    html, body {
      margin: 0;
      padding: 0;
      background: #fff;
      color: #111;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
      font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, "Noto Sans",
                   "Liberation Sans", "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji", sans-serif;
    }

    /* Asegura que no se aplique el zoom responsivo del show dentro del PDF */
    .zoom-outer, .zoom-inner { transform: none !important; width: auto !important; }

    /* La “hoja” mantiene el mismo ancho del show y se centra */
    .sheet { max-width: 940px; margin: 0 auto; }

    /* En PDF quitamos el aspecto de tarjeta para aprovechar el área imprimible */
    .doc { border: 0 !important; box-shadow: none !important; border-radius: 0 !important; padding: 0 !important; }

    /* Evitar saltos dentro de los bloques críticos */
    .hero, .meta-grid, .items { page-break-inside: avoid; }

    /* Si notas que la línea del título (muy larga) se rompe en tu máquina,
       puedes reducir levemente el tamaño base del título aquí:
       .title-main{ font-size: 13px !important; }
       o ajustar el “ancho visual” global con la escala del controlador (->scale(0.9)). */
  </style>
</head>
<body>

  @php
    /* ==== Opcional: si quieres asegurar imágenes sin llamadas externas (más rápido) ==== */
    $toB64 = function (?string $fullPath): ?string {
      if (!$fullPath || !is_file($fullPath)) return null;
      $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
      $mime = match ($ext) {
        'png' => 'image/png', 'jpg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'svg' => 'image/svg+xml',
        default => 'image/png'
      };
      $data = @file_get_contents($fullPath);
      if ($data === false) return null;
      return 'data:'.$mime.';base64,'.base64_encode($data);
    };

    // Si tu _sheet ya calcula $logo y $footerImg, no necesitas forzar base64 aquí.
    // Pero si quieres 100% robustez (sin red) puedes resolverlas así:

    // Logo (fallback)
    $empresaId     = (int) (session('empresa_activa') ?? auth()->user()?->empresa_id ?? 0);
    $empresaNombre = config('app.name', 'Laravel');
    $logoRel       = 'images/logos/default.png';

    if (class_exists(\App\Models\Empresa::class) && $empresaId) {
      $emp = \App\Models\Empresa::find($empresaId);
      if ($emp) {
        $empresaNombre = $emp->nombre ?? $empresaNombre;
        $candidates = [];

        if (!empty($emp->logo_url)) {
          $pathFromUrl = ltrim(parse_url($emp->logo_url, PHP_URL_PATH) ?? '', '/');
          if ($pathFromUrl) $candidates[] = $pathFromUrl;
        }
        if (!empty($emp->logo))      $candidates[] = 'images/logos/'.ltrim($emp->logo, '/');
        if (!empty($emp->logo_path)) $candidates[] = ltrim($emp->logo_path, '/');

        $slug = \Illuminate\Support\Str::slug($empresaNombre, '-');
        foreach (['png','jpg','jpeg','webp','svg'] as $ext) {
          $candidates[] = "images/logos/{$slug}.{$ext}";
          $candidates[] = "images/logos/empresa-{$empresaId}.{$ext}";
          $candidates[] = "images/logos/{$empresaId}.{$ext}";
        }
        foreach ($candidates as $rel) {
          if ($rel && file_exists(public_path($rel))) { $logoRel = $rel; break; }
        }
      }
    }
    $logoB64 = $toB64(public_path($logoRel)) ?: null;

    // Footer
    $footerRel = 'images/oc/footer oc.png';
    $footerB64 = file_exists(public_path($footerRel)) ? $toB64(public_path($footerRel)) : null;
  @endphp

  {{-- 
    Incluimos **el mismo partial** de la vista:
    - Si tu partial `oc/_sheet.blade.php` ya calcula logo/footer, puedes omitir el array.
    - Si quieres forzar base64, pásalos aquí y dentro del partial usa esas vars.
  --}}
  @include('oc._sheet', [
      'oc'        => $oc,
      // fuerza a usar base64 en el partial si lo contemplaste:
      'logo'      => $logoB64 ?: asset($logoRel),
      'footerImg' => $footerB64 ?: asset($footerRel),
  ])

</body>
</html>
