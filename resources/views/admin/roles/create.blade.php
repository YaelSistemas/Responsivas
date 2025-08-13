@extends('layouts.admin')

@section('title', 'Crear Rol')

@section('content')
<style>
    .btn-cancelar{
        background:#dc2626;color:#fff;padding:8px 16px;border:none;border-radius:6px;
        font-weight:600;text-decoration:none;text-align:center;transition:background .3s;
    }
    .btn-cancelar:hover{ background:#b91c1c; }
    .btn-guardar{
        background:#16a34a;color:#fff;padding:8px 16px;border:none;border-radius:6px;
        font-weight:600;cursor:pointer;transition:background .3s;
    }
    .btn-guardar:hover{ background:#15803d; }
    .section-title{font-weight:600;margin:.5rem 0 .25rem}
    .perm-list label{display:inline-flex;align-items:center;gap:.5rem;margin:.2rem 0}
    .muted{color:#6b7280;font-size:.85rem}
</style>

<div class="max-w-xl mx-auto py-6 sm:px-6 lg:px-8">
    <h2 class="text-xl font-semibold text-center mb-6">Crear rol</h2>

    <form method="POST" action="{{ route('admin.roles.store') }}" class="space-y-6">
        @csrf

        {{-- Slug interno (único) --}}
        <div>
            <label class="block mb-1 font-medium">Nombre del rol</label>
            <input type="text" name="name" value="{{ old('name') }}" class="w-full border rounded px-3 py-2" required>
            <small class="muted">Ej: Administrador, Supervisor, etc. Debe ser único.</small>
            @error('name') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
        </div>

        {{-- Nombre público --}}
        <div>
            <label class="block mb-1 font-medium">Nombre a Mostrar</label>
            <input type="text" name="display_name" value="{{ old('display_name') }}" class="w-full border rounded px-3 py-2">
            @error('display_name') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
        </div>

        {{-- Descripción --}}
        <div>
            <label class="block mb-1 font-medium">Descripción (opcional)</label>
            <textarea name="description" rows="3" class="w-full border rounded px-3 py-2">{{ old('description') }}</textarea>
            @error('description') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
        </div>

        {{-- Permisos (mismo estilo, varias secciones) --}}
        <div>
            @foreach($groups as $groupKey => $items)
                <div class="mt-4">
                    <div class="section-title">
                        Permisos: 
                        @switch($groupKey)
                            @case('colaboradores') Colaboradores @break
                            @case('unidades') Unidades de servicio @break
                            @case('areas') Áreas @break
                            @case('puestos') Puestos @break
                            @case('subsidiarias') Subsidiarias @break
                            @default {{ ucfirst($groupKey) }}
                        @endswitch
                    </div>

                    <div class="perm-list">
                        @foreach($items as $perm)
                            <label>
                                <input type="checkbox" name="permissions[]"
                                       value="{{ $perm['name'] }}"
                                       {{ in_array($perm['name'], old('permissions', [])) ? 'checked' : '' }}>
                                <span>{{ $perm['label'] }}</span>
                            </label><br>
                        @endforeach
                    </div>
                </div>
            @endforeach

            @error('permissions') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
            @error('permissions.*') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
        </div>

        <div class="flex gap-2 justify-end">
            <a href="{{ route('admin.roles.index') }}" class="btn-cancelar">Cancelar</a>
            <button type="submit" class="btn-guardar">Guardar</button>
        </div>
    </form>
</div>
@endsection
