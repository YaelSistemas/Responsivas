@php
  $tipoLabels = [
    'equipo_pc'  => 'Equipo de Cómputo',
    'impresora'  => 'Impresora/Multifuncional',
    'periferico' => 'Periférico',
    'consumible' => 'Consumible',
    'otro'       => 'Otro',
  ];
@endphp

<div id="tabla-productos">
  <style>
    /* Centrar headers y celdas por defecto */
    #tabla-productos .tbl th,
    #tabla-productos .tbl td{
      text-align:center;
      vertical-align:middle;
    }
    /* EXCEPCIÓN: contenido de la columna Producto alineado a la izquierda */
    #tabla-productos .tbl td.col-producto{
      text-align:left;
    }
  </style>

  <table class="tbl w-full">
    <thead>
      <tr>
        <th style="width:320px">Producto</th>
        <th>Marca / Modelo</th>
        <th style="width:180px">Tipo</th>
        <th style="width:160px">Stock</th>
        <th style="width:150px">Series / Existencia</th>
        <th style="width:120px">Acciones</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($productos as $p)
        <tr>
          {{-- Producto + SKU + Descripción (si aplica) --}}
          <td class="font-medium col-producto">
            <div>{{ $p->nombre }}</div>
            @if($p->sku)
              <div class="text-xs text-gray-500">SKU: {{ $p->sku }}</div>
            @endif
            @if($p->descripcion)
              <div class="text-xs text-gray-600 mt-1">{{ Str::limit($p->descripcion, 120) }}</div>
            @endif
          </td>

          {{-- Marca / Modelo --}}
          <td>
            <div>{{ $p->marca ?: '—' }}</div>
            @if($p->modelo)
              <div class="text-xs text-gray-500">{{ $p->modelo }}</div>
            @endif
          </td>

          {{-- Tipo / Tracking --}}
          <td class="text-sm">
            <div class="font-medium">
              {{ $tipoLabels[$p->tipo] ?? ucfirst($p->tipo ?? '') }}
            </div>
            <div class="text-xs text-gray-500">
              {{ $p->tracking === 'serial' ? 'Por número de serie' : 'Por cantidad' }}
            </div>
          </td>

          {{-- Stock --}}
          <td class="text-sm">
            @if($p->tracking === 'serial')
              <span class="font-semibold" title="Disponibles / Total">
                {{ (int)($p->series_disponibles_count ?? 0) }}
              </span>
              <span class="text-gray-500">/ {{ (int)($p->series_total_count ?? 0) }}</span>
            @else
              <span class="font-semibold">{{ (int) (optional($p->existencia)->cantidad ?? 0) }}</span>
              <span class="text-gray-500">{{ $p->unidad ?: 'pieza' }}</span>
            @endif
          </td>

          {{-- Series / Existencia --}}
          <td>
            @if($p->tracking === 'serial')
              <a href="{{ route('productos.series', $p) }}" class="text-indigo-600 hover:underline">
                Ver series
              </a>
            @else
              <a href="{{ route('productos.existencia', $p) }}" class="text-indigo-600 hover:underline">
                Ver existencia
              </a>
            @endif
          </td>

          {{-- Acciones --}}
          <td>
            <div class="flex justify-center items-center gap-4">
              @can('productos.edit')
                <a href="{{ route('productos.edit', $p) }}" class="text-gray-800 hover:text-gray-900" title="Editar">
                  <i class="fa-solid fa-pen"></i>
                </a>
              @endcan

              @can('productos.delete')
                <form action="{{ route('productos.destroy', $p) }}" method="POST"
                      onsubmit="return confirm('¿Eliminar este producto?');">
                  @csrf @method('DELETE')
                  <button type="submit" class="text-red-600 hover:text-red-800" title="Eliminar">
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </form>
              @endcan
            </div>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="6" class="text-center text-gray-500 py-6">No hay productos.</td>
        </tr>
      @endforelse
    </tbody>
  </table>

  <div class="mt-4">
    {{ $productos->links() }}
  </div>
</div>
