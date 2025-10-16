{{-- resources/views/oc/create.blade.php --}}
<x-app-layout title="Nueva OC">
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight text-center">Nueva orden de compra</h2>
  </x-slot>

  <style>
    .zoom-outer{ overflow-x:hidden; }
    .zoom-inner{ --zoom:1; transform:scale(var(--zoom)); transform-origin:top left; width:calc(100%/var(--zoom)); }
    @media (max-width:1024px){ .zoom-inner{ --zoom:.95 } .page-wrap{max-width:94vw;padding:0 4vw} }
    @media (max-width:768px){  .zoom-inner{ --zoom:.90 } .page-wrap{max-width:94vw;padding:0 4vw} }
    @media (max-width:640px){  .zoom-inner{ --zoom:.75 } .page-wrap{max-width:94vw;padding:0 4vw} }
    @media (max-width:400px){  .zoom-inner{ --zoom:.60 } .page-wrap{max-width:94vw;padding:0 4vw} }
    @media (max-width:768px){ input, select, textarea, button { font-size:16px; } }

    .page-wrap{ max-width:1100px; margin:0 auto; }
    .wrap{background:#fff;padding:24px;border-radius:10px;box-shadow:0 2px 6px rgba(0,0,0,.08); border:1px solid #e5e7eb}
    .row{margin-bottom:16px}
    select,textarea,input{width:100%;padding:8px;border:1px solid #d1d5db;border-radius:8px}
    label{font-weight:600; display:block; margin-bottom:6px;}
    .err{color:#dc2626;font-size:12px;margin-top:6px}
    .hint{font-size:12px;color:#6b7280}
    .btn{background:#16a34a;color:#fff;padding:10px 16px;border-radius:8px;font-weight:600;border:none}
    .btn:hover{background:#15803d}
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
    $proveedores = $proveedores ?? collect();
    $defaultIva = old('iva_porcentaje', 16);
    $oldItems = old('items', [
      ['cantidad'=>'','unidad'=>'','concepto'=>'','moneda'=>'MXN','precio'=>'','importe'=>'']
    ]);

    // Solo admin puede editar No. de orden
    $isAdmin = Auth::user()->hasRole('Administrador');

    // Viene del controlador leyendo oc_counters.last_seq + 1
    $numeroSugerido = $numeroSugerido ?? '';
    $numeroInicial  = old('numero_orden', $numeroSugerido);
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

          <form method="POST" action="{{ route('oc.store') }}">
            @csrf

            <div class="section-sep"><div class="line"></div><div class="label">Datos</div><div class="line"></div></div>

            <div class="grid2 row">
              <div>
                <label>No. de orden</label>

                <input
                  type="text"
                  name="numero_orden"
                  value="{{ $numeroInicial }}"
                  @unless($isAdmin) readonly @endunless
                >

                @if($isAdmin)
                  <div class="hint">
                    Consecutivo sugerido: <code>{{ $numeroSugerido }}</code>.
                    <button type="button" id="resetNoOrden" class="btn-gray" style="margin-left:.4rem;padding:.2rem .5rem">Usar sugerido</button>
                  </div>
                @else
                  <div class="hint">El folio se asignará al guardar y puede cambiar si alguien guarda antes.</div>
                @endif

                @error('numero_orden') <div class="err">{{ $message }}</div> @enderror
              </div>

              <div>
                <label>Fecha</label>
                <input type="date" name="fecha" value="{{ old('fecha') }}" required>
                @error('fecha') <div class="err">{{ $message }}</div> @enderror
              </div>
            </div>

            <div class="grid2 row">
              <div>
                <label>Solicitante</label>
                <select name="solicitante_id" required>
                  <option value="" disabled {{ old('solicitante_id') ? '' : 'selected' }}>— Selecciona —</option>
                  @foreach($colaboradores as $c)
                    @php
                      $aps = $c->apellido ?? $c->apellidos
                           ?? trim(($c->apellido_paterno ?? '').' '.($c->apellido_materno ?? ''));
                      $nombre = trim(($c->nombre ?? '').' '.$aps);
                    @endphp
                    <option value="{{ $c->id }}" @selected((int)old('solicitante_id')===$c->id)>{{ $nombre }}</option>
                  @endforeach
                </select>
                @error('solicitante_id') <div class="err">{{ $message }}</div> @enderror
              </div>

              <div>
                <label>Proveedor</label>
                <select name="proveedor_id" id="proveedor_id" required>
                  <option value="" disabled {{ old('proveedor_id') ? '' : 'selected' }}>— Selecciona —</option>
                  @foreach($proveedores as $p)
                    <option value="{{ $p->id }}" @selected((int)old('proveedor_id') === (int)$p->id)>{{ $p->nombre }}</option>
                  @endforeach
                </select>
                @error('proveedor_id') <div class="err">{{ $message }}</div> @enderror
              </div>
            </div>

            <div class="row">
              <div style="width:100%">
                <label>Descripción</label>
                <textarea name="descripcion" rows="3" placeholder="Detalle del pedido, referencias, condiciones, etc.">{{ old('descripcion') }}</textarea>
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
                  @foreach($oldItems as $idx => $it)
                  <tr class="item-row">
                    <td><input type="number" step="0.0001" min="0" name="items[{{ $idx }}][cantidad]" class="i-cantidad right" value="{{ $it['cantidad'] }}"></td>
                    <td><input type="text" name="items[{{ $idx }}][unidad]" value="{{ $it['unidad'] }}"></td>
                    <td><input type="text" name="items[{{ $idx }}][concepto]" value="{{ $it['concepto'] }}"></td>
                    <td>
                      @php $mon = $it['moneda'] ?? 'MXN'; @endphp
                      <select name="items[{{ $idx }}][moneda]" class="i-moneda">
                        <option value="MXN" @selected($mon==='MXN')>MXN</option>
                        <option value="USD" @selected($mon==='USD')>USD</option>
                      </select>
                    </td>
                    <td><input type="number" step="0.0001" min="0" name="items[{{ $idx }}][precio]" class="i-precio right" value="{{ $it['precio'] ?? $it['precio_unitario'] ?? '' }}"></td>
                    <td><input type="number" step="0.0001" min="0" name="items[{{ $idx }}][importe]" class="i-importe right" value="{{ $it['importe'] }}" readonly></td>
                    <td class="right"><button type="button" class="btn-danger del-row">X</button></td>
                  </tr>
                  @endforeach
                </tbody>
              </table>
              <div id="currencyAlert" class="currency-alert">Solo se puede seleccionar <b>una moneda</b> por OC. Se mantendrá la moneda de la primera partida.</div>
              <div style="margin-top:10px">
                <button type="button" class="btn-gray" id="addRow">+ Agregar partida</button>
              </div>
            </div>

            {{-- === Totales: Subtotal + IVA% === --}}
            <div class="grid3 row">
              <div>
                <div class="inline" style="gap:16px">
                  <div style="flex:1">
                    <label>Subtotal</label>
                    <input type="number" step="0.01" min="0" name="subtotal" id="subtotal" value="{{ old('subtotal') }}" readonly>
                  </div>
                  <div class="w-18">
                    <label>IVA %</label>
                    <div class="suffix-wrap">
                      <input type="number" step="0.01" min="0" name="iva_porcentaje" id="ivaPct" value="{{ $defaultIva }}" title="IVA %">
                      <span class="suffix">%</span>
                    </div>
                  </div>
                </div>
              </div>

              <div>
                <label>IVA</label>
                <input type="number" step="0.01" min="0" name="iva" id="iva" value="{{ old('iva') }}" readonly>
              </div>

              <div>
                <label>Total</label>
                <input type="number" step="0.01" min="0" name="total" id="total" value="{{ old('total') }}" readonly>
              </div>
            </div>

            <div class="row">
              <div style="width:100%">
                <label>Notas</label>
                <textarea name="notas" rows="4" placeholder="Notas internas u observaciones">{{ old('notas', $oc->notas ?? '') }}</textarea>
                @error('notas') <div class="err">{{ $message }}</div> @enderror
              </div>
            </div>

            <div class="grid2" style="margin-top:18px">
              <a href="{{ route('oc.index') }}" class="btn-cancel">Cancelar</a>
              <button type="submit" class="btn">Guardar</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <script>
    (function(){
      // Botón "Usar sugerido" (solo aparece para admin)
      document.getElementById('resetNoOrden')?.addEventListener('click', function(){
        const inp = document.querySelector('input[name="numero_orden"]');
        if(inp){ inp.value = @json($numeroSugerido); }
      });

      const tbody  = document.getElementById('itemsTbody');
      const addBtn = document.getElementById('addRow');
      const ivaPct = document.getElementById('ivaPct');
      const elSubtotal = document.getElementById('subtotal');
      const elIva      = document.getElementById('iva');
      const elTotal    = document.getElementById('total');
      const alertBox   = document.getElementById('currencyAlert');

      function getMasterSelect(){ return tbody.querySelector('.item-row .i-moneda'); }
      function getBaseCurrency(){ const ms = getMasterSelect(); return ms ? ms.value : null; }
      function showCurrencyAlert(){
        if(!alertBox) return;
        alertBox.classList.add('show');
        clearTimeout(showCurrencyAlert._t);
        showCurrencyAlert._t = setTimeout(()=>alertBox.classList.remove('show'), 3000);
      }
      function enforceCurrencyOnAll(base, exceptEl=null){
        tbody.querySelectorAll('.i-moneda').forEach(sel => {
          if(sel !== exceptEl && sel.value !== base){ sel.value = base; }
        });
      }
      function onCurrencyChange(e){
        const sel = e.target, master = getMasterSelect();
        if(!master) return;
        if(sel === master){
          enforceCurrencyOnAll(master.value, master);
        }else{
          const base = getBaseCurrency();
          if(base && sel.value !== base){ showCurrencyAlert(); sel.value = base; }
        }
        recalc();
      }

      function recalc(){
        let subtotal = 0;
        tbody.querySelectorAll('tr.item-row').forEach(tr=>{
          const q = parseFloat(tr.querySelector('.i-cantidad')?.value || '0');
          const p = parseFloat(tr.querySelector('.i-precio')?.value || '0');
          const imp = (q*p) || 0;
          const iImp = tr.querySelector('.i-importe');
          if(iImp){ iImp.value = imp.toFixed(2); }
          subtotal += imp;
        });
        const iva = subtotal * (parseFloat(ivaPct.value || '0')/100);
        const total = subtotal + iva;
        elSubtotal.value = subtotal.toFixed(2);
        elIva.value      = iva.toFixed(2);
        elTotal.value    = total.toFixed(2);
      }

      function rowTemplate(idx, baseMoneda){
        const mMXN = (!baseMoneda || baseMoneda === 'MXN') ? 'selected' : '';
        const mUSD = (baseMoneda === 'USD') ? 'selected' : '';
        return `
        <tr class="item-row">
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

      function bindRowEvents(scope=document){
        scope.querySelectorAll('.i-cantidad,.i-precio').forEach(inp=>{
          inp.removeEventListener('input', recalc);
          inp.addEventListener('input', recalc);
        });
        scope.querySelectorAll('.del-row').forEach(btn=>{
          btn.onclick = (e)=>{ e.preventDefault(); const row = btn.closest('tr'); row.remove(); recalc(); renumberNames(); };
        });
        scope.querySelectorAll('.i-moneda').forEach(sel=>{
          sel.removeEventListener('change', onCurrencyChange);
          sel.addEventListener('change', onCurrencyChange);
        });
      }

      function renumberNames(){
        Array.from(tbody.querySelectorAll('tr.item-row')).forEach((tr, i)=>{
          tr.querySelectorAll('input,select').forEach(el=>{
            el.name = el.name.replace(/items\[\d+\]/, `items[${i}]`);
          });
        });
      }

      addBtn?.addEventListener('click', ()=>{
        const idx = tbody.querySelectorAll('tr.item-row').length;
        const base = getBaseCurrency();
        tbody.insertAdjacentHTML('beforeend', rowTemplate(idx, base));
        const newRow = tbody.lastElementChild;
        bindRowEvents(newRow);
        const master = getMasterSelect();
        if(master && base){ enforceCurrencyOnAll(base); }
        recalc();
      });

      bindRowEvents();
      const baseInit = getBaseCurrency();
      if(baseInit){ enforceCurrencyOnAll(baseInit, getMasterSelect()); }
      recalc();
      ivaPct?.addEventListener('input', recalc);
    })();
  </script>
</x-app-layout>
