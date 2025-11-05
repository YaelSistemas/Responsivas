<div id="tabla-colaboradores">
  <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg mx-auto w-full max-w-5xl">
    <table class="table-auto w-full text-sm tbl text-center">
      <thead class="bg-gray-100">
        <tr class="text-center font-semibold text-gray-700">
          <th class="px-4 py-2">Colaborador</th>
          <th class="px-4 py-2">Empresa</th>
          <th class="px-4 py-2">Unidad Servicio</th>
          <th class="px-4 py-2">Área</th>
          <th class="px-4 py-2">Puesto</th>
          <th class="px-4 py-2">Historial</th> {{-- 🔹 Columna igual a OC --}}
          <th class="px-4 py-2">Acciones</th>
        </tr>
      </thead>

      <tbody>
        @forelse ($colaboradores as $c)
          <tr class="border-b hover:bg-gray-50 transition-colors duration-100 ease-in">
            {{-- Colaborador --}}
            <td class="px-4 py-2 text-left font-medium text-gray-800">
              {{ trim($c->nombre . ' ' . $c->apellidos) }}
            </td>

            {{-- Empresa / Unidad / Área / Puesto --}}
            <td class="px-4 py-2">{{ $c->subsidiaria->nombre ?? '—' }}</td>
            <td class="px-4 py-2">{{ $c->unidadServicio->nombre ?? '—' }}</td>
            <td class="px-4 py-2">{{ $c->area->nombre ?? '—' }}</td>
            <td class="px-4 py-2">{{ $c->puesto->nombre ?? '—' }}</td>

            {{-- 🔹 Historial --}}
            <td class="text-center">
                <button type="button"
                        class="btn btn-sm"
                        onclick="openColabHistorial({{ $c->id }})"
                        title="Ver historial">
                    Historial
                </button>
            </td>

            {{-- 🔹 Acciones --}}
            <td class="px-4 py-2">
              <div class="flex justify-center items-center gap-4">
                @can('colaboradores.edit')
                  <a href="{{ route('colaboradores.edit', $c) }}"
                     class="text-yellow-500 hover:text-yellow-700 text-lg"
                     title="Editar">
                    <i class="fa-solid fa-pen"></i>
                  </a>
                @endcan

                @can('colaboradores.delete')
                  <form action="{{ route('colaboradores.destroy', $c) }}"
                        method="POST"
                        onsubmit="return confirm('¿Eliminar este colaborador?');">
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
          </tr>
        @empty
          <tr>
            <td colspan="7" class="px-4 py-6 text-center text-gray-500">
              No hay colaboradores.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- Paginación --}}
  <div class="mt-4">
    {{ $colaboradores->withQueryString()->links() }}
  </div>
</div>
