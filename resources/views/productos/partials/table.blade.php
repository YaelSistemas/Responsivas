@php
  $tipoLabels = [
    'equipo_pc'  => 'Equipo de Cómputo',
    'impresora'  => 'Impresora/Multifuncional',
    'celular'    => 'Celular/Teléfono',
    'monitor'    => 'Monitor',
    'pantalla'   => 'Pantalla/TV',
    'periferico' => 'Periférico',
    'consumible' => 'Consumible',
    'otro'       => 'Otro',
  ];
@endphp

<div id="tabla-productos">
  <style>
    #tabla-productos .tbl th,
    #tabla-productos .tbl td { text-align:center; vertical-align:middle; }
    #tabla-productos .tbl td.col-producto { text-align:left; }

    /* chips series/SKU */
    .chips { display:flex; flex-wrap:wrap; gap:.35rem; justify-content:center }
    .chip {
      display:inline-block; font-size:.72rem; padding:.18rem .55rem; border-radius:9999px;
      background:#f3f4f6; border:1px solid #e5e7eb; color:#374151; white-space:nowrap;
    }
    .chip--asignado { background:#dcfce7; border-color:#bbf7d0; color:#166534; } /* verde */
    .chip--prestamo { background:#fef3c7; border-color:#fde68a; color:#92400e; } /* amarillo */

    .btn-historial {
      background:#f3f4f6; border:1px solid #d1d5db; border-radius:8px;
      padding:.25rem .65rem; font-size:.8rem; color:#1e3a8a; font-weight:500;
      transition: all .2s ease;
    }
    .btn-historial:hover { background:#e0e7ff; border-color:#a5b4fc; color:#1e40af; }
  </style>

  <table class="tbl w-full">
    <thead>
      <tr>
        <th style="width:380px">Producto</th>
        <th style="width:180px">Tipo</th>
        <th style="width:220px">Series / SKU</th>
        <th style="width:160px">Stock</th>
        <th style="width:150px">Series / Existencia</th>
        <th style="width:130px">Historial</th> {{-- ✅ Nueva columna --}}
        <th style="width:120px">Acciones</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($productos as $p)
        <tr>
          {{-- Producto + Marca + Modelo + SKU + Descripción --}}
          <td class="font-medium col-producto">
            <div>
              {{ $p->nombre }}
              @if($p->marca || $p->modelo)
                <span class="text-gray-600 text-sm">
                  — {{ trim($p->marca . ' ' . $p->modelo) }}
                </span>
              @endif
            </div>

            @if($p->sku)
              @php
                $color = strtolower(trim($p->especificaciones['color'] ?? ''));
                $colorHex = match (true) {
                  str_contains($color, 'magenta') => '#ff1dce',
                  str_contains($color, 'cian')    => '#06b6d4',
                  str_contains($color, 'yellow'),
                  str_contains($color, 'amarillo')=> '#ca8a04',
                  str_contains($color, 'black'),
                  str_contains($color, 'negro')   => '#000000',
                  default                         => '#6b7280',
                };
              @endphp
              <div class="text-xs" style="color: {{ $colorHex }}">
                SKU: {{ $p->sku }}
              </div>
            @endif

            @if($p->descripcion)
              <div class="text-xs text-gray-600 mt-1">{{ Str::limit($p->descripcion, 120) }}</div>
            @endif
          </td>

          {{-- Tipo / Tracking --}}
          <td class="text-sm">
            <div class="font-medium">{{ $tipoLabels[$p->tipo] ?? ucfirst($p->tipo ?? '') }}</div>
            <div class="text-xs text-gray-500">
              {{ $p->tracking === 'serial' ? 'Por número de serie' : 'Por cantidad' }}
            </div>
          </td>

          {{-- Series / SKU (coloreado) --}}
          <td>
            @if($p->tracking === 'serial')
              @php
                $series = ($seriesByProduct[$p->id] ?? collect());
              @endphp
              <div class="chips">
                @forelse($series as $s)
                  @php
                    $motivo = optional($s->responsivaAsignada)->motivo_entrega;
                    $cls = '';
                    if ($s->estado === 'asignado') {
                      $cls = ($motivo === 'prestamo_provisional') ? 'chip--prestamo' : 'chip--asignado';
                    }
                    $title = 'Estado: '.ucfirst($s->estado);
                    if ($motivo === 'prestamo_provisional') $title .= ' — Préstamo provisional';
                  @endphp
                  <span class="chip {{ $cls }}" title="{{ $title }}">{{ $s->serie }}</span>
                @empty
                  <span class="text-xs text-gray-400">Sin series</span>
                @endforelse
              </div>
            @else
              <div class="chips">
                <span class="chip">{{ $p->sku ?: '—' }}</span>
              </div>
            @endif
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

          {{-- Series/Existencia links --}}
          <td>
            @can('productos.view')
              @if($p->tracking === 'serial')
                <a href="{{ route('productos.series', $p) }}" class="text-indigo-600 hover:underline">Ver series</a>
              @else
                <a href="{{ route('productos.existencia', $p) }}" class="text-indigo-600 hover:underline">Ver existencia</a>
              @endif
            @endcan
          </td>

          {{-- Historial --}}
          <td>
            @can('productos.view')
              <button type="button"
                      class="text-blue-600 hover:text-blue-800 font-semibold"
                      onclick="openProductoHistorial('{{ $p->id }}')"
                      title="Ver historial">
                Historial
              </button>
            @endcan
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
          <td colspan="8" class="text-center text-gray-500 py-6">No hay productos.</td>
        </tr>
      @endforelse
    </tbody>
  </table>

  <div class="mt-4">
    {{ $productos->links() }}
  </div>
</div>