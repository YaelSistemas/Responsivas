<x-app-layout title="Editar unidad de servicio">
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight text-center">
      Editar unidad de servicio
    </h2>
  </x-slot>

  <style>
    /* ====== Zoom responsivo: MISMA VISTA, SOLO MÁS “PEQUEÑA” EN MÓVIL ====== */
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
    .form-container textarea,
    .form-container select{
      width:100%;
      padding:8px;
      border:1px solid #ccc;
      border-radius:6px;
      font-size:14px
    }
    .form-container input:focus,
    .form-container textarea:focus,
    .form-container select:focus{
      outline:none;
      border-color:#2563eb;
      box-shadow:0 0 0 3px rgba(37,99,235,.12)
    }
    .hint{font-size:12px;color:#6b7280;margin-top:6px}
    .err{color:#dc2626;font-size:12px;margin-top:6px}
    .form-buttons{display:flex;gap:12px;justify-content:space-between;padding-top:20px}
    .btn-cancel{background:#dc2626;color:#fff;padding:8px 16px;border:none;border-radius:6px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}
    .btn-cancel:hover{background:#b91c1c}
    .btn-save{background:#16a34a;color:#fff;padding:8px 16px;border:none;border-radius:6px;font-weight:600;cursor:pointer}
    .btn-save:hover{background:#15803d}

    @media (max-width: 480px){
      .form-buttons{flex-direction:column-reverse;align-items:stretch}
      .btn-cancel,.btn-save{width:100%}
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

          <form method="POST" action="{{ route('unidades.update', $unidad) }}">
            @csrf
            @method('PUT')

            {{-- Nombre --}}
            <div class="form-group">
              <label for="nombre">Nombre <span class="hint">(requerido)</span></label>
              <input id="nombre" name="nombre" value="{{ old('nombre', $unidad->nombre) }}" required autofocus>
              @error('nombre') <div class="err">{{ $message }}</div> @enderror
            </div>

            {{-- Dirección --}}
            <div class="form-group">
              <label for="direccion">Dirección <span class="hint">(opcional)</span></label>
              <textarea id="direccion" name="direccion" rows="3">{{ old('direccion', $unidad->direccion) }}</textarea>
              @error('direccion') <div class="err">{{ $message }}</div> @enderror
            </div>

            {{-- Responsable (select) --}}
            <div class="form-group">
              <label for="responsable_id">Responsable <span class="hint">(opcional)</span></label>
              <select id="responsable_id" name="responsable_id">
                <option value="">Seleccione un colaborador…</option>
                @foreach ($colaboradores as $id => $nombre)
                  <option value="{{ $id }}" {{ old('responsable_id', $unidad->responsable_id) == $id ? 'selected' : '' }}>
                    {{ $nombre }}
                  </option>
                @endforeach
              </select>
              @error('responsable_id') <div class="err">{{ $message }}</div> @enderror
            </div>

            <div class="form-buttons">
              <a href="{{ route('unidades.index') }}" class="btn-cancel">Cancelar</a>
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
          .ts-dropdown {
              max-height: 260px;
              overflow-y: auto;
              z-index: 9999 !important;
          }
          .ts-dropdown .ts-dropdown-content {
              max-height: inherit;
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
                  placeholder: 'Seleccione un colaborador…',
                  maxOptions: 5000,
                  sortField: { field: 'text', direction: 'asc' },
                  plugins: ['dropdown_input'],
                  dropdownParent: 'body',
                  onDropdownOpen: function () {
                      const rect = this.control.getBoundingClientRect();
                      const espacioAbajo = window.innerHeight - rect.bottom - 10;
                      const dropdown = this.dropdown;

                      if (dropdown) {
                          const minimo = 160;
                          const maximo = 260;
                          let alto = Math.max(minimo, Math.min(espacioAbajo, maximo));
                          dropdown.style.maxHeight = alto + 'px';
                      }
                  }
              };

              if (document.getElementById('responsable_id')) {
                  new TomSelect('#responsable_id', {
                      ...baseConfig
                  });
              }
          });
      </script>
  @endpush>

</x-app-layout>
