{{-- resources/views/oc/show.blade.php --}}
<x-app-layout title="Orden de compra {{ $oc->numero_orden ?? '' }}">
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight text-center">
      Orden de compra {{ $oc->numero_orden ?? '' }}
    </h2>
  </x-slot>

  @php
    // ================= Empresa / Logo =================
    $empresaId     = (int) session('empresa_activa', auth()->user()?->empresa_id);
    $empresaNombre = config('app.name', 'Laravel');
    $logo = asset('images/logos/default.png'); // fallback

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
          if (!empty($emp->logo_path)) $candidates[] = ltrim($emp->logo_path, '/');

          $slug = \Illuminate\Support\Str::slug($empresaNombre, '-');
          foreach (['png','jpg','jpeg','webp','svg'] as $ext) {
            $candidates[] = "images/logos/{$slug}.{$ext}";
            $candidates[] = "images/logos/empresa-{$empresaId}.{$ext}";
            $candidates[] = "images/logos/{$empresaId}.{$ext}";
          }
          foreach ($candidates as $rel) {
            if (file_exists(public_path($rel))) { $logo = asset($rel); break; }
          }
        }
      }
    }

    // ================= Fecha =================
    $fechaFmt = '';
    if (!empty($oc->fecha)) {
      $fechaFmt = $oc->fecha instanceof \Illuminate\Support\Carbon
        ? $oc->fecha->format('d/m/Y')
        : \Illuminate\Support\Carbon::parse($oc->fecha)->format('d/m/Y');
    }

    // ===== Nombre para firma: primer nombre + primer apellido (CREADOR) =====
    $full = trim($oc->creator?->name ?? '');
    $nombreFirma = '—';

    if ($full !== '') {
      $parts = preg_split('/\s+/', $full, -1, PREG_SPLIT_NO_EMPTY);

      // Arturo Gerardo Gomez Cruz => Arturo Gomez
      // Arturo Gomez => Arturo Gomez
      $nombreFirma = count($parts) >= 2
        ? ($parts[0] . ' ' . $parts[count($parts) - 2])
        : $parts[0];
    }

    // ===== Imagen de pie (footer) =====
    $footerImg = asset('images/oc/footer oc.png'); // archivo con espacio en el nombre
  @endphp

  <style>
    /* ====== Zoom responsivo (envoltura de la hoja) ====== */
    .zoom-outer{ overflow-x:hidden; }
    .zoom-inner{
      --zoom: 1;
      transform: scale(var(--zoom));
      transform-origin: top left;
      width: calc(100% / var(--zoom));
    }
    @media (max-width: 1024px){ .zoom-inner{ --zoom:.95; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }
    @media (max-width: 768px){  .zoom-inner{ --zoom:.90; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }
    @media (max-width: 640px){  .zoom-inner{ --zoom:.60; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }
    @media (max-width: 400px){  .zoom-inner{ --zoom:.55; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }

    /* ====== Hoja ====== */
    .sheet { max-width: 940px; margin: 0 auto; }
    .doc { background:#fff; border:1px solid #111; border-radius:6px; padding:18px; box-shadow:0 2px 6px rgba(0,0,0,.08); }

    /* ====== Acciones (botones) ====== */
    .actions{ display:flex; gap:10px; margin:14px auto; flex-wrap:wrap; align-items:center; max-width:940px; }
    .actions .spacer{ flex:1 1 auto; }
    .btn { padding:8px 12px; border-radius:6px; font-weight:600; border:1px solid transparent; display:inline-flex; align-items:center; gap:6px; }
    .btn-primary { background:#2563eb; color:#fff; }
    .btn-secondary { background:#f3f4f6; color:#111; border-color:#d1d5db; }
    .btn-danger{ background:#dc2626; color:#fff; border-color:#b91c1c; }
    .btn:hover { filter:brightness(.97); }

    .tbl { width:100%; border-collapse:collapse; table-layout:fixed; }
    .tbl th, .tbl td { border:1px solid #111; padding:6px 8px; font-size:12px; line-height:1.15; vertical-align:middle; }
    .tbl td.order-no { font-weight:700 !important; color:#c62828; font-size:14px; letter-spacing: .2px; }

    /* Helpers de alineación / mayúsculas */
    .center { text-align:center; }
    .right  { text-align:right; }
    .upper  { text-transform: uppercase; }
    .no-upper { text-transform: none !important; }
    .upper-force { text-transform: uppercase !important; }

    /* ===== Encabezado ===== */
    .hero { table-layout: fixed; --row-h: 36px; }
    .hero tr { height: var(--row-h); }
    .hero .logo-cell { width:28%; }
    .hero .logo-box{ height: calc(var(--row-h) * 4); display:flex; align-items:center; justify-content:center; }
    .hero .logo-cell img{ max-width:220px; max-height:105px; display:block; }
    .title-row{ text-align:center; }
    .title-main{ font-weight:800; font-size:14px; }
    .title-sub{ font-weight:700; font-size:12px; text-transform:uppercase; letter-spacing:.3px; }
    .rightcell{ padding:8px; font-size:12px; line-height:1.2; text-align:left; }
    .rightcell b{ font-weight:700; }

    /* ===== PROVEEDOR / ORDEN ===== */
    .meta-grid { margin-top:8px; }
    .meta-grid th{ font-weight:700; text-transform:uppercase; background:none; text-align:left; }
    .meta-grid th.center { text-align: center !important; }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace; }

    /* 1ª columna (proveedor) */
    .prov-f2, .prov-f3, .prov-f4 { border-right:0 !important; border-bottom:0 !important; }
    .prov-f3, .prov-f4, .prov-f5 { border-top:0 !important; }
    .prov-f5 { border-right:0 !important; }

    /* 2ª columna (proveedor) */
    .prov-c2-r2, .prov-c2-r3, .prov-c2-r4, .prov-c2-r5 { border-left:0 !important; }
    .prov-c2-r2, .prov-c2-r3, .prov-c2-r4 { border-bottom:0 !important; }
    .prov-c2-r3, .prov-c2-r4, .prov-c2-r5 { border-top:0 !important; }

    /* 3ª columna (proveedor) */
    .meta-grid td:nth-child(3), .meta-grid th:nth-child(3) { text-align:center; }

    /* ===== Tabla de partidas ===== */
    .items { margin-top:12px; }
    .items tr.data   { height: 26px; }
    .items tr.spacer { height: 12px; }
    .items tr.spacer td { padding-top: 0; padding-bottom: 0; line-height: 1; }
    .items tr.r2 td.c1 { border-bottom:0 !important; }
    .items tr.data td,
    .items tr.spacer td { border-top:0 !important; border-bottom:0 !important; }
    .items tr.summary td,
    .items tr.summary th { border-top:0 !important; border-bottom:0 !important; }
    .items tr.summary.last td,
    .items tr.summary.last th { border-top:0 !important; border-bottom:1px solid #111 !important; }
    .items tr.extra td { border:1px solid #111 !important; height:20px; }
    .items td.qty, .items td.unit { text-align:center; }
    .items td.money,
    .items td.money-total { padding-left:6px; padding-right:6px; }
    .items td.money > .mwrap,
    .items td.money-total > .mwrap { display:flex; align-items:center; justify-content:space-between; width:100%; gap:6px; }
    .items td.money > .mwrap .sym,
    .items td.money-total > .mwrap .sym { flex:0 0 auto; min-width:1.2em; text-align:left; }
    .items td.money > .mwrap .val,
    .items td.money-total > .mwrap .val { flex:1 1 auto; text-align:right; font-variant-numeric: tabular-nums; }
    .items tr.data > td:nth-child(2),
    .items tr.spacer > td:nth-child(2) { border-right:0 !important; }
    .items tr.data > td:nth-child(3),
    .items tr.spacer > td:nth-child(3) { border-left:0 !important; }

    .items .small    { font-size:11px; }
    .items .smaller  { font-size:10px; }
    .items .xsmall   { font-size:10px; }
    .items .xxsmall  { font-size:9px; }
    .items .xxxsmall { font-size:8px; }
    .items .xxxxsmall{ font-size:7px; }
    .items .big      { font-size:13px; }
    .items .bigger   { font-size:13px; font-weight:700; }
    .items .bigger2  { font-size:14px; font-weight:700; }
    .items .bigger3  { font-size:16px; font-weight:700; }
    .items .bigger4  { font-size:18px; font-weight:700; }

    /* Reglas extra */
    .items tr.extra.e1 td { border-bottom:0 !important; }
    .items tr.extra.e2 td,
    .items tr.extra.e3 td { border-top:0 !important; border-bottom:0 !important; }
    .items tr.extra.e4 td:first-child { border-top:0 !important; }
    .items tr.extra.e4 td:last-child  { border-top:0 !important; border-bottom:0 !important; }
    .items tr.extra.e5 td { border-top:0 !important; }
    .items tr.extra.e6 td { border-left:0 !important; border-right:0 !important; border-bottom:0 !important; }
    .items tr.extra.e7 td,
    .items tr.extra.e8 td { border:0 !important; }
    .items tr.extra.e1 td:last-child,
    .items tr.extra.e2 td:last-child,
    .items tr.extra.e3 td:last-child,
    .items tr.extra.e4 td:last-child,
    .items tr.extra.e5 td:last-child { border-right:1px solid #111 !important; }

    /* ====== Celda de imagen (ocupa col 4-5, filas 6 a 8) ====== */
    .footer-img-td{
      background-image: url('{{ $footerImg }}');
      background-repeat: no-repeat;
      background-position: center center;
      background-size: contain;
      height: 120px;
      border-left: 1px solid #111 !important;
      border-right: 1px solid #111 !important;
      border-top: 0 !important;
      border-bottom: 1px solid #111 !important;
      padding:0 !important;
    }

    /* Fila de NOTAS */
    .items tr.notes td{
      border-top: 0 !important;
      border-bottom: 0 !important;
    }
    .items tr.notes td.note-cell{
      text-align: left;
      padding-left: 6px;
      font-size: 11px;
      line-height: 1.2;
    }
    .items tr.notes td.note-empty,
    .items tr.notes td.note-first{
      padding-top:0 !important;
      padding-bottom:0 !important;
    }

    /* ====== Print ====== */
    @media print {
      .zoom-inner{ transform:none !important; width:auto !important; }
      body * { visibility: hidden !important; }
      #printable, #printable * { visibility: visible !important; }
      .sheet{ max-width:none !important; }
      #printable { position:static; width:100% !important; margin:0 !important; }
      .doc{ border:0 !important; border-radius:0 !important; box-shadow:none !important; padding:0 !important; }
      @page{ size:A4 portrait; margin:4mm; }
      .title-main{ font-size:12px; }
      .title-sub{ font-size:10px; }
      .hero .logo-box{ height:90px; }
      .hero .logo-cell img{ max-height:70px; max-width:160px; }
      .tbl th, .tbl td{ padding:4px 6px; font-size:10px; }
      br{ display:none !important; }
      .hero, .meta-grid, .items { page-break-inside: avoid; }
      html, body{ margin:0 !important; padding:0 !important; }
      .footer-img-td{ height:100px; background-size: contain; }
    }
  </style>

  {{-- ====== Barra de acciones ====== --}}
  <div class="actions">
    <a href="{{ url('/oc') }}" class="btn btn-secondary">← Órdenes de compra</a>

    <a href="{{ route('oc.pdf.open', $oc) }}" class="btn btn-primary" target="_blank" rel="noopener">
      Ver / Descargar PDF
    </a>
  </div>

  {{-- ====== Envoltura con ZOOM ====== --}}
  <div class="zoom-outer">
    <div class="zoom-inner">
      <div class="sheet">
        <div class="doc" id="printable">

          {{-- ========= ENCABEZADO ========= --}}
          <table class="tbl hero">
            <colgroup>
              <col style="width:28%">
              <col style="width:52%">
              <col style="width:20%">
            </colgroup>

            <tr>
              <td class="logo-cell" rowspan="4">
                <div class="logo-box"><img src="{{ $logo }}" alt="Logo"></div>
              </td>
              <td class="title-row title-main" rowspan="2">
                RECUBRIMIENTOS, PRODUCTOS Y SERVICIOS INDUSTRIALES, S.A. DE C.V.
              </td>
              <td class="rightcell">Página 1 de 1</td>
            </tr>
            <tr><td class="rightcell"><b>CODIFICACIÓN:</b> SGC-PO-CO-01-FO-01</td></tr>
            <tr>
              <td class="title-row title-sub">SISTEMA DE GESTION DE CALIDAD</td>
              <td class="rightcell"><b>20-jun-2025</b></td>
            </tr>
            <tr>
              <td class="title-row title-sub">ORDEN DE COMPRA</td>
              <td class="rightcell"><b>NÚMERO DE REVISIÓN:</b><br>01</td>
            </tr>
          </table>

          {{-- ========= PROVEEDOR / ORDEN ========= --}}
          <table class="tbl meta-grid upper">
            <colgroup>
              <col style="width:8%">
              <col style="width:72%">
              <col style="width:20%">
            </colgroup>

            <tr>
              <th colspan="2">PROVEEDOR</th>
              <th class="center">ORDEN DE COMPRA</th>
            </tr>

            <tr>
              <td class="prov-f2">&nbsp;</td>
              <td class="prov-c2-r2" style="font-weight:700">{{ $oc->proveedor['nombre'] ?? '' }}</td>
              <td rowspan="2" class="order-no">{{ $oc->numero_orden ?? '' }}</td>
            </tr>

            <tr>
              <td class="prov-f3">&nbsp;</td>
              <td class="prov-c2-r3">{{ $oc->proveedor['calle'] ?? '' }}</td>
            </tr>

            <tr>
              <td class="prov-f4">&nbsp;</td>
              <td class="prov-c2-r4">
                {{ $oc->proveedor['colonia'] ?? '' }},
                @if(!empty($oc->proveedor['colonia']) && !empty($oc->proveedor['codigo_postal'])) @endif
                {{ $oc->proveedor['codigo_postal'] ?? '' }}
              </td>
              <th>FECHA</th>
            </tr>

            <tr>
              <td class="prov-f5">&nbsp;</td>
              <td class="prov-c2-r5">
                {{ $oc->proveedor['ciudad'] ?? '' }},
                @if(!empty($oc->proveedor['ciudad']) && !empty($oc->proveedor['estado'])) @endif
                {{ $oc->proveedor['estado'] ?? '' }}
              </td>
              <td class="mono">{{ $fechaFmt }}</td>
            </tr>
          </table>

          {{-- ========= PARTIDAS ========= --}}
          @php
            $detalles = collect($oc->detalles ?? [])->values();

            $currencySymbol = function ($code) {
              $map = ['MXN'=>'$', 'USD'=>'US$', 'EUR'=>'€'];
              return $map[$code] ?? ($code ? "{$code}" : '');
            };

            $moneyParts = function ($n, $code) use ($currencySymbol) {
              if (!is_numeric($n)) return ['', ''];
              return [$currencySymbol($code), number_format((float)$n, 2, '.', ',')];
            };

            $fmtQty = function($n){
              if (!is_numeric($n)) return $n;
              $v = number_format((float)$n, 3, '.', '');
              return rtrim(rtrim($v, '0'), '.');
            };

            $round2 = function($n){
              $n = (float)($n ?? 0);
              return round($n, 2);
            };

            $trunc2 = function($n){
              $n = (float)($n ?? 0);
              return floor(($n + 0.0000001) * 100) / 100;
            };

            $samePctOrNull = function($rows, $field){
              $values = collect($rows)
                ->map(fn($d) => (float)($d->{$field} ?? 0))
                ->filter(fn($v) => $v > 0)
                ->unique()
                ->values();

              return $values->count() === 1 ? (float)$values->first() : null;
            };

            $displayRows = [];
            $sumSubtotalRaw = 0.0;

            foreach ($detalles as $d) {
              $cantidad = (float)($d->cantidad ?? 0);
              $precioRaw = $d->precio ?? null;
              $descuentoRaw = $d->descuento ?? null;

              $precioDisplay = is_numeric($precioRaw) ? $round2($precioRaw) : null;
              $descuentoDisplay = ((float)($descuentoRaw ?? 0) > 0) ? $round2($descuentoRaw) : null;

              $importeBase = null;
              if (isset($d->importe) && is_numeric($d->importe)) {
                $importeBase = (float)$d->importe;
              } elseif (is_numeric($cantidad) && is_numeric($precioRaw)) {
                $importeBase = ($cantidad * (float)$precioRaw) - (float)($descuentoRaw ?? 0);
              }

              $importeDisplay = is_numeric($importeBase) ? $round2($importeBase) : null;

              $displayRows[] = [
                'model' => $d,
                'precio_display' => $precioDisplay,
                'descuento_display' => $descuentoDisplay,
                'importe_display' => $importeDisplay,
              ];

              $sumSubtotalRaw += (float)($importeDisplay ?? 0);
            }

            $sumSubtotal = $round2($sumSubtotalRaw);

            $ivaPctCommon    = $samePctOrNull($detalles, 'iva_pct');
            $isrPctCommon    = $samePctOrNull($detalles, 'isr_pct');
            $retIvaPctCommon = $samePctOrNull($detalles, 'ret_iva_pct');

            if ($ivaPctCommon !== null) {
              $sumIva = $trunc2($sumSubtotal * ($ivaPctCommon / 100));
            } else {
              $sumIva = $trunc2(collect($detalles)->sum(fn($d) => (float)($d->iva_monto ?? 0)));
            }

            if ($isrPctCommon !== null) {
              $sumIsr = $trunc2($sumSubtotal * ($isrPctCommon / 100));
            } else {
              $sumIsr = $trunc2(collect($detalles)->sum(fn($d) => (float)($d->isr_monto ?? 0)));
            }

            if ($retIvaPctCommon !== null) {
              $sumRetIva = $trunc2($sumSubtotal * ($retIvaPctCommon / 100));
            } else {
              $sumRetIva = $trunc2(collect($detalles)->sum(fn($d) => (float)($d->ret_iva_monto ?? 0)));
            }

            $sumTotal = $round2($sumSubtotal + $sumIva - $sumIsr - $sumRetIva);

            $hasIsrPct = $detalles->contains(function($d){
              return (float)($d->isr_pct ?? 0) > 0;
            });

            $showIsr = ($sumIsr > 0) || $hasIsrPct;

            $hasRetIvaPct = $detalles->contains(function($d){
              return (float)($d->ret_iva_pct ?? 0) > 0;
            });

            $showRetIva = ($sumRetIva > 0) || $hasRetIvaPct;

            $moneda = $detalles->first()->moneda ?? 'MXN';
            $showDiscountColumn = $detalles->contains(function($d){
              return (float)($d->descuento ?? 0) > 0;
            });

            $baseBlocks   = 9;                            // ← mínimo visible
            $totalItems   = $detalles->count();
            $blocksToDraw = max($baseBlocks, $totalItems);
            $extraRightColspan = $showDiscountColumn ? 4 : 3;
            $footerImageColspan = $showDiscountColumn ? 3 : 2;
            $summaryLeadColspan = 3;
          @endphp

          <table class="tbl items upper">
            <colgroup>
              <col style="width:10%"><!-- Cantidad -->
              <col style="width:10%"><!-- Unidad   -->
              @if($showDiscountColumn)
                <col style="width:36%"><!-- Concepto -->
                <col style="width:12%"><!-- Precio   -->
                <col style="width:12%"><!-- Descuento -->
                <col style="width:20%"><!-- Importe  -->
              @else
                <col style="width:48%"><!-- Concepto -->
                <col style="width:12%"><!-- Precio   -->
                <col style="width:20%"><!-- Importe  -->
              @endif
            </colgroup>

            <tr>
              <th class="center">CANTIDAD</th>
              <th colspan="2">CONCEPTO</th>
              <th class="center">PRECIO</th>
              @if($showDiscountColumn)
                <th class="center">DESCUENTO</th>
              @endif
              <th class="center">IMPORTE</th>
            </tr>

            <tr class="spacer r2">
              <td class="c1">&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
              @if($showDiscountColumn)
                <td>&nbsp;</td>
              @endif
              <td>&nbsp;</td>
            </tr>

            @php $idxDetalle = 0; @endphp
            @for ($bloque = 0; $bloque < $blocksToDraw; $bloque++)
              @php
                $row = $displayRows[$idxDetalle] ?? null;
                $d = $row['model'] ?? null;
                $cantidad = $d->cantidad ?? null;
                $unidad   = $d->unidad   ?? '';
                $concepto = $d->concepto ?? '';
                $mon      = $d->moneda   ?? $moneda;

                [$pSym, $pVal] = $moneyParts($row['precio_display'] ?? null, $mon);
                [$dSym, $dVal] = $moneyParts($row['descuento_display'] ?? null, $mon);
                [$iSym, $iVal] = $moneyParts($row['importe_display'] ?? null, $mon);
              @endphp

              <tr class="data">
                <td class="c1 qty">{{ $d ? $fmtQty($cantidad) : ' ' }}</td>
                <td class="unit">{{ $d ? ($unidad ?: ' ') : ' ' }}</td>
                <td>{{ $d ? ($concepto ?: ' ') : ' ' }}</td>
                <td class="money">
                  <div class="mwrap">
                    <span class="sym">{{ $pSym ?: ' ' }}</span>
                    <span class="val">{{ $pVal ?: ' ' }}</span>
                  </div>
                </td>
                @if($showDiscountColumn)
                  <td class="money">
                    <div class="mwrap">
                      <span class="sym">{{ $dSym ?: ' ' }}</span>
                      <span class="val">{{ $dVal ?: ' ' }}</span>
                    </div>
                  </td>
                @endif
                <td class="money">
                  <div class="mwrap">
                    <span class="sym">{{ $iSym ?: ' ' }}</span>
                    <span class="val">{{ $iVal ?: ' ' }}</span>
                  </div>
                </td>
              </tr>

              <tr class="spacer">
                <td class="c1">&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
                @if($showDiscountColumn)
                  <td>&nbsp;</td>
                @endif
                <td>&nbsp;</td>
              </tr>

              @php if ($d) $idxDetalle++; @endphp
            @endfor

            {{-- NOTAS (solo en columnas 2 y 3) --}}
            @php $notas = trim((string)($oc->notas ?? '')); @endphp
            <tr class="notes no-upper">
              <td class="note-first">&nbsp;</td>
              <td colspan="2" class="note-cell">{!! $notas !== '' ? nl2br(e($notas)) : '&nbsp;' !!}</td>
              <td class="note-empty">&nbsp;</td>
              @if($showDiscountColumn)
                <td class="note-empty">&nbsp;</td>
              @endif
              <td class="note-empty note-last">&nbsp;</td>
            </tr>

            @php
              [$sSym, $sVal] = $moneyParts($sumSubtotal, $moneda);
              [$vSym, $vVal] = $moneyParts($sumIva,       $moneda);
              [$tSym, $tVal] = $moneyParts($sumTotal,     $moneda);
            @endphp

            {{-- ===== TOTALES ===== --}}
            <tr class="summary">
              <td>&nbsp;</td>
              <td colspan="2">&nbsp;</td>
              @if($showDiscountColumn)
                <td>&nbsp;</td>
                <td class="right"><b>SUBTOTAL</b></td>
              @else
                <td class="right"><b>SUBTOTAL</b></td>
              @endif
              <td class="money-total">
                <div class="mwrap"><span class="sym"><b>{{ $sSym }}</b></span><span class="val"><b>{{ $sVal }}</b></span></div>
              </td>
            </tr>
            <tr class="summary">
              <td>&nbsp;</td>
              <td colspan="2">&nbsp;</td>
              @if($showDiscountColumn)
                <td>&nbsp;</td>
                <td class="right"><b>IVA</b></td>
              @else
                <td class="right"><b>IVA</b></td>
              @endif
              <td class="money-total">
                <div class="mwrap"><span class="sym"><b>{{ $vSym }}</b></span><span class="val"><b>{{ $vVal }}</b></span></div>
              </td>
            </tr>
            @php
              [$rSym, $rVal] = $moneyParts($sumIsr, $moneda);
            @endphp

            @if($showIsr)
            <tr class="summary">
              <td>&nbsp;</td>
              <td colspan="2">&nbsp;</td>
              @if($showDiscountColumn)
                <td>&nbsp;</td>
                <td class="right"><b>RET ISR</b></td>
              @else
                <td class="right"><b>RET ISR</b></td>
              @endif
              <td class="money-total">
                <div class="mwrap">
                  <span class="sym"><b>{{ $rSym }}</b></span>
                  <span class="val"><b>{{ $rVal }}</b></span>
                </div>
              </td>
            </tr>
            @endif

            @php
              [$riSym, $riVal] = $moneyParts($sumRetIva, $moneda);
            @endphp

            @if($showRetIva)
            <tr class="summary">
              <td>&nbsp;</td>
              <td colspan="2">&nbsp;</td>
              @if($showDiscountColumn)
                <td>&nbsp;</td>
                <td class="right"><b>RET IVA</b></td>
              @else
                <td class="right"><b>RET IVA</b></td>
              @endif
              <td class="money-total">
                <div class="mwrap">
                  <span class="sym"><b>{{ $riSym }}</b></span>
                  <span class="val"><b>{{ $riVal }}</b></span>
                </div>
              </td>
            </tr>
            @endif

            <tr class="summary last">
              <td>&nbsp;</td>
              <td colspan="2">&nbsp;</td>
              @if($showDiscountColumn)
                <td>&nbsp;</td>
                <td class="right"><b>TOTAL ({{ $moneda }})</b></td>
              @else
                <td class="right"><b>TOTAL ({{ $moneda }})</b></td>
              @endif
              <td class="money-total">
                <div class="mwrap"><span class="sym"><b>{{ $tSym }}</b></span><span class="val"><b>{{ $tVal }}</b></span></div>
              </td>
            </tr>

            {{-- ===== 8 filas extra ===== --}}
            <tr class="extra e1 no-upper">
              <td colspan="2" class="center xxsmall"><b>DEPTO. DE COMPRAS</b></td>
              <td colspan="{{ $extraRightColspan }}" class="xxsmall"><b>OBSERVACIONES:</b></td>
            </tr>
            <tr class="extra e2 no-upper">
              <td colspan="2">&nbsp;</td>
              <td colspan="{{ $extraRightColspan }}" class="bigger3">Favor de poner # de O.C. a la factura y enviar la factura a</td>
            </tr>
            <tr class="extra e3 no-upper">
              <td colspan="2">&nbsp;</td>
              <td colspan="{{ $extraRightColspan }}" class="bigger3">almacen@reprosisa.com.mx</td>
            </tr>
            <tr class="extra e4 no-upper">
              <td colspan="2" class="center upper-force">{{ $nombreFirma }}</td>
              <td colspan="{{ $extraRightColspan }}">&nbsp;</td>
            </tr>
            <tr class="extra e5 no-upper">
              <td colspan="2" class="center"><b>FIRMA</b></td>
              <td colspan="{{ $extraRightColspan }}">&nbsp;</td>
            </tr>

            {{-- Fila 6: texto en col 3 y bloque de imagen ocupando las columnas restantes y filas 6-8 --}}
            <tr class="extra e6 no-upper">
              <td colspan="2">&nbsp;</td>
              <td class="xxxsmall">LAS FACTURAS DEBERAN MOSTRAR EL NUMERO DE ESTE ORDEN PARA SER PAGADAS</td>
              <td colspan="{{ $footerImageColspan }}" rowspan="3" class="footer-img-td"></td>
            </tr>
            <tr class="extra e7 no-upper">
              <td colspan="2">&nbsp;</td>
              <td>&nbsp;</td>
            </tr>
            <tr class="extra e8 no-upper">
              <td colspan="2">&nbsp;</td>
              <td>&nbsp;</td>
            </tr>
          </table>

        </div>
      </div>
    </div>
  </div>
</x-app-layout>
