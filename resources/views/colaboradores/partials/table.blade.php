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
                    <th class="px-4 py-2">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($colaboradores as $c)
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-4 py-2">{{ trim($c->nombre . ' ' . $c->apellidos) }}</td>
                        <td class="px-4 py-2">{{ $c->subsidiaria->nombre ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $c->unidadServicio->nombre ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $c->area->nombre ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $c->puesto->nombre ?? '—' }}</td>

                        <td class="px-4 py-2">
                            <div class="flex justify-center items-center gap-4">
                                @can('colaboradores.edit')
                                <a href="{{ route('colaboradores.edit', $c) }}"
                                   class="text-yellow-500 hover:text-yellow-700 text-lg" title="Editar">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                                @endcan

                                @can('colaboradores.delete')
                                <form action="{{ route('colaboradores.destroy', $c) }}" method="POST"
                                    onsubmit="return confirm('¿Eliminar este colaborador?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-lg" title="Eliminar">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">No hay colaboradores.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $colaboradores->withQueryString()->links() }}
    </div>
</div>
