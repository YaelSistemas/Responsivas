@forelse ($roles as $role)
<tr>
  <td class="mono text-gray-700">{{ $role->name }}</td>
  <td class="font-medium">{{ $role->display_name ?? '—' }}</td>
  <td class="text-gray-600">{{ $role->description ?? '—' }}</td>
  <td>
    @php $perms = $role->permissions->pluck('name'); @endphp
    @if($perms->isEmpty())
      <span class="text-gray-400 text-sm">Sin permisos</span>
    @else
      @foreach($perms as $p)
        <span class="chip">{{ $p }}</span>
      @endforeach
    @endif
  </td>
  <td>
    <div class="flex justify-center items-center gap-4">
      <a href="{{ route('admin.roles.edit', $role) }}" class="text-gray-800 hover:text-gray-900 text-lg" title="Editar">
        <i class="fa-solid fa-pen"></i>
      </a>
      <form action="{{ route('admin.roles.destroy', $role) }}" method="POST"
            onsubmit="return confirm('¿Eliminar este rol?');">
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
  <td colspan="5" class="text-center text-gray-500 py-6">No hay roles.</td>
</tr>
@endforelse
