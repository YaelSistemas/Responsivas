@php
  $canEdit     = auth()->user()?->can('areas.edit');
  $canDelete   = auth()->user()?->can('areas.delete');
  $showActions = $canEdit || $canDelete;
@endphp

<table class="tbl">
  <thead>
    <tr>
      <th style="width:260px">Nombre</th>
      <th>Descripción</th>
      @role('Administrador')
        <th style="width:110px">Historial</th>
      @endrole
      @if($showActions)
        <th style="width:110px">Acciones</th>
      @endif
    </tr>
  </thead>
  <tbody>
    @forelse ($areas as $area)
      <tr>
        {{-- Nombre --}}
        <td class="font-medium">{{ $area->nombre }}</td>

        {{-- Descripción --}}
        <td class="text-gray-600">{{ $area->descripcion ?: '—' }}</td>

        {{-- 🔹 Historial (solo Administrador) --}}
        @role('Administrador')
          <td class="text-center">
            <button type="button"
                    class="text-blue-600 hover:text-blue-800 font-semibold"
                    onclick="openAreaHistorial({{ $area->id }})"
                    title="Ver historial">
              Historial
            </button>
          </td>
        @endrole

        {{-- 🔹 Acciones (solo si tiene permisos) --}}
        @if($showActions)
          <td>
            <div class="flex justify-center items-center gap-4">
              @can('areas.edit')
                <a href="{{ route('areas.edit', $area) }}" 
                   class="text-gray-800 hover:text-gray-900 text-lg" 
                   title="Editar">
                  <i class="fa-solid fa-pen"></i>
                </a>
              @endcan

              @can('areas.delete')
                <form action="{{ route('areas.destroy', $area) }}" method="POST"
                      onsubmit="return confirm('¿Eliminar esta área?');">
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
        <td colspan="{{ (auth()->user()->hasRole('Administrador') ? 3 : 2) + ($showActions ? 1 : 0) }}"
            class="text-center text-gray-500 py-6">
          No hay áreas registradas.
        </td>
      </tr>
    @endforelse
  </tbody>
</table>

<div class="mt-4">
  {{ $areas->links() }}
</div>
