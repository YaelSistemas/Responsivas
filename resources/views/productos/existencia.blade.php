<x-app-layout title="Existencia - {{ $producto->nombre }}">
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight text-center">
      Existencia — {{ $producto->nombre }}
    </h2>
  </x-slot>

  <style>
    .page-wrap{max-width:950px;margin:0 auto}
    .card{background:#fff;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,.06)}
    .tbl{width:100%;border-collapse:separate;border-spacing:0}
    .tbl th,.tbl td{padding:.6rem .8rem;text-align:left;vertical-align:middle}
    .tbl thead th{font-weight:700;color:#374151;background:#f9fafb;border-bottom:1px solid #e5e7eb}
    .tbl tbody tr+tr td{border-top:1px solid #f1f5f9}
    .err{color:#dc2626;font-size:12px;margin-top:6px}
  </style>

  <div class="page-wrap py-6">
    @if (session('updated') || session('error'))
      @php
        $msg = session('updated') ? 'Stock actualizado.' : (session('error') ?: '');
        $cls = session('error') ? 'background:#fee2e2;color:#991b1b;border:1px solid #fecaca'
                                : 'background:#dcfce7;color:#166534;border:1px solid #bbf7d0';
      @endphp
      <div id="alert" style="border-radius:8px;padding:.6rem .9rem; {{ $cls }}" class="mb-4">{{ $msg }}</div>
      <script>setTimeout(()=>{const a=document.getElementById('alert'); if(a){a.style.opacity='0';a.style.transition='opacity .4s'; setTimeout(()=>a.remove(),400)}},2000);</script>
    @endif

    <div class="grid md:grid-cols-2 gap-6">
      <div class="card p-5">
        <div class="text-sm text-gray-500">Existencia actual</div>
        <div class="text-3xl font-semibold">
          {{ $stock->cantidad }} <span class="text-lg font-normal text-gray-500">{{ $producto->unidad_medida ?? 'pz' }}</span>
        </div>

        <form method="POST" action="{{ route('productos.existencia.ajustar', $producto) }}" class="mt-5 space-y-3">
          @csrf
          <div>
            <label class="block text-sm mb-1">Tipo de movimiento</label>
            <select name="tipo" class="w-full border rounded px-3 py-2">
              <option value="entrada">Entrada</option>
              <option value="salida">Salida</option>
              <option value="ajuste">Ajuste (±)</option>
            </select>
          </div>

          <div>
            <label class="block text-sm mb-1">Cantidad</label>
            <input type="number" step="1" name="cantidad" value="1" class="w-full border rounded px-3 py-2">
            @error('cantidad') <div class="err">{{ $message }}</div> @enderror
          </div>

          <div>
            <label class="block text-sm mb-1">Motivo (opcional)</label>
            <input name="motivo" class="w-full border rounded px-3 py-2" placeholder="Recepción, ajuste inventario, etc.">
          </div>

          <div>
            <label class="block text-sm mb-1">Referencia (opcional)</label>
            <input name="referencia" class="w-full border rounded px-3 py-2" placeholder="OC-123, Rem-55, etc.">
          </div>

          <div class="pt-2">
            <button type="submit" class="inline-block bg-green-600 text-white font-semibold px-4 py-2 rounded hover:bg-green-700">
              Guardar movimiento
            </button>
            <a href="{{ route('productos.index') }}" class="ml-3 text-gray-600 hover:underline">Volver a productos</a>
          </div>
        </form>
      </div>

      <div class="card p-5">
        <div class="text-lg font-semibold mb-3">Últimos movimientos</div>
        <div class="overflow-x-auto">
          <table class="tbl">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Cantidad</th>
                <th>Motivo</th>
                <th>Ref.</th>
              </tr>
            </thead>
            <tbody>
              @forelse($movs as $m)
                <tr>
                  <td class="text-sm text-gray-600">{{ $m->created_at->format('Y-m-d H:i') }}</td>
                  <td class="text-sm">{{ ucfirst($m->tipo) }}</td>
                  <td class="text-sm font-semibold">{{ $m->cantidad }}</td>
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
</x-app-layout>
