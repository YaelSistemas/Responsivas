@php
  $canEdit     = auth()->user()?->can('subsidiarias.edit');
  $canDelete   = auth()->user()?->can('subsidiarias.delete');
  $showActions = $canEdit || $canDelete;
  $isAdmin     = auth()->user()?->hasRole('Administrador');
@endphp

<table class="tbl w-full text-sm text-center border-collapse">
  <thead class="bg-gray-100">
    <tr class="text-gray-700 font-semibold">
      <th style="width:260px" class="px-4 py-2 text-left">Nombre</th>
      <th class="px-4 py-2 text-left">Razón social</th>

      {{-- 🔹 Mostrar la columna de historial solo si el usuario es Administrador --}}
      @if($isAdmin)
        <th style="width:110px" class="px-4 py-2 text-center">Historial</th>
      @endif

      {{-- 🔹 Mostrar acciones solo si tiene permisos --}}
      @if($showActions)
        <th style="width:110px" class="px-4 py-2 text-center">Acciones</th>
      @endif
    </tr>
  </thead>

  <tbody>
    @forelse($subsidiarias as $s)
      <tr class="border-b hover:bg-gray-50 transition-colors duration-100 ease-in">
        {{-- Nombre --}}
        <td class="px-4 py-2 text-left font-medium text-gray-800">{{ $s->nombre }}</td>

        {{-- Razón social o descripción --}}
        <td class="px-4 py-2 text-left text-gray-600">{{ $s->razon_social ?? $s->descripcion ?? '—' }}</td>

        {{-- 🔹 Historial (solo Administrador) --}}
        @if($isAdmin)
          <td class="text-center">
            <button type="button"
                    class="text-blue-600 hover:text-blue-800 font-semibold"
                    onclick="openSubsidiariaHistorial({{ $s->id }})"
                    title="Ver historial">
              Historial
            </button>
          </td>
        @endif

        {{-- 🔹 Acciones (según permisos) --}}
        @if($showActions)
          <td class="px-4 py-2 text-center">
            <div class="flex items-center justify-center gap-4">
              @can('subsidiarias.edit')
                <a href="{{ route('subsidiarias.edit', $s) }}"
                   class="text-yellow-500 hover:text-yellow-700 text-lg"
                   title="Editar">
                  <i class="fa-solid fa-pen"></i>
                </a>
              @endcan

              @can('subsidiarias.delete')
                <form action="{{ route('subsidiarias.destroy', $s) }}" method="POST"
                      onsubmit="return confirm('¿Eliminar esta subsidiaria?');">
                  @csrf
                  @method('DELETE')
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
        {{-- 🔹 colspan dinámico según rol y permisos --}}
        <td colspan="{{ ($isAdmin ? 3 : 2) + ($showActions ? 1 : 0) }}"
            class="text-center text-gray-500 py-6">
          No hay subsidiarias registradas.
        </td>
      </tr>
    @endforelse
  </tbody>
</table>

<div class="p-3">
  {{ $subsidiarias->links() }}
</div>