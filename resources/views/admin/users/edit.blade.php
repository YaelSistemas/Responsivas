@extends('layouts.admin')

@section('title', 'Editar Usuario')

@section('content')
<style>
    /* ====== Zoom responsivo: MISMA VISTA, SOLO MÁS “PEQUEÑA” EN MÓVIL ====== */
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

    .btn-guardar {
        background: #16a34a;
        color: #fff;
        padding: 8px 16px;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        transition: background .3s;
    }
    .btn-guardar:hover { background: #15803d; }
</style>

<!-- Envoltura de zoom -->
<div class="zoom-outer">
  <div class="zoom-inner">

    <div class="max-w-xl mx-auto py-6 sm:px-6 lg:px-8">
        <h2 class="text-xl font-semibold text-center mb-6">Editar usuario</h2>

        @if ($errors->any())
            <div id="error-alert" class="mb-4" style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:8px;padding:12px;">
                <div style="font-weight:700;margin-bottom:6px;">Se encontraron errores:</div>
                <ul style="margin-left:18px;list-style:disc;">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
            <script>
                setTimeout(() => {
                    const el = document.getElementById('error-alert');
                    if (el) { el.style.opacity = '0'; setTimeout(()=>el.remove(), 400); }
                }, 4000);
            </script>
        @endif

        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-6">
            @csrf
            @method('PUT')

            {{-- Nombre --}}
            <div>
                <label class="block mb-1 font-medium">Nombre</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" class="w-full border rounded px-3 py-2">
                @error('name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Correo --}}
            <div>
                <label class="block mb-1 font-medium">Correo</label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}" class="w-full border rounded px-3 py-2">
                @error('email') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Nueva contraseña --}}
            <div>
                <label class="block mb-1 font-medium">Nueva contraseña <span class="text-gray-500 font-normal">(opcional)</span></label>
                <input type="password" name="password" placeholder="Dejar vacío si no cambia" class="w-full border rounded px-3 py-2">
                @error('password') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Rol --}}
            <div>
                <label class="block mb-1 font-medium">Rol</label>
                @php $current = $user->getRoleNames()->toArray(); @endphp
                <select name="roles[]" class="w-full border rounded px-3 py-2" required>
                    <option value="" disabled {{ old('roles') ? '' : 'selected' }}>-- Selecciona un rol --</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->name }}" @selected(in_array($role->name, old('roles', $current)))>
                            {{ $role->name }}
                        </option>
                    @endforeach
                </select>
                @error('roles') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Empresa --}}
            <div>
                <label class="block mb-1 font-medium">Empresa</label>
                <select name="empresa_id" class="w-full border rounded px-3 py-2">
                    @foreach($empresas as $empresa)
                        <option value="{{ $empresa->id }}" @selected(old('empresa_id', $user->empresa_id) == $empresa->id)>
                            {{ $empresa->nombre }}
                        </option>
                    @endforeach
                </select>
                @error('empresa_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Activo --}}
            <div>
                <label class="inline-flex items-center gap-2">
                    <input type="hidden" name="activo" value="0">
                    <input type="checkbox" id="activo" name="activo" value="1" @checked(old('activo', $user->activo)) class="h-4 w-4">
                    <span>Activo</span>
                </label>
                @error('activo') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Botones --}}
            <div class="flex gap-2 justify-end">
                <a href="{{ route('admin.users.index') }}" class="btn-cancelar">Cancelar</a>
                <button type="submit" class="btn-guardar">Actualizar</button>
            </div>
        </form>
    </div>

  </div>
</div>
@endsection
