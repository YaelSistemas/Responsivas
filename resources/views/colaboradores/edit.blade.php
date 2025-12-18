<x-app-layout title="Editar Colaborador">
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight text-center">Editar colaborador</h2>
  </x-slot>

  <style>
    /* ====== Zoom responsivo ====== */
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

    /* ====== Estilos propios ====== */
    .page-wrap{max-width:1100px;margin:0 auto}
    .form-container{max-width:700px;margin:0 auto;background:#fff;padding:24px;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,.1)}
    .form-group{margin-bottom:16px}
    .form-container label{display:block;margin-bottom:6px;color:#374151;font-weight:600}
    .form-container input,
    .form-container select{width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;font-size:14px}
    .form-container input:focus,
    .form-container select:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.12)}
    .form-buttons{display:flex;gap:12px;justify-content:space-between;padding-top:20px}
    .btn-cancel{background:#dc2626;color:#fff;padding:8px 16px;border:none;border-radius:6px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}
    .btn-cancel:hover{background:#b91c1c}
    .btn-save{background:#16a34a;color:#fff;padding:8px 16px;border:none;border-radius:6px;font-weight:600;cursor:pointer}
    .btn-save:hover{background:#15803d}
    .hint{font-size:12px;color:#6b7280;margin-top:6px}

    @media (max-width: 480px){
      .form-buttons{flex-direction:column-reverse;align-items:stretch}
      .btn-cancel,.btn-save{width:100%}
    }

    /* === Toggle Switch === */
    .switch {
      position: relative;
      display: inline-block;
      width: 48px;
      height: 26px;
    }
    .switch input {
      opacity: 0;
      width: 0;
      height: 0;
    }
    .slider {
      position: absolute;
      cursor: pointer;
      top: 0; left: 0; right: 0; bottom: 0;
      background-color: #e5e7eb;
      border-radius: 26px;
      transition: .3s;
    }
    .slider:before {
      position: absolute;
      content: "";
      height: 20px; width: 20px;
      left: 3px; bottom: 3px;
      background-color: white;
      border-radius: 50%;
      transition: .3s;
    }
    input:checked + .slider {
      background-color: #16a34a;
    }
    input:checked + .slider:before {
      transform: translateX(22px);
    }
  </style>

  <div class="zoom-outer">
    <div class="zoom-inner">
      <div class="page-wrap py-6">
        <div class="form-container">
          @if ($errors->any())
            <div style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:8px;padding:12px;margin-bottom:14px;">
              <div style="font-weight:700;margin-bottom:6px;">Se encontraron errores:</div>
              <ul style="margin-left:18px;list-style:disc;">
                @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
              </ul>
            </div>
          @endif

          <form method="POST" action="{{ route('colaboradores.update', $colaborador) }}">
            @csrf
            @method('PUT')

            <div class="form-group">
              <label for="nombre">Nombre</label>
              <input id="nombre" name="nombre" value="{{ old('nombre', $colaborador->nombre) }}" required autofocus>
              @error('nombre') <div class="hint" style="color:#dc2626">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
              <label for="apellidos">Apellidos</label>
              <input id="apellidos" name="apellidos" value="{{ old('apellidos', $colaborador->apellidos) }}" required>
              @error('apellidos') <div class="hint" style="color:#dc2626">{{ $message }}</div> @enderror
            </div>

            {{-- Subsidiaria --}}
            <div class="form-group">
              <label for="subsidiaria_id">Empresa</label>
              <select id="subsidiaria_id" name="subsidiaria_id" required>
                <option value="" disabled>-- Selecciona --</option>
                @foreach($subsidiarias as $id => $nombre)
                  <option value="{{ $id }}" {{ old('subsidiaria_id', $colaborador->subsidiaria_id) == $id ? 'selected' : '' }}>
                    {{ $nombre }}
                  </option>
                @endforeach
              </select>
              @error('subsidiaria_id') <div class="hint" style="color:#dc2626">{{ $message }}</div> @enderror
            </div>

            {{-- Unidad de servicio --}}
            <div class="form-group">
              <label for="unidad_servicio_id">Unidad de servicio</label>
              <select id="unidad_servicio_id" name="unidad_servicio_id">
                <option value="">-- Selecciona --</option>
                @foreach($unidades as $id => $nombre)
                  <option value="{{ $id }}" {{ old('unidad_servicio_id', $colaborador->unidad_servicio_id) == $id ? 'selected' : '' }}>
                    {{ $nombre }}
                  </option>
                @endforeach
              </select>
              @error('unidad_servicio_id') <div class="hint" style="color:#dc2626">{{ $message }}</div> @enderror
            </div>

            {{-- Área / Departamento / Sede --}}
            <div class="form-group">
              <label for="area_id">Área / Departamento / Sede</label>
              <select id="area_id" name="area_id">
                <option value="">-- Selecciona --</option>
                @foreach($areas as $id => $nombre)
                  <option value="{{ $id }}" {{ old('area_id', $colaborador->area_id) == $id ? 'selected' : '' }}>
                    {{ $nombre }}
                  </option>
                @endforeach
              </select>
              @error('area_id') <div class="hint" style="color:#dc2626">{{ $message }}</div> @enderror
            </div>

            {{-- Puesto --}}
            <div class="form-group">
              <label for="puesto_id">Puesto</label>
              <select id="puesto_id" name="puesto_id">
                <option value="">-- Selecciona --</option>
                @foreach($puestos as $id => $nombre)
                  <option value="{{ $id }}" {{ old('puesto_id', $colaborador->puesto_id) == $id ? 'selected' : '' }}>
                    {{ $nombre }}
                  </option>
                @endforeach
              </select>
              @error('puesto_id') <div class="hint" style="color:#dc2626">{{ $message }}</div> @enderror
            </div>

            {{-- Estado (Activo / Inactivo) --}}
            <div class="form-group">
              <label for="activo">Estado</label>
              <input type="hidden" name="activo" value="0">
              <label class="switch">
                <input type="checkbox" id="activo" name="activo" value="1" {{ old('activo', $colaborador->activo) ? 'checked' : '' }}>
                <span class="slider"></span>
              </label>
            </div>

            <div class="form-buttons">
              <a href="{{ route('colaboradores.index') }}" class="btn-cancel">Cancelar</a>
              <button class="btn-save" type="submit">Actualizar</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  @push('styles')
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css">
    <style>
        /* Solo el contenido interno tendrá scroll */
        .ts-dropdown {
            max-height: none;
            overflow: visible;
            z-index: 9999 !important;
        }
        .ts-dropdown .ts-dropdown-content {
            max-height: 260px;   /* o el alto que prefieras */
            overflow-y: auto;
        }
    </style>
@endpush

  @push('scripts')
      <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
      <script>
          document.addEventListener('DOMContentLoaded', () => {
              const baseConfig = {
                  allowEmptyOption: true,
                  placeholder: '-- Selecciona --',
                  maxOptions: 5000,
                  sortField: { field: 'text', direction: 'asc' },
                  plugins: ['dropdown_input'],
                  dropdownParent: 'body',
              };

              if (document.getElementById('subsidiaria_id')) {
                  new TomSelect('#subsidiaria_id', {
                      ...baseConfig,
                      placeholder: '— Selecciona empresa —'
                  });
              }

              if (document.getElementById('unidad_servicio_id')) {
                  new TomSelect('#unidad_servicio_id', {
                      ...baseConfig,
                      placeholder: '— Selecciona unidad —'
                  });
              }

              if (document.getElementById('area_id')) {
                  new TomSelect('#area_id', {
                      ...baseConfig,
                      placeholder: '— Selecciona área —'
                  });
              }

              if (document.getElementById('puesto_id')) {
                  new TomSelect('#puesto_id', {
                      ...baseConfig,
                      placeholder: '— Selecciona puesto —'
                  });
              }
          });
      </script>
  @endpush

</x-app-layout>
