<div id="tabla-unidades">
  <table class="tbl w-full">
    <thead>
      <tr>
        <th style="width:240px">Nombre</th>
        <th >Dirección</th>
        <th style="width:220px">Responsable</th>
        <th style="width:110px">Acciones</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($unidades as $u)
        <tr>
          <td class="font-medium">{{ $u->nombre }}</td>
          <td class="text-gray-600">{{ $u->direccion ?: '—' }}</td>
          <td class="text-gray-700">
            @php
              $resp = $u->responsable ? trim(($u->responsable->nombre ?? '').' '.($u->responsable->apellidos ?? '')) : null;
            @endphp
            {{ $resp ?: '—' }}
          </td>
          <td>
            <div class="flex justify-center items-center gap-4">
              <a href="{{ route('unidades.edit', $u) }}" class="text-gray-800 hover:text-gray-900 text-lg" title="Editar">
                <i class="fa-solid fa-pen"></i>
              </a>
              <form action="{{ route('unidades.destroy', $u) }}" method="POST"
                    onsubmit="return confirm('¿Eliminar esta unidad?');">
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
          <td colspan="4" class="text-center text-gray-500 py-6">No hay unidades de servicio.</td>
        </tr>
      @endforelse
    </tbody>
  </table>

  <div class="mt-4">
    {{ $unidades->links() }}
  </div>
</div>
