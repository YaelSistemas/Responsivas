<x-app-layout title="Editar producto">
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight text-center">Editar producto</h2>
  </x-slot>

  <style>
    /* ====== Zoom responsivo: misma UI, solo se “reduce” en pantallas chicas ====== */
    .zoom-outer{ overflow-x:hidden; }
    .zoom-inner{
      --zoom: 1;
      transform: scale(var(--zoom));
      transform-origin: top left;
      width: calc(100% / var(--zoom));
    }
    @media (max-width: 1024px){ .zoom-inner{ --zoom:.95; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }
    @media (max-width: 768px){  .zoom-inner{ --zoom:.90; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }
    @media (max-width: 640px){  .zoom-inner{ --zoom:.70; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }
    @media (max-width: 400px){  .zoom-inner{ --zoom:.55; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }

    @media (max-width: 768px){
      input, select, textarea{ font-size:16px; }
    }

    .form-container{max-width:700px;margin:0 auto;background:#fff;padding:24px;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,.1)}
    .form-group{margin-bottom:16px}
    .form-container input,.form-container select,.form-container textarea{width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;font-size:14px}
    .hint{font-size:12px;color:#6b7280;margin-top:6px}
    .err{color:#dc2626;font-size:12px;margin-top:6px}
    .form-buttons{display:flex;justify-content:space-between;padding-top:20px}
    .btn-cancel{background:#dc2626;color:#fff;padding:8px 16px;border:none;border-radius:6px;font-weight:600;text-decoration:none}
    .btn-cancel:hover{background:#b91c1c}
    .btn-save{background:#16a34a;color:#fff;padding:8px 16px;border:none;border-radius:6px;font-weight:600;cursor:pointer}
    .btn-save:hover{background:#15803d}

    /* ===== Toggle Switch ===== */
    .switch {position: relative;display: inline-block;width: 50px;height: 26px;}
    .switch input {opacity: 0;width: 0;height: 0;}
    .slider {position: absolute;cursor: pointer;top: 0; left: 0; right: 0; bottom: 0;background-color: #d1d5db;transition: .3s;border-radius: 26px;}
    .slider:before {position: absolute;content: "";height: 20px; width: 20px;left: 3px; bottom: 3px;background-color: white;transition: .3s;border-radius: 50%;}
    input:checked + .slider {background-color: #16a34a;}
    input:checked + .slider:before {transform: translateX(24px);}
  </style>

  <div class="zoom-outer">
    <div class="zoom-inner">
      <div class="py-6">
        <div class="form-container">
          @if ($errors->any())
            <div style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:8px;padding:12px;margin-bottom:14px;">
              <div style="font-weight:700;margin-bottom:6px;">Se encontraron errores:</div>
              <ul style="margin-left:18px;list-style:disc;">
                @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
              </ul>
            </div>
          @endif

          <form method="POST" action="{{ route('productos.update', $producto) }}">
            @csrf
            @method('PUT')

            {{-- Nombre --}}
            <div class="form-group">
              <label>Nombre <span class="hint">(requerido)</span></label>
              <input name="nombre" value="{{ old('nombre', $producto->nombre) }}" required autofocus maxlength="255">
              @error('nombre') <div class="err">{{ $message }}</div> @enderror
            </div>

            {{-- Marca --}}
            <div class="form-group">
              <label>Marca</label>
              <input name="marca" value="{{ old('marca', $producto->marca) }}" maxlength="100">
              @error('marca') <div class="err">{{ $message }}</div> @enderror
            </div>

            {{-- Modelo --}}
            <div class="form-group">
              <label>Modelo</label>
              <input name="modelo" value="{{ old('modelo', $producto->modelo) }}" maxlength="100">
              @error('modelo') <div class="err">{{ $message }}</div> @enderror
            </div>

            {{-- Activo (toggle switch) --}}
            <div class="form-group">
              <label for="activo" style="display:block;margin-bottom:6px;">Activo</label>

              {{-- ✅ Para que al desmarcar sí se mande 0 --}}
              <input type="hidden" name="activo" value="0">

              <label class="switch">
                <input type="checkbox" name="activo" id="activo" value="1" {{ old('activo', $producto->activo) ? 'checked' : '' }}>
                <span class="slider"></span>
              </label>
            </div>

            <div class="form-buttons">
              <a href="{{ route('productos.index') }}" class="btn-cancel">Cancelar</a>
              <button class="btn-save" type="submit">Actualizar</button>
            </div>
          </form>

        </div>
      </div>
    </div>
  </div>
</x-app-layout>
