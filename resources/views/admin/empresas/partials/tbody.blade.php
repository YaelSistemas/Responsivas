@forelse ($empresas as $e)
  <tr>
    <td>{{ $e->id }}</td>
    <td>{{ $e->nombre }}</td>
    <td>{{ optional($e->created_at)->format('Y-m-d') }}</td>
    <td>
      <div class="flex items-center gap-3">
        <a href="{{ route('admin.empresas.edit', $e) }}" class="text-yellow-600 hover:text-yellow-800" title="Editar">
          <i class="fa-solid fa-pen"></i>
        </a>
        <form action="{{ route('admin.empresas.destroy', $e) }}" method="POST"
              onsubmit="return confirm('¿Eliminar esta empresa?');">
          @csrf @method('DELETE')
          <button type="submit" class="text-red-600 hover:text-red-800" title="Eliminar">
            <i class="fa-solid fa-trash"></i>
          </button>
        </form>
      </div>
    </td>
  </tr>
@empty
  <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">No hay empresas registradas.</td></tr>
@endforelse
