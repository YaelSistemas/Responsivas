@php
  $canEdit     = auth()->user()?->can('puestos.edit');
  $canDelete   = auth()->user()?->can('puestos.delete');
  $showActions = $canEdit || $canDelete;
@endphp

<table class="tbl w-full text-sm text-center border-collapse">
  <thead class="bg-gray-100">
    <tr class="text-gray-700 font-semibold">
      <th style="width:260px" class="px-4 py-2 text-left">Nombre</th>
      <th class="px-4 py-2 text-left">Descripción</th>

      {{-- 🔹 Solo visible para el rol Administrador --}}
      @role('Administrador')
        <th style="width:110px" class="px-4 py-2 text-center">Historial</th>
      @endrole

      {{-- 🔹 Solo si tiene permisos de acción --}}
      @if($showActions)
        <th style="width:110px" class="px-4 py-2 text-center">Acciones</th>
      @endif
    </tr>
  </thead>

  <tbody>
    @forelse($puestos as $p)
      <tr class="border-b hover:bg-gray-50 transition-colors duration-100 ease-in">
        {{-- Nombre --}}
        <td class="px-4 py-2 text-left font-medium text-gray-800">{{ $p->nombre }}</td>

        {{-- Descripción --}}
        <td class="px-4 py-2 text-left text-gray-600">{{ $p->descripcion ?: '—' }}</td>

        {{-- 🔹 Historial (solo Administrador) --}}
        @role('Administrador')
          <td class="text-center">
            <button type="button"
                    class="text-blue-600 hover:text-blue-800 font-semibold"
                    onclick="openPuestoHistorial({{ $p->id }})"
                    title="Ver historial">
              Historial
            </button>
          </td>
        @endrole

        {{-- 🔹 Acciones (según permisos) --}}
        @if($showActions)
          <td class="px-4 py-2 text-center">
            <div class="flex items-center justify-center gap-4">
              @can('puestos.edit')
                <a href="{{ route('puestos.edit', $p) }}"
                   class="text-yellow-500 hover:text-yellow-700 text-lg"
                   title="Editar">
                  <i class="fa-solid fa-pen"></i>
                </a>
              @endcan

              @can('puestos.delete')
                <form action="{{ route('puestos.destroy', $p) }}" method="POST"
                      onsubmit="return confirm('¿Eliminar este puesto?');">
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
        <td colspan="{{ (auth()->user()->hasRole('Administrador') ? 3 : 2) + ($showActions ? 1 : 0) }}"
            class="text-center text-gray-500 py-6">
          No hay puestos registrados.
        </td>
      </tr>
    @endforelse
  </tbody>
</table>

<div class="mt-4">
  {{ $puestos->links() }}
</div>
