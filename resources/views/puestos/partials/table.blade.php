@php
  $canEdit     = auth()->user()?->can('puestos.edit');
  $canDelete   = auth()->user()?->can('puestos.delete');
  $showActions = $canEdit || $canDelete;
@endphp

<table class="tbl">
  <thead>
    <tr>
      <th style="width:260px">Nombre</th>
      <th>Descripción</th>
      <th style="width:110px">Historial</th>
      @if($showActions)
        <th style="width:110px">Acciones</th>
      @endif
    </tr>
  </thead>
  <tbody>
    @forelse($puestos as $p)
      <tr>
        {{-- Nombre --}}
        <td class="font-medium">{{ $p->nombre }}</td>

        {{-- Descripción --}}
        <td class="text-gray-600">{{ $p->descripcion ?: '—' }}</td>

        {{-- 🔹 Historial --}}
        <td class="text-center">
          <button type="button"
                  class="text-blue-600 hover:text-blue-800 font-semibold"
                  onclick="openPuestoHistorial({{ $p->id }})"
                  title="Ver historial">
            Historial
          </button>
        </td>

        {{-- 🔹 Acciones --}}
        @if($showActions)
          <td>
            <div class="flex items-center justify-center gap-4">
              @can('puestos.edit')
                <a href="{{ route('puestos.edit', $p) }}"
                   class="text-gray-800 hover:text-gray-900 text-lg"
                   title="Editar">
                  <i class="fa-solid fa-pen"></i>
                </a>
              @endcan

              @can('puestos.delete')
                <form action="{{ route('puestos.destroy', $p) }}" method="POST"
                      onsubmit="return confirm('¿Eliminar este puesto?');">
                  @csrf @method('DELETE')
                  <button type="submit"
                          class="text-red-600 hover:text-red-800 text-lg"
                          title="Eliminar">
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </form>
              @endcan
            </div>
          </td>
        @endif
      </tr>
    @empty
      <tr>
        <td colspan="{{ $showActions ? 4 : 3 }}" class="text-center text-gray-500 py-6">
          No hay puestos registrados.
        </td>
      </tr>
    @endforelse
  </tbody>
</table>

<div class="mt-4">
  {{ $puestos->links() }}
</div>
