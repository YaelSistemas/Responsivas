<x-app-layout title="Crear Subsidiaria">
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight text-center">Crear Subsidiaria</h2>
  </x-slot>

  <style>
    /* ====== Zoom responsivo: MISMA VISTA, SOLO MÁS “PEQUEÑA” EN MÓVIL ====== */
    .zoom-outer{ overflow-x:hidden; } /* evita scroll horizontal por el ancho compensado */
    .zoom-inner{
      --zoom: 1;                       /* desktop */
      transform: scale(var(--zoom));
      transform-origin: top left;
      width: calc(100% / var(--zoom)); /* compensa el ancho visual */
    }
    /* Breakpoints (mismos que en otras vistas) */
    @media (max-width: 1024px){ .zoom-inner{ --zoom:.95; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }  /* tablets landscape */
    @media (max-width: 768px){  .zoom-inner{ --zoom:.90; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }  /* tablets/phones grandes */
    @media (max-width: 640px){  .zoom-inner{ --zoom:.70; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} } /* phones comunes */
    @media (max-width: 400px){  .zoom-inner{ --zoom:.55; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }  /* phones muy chicos */

    /* iOS: evita auto-zoom al enfocar inputs */
    @media (max-width: 768px){
      input, select, textarea{ font-size:16px; }
    }

    /* ====== Estilos propios del form ====== */
    .form-container{max-width:700px;margin:0 auto;background:#fff;padding:24px;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,.1)}
    .form-group{margin-bottom:16px}
    .form-container input,.form-container textarea{width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;font-size:14px}
    .form-buttons{display:flex;justify-content:space-between;padding-top:20px}
    .btn-cancel{background:#dc2626;color:#fff;padding:8px 16px;border:none;border-radius:6px;font-weight:600;text-decoration:none}
    .btn-cancel:hover{background:#b91c1c}
    .btn-save{background:#16a34a;color:#fff;padding:8px 16px;border:none;border-radius:6px;font-weight:600;cursor:pointer}
    .btn-save:hover{background:#15803d}
  </style>

  <!-- Envoltura de zoom: mantiene el layout, solo escala visualmente en móvil -->
  <div class="zoom-outer">
    <div class="zoom-inner">
      <div class="py-6 page-pad">
        <div class="form-container">
          @if ($errors->any())
            <div style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:8px;padding:12px;margin-bottom:14px;">
              <div style="font-weight:700;margin-bottom:6px;">Se encontraron errores:</div>
              <ul style="margin-left:18px;list-style:disc;">
                @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
              </ul>
            </div>
          @endif

          <form method="POST" action="{{ route('subsidiarias.store') }}">
            @csrf

            <div class="form-group">
              <label>Nombre</label>
              <input name="nombre" value="{{ old('nombre') }}" required>
              @error('nombre') <p style="color:#dc2626;margin-top:6px">{{ $message }}</p> @enderror
            </div>

            <div class="form-group">
              <label>Razón Social (opcional)</label>
              <textarea name="descripcion" rows="3">{{ old('descripcion') }}</textarea>
              @error('descripcion') <p style="color:#dc2626;margin-top:6px">{{ $message }}</p> @enderror
            </div>

            <div class="form-buttons">
              <a href="{{ route('subsidiarias.index') }}" class="btn-cancel">Cancelar</a>
              <button type="submit" class="btn-save">Crear</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</x-app-layout>
