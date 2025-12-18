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

$canManual = auth()->user()->hasRole('Administrador')
             || auth()->user()->hasRole('Compras IVA');

// ====================================================
// 1) OBTENER IVA% REAL
// ====================================================
$ivaPct = $oc->iva_porcentaje;

// Si la cabecera no tiene IVA, intentar deducirlo de partidas
if ($ivaPct === null) {

    $detalles = $oc->relationLoaded('detalles')
        ? $oc->detalles
        : $oc->detalles()->get();

    if ($detalles->count() > 0) {

        // Ver si todas las partidas tienen el mismo IVA%
        $distinct = $detalles->pluck('iva_pct')->unique()->values();

        if ($distinct->count() === 1) {
            $ivaPct = (float)$distinct->first();   // Ejemplo: 16
        }
    }
}

// Si sigue null → utilizar 0 (modo manual)
if ($ivaPct === null) {
    $ivaPct = 0;
}


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
// 4) Detectar si es IVA manual
// =======================
$sumIvaDetalles = $oc->detalles->sum('iva_monto');

// Modo manual cuando IVA% es 0
$isManual = ($ivaPct == 0);


// =======================
// 5) Calcular IVA monto
// =======================
if ($isManual) {
    // Usuario controla IVA monto
    $defaultIva = 0;
    $defaultIvaMonto = $sumIvaDetalles;

} else {
    // IVA automático basado en % detectado
    $defaultIva = $ivaPct;
    $defaultIvaMonto = $phpSubtotal * ($defaultIva / 100);
}


// =======================
// 6) Total
// =======================
$phpTotal = $phpSubtotal + $defaultIvaMonto;


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
                        // Tomamos old() si existe, sino el IVA correcto de BD
                        $ivaFieldValue = old('iva_porcentaje', $ivaPct);

                        // Pero si old() está vacío → debemos regresar a $defaultIva
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
                      {{-- Admin y Compras IVA pueden modificar si IVA% == 0 --}}
                      <input type="number" step="0.01" min="0" name="iva" id="iva"
                            value="{{ $fmt2($defaultIvaMonto) }}"
                            @if($defaultIva != 0) readonly @endif>
                  @else
                      {{-- Otros usuarios: solo lectura SIEMPRE --}}
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

  const canManual = {{ $canManual ? 'true' : 'false' }};

    /* ============================================================
       VARIABLES DE DOM
    ============================================================ */
    const tbody     = document.getElementById('itemsTbody');
    const addBtn    = document.getElementById('addRow');
    const ivaPct    = document.getElementById('ivaPct');
    const elSub     = document.getElementById('subtotal');
    const elIva     = document.getElementById('iva');
    const elTotal   = document.getElementById('total');
    const alertBox  = document.getElementById('currencyAlert');

    /* ============================================================
       FUNCIONES DE UTILIDAD
    ============================================================ */
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

    /* ============================================================
       RECÁLCULO GENERAL (Subtotal, IVA y Total)
    ============================================================ */
    function recalc() {
        let subtotal = 0;

        tbody.querySelectorAll("tr.item-row").forEach(tr => {
            const q = parseFloat(tr.querySelector(".i-cantidad")?.value || "0");
            const p = parseFloat(tr.querySelector(".i-precio")?.value || "0");
            const imp = (q * p) || 0;

            const impInput = tr.querySelector(".i-importe");
            if (impInput) impInput.value = imp.toFixed(2);

            subtotal += imp;
        });

        elSub.value = subtotal.toFixed(2);

        /* ======================================
          NUEVA LÓGICA:
          MODO MANUAL = cuando IVA% es 0
        ======================================= */
        const pct = parseFloat(ivaPct.value || "0");
        const isManual = (pct === 0);

        if (isManual) {
            // IVA MONTO editable SOLO si tiene permiso
            elIva.readOnly = !canManual;

            const ivaUser = parseFloat(elIva.value || 0);
            elTotal.value = (subtotal + ivaUser).toFixed(2);
            return;
        }

        // IVA automático
        elIva.readOnly = true;
        const ivaCalc = subtotal * (pct / 100);
        elIva.value = ivaCalc.toFixed(2);
        elTotal.value = (subtotal + ivaCalc).toFixed(2);
    }

    /* ============================================================
       RENOMBRAR FILAS CUANDO SE ELIMINA UNA
    ============================================================ */
    function renumberNames() {
        Array.from(tbody.querySelectorAll("tr.item-row")).forEach((tr, i) => {
            tr.querySelectorAll("input, select").forEach(el => {
                el.name = el.name.replace(/items\[\d+\]/, `items[${i}]`);
            });
        });
    }

    /* ============================================================
       PLANTILLA DE FILA NUEVA
    ============================================================ */
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

    /* ============================================================
       ENLAZAR EVENTOS A UNA FILA
    ============================================================ */
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

    /* ============================================================
       AGREGAR FILA
    ============================================================ */
    addBtn?.addEventListener("click", () => {
        const idx = tbody.querySelectorAll("tr.item-row").length;
        const base = getBaseCurrency();
        tbody.insertAdjacentHTML("beforeend", rowTemplate(idx, base));
        const newRow = tbody.lastElementChild;

        bindRowEvents(newRow);
        if (base) enforceCurrencyOnAll(base);

        recalc();
    });

    /* ============================================================
       ACTIVAR EVENTOS EN FILAS EXISTENTES
    ============================================================ */
    bindRowEvents();

    /* ============================================================
       ENFORZAR MONEDA AL INICIO Y RECALCULAR
    ============================================================ */
    const baseInit = getBaseCurrency();
    if (baseInit) enforceCurrencyOnAll(baseInit);

    recalc(); // cálculo inicial

    // ============================================================
    // CONFIGURAR ESTADO INICIAL DE IVA SEGÚN PERMISOS Y IVA%
    // ============================================================
    const pctInit = parseFloat(ivaPct.value || "0");

    if (pctInit === 0) {
        // Modo manual → respetar valor cargado desde Blade
        elIva.readOnly = !canManual;
    } else {
        // IVA automático
        elIva.readOnly = true;
        // recalcular por si subtotal cambió
        elIva.value = ((parseFloat(elSub.value || 0) * pctInit) / 100).toFixed(2);
    }

    // Activar modo manual al inicio si IVA% = 0, pero solo si tiene permisos
    if (parseFloat(ivaPct.value || "0") === 0) {
        elIva.readOnly = !canManual; // ← SOLO usuarios con permiso pueden editar
    } else {
        elIva.readOnly = true; // IVA automático siempre bloqueado
    }

    /* ============================================================
       EVITAR DOBLE SUBMIT
    ============================================================ */
    const form = document.querySelector('form[action*="oc"][method="post"]');
    if (form) {
        let sent = false;
        form.addEventListener("submit", e => {
            if (sent) { e.preventDefault(); return; }
            sent = true;

            const btn = form.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = true;
                btn.textContent = "Guardando...";
            }
        });
    }

    /* ============================================================
       CUANDO EL USUARIO MODIFICA EL IVA MANUALMENTE
    ============================================================ */
    elIva.addEventListener("input", () => {
        const subtotal = parseFloat(elSub.value || 0);
        const iva      = parseFloat(elIva.value || 0);
        elTotal.value  = (subtotal + iva).toFixed(2);
    });

    ivaPct.addEventListener("input", () => {
        const pct = parseFloat(ivaPct.value || "0");

        if (pct === 0) {
            // IVA% = 0 → siempre poner IVA monto en 0
            elIva.value = "0.00";
            elIva.readOnly = !canManual;
        } else {
            // IVA automático
            elIva.readOnly = true;
            elIva.value = ((parseFloat(elSub.value || 0) * pct) / 100).toFixed(2);
        }

        recalc();
    });

});
  </script>

@push('styles')
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css">
    <style>
        /* Altura máxima del menú de Tom Select y scroll interno */
        .ts-dropdown {
            max-height: 260px;
            overflow-y: auto;
            z-index: 9999 !important; /* por encima del footer/nav */
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
                plugins: ['dropdown_input'],   // buscador interno
                dropdownParent: 'body',        // se monta en <body>
                onDropdownOpen: function () {  // ajusta altura al espacio disponible
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

            // Solicitante
            if (document.getElementById('solicitante_id')) {
                new TomSelect('#solicitante_id', {
                    ...baseConfig
                });
            }

            // Proveedor
            if (document.getElementById('proveedor_id')) {
                new TomSelect('#proveedor_id', {
                    ...baseConfig
                });
            }
        });
    </script>
@endpush

</x-app-layout>
