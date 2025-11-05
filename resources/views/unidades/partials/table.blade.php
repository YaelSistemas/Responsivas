<div id="tabla-unidades">
  @php
    $canEdit   = auth()->user()?->can('unidades.edit');
    $canDelete = auth()->user()?->can('unidades.delete');
    $showActions = $canEdit || $canDelete;
  @endphp

  <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg mx-auto w-full max-w-5xl">
    <table class="table-auto w-full text-sm tbl text-center">
      <thead class="bg-gray-100">
        <tr class="text-center font-semibold text-gray-700">
          <th class="px-4 py-2">Nombre</th>
          <th class="px-4 py-2">Dirección</th>
          <th class="px-4 py-2">Responsable</th>
          <th class="px-4 py-2">Historial</th>
          @if($showActions)
            <th class="px-4 py-2">Acciones</th>
          @endif
        </tr>
      </thead>

      <tbody>
        @forelse ($unidades as $u)
          <tr class="border-b hover:bg-gray-50 transition-colors duration-100 ease-in">
            {{-- Nombre --}}
            <td class="px-4 py-2 font-medium text-gray-800">{{ $u->nombre }}</td>

            {{-- Dirección --}}
            <td class="px-4 py-2 text-gray-600">{{ $u->direccion ?: '—' }}</td>

            {{-- Responsable --}}
            <td class="px-4 py-2 text-gray-700">
              @php
                $resp = $u->responsable ? trim(($u->responsable->nombre ?? '').' '.($u->responsable->apellidos ?? '')) : null;
              @endphp
              {{ $resp ?: '—' }}
            </td>

            {{-- 🔹 Historial --}}
            <td class="text-center">
              <button type="button"
                      class="text-blue-600 hover:text-blue-800 font-semibold"
                      onclick="openUnidadHistorial({{ $u->id }})"
                      title="Ver historial">
                Historial
              </button>
            </td>

            {{-- 🔹 Acciones --}}
            @if($showActions)
              <td class="px-4 py-2">
                <div class="flex justify-center items-center gap-4">
                  @can('unidades.edit')
                    <a href="{{ route('unidades.edit', $u) }}"
                       class="text-yellow-500 hover:text-yellow-700 text-lg"
                       title="Editar">
                      <i class="fa-solid fa-pen"></i>
                    </a>
                  @endcan

                  @can('unidades.delete')
                    <form action="{{ route('unidades.destroy', $u) }}" method="POST"
                          onsubmit="return confirm('¿Eliminar esta unidad?');">
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
            <td colspan="{{ $showActions ? 5 : 4 }}" class="px-4 py-6 text-center text-gray-500">
              No hay unidades de servicio.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- 🔸 Paginación --}}
  <div class="mt-4">
    {{ $unidades->links() }}
  </div>
</div>