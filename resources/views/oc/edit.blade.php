{{-- resources/views/oc/edit.blade.php --}}
<x-app-layout title="Editar OC {{ $oc->numero_orden }}">
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight text-center">
      Editar orden de compra — {{ $oc->numero_orden }}
    </h2>
  </x-slot>

  <style>
    .zoom-outer{ overflow-x:hidden; }
    .zoom-inner{ --zoom:1; transform:scale(var(--zoom)); transform-origin:top left; width:calc(100%/var(--zoom)); }
    @media (max-width:1024px){ .zoom-inner{ --zoom:.95 } .page-wrap{max-width:94vw;padding:0 4vw} }
    @media (max-width:768px){  .zoom-inner{ --zoom:.90 } .page-wrap{max-width:94vw;padding:0 4vw} }
    @media (max-width:640px){  .zoom-inner{ --zoom:.75 } .page-wrap{max-width:94vw;padding:0 4vw} }
    @media (max-width:400px){  .zoom-inner{ --zoom:.60 } .page-wrap{max-width:94vw;padding:0 4vw} }
    @media (max-width:768px){ input, select, textarea, button { font-size:16px } }

    .page-wrap{ max-width:1100px; margin:0 auto; }
    .wrap{background:#fff;padding:24px;border-radius:10px;box-shadow:0 2px 6px rgba(0,0,0,.08); border:1px solid #e5e7eb}
    .row{margin-bottom:16px}
    select,textarea,input{width:100%;padding:8px;border:1px solid #d1d5db;border-radius:8px}
    label{font-weight:600; display:block; margin-bottom:6px;}
    .err{color:#dc2626;font-size:12px;margin-top:6px}
    .hint{font-size:12px;color:#6b7280}
    .btn{background:#2563eb;color:#fff;padding:10px 16px;border-radius:8px;font-weight:600;border:none}
    .btn-cancel{background:#dc2626;color:#fff;padding:10px 16px;border-radius:8px;text-decoration:none}
    .btn-gray{background:#374151;color:#fff;padding:8px 12px;border-radius:8px;border:none}
    .btn-gray:hover{background:#111827}
    .btn-danger{background:#b91c1c;color:#fff;padding:8px 10px;border-radius:8px;border:none}
    .grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
    .grid3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px}
    .section-sep{display:flex;align-items:center;margin:22px 0 14px}
    .section-sep .line{flex:1;height:1px;background:#e5e7eb}
    .section-sep .label{margin:0 10px;font-size:12px;color:#6b7280;letter-spacing:.06em;text-transform:uppercase;font-weight:700;white-space:nowrap}

    .items-table{width:100%; border-collapse:collapse}
    .items-table th,.items-table td{border:1px solid #e5e7eb; padding:8px}
    .items-table th{background:#f9fafb; font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:#6b7280}
    .items-table input, .items-table select{width:100%; box-sizing:border-box}
    .right{text-align:right}
    .nowrap{white-space:nowrap}

    .items-table td:nth-child(4){ min-width: 110px; }
    .items-table select.i-moneda{
      -webkit-appearance: none; -moz-appearance: none; appearance: none;
      padding-right: 2.6rem;
      background-image: url("data:image/svg+xml;utf8,<svg fill='none' stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' viewBox='0 0 24 24' xmlns='http://www.w3.org/2000/svg'><path d='M19 9l-7 7-7-7'/></svg>");
      background-repeat: no-repeat; background-position: right .45rem center; background-size: 12px 12px; background-color:#fff; line-height:1.25;
    }
    .items-table select.i-moneda::-ms-expand{ display:none; }

    .suffix-wrap{ position:relative; display:inline-block; width:100%; }
    .suffix-wrap input{ width:100%; padding-right:2rem; box-sizing:border-box; }
    .suffix-wrap .suffix{ position:absolute; right:.5rem; top:50%; transform:translateY(-50%); color:#6b7280; pointer-events:none; font-weight:600; }

    .w-18{ width:120px; }
    .inline{ display:flex; align-items:flex-end; gap:12px; }

    .currency-alert{ display:none; margin-top:8px; padding:8px 10px; border-radius:8px; background:#fff7ed; color:#9a3412; border:1px solid #fdba74; font-size:13px; }
    .currency-alert.show{ display:block; }
  </style>

@php
  $fechaDefault = old('fecha', \Illuminate\Support\Carbon::parse($oc->fecha)->toDateString());

  $isAdminCanEditFolio = auth()->user()->hasRole('Administrador')
      || auth()->user()->can('oc.edit_prefix');

  // Permisos (misma regla para IVA manual e ISR manual)
  $canManual = auth()->user()->hasRole('Administrador')
              || auth()->user()->hasRole('Compras IVA');

  // ====================================================
  // 1) OBTENER IVA% REAL
  // ====================================================
  $ivaPct = $oc->iva_porcentaje;

  if ($ivaPct === null) {
      $detalles = $oc->relationLoaded('detalles')
          ? $oc->detalles
          : $oc->detalles()->get();

      if ($detalles->count() > 0) {
          $distinct = $detalles->pluck('iva_pct')->unique()->values();
          if ($distinct->count() === 1) {
              $ivaPct = (float)$distinct->first();
          }
      }
  }

  if ($ivaPct === null) $ivaPct = 0;

  // =======================
  // 2) PREFILL PARTIDAS
  // =======================
  $prefill = old('items');

  if (!$prefill) {
      $prefill = ($oc->relationLoaded('detalles') ? $oc->detalles : $oc->detalles()->get())
          ->map(function($d){
              return [
                  'id'       => $d->id,
                  'cantidad' => $d->cantidad ?? '',
                  'unidad'   => $d->unidad ?? '',
                  'concepto' => $d->concepto ?? '',
                  'moneda'   => $d->moneda ?? 'MXN',
                  'precio'   => $d->precio ?? '',
                  'importe'  => $d->importe ?? '',
              ];
          })->values()->all();
  }

  if (empty($prefill)) {
      $prefill = [[
          'id' => null,
          'cantidad' => '',
          'unidad'   => '',
          'concepto' => '',
          'moneda'   => 'MXN',
          'precio'   => '',
          'importe'  => ''
      ]];
  }

  // =======================
  // 3) Calcular subtotal
  // =======================
  $phpSubtotal = 0;
  foreach ($prefill as $r) {
      $phpSubtotal += (float)$r['cantidad'] * (float)$r['precio'];
  }

  // =======================
  // 4) Detectar IVA manual
  // =======================
  $sumIvaDetalles = ($oc->relationLoaded('detalles') ? $oc->detalles : $oc->detalles()->get())->sum('iva_monto');
  $isManual = ($ivaPct == 0);

  // =======================
  // 5) Calcular IVA monto
  // =======================
  if ($isManual) {
      $defaultIva = 0;
      $defaultIvaMonto = $sumIvaDetalles;
  } else {
      $defaultIva = $ivaPct;
      $defaultIvaMonto = $phpSubtotal * ($defaultIva / 100);
  }

  // =======================
  // 6) Total base (sin ISR)
  // =======================
  $phpTotal = $phpSubtotal + $defaultIvaMonto;

  // =======================
  // 7) ISR (Retención) - detección correcta (auto o manual)
  // =======================
  $detallesISR = ($oc->relationLoaded('detalles') ? $oc->detalles : $oc->detalles()->get());

  $sumIsrMonto = (float) $detallesISR->sum('isr_monto');

  // pct > 0 (AUTO). Tomamos todos los pct > 0 y vemos si es único
  $distinctIsrPctPos = $detallesISR->pluck('isr_pct')
      ->map(fn($v) => (float)$v)
      ->filter(fn($v) => $v > 0)
      ->unique()
      ->values();

  // ✅ Reglas:
  // - ISR activo si (hay pct>0) o (hay monto>0)
  // - Si ambos 0 -> NO activo
  $inferIsrEnabled = ($distinctIsrPctPos->count() > 0) || ($sumIsrMonto > 0);

  // Si el usuario viene de un submit fallido, respetar old(); si no, inferir:
  $isrEnabled = (int) old('isr_enabled', $inferIsrEnabled ? 1 : 0);

  // ✅ Inferir pct:
  // - Si hay un único pct>0 -> usarlo (AUTO)
  // - Si no hay pct>0 -> 0 (MANUAL)
  $inferIsrPct = ($distinctIsrPctPos->count() === 1) ? (float)$distinctIsrPctPos->first() : 0;
  $isrPct      = (float) old('isr_pct', $inferIsrPct);

  // ✅ Manual si:
  // - está activo
  // - pct == 0
  // - y hay monto > 0 (porque si ambos son 0, no queremos “activar”)
  $isrIsManual = ($isrEnabled && ((float)$isrPct == 0) && ($sumIsrMonto > 0));

  // ✅ Monto precargado:
  // - Manual -> sumatoria guardada
  // - Automático -> 0 (JS lo recalcula con el %)
  $defaultIsrMonto = $isrIsManual ? $sumIsrMonto : 0;

  // =======================
  // 8) RET IVA (Retención IVA) - detección correcta (auto o manual)
  // =======================
  $detallesRetIva = ($oc->relationLoaded('detalles') ? $oc->detalles : $oc->detalles()->get());

  $sumRetIvaMonto = (float) $detallesRetIva->sum('ret_iva_monto');

  $distinctRetIvaPctPos = $detallesRetIva->pluck('ret_iva_pct')
      ->map(fn($v) => (float)$v)
      ->filter(fn($v) => $v > 0)
      ->unique()
      ->values();

  // Ret IVA activo si (hay pct>0) o (hay monto>0)
  $inferRetIvaEnabled = ($distinctRetIvaPctPos->count() > 0) || ($sumRetIvaMonto > 0);

  // si viene de submit fallido, respeta old(); si no, inferimos:
  $retIvaEnabled = (int) old('ret_iva_enabled', $inferRetIvaEnabled ? 1 : 0);

  // Inferir pct:
  // - si hay 1 pct>0 -> AUTO
  // - si no -> 0 (MANUAL)
  $inferRetIvaPct = ($distinctRetIvaPctPos->count() === 1) ? (float)$distinctRetIvaPctPos->first() : 0;
  $retIvaPct      = (float) old('ret_iva_pct', $inferRetIvaPct);

  // Manual si: activo + pct == 0 + monto > 0
  $retIvaIsManual = ($retIvaEnabled && ((float)$retIvaPct == 0) && ($sumRetIvaMonto > 0));

  // Monto precargado:
  // - Manual -> sumatoria guardada
  // - Automático -> 0 (JS lo recalcula con el %)
  $defaultRetIvaMonto = $retIvaIsManual ? $sumRetIvaMonto : 0;

  // ============================
  // FORMATEADORES NUMÉRICOS
  // ============================
  $fmt2 = fn($n) => number_format((float)$n, 2, '.', '');

  $hasNonZeroDecimals = function($val): bool {
      if ($val === null || $val === '') return false;
      $p = explode('.', (string)$val, 2);
      return isset($p[1]) && rtrim($p[1], '0') !== '';
  };

  $precioDisplay = function($val) use ($hasNonZeroDecimals) {
      if ($val === null || $val === '') return '';
      return $hasNonZeroDecimals($val)
          ? rtrim(rtrim((string)$val, '0'), '.')
          : number_format((float)$val, 2, '.', '');
  };

  $cantidadDisplay = function($val) use ($hasNonZeroDecimals) {
      if ($val === null || $val === '') return '';
      return $hasNonZeroDecimals($val)
          ? rtrim(rtrim((string)$val, '0'), '.')
          : (string)intval((float)$val);
  };
@endphp

  <div class="zoom-outer">
    <div class="zoom-inner">
      <div class="py-6 page-wrap">
        <div class="wrap">

          @if ($errors->any())
            <div style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:8px;padding:12px;margin-bottom:14px;">
              <b>Revisa los campos:</b>
              <ul style="margin-left:18px;list-style:disc;">
                @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
              </ul>
            </div>
          @endif

          <form method="POST" action="{{ route('oc.update', $oc) }}">
            @csrf
            @method('PUT')

            <div class="section-sep"><div class="line"></div><div class="label">Datos</div><div class="line"></div></div>

            <div class="grid2 row">
              <div>
                <label>No. de orden</label>
                <input
                  type="text"
                  name="numero_orden"
                  value="{{ old('numero_orden', $oc->numero_orden) }}"
                  @unless($isAdminCanEditFolio) readonly @endunless
                  required
                >
                <div class="hint">
                  @if($isAdminCanEditFolio)
                    Puedes ajustar este folio. Si lo colocas <b>mayor o igual</b> que el próximo consecutivo,
                    el sistema moverá el contador para que la siguiente OC use el siguiente número.
                    Si lo colocas <b>menor</b>, el contador no se mueve.
                  @else
                    Solo administradores pueden modificar el folio.
                  @endif
                </div>
                @error('numero_orden') <div class="err">{{ $message }}</div> @enderror
              </div>

              <div>
                <label>Fecha</label>
                <input type="date" name="fecha" value="{{ $fechaDefault }}" required>
                @error('fecha') <div class="err">{{ $message }}</div> @enderror
              </div>
            </div>

            <div class="grid2 row">
              <div>
                <label>Solicitante</label>
                <select name="solicitante_id" id="solicitante_id" required>
                  <option value="" disabled>— Selecciona —</option>
                  @foreach($colaboradores as $c)
                    @php
                      $aps = $c->apellido ?? $c->apellidos
                           ?? trim(($c->apellido_paterno ?? '').' '.($c->apellido_materno ?? ''));
                      $nombre = trim(($c->nombre ?? '').' '.$aps);
                    @endphp
                    <option value="{{ $c->id }}" @selected((int)old('solicitante_id', $oc->solicitante_id)===$c->id)>{{ $nombre }}</option>
                  @endforeach
                </select>
                @error('solicitante_id') <div class="err">{{ $message }}</div> @enderror
              </div>

              <div>
                <label>Proveedor</label>
                <select name="proveedor_id" id="proveedor_id" required>
                  <option value="" disabled>— Selecciona —</option>
                  @foreach($proveedores as $p)
                    <option value="{{ $p->id }}" @selected((int)old('proveedor_id', $oc->proveedor_id)===$p->id)>{{ $p->nombre }}</option>
                  @endforeach
                </select>
                @error('proveedor_id') <div class="err">{{ $message }}</div> @enderror
              </div>
            </div>

            <div class="row">
              <div style="width:100%">
                <label>Descripción</label>
                <textarea name="descripcion" rows="3" placeholder="Detalle del pedido, referencias, condiciones, etc.">{{ old('descripcion', $oc->descripcion) }}</textarea>
                @error('descripcion') <div class="err">{{ $message }}</div> @enderror
              </div>
            </div>

            {{-- ================= PARTIDAS ================= --}}
            <div class="section-sep"><div class="line"></div><div class="label">Partidas</div><div class="line"></div></div>

            <div class="row" style="overflow-x:auto">
              <table class="items-table" id="itemsTable">
                <thead>
                  <tr>
                    <th class="nowrap">Cant.</th>
                    <th class="nowrap">U/M</th>
                    <th>Concepto</th>
                    <th class="nowrap">Moneda</th>
                    <th class="nowrap">Precio</th>
                    <th class="nowrap">Importe</th>
                    <th class="nowrap">—</th>
                  </tr>
                </thead>
                <tbody id="itemsTbody">
                  @foreach($prefill as $idx => $it)
                  <tr class="item-row">
                    <input type="hidden" name="items[{{ $idx }}][id]" value="{{ old("items.$idx.id", $it['id'] ?? '') }}">
                    <td>
                      <input type="number" step="0.0001" min="0"
                             name="items[{{ $idx }}][cantidad]"
                             class="i-cantidad right"
                             value="{{ $cantidadDisplay(old("items.$idx.cantidad", $it['cantidad'])) }}">
                    </td>
                    <td><input type="text" name="items[{{ $idx }}][unidad]" value="{{ old("items.$idx.unidad", $it['unidad']) }}"></td>
                    <td><input type="text" name="items[{{ $idx }}][concepto]" value="{{ old("items.$idx.concepto", $it['concepto']) }}"></td>
                    <td>
                      @php $mon = old("items.$idx.moneda", $it['moneda'] ?? 'MXN'); @endphp
                      <select name="items[{{ $idx }}][moneda]" class="i-moneda">
                        <option value="MXN" @selected($mon==='MXN')>MXN&nbsp;</option>
                        <option value="USD" @selected($mon==='USD')>USD&nbsp;</option>
                      </select>
                    </td>
                    <td>
                      <input type="number" step="0.0001" min="0"
                             name="items[{{ $idx }}][precio]"
                             class="i-precio right"
                             value="{{ $precioDisplay(old("items.$idx.precio", $it['precio'])) }}">
                    </td>
                    <td>
                      <input type="number" step="0.0001" min="0"
                             name="items[{{ $idx }}][importe]"
                             class="i-importe right"
                             value="{{ old("items.$idx.importe", $it['importe']) }}"
                             readonly>
                    </td>
                    <td class="right"><button type="button" class="btn-danger del-row">X</button></td>
                  </tr>
                  @endforeach
                </tbody>
              </table>
              <div id="currencyAlert" class="currency-alert">
                Solo se puede seleccionar <b>una moneda</b> por OC. Se mantendrá la moneda de la primera partida.
              </div>
              <div style="margin-top:10px">
                <button type="button" class="btn-gray" id="addRow">+ Agregar partida</button>
              </div>
            </div>

            {{-- Totales --}}
            <div class="grid3 row">
              <div>
                <div class="inline" style="gap:16px">
                  <div style="flex:1">
                    <label>Subtotal</label>
                    <input type="number" step="0.01" min="0" name="subtotal" id="subtotal" value="{{ $fmt2(old('subtotal', $phpSubtotal)) }}" readonly>
                  </div>
                  <div class="w-18">
                    @php
                      $ivaFieldValue = old('iva_porcentaje', $ivaPct);
                      if ($ivaFieldValue === null || $ivaFieldValue === '') {
                          $ivaFieldValue = $defaultIva;
                      }
                    @endphp

                    <label>IVA %</label>
                    <div class="suffix-wrap">
                      <input type="number" step="0.01" min="0" name="iva_porcentaje" id="ivaPct" value="{{ $ivaFieldValue }}">
                      <span class="suffix">%</span>
                    </div>
                  </div>
                </div>
              </div>

              {{-- IVA MONTO --}}
              <div>
                <label>IVA</label>

                @if($canManual)
                  <input type="number" step="0.01" min="0" name="iva" id="iva"
                         value="{{ $fmt2($defaultIvaMonto) }}"
                         @if($defaultIva != 0) readonly @endif>
                @else
                  <input type="number" step="0.01" min="0" name="iva" id="iva"
                         value="{{ $fmt2($defaultIvaMonto) }}" readonly>
                @endif

                <input type="hidden" name="iva_manual" id="ivaManualInput"
                       value="{{ $defaultIva == 0 ? $fmt2($defaultIvaMonto) : '' }}">
              </div>

              <div>
                <label>Total</label>
                <input type="number" step="0.01" min="0" name="total" id="total" value="{{ $fmt2(old('total', $phpTotal)) }}" readonly>
              </div>
            </div>

            {{-- === Retención ISR (debajo de totales) === --}}
            <div class="row" style="margin-top:-6px;">
              <div style="display:grid;grid-template-columns: 1.1fr 1fr 1fr;gap:16px;align-items:start;">

                <div>
                  <label style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
                    <input type="checkbox" id="isrEnabled" name="isr_enabled" value="1" {{ $isrEnabled ? 'checked' : '' }}>
                    <span>Activar retención ISR</span>
                  </label>
                  <div class="hint" id="isrHint">El total será: <b>Subtotal + IVA - ISR</b></div>
                </div>

                <div id="isrPctBox" style="{{ $isrEnabled ? '' : 'display:none;' }}">
                  <label>ISR %</label>
                  <div class="suffix-wrap">
                    <input type="number" step="0.01" min="0" name="isr_pct" id="isrPct"
                           value="{{ number_format($isrPct, 2, '.', '') }}">
                    <span class="suffix">%</span>
                  </div>
                </div>

                {{-- ✅ ISR monto ahora funciona igual que IVA --}}
                <div id="isrMontoBox" style="{{ $isrEnabled ? '' : 'display:none;' }}">
                  <label>ISR</label>

                  <input
                    type="number" step="0.01" min="0"
                    name="isr" id="isr"
                    value="{{ $fmt2(old('isr', $defaultIsrMonto)) }}"
                    {{-- si ISR% != 0 => automático (readonly) --}}
                    @if(!$isrIsManual) readonly @endif
                  >

                  {{-- bandera para detectar modo manual (igual que iva_manual) --}}
                  <input
                    type="hidden"
                    name="isr_manual"
                    id="isrManualInput"
                    value="{{ $isrIsManual ? $fmt2(old('isr', $defaultIsrMonto)) : '' }}"
                  >
                </div>

              </div>
            </div>

            {{-- === Retención IVA (debajo de ISR) === --}}
            <div class="row" style="margin-top:-6px;">
              <div style="display:grid;grid-template-columns: 1.1fr 1fr 1fr;gap:16px;align-items:start;">

                <div>
                  <label style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
                    <input type="checkbox" id="retIvaEnabled" name="ret_iva_enabled" value="1" {{ $retIvaEnabled ? 'checked' : '' }}>
                    <span>Activar retención IVA</span>
                  </label>
                  <div class="hint" id="retIvaHint">El total será: <b>Subtotal + IVA - Ret IVA</b></div>
                </div>

                <div id="retIvaPctBox" style="{{ $retIvaEnabled ? '' : 'display:none;' }}">
                  <label>Ret IVA %</label>
                  <div class="suffix-wrap">
                    <input type="number" step="0.01" min="0" name="ret_iva_pct" id="retIvaPct"
                          value="{{ number_format((float)$retIvaPct, 2, '.', '') }}">
                    <span class="suffix">%</span>
                  </div>
                </div>

                <div id="retIvaMontoBox" style="{{ $retIvaEnabled ? '' : 'display:none;' }}">
                  <label>Ret IVA</label>
                  <input type="number" step="0.01" min="0"
                        name="ret_iva_monto" id="retIvaMonto"
                        value="{{ $fmt2(old('ret_iva_monto', $defaultRetIvaMonto ?? 0)) }}"
                        @if(empty($retIvaIsManual)) readonly @endif>
                  <input type="hidden" name="ret_iva_manual" id="retIvaManualInput"
                        value="{{ !empty($retIvaIsManual) ? $fmt2(old('ret_iva_monto', $defaultRetIvaMonto ?? 0)) : '' }}">
                </div>

              </div>
            </div>

            <div class="row">
              <div style="width:100%">
                <label>Notas</label>
                <textarea name="notas" rows="4" placeholder="Notas internas u observaciones">{{ old('notas', $oc->notas ?? '') }}</textarea>
                @error('notas') <div class="err">{{ $message }}</div> @enderror
              </div>
            </div>

            <div class="grid2">
              <a href="{{ route('oc.index') }}" class="btn-cancel">Cancelar</a>
              <button type="submit" class="btn">Actualizar orden</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <script>
document.addEventListener("DOMContentLoaded", () => {

  const CAN_MANUAL = {{ $canManual ? 'true' : 'false' }};

  // DOM
  const tbody     = document.getElementById('itemsTbody');
  const addBtn    = document.getElementById('addRow');
  const ivaPct    = document.getElementById('ivaPct');
  const elSub     = document.getElementById('subtotal');
  const elIva     = document.getElementById('iva');
  const elTotal   = document.getElementById('total');
  const alertBox  = document.getElementById('currencyAlert');

  // IVA hidden
  const ivaManualHidden = document.getElementById("ivaManualInput");

  // ISR DOM
  const isrEnabled  = document.getElementById("isrEnabled");
  const isrPctEl    = document.getElementById("isrPct");
  const isrMontoEl  = document.getElementById("isr");   // ahora ES input name="isr"
  const isrManualEl = document.getElementById("isrManualInput"); // hidden bandera
  const isrPctBox   = document.getElementById("isrPctBox");
  const isrMontoBox = document.getElementById("isrMontoBox");

  // RET IVA DOM
  const retIvaEnabled  = document.getElementById("retIvaEnabled");
  const retIvaPctEl    = document.getElementById("retIvaPct");
  const retIvaMontoEl  = document.getElementById("retIvaMonto");
  const retIvaManualEl = document.getElementById("retIvaManualInput");
  const retIvaPctBox   = document.getElementById("retIvaPctBox");
  const retIvaMontoBox = document.getElementById("retIvaMontoBox");

  function r2(n){
    n = Number(n || 0);
    return Math.round((n + Number.EPSILON) * 100) / 100;
  }
  function fmt2(n){
    return r2(n).toFixed(2);
  }

  /* =========================
     MONEDA (una sola)
  ========================= */
  function getMasterSelect() {
      return tbody.querySelector('.item-row .i-moneda');
  }

  function getBaseCurrency() {
      const s = getMasterSelect();
      return s ? s.value : null;
  }

  function showCurrencyAlert() {
      if (!alertBox) return;
      alertBox.classList.add("show");
      clearTimeout(showCurrencyAlert._timer);
      showCurrencyAlert._timer = setTimeout(() => alertBox.classList.remove("show"), 3000);
  }

  function enforceCurrencyOnAll(base, except=null) {
      tbody.querySelectorAll(".i-moneda").forEach(sel => {
          if (sel !== except) sel.value = base;
      });
  }

  function onCurrencyChange(e) {
      const sel = e.target;
      const master = getMasterSelect();
      if (!master) return;

      if (sel === master) {
          enforceCurrencyOnAll(master.value, master);
      } else {
          const base = getBaseCurrency();
          if (base && sel.value !== base) {
              showCurrencyAlert();
              sel.value = base;
          }
      }

      recalc();
  }

  /* =========================
     RECALC: SOLO SUBTOTAL + IMPORTES
  ========================= */
  function recalc() {
      let subtotal = 0;

      tbody.querySelectorAll("tr.item-row").forEach(tr => {
          const q = parseFloat(tr.querySelector(".i-cantidad")?.value || "0");
          const p = parseFloat(tr.querySelector(".i-precio")?.value || "0");
          const imp = r2((q * p) || 0);

          const impInput = tr.querySelector(".i-importe");
          if (impInput) impInput.value = fmt2(imp);

          subtotal += imp;
      });

      elSub.value = fmt2(subtotal);

      // Dispara cálculo final
      elSub.dispatchEvent(new Event("input", { bubbles: true }));
  }

  /* =========================
     TOTALS: IVA + ISR + TOTAL
     Total = Subtotal + IVA - ISR
     - IVA manual cuando IVA% = 0 y tiene permiso
     - ISR manual cuando ISR% = 0, ISR activo y tiene permiso
  ========================= */
  function applyTotals() {
      const sub = r2(parseFloat(elSub.value || 0));

      // =========================
      // IVA (igual que antes)
      // =========================
      const pctIva = parseFloat(ivaPct.value || 0);
      let ivaFinal = 0;

      if (pctIva === 0) {
          if (!CAN_MANUAL) {
              elIva.readOnly = true;
              elIva.value = "0.00";
              if (ivaManualHidden) ivaManualHidden.value = "0.00";
              ivaFinal = 0;
          } else {
              elIva.readOnly = false;

              if (elIva.value === "" || isNaN(parseFloat(elIva.value))) {
                  elIva.value = "0.00";
              }

              const ivaUser = r2(parseFloat(elIva.value || 0));
              ivaFinal = ivaUser;

              if (ivaManualHidden) ivaManualHidden.value = fmt2(ivaUser);
          }
      } else {
          elIva.readOnly = true;
          const ivaCalc = r2(sub * (pctIva / 100));
          elIva.value = fmt2(ivaCalc);
          ivaFinal = ivaCalc;

          if (ivaManualHidden) ivaManualHidden.value = "";
      }

      // =========================
      // ISR (manual/auto como IVA)
      // =========================
      const isrOn = !!(isrEnabled && isrEnabled.checked);

      if (isrPctBox)   isrPctBox.style.display   = isrOn ? "" : "none";
      if (isrMontoBox) isrMontoBox.style.display = isrOn ? "" : "none";

      let isrFinal = 0;

      if (!isrOn) {
          if (isrPctEl) isrPctEl.value = "0.00";
          if (isrMontoEl) {
              isrMontoEl.readOnly = true;
              isrMontoEl.value = "0.00";
          }
          if (isrManualEl) isrManualEl.value = "";
          isrFinal = 0;
      } else {
          const pctIsr = parseFloat(isrPctEl?.value || 0);

          // --- ISR manual cuando ISR% = 0 ---
          if (pctIsr === 0) {

              if (!CAN_MANUAL) {
                  // sin permiso: forzar a 0
                  if (isrMontoEl) {
                      isrMontoEl.readOnly = true;
                      isrMontoEl.value = "0.00";
                  }
                  if (isrManualEl) isrManualEl.value = "0.00";
                  isrFinal = 0;
              } else {
                  // con permiso: editable manual
                  if (isrMontoEl) isrMontoEl.readOnly = false;

                  if (isrMontoEl && (isrMontoEl.value === "" || isNaN(parseFloat(isrMontoEl.value)))) {
                      isrMontoEl.value = "0.00";
                  }

                  const isrUser = r2(parseFloat(isrMontoEl?.value || 0));
                  isrFinal = isrUser;

                  if (isrManualEl) isrManualEl.value = fmt2(isrUser);
              }

          } else {
              // --- ISR automático por % ---
              if (isrMontoEl) isrMontoEl.readOnly = true;

              const isrCalc = r2(sub * (pctIsr / 100));
              if (isrMontoEl) isrMontoEl.value = fmt2(isrCalc);

              if (isrManualEl) isrManualEl.value = "";
              isrFinal = isrCalc;
          }
      }

      // =========================
      // RET IVA (manual/auto)
      // base: SUBTOTAL (como ISR)
      // =========================
      const retIvaOn = !!(retIvaEnabled && retIvaEnabled.checked);

      if (retIvaPctBox)   retIvaPctBox.style.display   = retIvaOn ? "" : "none";
      if (retIvaMontoBox) retIvaMontoBox.style.display = retIvaOn ? "" : "none";

      let retIvaFinal = 0;

      if (!retIvaOn) {
          if (retIvaPctEl) retIvaPctEl.value = "0.00";
          if (retIvaMontoEl) {
              retIvaMontoEl.readOnly = true;
              retIvaMontoEl.value = "0.00";
          }
          if (retIvaManualEl) retIvaManualEl.value = "";
          retIvaFinal = 0;
      } else {
          const pctRetIva = parseFloat(retIvaPctEl?.value || 0);

          if (pctRetIva === 0) {
              // manual
              if (!CAN_MANUAL) {
                  if (retIvaMontoEl) {
                      retIvaMontoEl.readOnly = true;
                      retIvaMontoEl.value = "0.00";
                  }
                  if (retIvaManualEl) retIvaManualEl.value = "0.00";
                  retIvaFinal = 0;
              } else {
                  if (retIvaMontoEl) retIvaMontoEl.readOnly = false;

                  if (retIvaMontoEl && (retIvaMontoEl.value === "" || isNaN(parseFloat(retIvaMontoEl.value)))) {
                      retIvaMontoEl.value = "0.00";
                  }

                  const user = r2(parseFloat(retIvaMontoEl?.value || 0));
                  retIvaFinal = user;

                  if (retIvaManualEl) retIvaManualEl.value = fmt2(user);
              }
          } else {
              // automático sobre SUBTOTAL
              if (retIvaMontoEl) retIvaMontoEl.readOnly = true;

              const calc = r2(sub * (pctRetIva / 100));
              if (retIvaMontoEl) retIvaMontoEl.value = fmt2(calc);

              if (retIvaManualEl) retIvaManualEl.value = "";
              retIvaFinal = calc;
          }
      }

      // =========================
      // Total final
      // =========================
      const totalCalc = r2(sub + r2(ivaFinal) - r2(isrFinal) - r2(retIvaFinal));
      elTotal.value = fmt2(totalCalc);

      // =========================
      // HINTS dinámicos
      // =========================
      const isrHintEl = document.getElementById("isrHint");
      const retIvaHintEl = document.getElementById("retIvaHint");
      const bothOn = isrOn && retIvaOn;

      if (isrHintEl) {
        isrHintEl.innerHTML = bothOn
          ? 'El total será: <b>Subtotal + IVA - ISR - Ret IVA</b>'
          : 'El total será: <b>Subtotal + IVA - ISR</b>';
      }

      if (retIvaHintEl) {
        retIvaHintEl.innerHTML = bothOn
          ? 'El total será: <b>Subtotal + IVA - ISR - Ret IVA</b>'
          : 'El total será: <b>Subtotal + IVA - Ret IVA</b>';
      }

  }

  /* =========================
     RENOMBRAR FILAS
  ========================= */
  function renumberNames() {
      Array.from(tbody.querySelectorAll("tr.item-row")).forEach((tr, i) => {
          tr.querySelectorAll("input, select").forEach(el => {
              el.name = el.name.replace(/items\[\d+\]/, `items[${i}]`);
          });
      });
  }

  /* =========================
     PLANTILLA FILA NUEVA
  ========================= */
  function rowTemplate(idx, baseMoneda) {
      const mMXN = (!baseMoneda || baseMoneda === "MXN") ? "selected" : "";
      const mUSD = (baseMoneda === "USD") ? "selected" : "";

      return `
      <tr class="item-row">
          <input type="hidden" name="items[${idx}][id]" value="">
          <td><input type="number" step="0.0001" min="0" name="items[${idx}][cantidad]" class="i-cantidad right"></td>
          <td><input type="text" name="items[${idx}][unidad]"></td>
          <td><input type="text" name="items[${idx}][concepto]"></td>
          <td>
              <select name="items[${idx}][moneda]" class="i-moneda">
                  <option value="MXN" ${mMXN}>MXN</option>
                  <option value="USD" ${mUSD}>USD</option>
              </select>
          </td>
          <td><input type="number" step="0.0001" min="0" name="items[${idx}][precio]" class="i-precio right"></td>
          <td><input type="number" step="0.01" min="0" name="items[${idx}][importe]" class="i-importe right" readonly></td>
          <td class="right"><button type="button" class="btn-danger del-row">X</button></td>
      </tr>`;
  }

  /* =========================
     BIND EVENTS FILAS
  ========================= */
  function bindRowEvents(scope = document) {
      scope.querySelectorAll(".i-cantidad, .i-precio").forEach(inp => {
          inp.addEventListener("input", recalc);
      });

      scope.querySelectorAll(".i-moneda").forEach(sel => {
          sel.addEventListener("change", onCurrencyChange);
      });

      scope.querySelectorAll(".del-row").forEach(btn => {
          btn.onclick = e => {
              e.preventDefault();
              btn.closest("tr").remove();
              renumberNames();
              recalc();
          };
      });
  }

  /* =========================
     AGREGAR FILA
  ========================= */
  addBtn?.addEventListener("click", () => {
      const idx = tbody.querySelectorAll("tr.item-row").length;
      const base = getBaseCurrency();
      tbody.insertAdjacentHTML("beforeend", rowTemplate(idx, base));
      const newRow = tbody.lastElementChild;

      bindRowEvents(newRow);
      if (base) enforceCurrencyOnAll(base);

      recalc();
  });

  /* =========================
     EVENTOS TOTALES
  ========================= */
  elSub.addEventListener("input", applyTotals);
  ivaPct.addEventListener("input", applyTotals);
  elIva.addEventListener("input", applyTotals);

  isrEnabled?.addEventListener("change", applyTotals);
  isrPctEl?.addEventListener("input", applyTotals);
  isrMontoEl?.addEventListener("input", applyTotals); // ✅ para recalcular total cuando ISR manual cambia

  retIvaEnabled?.addEventListener("change", applyTotals);
  retIvaPctEl?.addEventListener("input", applyTotals);
  retIvaMontoEl?.addEventListener("input", applyTotals);

  /* =========================
     INIT
  ========================= */
  bindRowEvents();

  const baseInit = getBaseCurrency();
  if (baseInit) enforceCurrencyOnAll(baseInit);

  recalc();
  applyTotals();
});
  </script>

@push('styles')
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css">
    <style>
        .ts-dropdown {
            max-height: 260px;
            overflow-y: auto;
            z-index: 9999 !important;
        }
        .ts-dropdown .ts-dropdown-content {
            max-height: inherit;
            overflow-y: auto;
        }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const baseConfig = {
                allowEmptyOption: true,
                placeholder: '— Selecciona —',
                maxOptions: 5000,
                sortField: { field: 'text', direction: 'asc' },
                plugins: ['dropdown_input'],
                dropdownParent: 'body',
                onDropdownOpen: function () {
                    const rect = this.control.getBoundingClientRect();
                    const espacioAbajo = window.innerHeight - rect.bottom - 10;
                    const dropdown = this.dropdown;

                    if (dropdown) {
                        const minimo = 160;
                        const maximo = 260;
                        let alto = Math.max(minimo, Math.min(espacioAbajo, maximo));
                        dropdown.style.maxHeight = alto + 'px';
                    }
                }
            };

            if (document.getElementById('solicitante_id')) {
                new TomSelect('#solicitante_id', { ...baseConfig });
            }

            if (document.getElementById('proveedor_id')) {
                new TomSelect('#proveedor_id', { ...baseConfig });
            }
        });
    </script>
@endpush

</x-app-layout>
