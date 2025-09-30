@extends('layouts.admin')

@section('title', 'Crear Usuario')

@section('content')
<style>
    /* ====== Zoom responsivo: MISMA VISTA, SOLO ESCALA EN MÓVIL ====== */
    .zoom-outer{ overflow-x:hidden; }
    .zoom-inner{
      --zoom: 1;
      transform: scale(var(--zoom));
      transform-origin: top left;
      width: calc(100% / var(--zoom));
    }
    /* Breakpoints (ajusta si quieres) */
    @media (max-width: 1024px){ .zoom-inner{ --zoom:.95; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }  /* tablets landscape */
    @media (max-width: 768px){  .zoom-inner{ --zoom:.90; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }  /* tablets/phones grandes */
    @media (max-width: 640px){  .zoom-inner{ --zoom:.70; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} } /* phones comunes */
    @media (max-width: 400px){  .zoom-inner{ --zoom:.55; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }  /* phones muy chicos */
    
    /* iOS: evita auto-zoom al enfocar inputs */
    @media (max-width:768px){ input, select, textarea, button { font-size:16px; } }

    /* ====== Estilos existentes ====== */
    .btn-cancelar {
        background: #dc2626;
        color: #fff;
        padding: 8px 16px;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        text-decoration: none;
        text-align: center;
        transition: background .3s;
    }
    .btn-cancelar:hover { background: #b91c1c; }

    .btn-crear {
        background: #16a34a;
        color: #fff;
        padding: 8px 16px;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        transition: background .3s;
    }
    .btn-crear:hover { background: #15803d; }
</style>

<!-- Envoltura de zoom -->
<div class="zoom-outer">
  <div class="zoom-inner">

    <div class="max-w-xl mx-auto py-6 sm:px-6 lg:px-8">
        <h2 class="text-xl font-semibold text-center mb-6">Crear usuario</h2>

        <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-6">
            @csrf

            {{-- Nombre --}}
            <div>
                <label class="block mb-1 font-medium">Nombre</label>
                <input
                    type="text"
                    name="name"
                    value="{{ old('name') }}"
                    class="w-full border rounded px-3 py-2"
                    required
                >
                @error('name')
                    <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                @enderror
            </div>

            {{-- Correo --}}
            <div>
                <label class="block mb-1 font-medium">Correo</label>
                <input
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    class="w-full border rounded px-3 py-2"
                    required
                >
                @error('email')
                    <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                @enderror
            </div>

            {{-- Contraseña --}}
            <div>
                <label class="block mb-1 font-medium">Contraseña</label>
                <input
                    type="password"
                    name="password"
                    class="w-full border rounded px-3 py-2"
                    required
                >
                @error('password')
                    <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                @enderror
            </div>

            {{-- Rol --}}
            <div>
                <label class="block mb-1 font-medium">Rol</label>
                <select name="roles[]" class="w-full border rounded px-3 py-2" required>
                    <option value="" disabled {{ old('roles') ? '' : 'selected' }}>-- Selecciona un rol --</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->name }}" {{ in_array($role->name, old('roles', [])) ? 'selected' : '' }}>
                            {{ $role->name }}
                        </option>
                    @endforeach
                </select>
                @error('roles')
                    <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                @enderror
            </div>

            {{-- Empresa --}}
            <div>
                <label class="block mb-1 font-medium">Empresa</label>
                <select name="empresa_id" class="w-full border rounded px-3 py-2" required>
                    <option value="" disabled {{ old('empresa_id') ? '' : 'selected' }}>-- Selecciona --</option>
                    @foreach($empresas as $empresa)
                        <option value="{{ $empresa->id }}" {{ old('empresa_id') == $empresa->id ? 'selected' : '' }}>
                            {{ $empresa->nombre }}
                        </option>
                    @endforeach
                </select>
                @error('empresa_id')
                    <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                @enderror
            </div>

            {{-- Activo --}}
            <div>
                <label class="inline-flex items-center gap-2">
                    <input type="hidden" name="activo" value="0">
                    <input
                        type="checkbox"
                        id="activo"
                        name="activo"
                        value="1"
                        {{ old('activo', 1) ? 'checked' : '' }}
                        class="h-4 w-4"
                    >
                    <span>Activo</span>
                </label>
                @error('activo')
                    <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                @enderror
            </div>

            {{-- Botones --}}
            <div class="flex gap-2 justify-end">
                <a href="{{ route('admin.users.index') }}" class="btn-cancelar">Cancelar</a>
                <button type="submit" class="btn-crear">Crear</button>
            </div>
        </form>
    </div>

  </div>
</div>
@endsection
