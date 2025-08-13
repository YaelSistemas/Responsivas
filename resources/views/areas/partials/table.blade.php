<table class="tbl">
  <thead>
    <tr>
      <th style="width:260px">Nombre</th>
      <th>Descripción</th>
      <th style="width:110px">Acciones</th>
    </tr>
  </thead>
  <tbody>
    @forelse ($areas as $area)
      <tr>
        <td class="font-medium">{{ $area->nombre }}</td>
        <td class="text-gray-600">{{ $area->descripcion ?: '—' }}</td>
        <td>
          <div class="flex justify-center items-center gap-4">
            <a href="{{ route('areas.edit', $area) }}" class="text-gray-800 hover:text-gray-900 text-lg" title="Editar">
              <i class="fa-solid fa-pen"></i>
            </a>
            <form action="{{ route('areas.destroy', $area) }}" method="POST"
                  onsubmit="return confirm('¿Eliminar esta área?');">
              @csrf @method('DELETE')
              <button type="submit" class="text-red-600 hover:text-red-800 text-lg" title="Eliminar">
                <i class="fa-solid fa-trash"></i>
              </button>
            </form>
          </div>
        </td>
      </tr>
    @empty
      <tr>
        <td colspan="3" class="text-center text-gray-500 py-6">No hay áreas.</td>
      </tr>
    @endforelse
  </tbody>
</table>

<div class="mt-4">
  {{ $areas->links() }}
</div>
