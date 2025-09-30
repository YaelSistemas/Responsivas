@extends('layouts.admin')

@section('title', 'Editar Rol')

@section('content')
<style>
    /* ====== Zoom responsivo: MISMA VISTA, SOLO ESCALA EN MÓVIL ====== */
    .zoom-outer{ overflow-x:hidden; }
    .zoom-inner{
        --zoom: .90;                       /* desktop */
        transform: scale(var(--zoom));
        transform-origin: top left;
        width: calc(100% / var(--zoom)); /* compensa el ancho */
    }
    /* Breakpoints (ajusta si quieres) */
    @media (max-width: 1024px){ .zoom-inner{ --zoom:.95; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }  /* tablets landscape */
    @media (max-width: 768px){  .zoom-inner{ --zoom:.90; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }  /* tablets/phones grandes */
    @media (max-width: 640px){  .zoom-inner{ --zoom:.70; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} } /* phones comunes */
    @media (max-width: 400px){  .zoom-inner{ --zoom:.55; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }  /* phones muy chicos */

    /* iOS: evita auto-zoom al enfocar inputs */
    @media (max-width:768px){ input, select, textarea, button { font-size:16px; } }

    /* ====== Estilos existentes ====== */
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

<!-- Envoltura de zoom -->
<div class="zoom-outer">
  <div class="zoom-inner">

    <div class="max-w-xl mx-auto py-6 sm:px-6 lg:px-8">
        <h2 class="text-xl font-semibold text-center mb-6">Editar rol</h2>

        <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="space-y-6">
            @csrf
            @method('PUT')

            {{-- Slug interno (único) --}}
            <div>
                <label class="block mb-1 font-medium">Nombre del rol</label>
                <input type="text" name="name" value="{{ old('name', $role->name) }}" class="w-full border rounded px-3 py-2" required>
                <small class="muted">Debe ser único. Ej: administrador, supervisor.</small>
                @error('name') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
            </div>

            {{-- Nombre público --}}
            <div>
                <label class="block mb-1 font-medium">Nombre a Mostrar</label>
                <input type="text" name="display_name" value="{{ old('display_name', $role->display_name) }}" class="w-full border rounded px-3 py-2">
                @error('display_name') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
            </div>

            {{-- Descripción --}}
            <div>
                <label class="block mb-1 font-medium">Descripción (opcional)</label>
                <textarea name="description" rows="3" class="w-full border rounded px-3 py-2">{{ old('description', $role->description) }}</textarea>
                @error('description') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
            </div>

            {{-- Permisos por grupo --}}
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
                                @case('productos') Productos @break
                                @case('responsivas') Responsivas @break
                                @default {{ ucfirst($groupKey) }}
                            @endswitch
                        </div>

                        <div class="perm-list">
                            @foreach($items as $perm)
                                <label>
                                    <input type="checkbox" name="permissions[]"
                                           value="{{ $perm['name'] }}"
                                           @checked(in_array($perm['name'], old('permissions', $rolePermissions)))>
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
                <button type="submit" class="btn-guardar">Actualizar</button>
            </div>
        </form>
    </div>

  </div>
</div>
@endsection
