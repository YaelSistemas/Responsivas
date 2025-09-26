<x-app-layout title="Existencia - {{ $producto->nombre }}">
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight text-center">
      Existencia — {{ $producto->nombre }}
    </h2>
  </x-slot>

  @php
    // Ajusta el nombre del permiso al que uses para permitir movimientos de stock
    $canMovimientos = auth()->user()->can('productos.edit');
  @endphp

  <style>
    /* ====== Zoom responsivo ====== */
    .zoom-outer{ overflow-x:hidden; }
    .zoom-inner{
      --zoom: 1;
      transform: scale(var(--zoom));
      transform-origin: top left;
      width: calc(100% / var(--zoom));
    }
    @media (max-width: 1024px){ .zoom-inner{ --zoom:.95; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }
    @media (max-width: 768px){  .zoom-inner{ --zoom:.90; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }
    @media (max-width: 640px){  .zoom-inner{ --zoom:.70; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }
    @media (max-width: 400px){  .zoom-inner{ --zoom:.55; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }

    @media (max-width:768px){ input, select, textarea{ font-size:16px; } }

    .page-wrap{max-width:1000px;margin:0 auto}
    .card{background:#fff;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.06)}
    .tbl{width:100%;border-collapse:separate;border-spacing:0}
    .tbl th,.tbl td{padding:.65rem .85rem;vertical-align:middle}
    .tbl thead th{font-weight:700;color:#374151;background:#f9fafb;border-bottom:1px solid #e5e7eb}
    .tbl tbody tr+tr td{border-top:1px solid #f1f5f9}
    .err{color:#dc2626;font-size:12px;margin-top:6px}

    .stock-badge{display:inline-flex;align-items:baseline;gap:.4rem;background:#f1f5f9;border:1px solid #e5e7eb;
      border-radius:9999px;padding:.5rem 1rem}
    .stock-badge .n{font-weight:700;font-size:1.4rem;color:#0f172a}
    .stock-badge .u{font-size:.9rem;color:#475569}

    .seg{display:flex;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}
    .seg button{flex:1;padding:.6rem .9rem;font-weight:600;color:#374151}
    .seg button:hover{background:#eef2ff}
    .seg button.active{background:#2563eb;color:#fff}

    .qty-wrap{display:flex;align-items:center;border:1px solid #d1d5db;border-radius:12px;overflow:hidden}
    .qty-wrap button{width:44px;height:40px;font-weight:700;color:#334155;background:#f8fafc}
    .qty-wrap button:hover{background:#eef2ff}
    .qty-wrap input{border:0;outline:0;padding:.55rem .75rem;width:110px;text-align:center}
    .suffix{margin-left:.5rem;color:#64748b;font-size:.9rem}

    .field{display:flex;flex-direction:column;gap:.45rem}
    .field label{font-size:.9rem;color:#475569}
    .field input,.field select,.field textarea{
      border:1px solid #d1d5db;border-radius:12px;padding:.6rem .75rem
    }

    .btn{display:inline-flex;align-items:center;gap:.5rem;font-weight:700;border-radius:12px}
    .btn-primary{background:#16a34a;color:#fff;padding:.7rem 1.1rem}
    .btn-primary:hover{background:#15803d}
    .btn-ghost{padding:.6rem 1rem;border:1px solid #e5e7eb;background:#f8fafc;color:#334155}
    .btn-ghost:hover{background:#eef2ff}

    .chip{border:1px solid #e5e7eb;border-radius:9999px;padding:.12rem .55rem;font-size:.75rem}
    .chip.in{background:#dcfce7;color:#065f46;border-color:#bbf7d0}
    .chip.out{background:#fee2e2;color:#7f1d1d;border-color:#fecaca}
    .chip.adj{background:#e0e7ff;color:#3730a3;border-color:#c7d2fe}

    .toast{border-radius:10px;padding:.6rem .9rem}
  </style>

  <div class="zoom-outer">
    <div class="zoom-inner">
      <div class="page-wrap py-6">
        @if (session('updated') || session('error'))
          @php
            $msg = session('updated') ? 'Stock actualizado.' : (session('error') ?: '');
            $cls = session('error') ? 'background:#fee2e2;color:#991b1b;border:1px solid #fecaca'
                                    : 'background:#dcfce7;color:#166534;border:1px solid #bbf7d0';
          @endphp
          <div id="alert" class="toast mb-4" style="{{ $cls }}">{{ $msg }}</div>
          <script>setTimeout(()=>{const a=document.getElementById('alert'); if(a){a.style.opacity='0';a.style.transition='opacity .4s'; setTimeout(()=>a.remove(),400)}},2000);</script>
        @endif

        {{-- Barra superior SIEMPRE visible con "Volver a productos" --}}
        <div class="flex items-center justify-end mb-4">
          <a href="{{ route('productos.index') }}" class="btn btn-ghost">Volver a productos</a>
        </div>

        <div class="grid {{ $canMovimientos ? 'md:grid-cols-2' : '' }} gap-6">
          
          {{-- PANEL IZQUIERDO SOLO SI TIENE PERMISOS (sin el botón de volver aquí) --}}
          @if($canMovimientos)
            <div class="card p-6 md:p-8">
              <div class="text-sm text-gray-500 mb-4">Existencia actual</div>

              <div class="stock-badge mb-6">
                <span class="n">{{ $stock->cantidad }}</span>
                <span class="u">{{ $producto->unidad_medida ?? 'pieza' }}{{ $stock->cantidad==1?'':'s' }}</span>
              </div>

              <form id="mov-form" method="POST" action="{{ route('productos.existencia.ajustar', $producto) }}" class="space-y-5">
                @csrf

                <div class="field">
                  <label>Tipo de movimiento</label>
                  <input type="hidden" name="tipo" id="tipo" value="entrada">
                  <div class="seg" id="seg-tipo">
                    <button type="button" data-t="entrada" class="active">Entrada</button>
                    <button type="button" data-t="salida">Salida</button>
                    <button type="button" data-t="ajuste">Ajuste (±)</button>
                  </div>
                </div>

                <div class="field">
                  <label>Cantidad</label>
                  <div class="qty-wrap">
                    <button type="button" id="btn-dec">−</button>
                    <input type="number" step="1" name="cantidad" id="cantidad" value="1" inputmode="numeric">
                    <button type="button" id="btn-inc">+</button>
                    <span class="suffix">{{ $producto->unidad_medida ?? 'pz' }}</span>
                  </div>
                  @error('cantidad') <div class="err">{{ $message }}</div> @enderror
                </div>

                <div class="field">
                  <label>Motivo (opcional)</label>
                  <input name="motivo" placeholder="Recepción, ajuste inventario, etc.">
                </div>

                <div class="field">
                  <label>Referencia (opcional)</label>
                  <input name="referencia" placeholder="OC-123, etc.">
                </div>

                <div class="pt-1 flex items-center gap-3">
                  <button type="submit" class="btn btn-primary">
                    Guardar movimiento
                  </button>
                  <span id="preview" class="text-sm text-gray-500"></span>
                </div>
              </form>
            </div>
          @endif

          {{-- PANEL DERECHO: Últimos movimientos (visible siempre) --}}
          <div class="card p-6 md:p-8">
            <div class="text-lg font-semibold mb-3">Últimos movimientos</div>
            <div class="overflow-x-auto">
              <table class="tbl">
                <thead>
                  <tr>
                    <th style="width:150px">Fecha</th>
                    <th style="width:110px" class="text-center">Tipo</th>
                    <th style="width:100px" class="text-center">Cantidad</th>
                    <th>Motivo</th>
                    <th style="width:130px">Ref.</th>
                  </tr>
                </thead>
                <tbody>
                  @forelse($movs as $m)
                    @php
                      $chipClass = $m->tipo==='entrada' ? 'in' : ($m->tipo==='salida' ? 'out' : 'adj');
                    @endphp
                    <tr>
                      <td class="text-sm text-gray-600">{{ $m->created_at->format('Y-m-d H:i') }}</td>
                      <td class="text-center">
                        <span class="chip {{ $chipClass }}">{{ ucfirst($m->tipo) }}</span>
                      </td>
                      <td class="text-center text-sm font-semibold">{{ $m->cantidad }}</td>
                      <td class="text-sm text-gray-700">{{ $m->motivo ?? '—' }}</td>
                      <td class="text-sm text-gray-700">{{ $m->referencia ?? '—' }}</td>
                    </tr>
                  @empty
                    <tr><td colspan="5" class="text-center text-gray-500 py-6">Sin movimientos.</td></tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>

  <script>
    // ====== UI: Segmented control ======
    (function(){
      const seg = document.getElementById('seg-tipo');
      if(!seg) return; // si no hay panel de edición, no ejecutar
      const hidden = document.getElementById('tipo');
      const inp = document.getElementById('cantidad');

      function onTypeChange(newType){
        hidden.value = newType;
        if(newType === 'ajuste'){
          inp.removeAttribute('min');
        } else {
          inp.setAttribute('min', '1');
          if(parseInt(inp.value||1,10) <= 0) inp.value = 1;
        }
        updatePreview();
      }

      seg.querySelectorAll('button').forEach(btn=>{
        btn.addEventListener('click', ()=>{
          seg.querySelectorAll('button').forEach(b=> b.classList.remove('active'));
          btn.classList.add('active');
          onTypeChange(btn.dataset.t);
        });
      });
    })();

    (function(){
      const inp = document.getElementById('cantidad');
      if(!inp) return;
      const tipo = document.getElementById('tipo');

      function allowNegative(){ return tipo.value === 'ajuste'; }

      document.getElementById('btn-dec').addEventListener('click', ()=>{
        let v = parseInt(inp.value || 0, 10);
        v = isNaN(v) ? 0 : v;
        v = v - 1;
        if(!allowNegative() && v < 1) v = 1;
        inp.value = v;
        updatePreview();
      });

      document.getElementById('btn-inc').addEventListener('click', ()=>{
        let v = parseInt(inp.value || 0, 10);
        v = isNaN(v) ? 0 : v;
        v = v + 1;
        if(!allowNegative() && v < 1) v = 1;
        inp.value = v;
        updatePreview();
      });

      inp.addEventListener('input', ()=>{
        let v = parseInt(inp.value, 10);
        if(isNaN(v)) v = allowNegative() ? 0 : 1;
        if(!allowNegative() && v < 1) v = 1;
        inp.value = v;
        updatePreview();
      });

      updatePreview();
    })();

    function updatePreview(){
      const prev = document.getElementById('preview');
      if(!prev) return;
      const tipo = document.getElementById('tipo').value;
      const raw = parseInt(document.getElementById('cantidad').value || 0, 10);
      const v = isNaN(raw) ? 0 : raw;

      let txt = '';
      if(tipo==='entrada'){
        txt = `Se sumarán +${Math.abs(v||1)}.`;
      } else if(tipo==='salida'){
        txt = `Se restarán −${Math.abs(v||1)}.`;
      } else {
        if(v < 0)      txt = `Se ajustará ${v} (quita).`;
        else if(v > 0) txt = `Se ajustará +${v} (agrega).`;
        else           txt = `Ajuste 0 (sin cambio).`;
      }
      prev.textContent = txt;
    }
  </script>
</x-app-layout>
