@php
  $canEdit     = auth()->user()?->can('subsidiarias.edit');
  $canDelete   = auth()->user()?->can('subsidiarias.delete');
  $showActions = $canEdit || $canDelete;
@endphp

<table class="tbl">
  <thead>
    <tr>
      <th style="width:260px">Nombre</th>
      <th>Razón social</th>
      <th style="width:110px">Historial</th>
      @if($showActions)
        <th style="width:110px">Acciones</th>
      @endif
    </tr>
  </thead>
  <tbody>
    @forelse($subsidiarias as $s)
      <tr>
        {{-- Nombre --}}
        <td class="font-medium">{{ $s->nombre }}</td>

        {{-- Razón social o descripción --}}
        <td class="text-gray-600">{{ $s->razon_social ?? $s->descripcion ?? '—' }}</td>

        {{-- 🔹 Historial --}}
        <td class="text-center">
          <button type="button"
                  class="text-blue-600 hover:text-blue-800 font-semibold"
                  onclick="openSubsidiariaHistorial({{ $s->id }})"
                  title="Ver historial">
            Historial
          </button>
        </td>

        {{-- 🔹 Acciones --}}
        @if($showActions)
          <td>
            <div class="flex items-center justify-center gap-4">
              @can('subsidiarias.edit')
                <a href="{{ route('subsidiarias.edit', $s) }}"
                   class="text-gray-800 hover:text-gray-900 text-lg"
                   title="Editar">
                  <i class="fa-solid fa-pen"></i>
                </a>
              @endcan

              @can('subsidiarias.delete')
                <form action="{{ route('subsidiarias.destroy', $s) }}" method="POST"
                      onsubmit="return confirm('¿Eliminar esta subsidiaria?');">
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
          No hay subsidiarias registradas.
        </td>
      </tr>
    @endforelse
  </tbody>
</table>

<div class="p-3">
  {{ $subsidiarias->links() }}
</div>
