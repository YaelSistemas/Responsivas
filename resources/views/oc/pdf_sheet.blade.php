{{-- resources/views/oc/pdf_sheet.blade.php (OPCIÓN A) --}}
@php
  use Illuminate\Support\Str;

  /* ===== Fecha ===== */
  $fechaFmt = '';
  if (!empty($oc->fecha)) {
    $fechaFmt = $oc->fecha instanceof \Illuminate\Support\Carbon
      ? $oc->fecha->format('d/m/Y')
      : \Illuminate\Support\Carbon::parse($oc->fecha)->format('d/m/Y');
  }

  /* ===== Empresa / Logo ===== */
  $empresaId     = (int) session('empresa_activa', auth()->user()?->empresa_id);
  $empresaNombre = config('app.name', 'Laravel');
  $logoFile      = public_path('images/logos/default.png');

  if (class_exists(\App\Models\Empresa::class) && $empresaId) {
    $emp = \App\Models\Empresa::find($empresaId);
    if ($emp) {
      $empresaNombre = $emp->nombre ?? $empresaNombre;
      $candidates = [];
      if (!empty($emp->logo_url))  $candidates[] = public_path(ltrim($emp->logo_url, '/'));
      if (!empty($emp->logo))      $candidates[] = public_path('images/logos/'.ltrim($emp->logo, '/'));
      if (!empty($emp->logo_path)) $candidates[] = public_path(ltrim($emp->logo_path, '/'));
      $slug = Str::slug($empresaNombre, '-');
      foreach (['png','jpg','jpeg','webp','svg'] as $ext) {
        $candidates[] = public_path("images/logos/{$slug}.{$ext}");
        $candidates[] = public_path("images/logos/empresa-{$empresaId}.{$ext}");
        $candidates[] = public_path("images/logos/{$empresaId}.{$ext}");
      }
      foreach ($candidates as $abs) { if ($abs && is_file($abs)) { $logoFile = $abs; break; } }
    }
  }

  /* ===== Firma ===== */
  $userName    = trim(auth()->user()->name ?? '');
  $nombreFirma = '';
  if ($userName !== '') {
    $parts = preg_split('/\s+/', $userName);
    $nombreFirma = count($parts) >= 2 ? $parts[0].' '.$parts[count($parts)-2] : $userName;
  }

  /* ===== Footer ===== */
  $footerFile = public_path('images/oc/footer oc.png');

  /* ===== Embed base64 ===== */
  $toB64 = function ($fullPath) {
    if (!$fullPath || !is_file($fullPath)) return null;
    $ext  = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    $mime = match ($ext) {'png'=>'image/png','jpg','jpeg'=>'image/jpeg','webp'=>'image/webp','svg'=>'image/svg+xml',default=>'image/png'};
    $data = @file_get_contents($fullPath);
    return $data ? 'data:'.$mime.';base64,'.base64_encode($data) : null;
  };
  $logoB64   = $toB64($logoFile);
  $footerB64 = $toB64($footerFile);

  /* ===== Helpers ===== */
  $currencySymbol = fn($code) => ['MXN'=>'$', 'USD'=>'US$', 'EUR'=>'€'][$code] ?? ($code ?: '');
  $moneyParts = function ($n, $code) use ($currencySymbol) {
    if (!is_numeric($n)) return ['',''];
    return [$currencySymbol($code), number_format((float)$n, 2, '.', ',')];
  };
  $fmtQty = function ($n) {
    if (!is_numeric($n)) return $n;
    $v = number_format((float)$n, 3, '.', '');
    return rtrim(rtrim($v, '0'), '.');
  };

  /* ===== Partidas ===== */
  $detalles    = collect($oc->detalles ?? [])->values();
  $sumSubtotal = $detalles->sum(fn($d) => (float)($d->subtotal ?? 0));
  $sumIva      = $detalles->sum(fn($d) => (float)($d->iva_monto ?? 0));
  $sumTotal    = $detalles->sum(fn($d) => (float)($d->total ?? 0));
  $moneda      = $detalles->first()->moneda ?? 'MXN';

  // 13 filas base; si hay más, se generan dinámicamente
  $baseBlocks   = 9;
  $blocksToDraw = max($baseBlocks, $detalles->count());

  /* ===== Layout ===== */
  $rows = $detalles->count();

  $PAGE_MARGIN = '10mm';
  $FS_BASE = 12; $PAD = 6; $ROW_H = 26; $SPACER_H = 12;
  $HERO_ROW_H = 36; $LOGO_MAX_H = 105; $LOGO_MAX_W = 220;
  $TITLE_MAIN = 14; $TITLE_SUB = 12;

  if ($rows > 9 && $rows <= 16) {
    $PAGE_MARGIN = '9mm';
    $FS_BASE = 11; $PAD = 5; $ROW_H = 22; $SPACER_H = 8;
    $HERO_ROW_H = 32; $LOGO_MAX_H = 95; $LOGO_MAX_W = 200;
    $TITLE_MAIN = 13; $TITLE_SUB = 11;
  } elseif ($rows > 16 && $rows <= 22) {
    $PAGE_MARGIN = '8mm';
    $FS_BASE = 10; $PAD = 4; $ROW_H = 20; $SPACER_H = 4;
    $HERO_ROW_H = 28; $LOGO_MAX_H = 85; $LOGO_MAX_W = 190;
    $TITLE_MAIN = 12; $TITLE_SUB = 10;
  } elseif ($rows > 22) {
    $PAGE_MARGIN = '6mm';
    $FS_BASE = 9;  $PAD = 3; $ROW_H = 18; $SPACER_H = 0;
    $HERO_ROW_H = 24; $LOGO_MAX_H = 72; $LOGO_MAX_W = 170;
    $TITLE_MAIN = 11; $TITLE_SUB = 9;
  }

  // Relleno visual para OCs cortas (≤13)
  if ($rows <= 9) {
    $SPACER_H = 6;                // separadores pequeños entre filas
    $ROW_H    = 26;               // conserva altura estándar
  }

  $DRAW_SPACERS = $SPACER_H > 0;

  // Altura del bloque de imagen del pie (más grande en OCs cortas)
  $FOOTER_IMG_H = ($rows <= 9) ? max(110, $ROW_H * 4 + 6) : max(90, $ROW_H * 4);
@endphp

<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>OC {{ $oc->numero_orden }}</title>
  <style>
    @page { size: A4 portrait; margin: {{ $PAGE_MARGIN }}; }
    html, body { margin:0; padding:0; font-family: DejaVu Sans, Arial, sans-serif; }

    .tbl { width:100%; border-collapse:collapse; table-layout:fixed; }
    .tbl th, .tbl td { border:1px solid #111; padding: {{ $PAD }}px {{ $PAD+2 }}px; font-size: {{ $FS_BASE }}px; line-height:1.15; vertical-align:middle; }

    .center{ text-align:center; } .right{ text-align:right; } .upper{ text-transform:uppercase; }
    .no-upper{ text-transform:none !important; } .upper-force{ text-transform:uppercase !important; }
    .mono{ font-variant-numeric:tabular-nums; }

    /* Encabezado */
    .hero{ table-layout:fixed; --row-h: {{ $HERO_ROW_H }}px; } .hero tr{ height:var(--row-h); }
    .hero .logo-cell{ width:28%; }
    .hero .logo-box{ height: calc(var(--row-h) * 4); display:flex; align-items:center; justify-content:center; }
    .hero .logo-cell img{ max-width: {{ $LOGO_MAX_W }}px; max-height: {{ $LOGO_MAX_H }}px; display:block; }
    .title-row{ text-align:center; } .title-main{ font-weight:800; font-size: {{ $TITLE_MAIN }}px; }
    .title-sub{ font-weight:700; font-size: {{ $TITLE_SUB }}px; letter-spacing:.3px; }
    .rightcell{ padding: {{ max(4,$PAD-1) }}px; font-size: {{ max(10,$FS_BASE-1) }}px; line-height:1.2; text-align:left; }
    .rightcell b{ font-weight:700; }

    /* Proveedor */
    .meta-grid{ margin-top: {{ max(6,$PAD-2) }}px; }
    .meta-grid th{ font-weight:700; text-transform:uppercase; background:none; text-align:left; }
    .meta-grid th.center{ text-align:center !important; }
    .order-no{ font-weight:700 !important; color:#c62828; font-size: {{ max(12,$FS_BASE) }}px; letter-spacing:.2px; }

    /* Bordes especiales proveedor */
    .prov-f2{ border-right:0 !important; border-bottom:0 !important; }
    .prov-f3,.prov-f4{ border-top:0 !important; border-right:0 !important; border-bottom:0 !important; }
    .prov-f5{ border-top:0 !important; border-right:0 !important; }
    .prov-c2-r2{ border-left:0 !important; border-bottom:0 !important; }
    .prov-c2-r3,.prov-c2-r4{ border-top:0 !important; border-left:0 !important; border-bottom:0 !important; }
    .prov-c2-r5{ border-top:0 !important; border-left:0 !important; }

    /* Items */
    .items { margin-bottom: -3px; } /* o -4px si tu motor PDF es muy estricto */
    .items tr.data   { height: {{ $ROW_H }}px; }
    .items tr.spacer { height: {{ $SPACER_H }}px; }
    .items tr.spacer td { padding-top:0; padding-bottom:0; line-height:1; }
    .items tr.r2 td.c1{ border-bottom:0 !important; }
    .items tr.data td, .items tr.spacer td{ border-top:0 !important; border-bottom:0 !important; }
    .items tr.summary td, .items tr.summary th{ border-top:0 !important; border-bottom:0 !important; }
    .items tr.summary.last td, .items tr.summary.last th{ border-top:0 !important; border-bottom:1px solid #111 !important; }
    .items tr.extra td{ border:1px solid #111 !important; height: {{ max(16,$ROW_H-6) }}px; }

    .items td.qty,.items td.unit{ text-align:center; }
    .items td.money,.items td.money-total{ padding-left:{{ max(4,$PAD-2) }}px; padding-right:{{ max(4,$PAD-2) }}px; }
    .items td.money > .mwrap,.items td.money-total > .mwrap{ display:flex; align-items:center; justify-content:space-between; width:100%; gap:6px; }
    .items td.money .sym,.items td.money-total .sym{ flex:0 0 auto; min-width:1.2em; text-align:left; }
    .items td.money .val,.items td.money-total .val{ flex:1 1 auto; text-align:right; font-variant-numeric:tabular-nums; }
    .items tr:nth-child(n+2)>td:nth-child(2){ border-right:0 !important; }
    .items tr:nth-child(n+2)>td:nth-child(3){ border-left:0  !important; }

    .items .xxsmall{ font-size: {{ max(8,$FS_BASE-3) }}px; }
    .items .bigger3{ font-size: {{ max(12,$FS_BASE+4) }}px; font-weight:700; }

    /* Reglas extra */
    .items tr.extra.e1 td{ border-bottom:0 !important; }
    .items tr.extra.e2 td,.items tr.extra.e3 td{ border-top:0 !important; border-bottom:0 !important; }
    .items tr.extra.e4 td:first-child{ border-top:0 !important; }
    .items tr.extra.e4 td:last-child{  border-top:0 !important; border-bottom:0 !important; }
    .items tr.extra.e5 td{ border-top:0 !important; }
    .items tr.extra.e6 td{ border-left:0 !important; border-right:0 !important; border-bottom:0 !important; }
    .items tr.extra.e7 td,.items tr.extra.e8 td{ border:0 !important; }
    .items tr.extra.e1 td:last-child,
    .items tr.extra.e2 td:last-child,
    .items tr.extra.e3 td:last-child,
    .items tr.extra.e4 td:last-child,
    .items tr.extra.e5 td:last-child{ border-right:1px solid #111 !important; }

    .footer-img-td{
      background-image:url('{{ $footerB64 }}'); background-repeat:no-repeat; background-position:center center; background-size:contain;
      height: {{ $FOOTER_IMG_H }}px;
      border-left:1px solid #111 !important; border-right:1px solid #111 !important;
      border-top:0 !important; border-bottom:1px solid #111 !important; padding:0 !important;
    }

    @if (!$DRAW_SPACERS)
      .items tr.spacer{ display:none; }
    @endif
  </style>
</head>
<body>

  {{-- ENCABEZADO --}}
  <table class="tbl hero">
    <colgroup><col style="width:28%"><col style="width:52%"><col style="width:20%"></colgroup>
    <tr>
      <td class="logo-cell" rowspan="4"><div class="logo-box">@if($logoB64)<img src="{{ $logoB64 }}" alt="Logo">@endif</div></td>
      <td class="title-row title-main" rowspan="2">RECUBRIMIENTOS, PRODUCTOS Y SERVICIOS INDUSTRIALES, S.A. DE C.V.</td>
      <td class="rightcell">Página 1 de 1</td>
    </tr>
    <tr><td class="rightcell"><b>CODIFICACIÓN:</b> SGC-PO-CO-01-FO-01</td></tr>
    <tr><td class="title-row title-sub">SISTEMA DE GESTION DE CALIDAD</td><td class="rightcell"><b>20-jun-2025</b></td></tr>
    <tr><td class="title-row title-sub">ORDEN DE COMPRA</td><td class="rightcell"><b>NÚMERO DE REVISIÓN:</b><br>01</td></tr>
  </table>

  {{-- PROVEEDOR / ORDEN --}}
  <table class="tbl meta-grid upper" style="margin-top:8px">
    <colgroup><col style="width:8%"><col style="width:72%"><col style="width:20%"></colgroup>
    <tr><th colspan="2">PROVEEDOR</th><th class="center">ORDEN DE COMPRA</th></tr>
    <tr><td class="prov-f2">&nbsp;</td><td class="prov-c2-r2" style="font-weight:700">{{ $oc->proveedor['nombre'] ?? '' }}</td><td rowspan="2" class="order-no center">{{ $oc->numero_orden ?? '' }}</td></tr>
    <tr><td class="prov-f3">&nbsp;</td><td class="prov-c2-r3">{{ $oc->proveedor['calle'] ?? '' }}</td></tr>
    <tr>
      <td class="prov-f4">&nbsp;</td>
      <td class="prov-c2-r4">{{ $oc->proveedor['colonia'] ?? '' }}@if(!empty($oc->proveedor['colonia']) && !empty($oc->proveedor['codigo_postal']))@endif, {{ $oc->proveedor['codigo_postal'] ?? '' }}</td>
      <th class="center">FECHA</th>
    </tr>
    <tr>
      <td class="prov-f5">&nbsp;</td>
      <td class="prov-c2-r5">{{ $oc->proveedor['ciudad'] ?? '' }}@if(!empty($oc->proveedor['ciudad']) && !empty($oc->proveedor['estado']))@endif, {{ $oc->proveedor['estado'] ?? '' }}</td>
      <td class="mono center">{{ $fechaFmt }}</td>
    </tr>
  </table>

  {{-- PARTIDAS --}}
  <table class="tbl items upper">
    <colgroup><col style="width:10%"><col style="width:10%"><col style="width:48%"><col style="width:12%"><col style="width:20%"></colgroup>
    <tr><th class="center">CANTIDAD</th><th colspan="2">CONCEPTO</th><th class="center">PRECIO</th><th class="center">IMPORTE</th></tr>
    @if($DRAW_SPACERS)
      <tr class="spacer r2"><td class="c1">&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
    @endif

    @php $idxDetalle = 0; @endphp
    @for ($bloque = 0; $bloque < $blocksToDraw; $bloque++)
      @php
        $d = $detalles->get($idxDetalle);
        $cantidad = $d->cantidad ?? null; $unidad = $d->unidad ?? ''; $concepto = $d->concepto ?? '';
        $precio = $d->precio ?? null; $mon = $d->moneda ?? $moneda;
        $importe = $d ? ($d->importe ?? ((is_numeric($cantidad) && is_numeric($precio)) ? $cantidad * $precio : null)) : null;
        [$pSym, $pVal] = $moneyParts($precio, $mon);
        [$iSym, $iVal] = $moneyParts($importe, $mon);
      @endphp

      <tr class="data">
        <td class="c1 qty mono">{{ $d ? $fmtQty($cantidad) : ' ' }}</td>
        <td class="unit">{{ $d ? ($unidad ?: ' ') : ' ' }}</td>
        <td>{{ $d ? ($concepto ?: ' ') : ' ' }}</td>
        <td class="money mono"><div class="mwrap"><span class="sym">{{ $pSym ?: ' ' }}</span><span class="val">{{ $pVal ?: ' ' }}</span></div></td>
        <td class="money mono"><div class="mwrap"><span class="sym">{{ $iSym ?: ' ' }}</span><span class="val">{{ $iVal ?: ' ' }}</span></div></td>
      </tr>

      @if($DRAW_SPACERS)
        <tr class="spacer"><td class="c1">&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
      @endif

      @php if ($d) $idxDetalle++; @endphp
    @endfor

    @php
      [$sSym, $sVal] = $moneyParts($sumSubtotal, $moneda);
      [$vSym, $vVal] = $moneyParts($sumIva, $moneda);
      [$tSym, $tVal] = $moneyParts($sumTotal, $moneda);
    @endphp

    <tr class="summary"><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td class="right"><b>SUBTOTAL</b></td>
      <td class="money-total"><div class="mwrap"><span class="sym"><b>{{ $sSym }}</b></span><span class="val"><b>{{ $sVal }}</b></span></div></td></tr>
    <tr class="summary"><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td class="right"><b>IVA</b></td>
      <td class="money-total"><div class="mwrap"><span class="sym"><b>{{ $vSym }}</b></span><span class="val"><b>{{ $vVal }}</b></span></div></td></tr>
    <tr class="summary last"><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td class="right"><b>TOTAL ({{ $moneda }})</b></td>
      <td class="money-total"><div class="mwrap"><span class="sym"><b>{{ $tSym }}</b></span><span class="val"><b>{{ $tVal }}</b></span></div></td></tr>

    {{-- 8 filas extra --}}
    <tr class="extra e1 no-upper"><td colspan="2" class="center xxsmall"><b>DEPTO. DE COMPRAS</b></td><td colspan="3" class="xxsmall"><b>OBSERVACIONES:</b></td></tr>
    <tr class="extra e2 no-upper"><td colspan="2">&nbsp;</td><td colspan="3" class="bigger3">Favor de poner # de O.C. a la factura y enviar la factura a</td></tr>
    <tr class="extra e3 no-upper"><td colspan="2">&nbsp;</td><td colspan="3" class="bigger3">almacen@reprosisa.com.mx</td></tr>
    <tr class="extra e4 no-upper"><td colspan="2" class="center upper-force">{{ $nombreFirma }}</td><td colspan="3">&nbsp;</td></tr>
    <tr class="extra e5 no-upper"><td colspan="2" class="center"><b>FIRMA</b></td><td colspan="3">&nbsp;</td></tr>
    <tr class="extra e6 no-upper"><td colspan="2">&nbsp;</td><td class="xxsmall">LAS FACTURAS DEBERAN MOSTRAR EL NUMERO DE ESTE ORDEN PARA SER PAGADAS</td><td colspan="2" rowspan="3" class="footer-img-td"></td></tr>
    <tr class="extra e7 no-upper"><td colspan="2">&nbsp;</td><td>&nbsp;</td></tr>
    <tr class="extra e8 no-upper"><td colspan="2">&nbsp;</td><td>&nbsp;</td></tr>
  </table>

</body>
</html>
