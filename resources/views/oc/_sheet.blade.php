@php
  // ===== Entradas esperadas =====
  // $oc            : modelo OC
  // $logoB64       : (opcional) dataURL del logo para PDF
  // $footerB64     : (opcional) dataURL de la imagen de pie para PDF
  // $logo          : (opcional) URL del logo (para show)
  // $footerImg     : (opcional) URL de imagen footer (para show)

  // ===== Fecha formateada =====
  $fechaFmt = '';
  if (!empty($oc->fecha)) {
    $fechaFmt = $oc->fecha instanceof \Illuminate\Support\Carbon
      ? $oc->fecha->format('d/m/Y')
      : \Illuminate\Support\Carbon::parse($oc->fecha)->format('d/m/Y');
  }

  // ===== Nombre firma (opcional, para la leyenda inferior) =====
  $userName    = trim(auth()->user()->name ?? '');
  $nombreFirma = '';
  if ($userName !== '') {
    $parts = preg_split('/\s+/', $userName);
    $nombreFirma = count($parts) >= 2 ? $parts[0] . ' ' . $parts[count($parts) - 2] : $userName;
  }

  // ===== Logo / footer: preferir base64 (PDF) y caer a URL (show) =====
  $logoSrc   = $logoB64 ?? ($logo ?? asset('images/logos/default.png'));
  $footerSrc = $footerB64 ?? ($footerImg ?? asset('images/oc/footer oc.png'));

  // ===== Datos proveedor
  $prov = (array) ($oc->proveedor ?? []);
  $detalles = collect($oc->detalles ?? [])->values();

  // Helpers de dinero
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

  $sumSubtotal = $detalles->sum(fn($d) => (float)($d->subtotal ?? 0));
  $sumIva      = $detalles->sum(fn($d) => (float)($d->iva_monto ?? 0));
  $sumTotal    = $detalles->sum(fn($d) => (float)($d->total ?? 0));
  $moneda      = $detalles->first()->moneda ?? 'MXN';

  $baseBlocks   = 9;
  $totalItems   = $detalles->count();
  $blocksToDraw = max($baseBlocks, $totalItems);
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
        <img src="{{ $logoSrc }}" alt="Logo">
      </div>
    </td>
    <td class="title-row title-main" rowspan="2">
      RECUBRIMIENTOS, PRODUCTOS Y SERVICIOS INDUSTRIALES, S.A. DE C.V.
    </td>
    <td class="rightcell">Página 1 de 1</td>
  </tr>
  <tr><td class="rightcell"><b>CODIFICACIÓN:</b> SGC-PO-CO-01-FO-01</td></tr>
  <tr>
    <td class="title-row title-sub">SISTEMA DE GESTION DE CALIDAD</td>
    <td class="rightcell"><b>FECHA DE EMISIÓN:</b><br>{{ $fechaFmt }}</td>
  </tr>
  <tr>
    <td class="title-row title-sub">ORDEN DE COMPRA</td>
    <td class="rightcell"><b>NÚMERO DE REVISIÓN:</b><br>01</td>
  </tr>
</table>

{{-- ========= PROVEEDOR / ORDEN ========= --}}
<table class="tbl meta-grid">
  <colgroup>
    <col style="width:8%">
    <col style="width:72%">
    <col style="width:20%">
  </colgroup>

  <tr>
    <th colspan="2">PROVEEDOR</th>
    <th>ORDEN DE COMPRA</th>
  </tr>

  <tr>
    <td class="prov-f2">&nbsp;</td>
    <td class="prov-c2-r2" style="font-weight:700">{{ $prov['nombre'] ?? '' }}</td>
    <td rowspan="2" class="order-no">{{ $oc->numero_orden ?? '' }}</td>
  </tr>

  <tr>
    <td class="prov-f3">&nbsp;</td>
    <td class="prov-c2-r3">{{ $prov['calle'] ?? '' }}</td>
  </tr>

  <tr>
    <td class="prov-f4">&nbsp;</td>
    <td class="prov-c2-r4">
      {{ $prov['colonia'] ?? '' }}
      @if(!empty($prov['colonia']) && !empty($prov['codigo_postal'])) - @endif
      {{ $prov['codigo_postal'] ?? '' }}
    </td>
    <th>FECHA</th>
  </tr>

  <tr>
    <td class="prov-f5">&nbsp;</td>
    <td class="prov-c2-r5">
      {{ $prov['ciudad'] ?? '' }}
      @if(!empty($prov['ciudad']) && !empty($prov['estado'])) , @endif
      {{ $prov['estado'] ?? '' }}
    </td>
    <td class="mono">{{ $fechaFmt }}</td>
  </tr>
</table>

{{-- ========= PARTIDAS ========= --}}
@php
  $idxDetalle = 0;
@endphp
<table class="tbl items">
  <colgroup>
    <col style="width:10%">  {{-- Cantidad --}}
    <col style="width:10%">  {{-- Unidad --}}
    <col style="width:50%">  {{-- Concepto --}}
    <col style="width:15%">  {{-- Precio --}}
    <col style="width:15%">  {{-- Importe --}}
  </colgroup>

  <tr>
    <th class="center">CANTIDAD</th>
    <th colspan="2">CONCEPTO</th>
    <th class="center">PRECIO</th>
    <th class="center">IMPORTE</th>
  </tr>

  <tr class="spacer r2">
    <td class="c1">&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
  </tr>

  @for ($bloque = 0; $bloque < $blocksToDraw; $bloque++)
    @php
      $d = $detalles->get($idxDetalle);
      $cantidad = $d->cantidad ?? null;
      $unidad   = $d->unidad   ?? '';
      $concepto = $d->concepto ?? '';
      $precio   = $d->precio   ?? null;
      $mon      = $d->moneda   ?? $moneda;
      $importe  = $d ? ($d->importe ?? ((is_numeric($cantidad) && is_numeric($precio)) ? $cantidad * $precio : null)) : null;

      [$pSym, $pVal] = $moneyParts($precio, $mon);
      [$iSym, $iVal] = $moneyParts($importe, $mon);
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
      <td class="money">
        <div class="mwrap">
          <span class="sym">{{ $iSym ?: ' ' }}</span>
          <span class="val">{{ $iVal ?: ' ' }}</span>
        </div>
      </td>
    </tr>

    <tr class="spacer">
      <td class="c1">&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
    </tr>

    @php if ($d) $idxDetalle++; @endphp
  @endfor

  @php
    [$sSym, $sVal] = $moneyParts($sumSubtotal, $moneda);
    [$vSym, $vVal] = $moneyParts($sumIva,       $moneda);
    [$tSym, $tVal] = $moneyParts($sumTotal,     $moneda);
  @endphp

  <tr class="summary">
    <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
    <td class="center"><b>SUBTOTAL</b></td>
    <td class="money-total">
      <div class="mwrap"><span class="sym"><b>{{ $sSym }}</b></span><span class="val"><b>{{ $sVal }}</b></span></div>
    </td>
  </tr>
  <tr class="summary">
    <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
    <td class="center"><b>IVA</b></td>
    <td class="money-total">
      <div class="mwrap"><span class="sym"><b>{{ $vSym }}</b></span><span class="val"><b>{{ $vVal }}</b></span></div>
    </td>
  </tr>
  <tr class="summary last">
    <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
    <td class="center"><b>TOTAL ({{ $moneda }})</b></td>
    <td class="money-total">
      <div class="mwrap"><span class="sym"><b>{{ $tSym }}</b></span><span class="val"><b>{{ $tVal }}</b></span></div>
    </td>
  </tr>

  {{-- ===== 8 filas extra ===== --}}
  <tr class="extra e1">
    <td colspan="2" class="center xxsmall"><b>DEPTO. DE COMPRAS</b></td>
    <td colspan="3" class="xxsmall"><b>OBSERVACIONES:</b></td>
  </tr>
  <tr class="extra e2">
    <td colspan="2">&nbsp;</td>
    <td colspan="3" class="bigger3">Favor de poner # de O.C. a la factura y enviar la factura a</td>
  </tr>
  <tr class="extra e3">
    <td colspan="2">&nbsp;</td>
    <td colspan="3" class="bigger3">almacen@reprosisa.com.mx</td>
  </tr>
  <tr class="extra e4">
    <td colspan="2" class="center">{{ $nombreFirma }}</td>
    <td colspan="3">&nbsp;</td>
  </tr>
  <tr class="extra e5">
    <td colspan="2" class="center"><b>FIRMA</b></td>
    <td colspan="3">&nbsp;</td>
  </tr>

  {{-- Fila 6: texto en col 3 y bloque de imagen ocupando col 4-5 y filas 6-8 --}}
  <tr class="extra e6">
    <td colspan="2">&nbsp;</td>
    <td class="xxxsmall">LAS FACTURAS DEBERAN MOSTRAR EL NUMERO DE ESTE ORDEN PARA SER PAGADAS</td>
    <td colspan="2" rowspan="3" class="footer-img-td" style="background-image:url('{{ $footerSrc }}')"></td>
  </tr>
  <tr class="extra e7">
    <td colspan="2">&nbsp;</td>
    <td>&nbsp;</td>
  </tr>
  <tr class="extra e8">
    <td colspan="2">&nbsp;</td>
    <td>&nbsp;</td>
  </tr>
</table>
