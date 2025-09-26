<x-app-layout title="Crear Colaborador">
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight text-center">Crear colaborador</h2>
  </x-slot>

  <style>
    /* ====== Zoom responsivo: MISMA VISTA, SOLO MÁS “PEQUEÑA” EN MÓVIL ====== */
    .zoom-outer{ overflow-x:hidden; } /* evita scroll horizontal por el ancho compensado */
    .zoom-inner{
      --zoom: 1;                       /* valor por defecto en desktop */
      transform: scale(var(--zoom));
      transform-origin: top left;
      /* compensamos el ancho para que visualmente quepa todo sin recortar */
      width: calc(100% / var(--zoom));
    }
    /* Breakpoints (mismos que usas en otras vistas) */
    @media (max-width: 1024px){ .zoom-inner{ --zoom:.95; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }  /* tablets landscape */
    @media (max-width: 768px){  .zoom-inner{ --zoom:.90; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }  /* tablets/phones grandes */
    @media (max-width: 640px){  .zoom-inner{ --zoom:.70; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} } /* phones comunes */
    @media (max-width: 400px){  .zoom-inner{ --zoom:.55; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }  /* phones muy chicos */

    /* iOS: evita auto-zoom al enfocar inputs */
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

    /* En móviles, apilar botones */
    @media (max-width: 480px){
      .form-buttons{flex-direction:column-reverse;align-items:stretch}
      .btn-cancel,.btn-save{width:100%}
    }
  </style>

  <!-- Envoltura de zoom: mantiene el layout, solo escala visualmente en móvil -->
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

          <form method="POST" action="{{ route('colaboradores.store') }}">
            @csrf

            <div class="form-group">
              <label for="nombre">Nombre</label>
              <input id="nombre" name="nombre" value="{{ old('nombre') }}" required autofocus>
              @error('nombre') <div class="hint" style="color:#dc2626">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
              <label for="apellidos">Apellidos</label>
              <input id="apellidos" name="apellidos" value="{{ old('apellidos') }}" required>
              @error('apellidos') <div class="hint" style="color:#dc2626">{{ $message }}</div> @enderror
            </div>

            {{-- Subsidiaria --}}
            <div class="form-group">
              <label for="subsidiaria_id">Empresa</label>
              <select id="subsidiaria_id" name="subsidiaria_id" required>
                <option value="" disabled {{ old('subsidiaria_id') ? '' : 'selected' }}>-- Selecciona --</option>
                @foreach($subsidiarias as $id => $nombre)
                  <option value="{{ $id }}" {{ old('subsidiaria_id') == $id ? 'selected' : '' }}>
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
                  <option value="{{ $id }}" {{ old('unidad_servicio_id') == $id ? 'selected' : '' }}>
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
                  <option value="{{ $id }}" {{ old('area_id') == $id ? 'selected' : '' }}>
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
                  <option value="{{ $id }}" {{ old('puesto_id') == $id ? 'selected' : '' }}>
                    {{ $nombre }}
                  </option>
                @endforeach
              </select>
              @error('puesto_id') <div class="hint" style="color:#dc2626">{{ $message }}</div> @enderror
            </div>

            <div class="form-buttons">
              <a href="{{ route('colaboradores.index') }}" class="btn-cancel">Cancelar</a>
              <button class="btn-save" type="submit">Crear</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</x-app-layout>
