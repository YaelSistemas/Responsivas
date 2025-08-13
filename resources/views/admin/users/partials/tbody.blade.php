@forelse($users as $user)
<tr>
  <td>{{ $user->name }}</td>
  <td class="mono">{{ $user->email }}</td>
  <td>{{ $user->roles->pluck('name')->join(', ') ?: '—' }}</td>
  <td>{{ $user->empresa->nombre ?? '—' }}</td>
  <td>
    @if($user->activo)
      <span class="chip">Activo</span>
    @else
      —
    @endif
  </td>
  <td>
    <div class="flex justify-center items-center gap-4">
      {{-- Botón Editar --}}
      <a href="{{ route('admin.users.edit', $user) }}" 
         class="text-gray-800 hover:text-gray-900 text-lg" 
         title="Editar">
        <i class="fa-solid fa-pen"></i>
      </a>

      {{-- Botón Eliminar (evita borrarse a sí mismo) --}}
      @if(auth()->id() !== $user->id)
        <form action="{{ route('admin.users.destroy', $user) }}" method="POST"
              onsubmit="return confirm('¿Eliminar este usuario?');">
          @csrf 
          @method('DELETE')
          <button type="submit" 
                  class="text-red-600 hover:text-red-800 text-lg" 
                  title="Eliminar">
            <i class="fa-solid fa-trash"></i>
          </button>
        </form>
      @endif
    </div>
  </td>
</tr>
@empty
<tr>
  <td colspan="6" class="text-center text-gray-500 py-6">
    No se encontraron usuarios.
  </td>
</tr>
@endforelse
