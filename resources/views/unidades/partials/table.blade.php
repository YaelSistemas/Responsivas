<div id="tabla-unidades">
  @php
    $canEdit   = auth()->user()?->can('unidades.edit');
    $canDelete = auth()->user()?->can('unidades.delete');
    $showActions = $canEdit || $canDelete;
  @endphp

  <table class="tbl w-full">
    <thead>
      <tr>
        <th style="width:260px">Nombre</th>
        <th>Dirección</th>
        <th>Responsable</th>
        @role('Administrador')
          <th style="width:110px">Historial</th>
        @endrole
        @if($showActions)
          <th style="width:110px">Acciones</th>
        @endif
      </tr>
    </thead>
    <tbody>
      @forelse ($unidades as $u)
        <tr>
          {{-- Nombre --}}
          <td class="font-medium">{{ $u->nombre }}</td>

          {{-- Dirección --}}
          <td class="text-gray-600">{{ $u->direccion ?: '—' }}</td>

          {{-- Responsable --}}
          <td class="text-gray-700">
            @php
              $resp = $u->responsable ? trim(($u->responsable->nombre ?? '').' '.($u->responsable->apellidos ?? '')) : null;
            @endphp
            {{ $resp ?: '—' }}
          </td>

          {{-- 🔹 Historial (solo Administrador) --}}
          @role('Administrador')
            <td class="text-center">
              <button type="button"
                      class="text-blue-600 hover:text-blue-800 font-semibold"
                      onclick="openUnidadHistorial({{ $u->id }})"
                      title="Ver historial">
                Historial
              </button>
            </td>
          @endrole

          {{-- 🔹 Acciones (si tiene permisos) --}}
          @if($showActions)
            <td>
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
          <td colspan="{{ (auth()->user()->hasRole('Administrador') ? 4 : 3) + ($showActions ? 1 : 0) }}"
              class="text-center text-gray-500 py-6">
            No hay unidades de servicio.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>

  <div class="mt-4">
    {{ $unidades->links() }}
  </div>
</div>
