<table class="tbl">
  <thead>
    <tr>
      <th style="width:260px">Nombre</th>
      <th>Descripción</th>
      <th style="width:110px">Acciones</th>
    </tr>
  </thead>
  <tbody>
    @forelse($puestos as $p)
      <tr>
        <td class="font-medium">{{ $p->nombre }}</td>
        <td class="text-gray-600">{{ $p->descripcion ?: '—' }}</td>
        <td>
          <div class="flex items-center gap-4">
            <a href="{{ route('puestos.edit', $p) }}" class="text-gray-800 hover:text-gray-900 text-lg" title="Editar">
              <i class="fa-solid fa-pen"></i>
            </a>
            <form action="{{ route('puestos.destroy', $p) }}" method="POST"
                  onsubmit="return confirm('¿Eliminar este puesto?');">
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
        <td colspan="3" class="text-center text-gray-500 py-6">No hay puestos.</td>
      </tr>
    @endforelse
  </tbody>
</table>

<div class="p-3">
  {{ $puestos->links() }}
</div>
