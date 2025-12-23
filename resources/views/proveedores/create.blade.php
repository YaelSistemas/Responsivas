{{-- resources/views/proveedores/create.blade.php --}}
<x-app-layout title="Nuevo proveedor">
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight text-center">Nuevo proveedor</h2>
  </x-slot>

  <style>
    /* ====== Zoom responsivo (igual que OC/Responsivas) ====== */
    .zoom-outer{ overflow-x:hidden; }
    .zoom-inner{
      --zoom: 1;
      transform: scale(var(--zoom));
      transform-origin: top left;
      width: calc(100% / var(--zoom));
    }
    @media (max-width:1024px){ .zoom-inner{ --zoom:.95 } .page-wrap{max-width:94vw;padding:0 4vw} }
    @media (max-width:768px){  .zoom-inner{ --zoom:.90 } .page-wrap{max-width:94vw;padding:0 4vw} }
    @media (max-width:640px){  .zoom-inner{ --zoom:.75 } .page-wrap{max-width:94vw;padding:0 4vw} }
    @media (max-width:400px){  .zoom-inner{ --zoom:.60 } .page-wrap{max-width:94vw;padding:0 4vw} }

    /* iOS: evita auto-zoom en inputs */
    @media (max-width:768px){
      input, select, textarea, button { font-size:16px; }
    }

    /* ====== Estilos base (mismos de OC/EDIT) ====== */
    .page-wrap{ max-width:880px; margin:0 auto; }
    .wrap{max-width:880px;margin:0 auto;background:#fff;padding:24px;border-radius:10px;box-shadow:0 2px 6px rgba(0,0,0,.08); border:1px solid #e5e7eb}
    .row{margin-bottom:16px}
    select,textarea,input{width:100%;padding:8px;border:1px solid #d1d5db;border-radius:8px}
    label{font-weight:600; display:block; margin-bottom:6px;}
    .err{color:#dc2626;font-size:12px;margin-top:6px}
    .hint{font-size:12px;color:#6b7280}
    .btn{background:#16a34a;color:#fff;padding:10px 16px;border-radius:8px;font-weight:600;border:none}
    .btn:hover{background:#15803d}
    .btn-cancel{background:#dc2626;color:#fff;padding:10px 16px;border-radius:8px;text-decoration:none}
    .grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
    .grid3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px}
    .section-sep{display:flex;align-items:center;margin:22px 0 14px}
    .section-sep .line{flex:1;height:1px;background:#e5e7eb}
    .section-sep .label{margin:0 10px;font-size:12px;color:#6b7280;letter-spacing:.06em;text-transform:uppercase;font-weight:700;white-space:nowrap}
     /* === Toggle switch igual que colaboradores === */
    .switch {
        position: relative;
        display: inline-block;
        width: 46px;
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
        background-color: #e5e7eb; /* gris claro */
        transition: .3s;
        border-radius: 999px;
    }
    .slider:before {
        position: absolute;
        content: "";
        height: 20px;
        width: 20px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .3s;
        border-radius: 999px;
        box-shadow: 0 1px 2px rgba(0,0,0,.25);
    }
    input:checked + .slider {
        background-color: #16a34a; /* verde */
    }
    input:checked + .slider:before {
        transform: translateX(18px);
    }
  </style>

  <div class="zoom-outer">
    <div class="zoom-inner">
      <div class="py-6 page-wrap">
        <div class="wrap">

          {{-- Alertas de validación --}}
          @if ($errors->any())
            <div style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:8px;padding:12px;margin-bottom:14px;">
              <b>Revisa los campos:</b>
              <ul style="margin-left:18px;list-style:disc;">
                @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
              </ul>
            </div>
          @endif

          <form method="POST" action="{{ route('proveedores.store') }}">
            @csrf

            {{-- ======= Datos del proveedor ======= --}}
            <div class="section-sep"><div class="line"></div><div class="label">Datos del proveedor</div><div class="line"></div></div>

            {{-- Nombre + RFC en dos columnas (igual que EDIT) --}}
            <div class="grid2 row">
              <div>
                <label>Nombre del proveedor</label>
                <input type="text" name="nombre" value="{{ old('nombre') }}" required>
                <div class="hint">Razón social o nombre comercial.</div>
                @error('nombre') <div class="err">{{ $message }}</div> @enderror
              </div>
              <div>
                <label>RFC</label>
                <input type="text" name="rfc" value="{{ old('rfc') }}" placeholder="Opcional">
                <div class="hint">Ejemplo: RPS880920E12 (12–13 caracteres).</div>
                @error('rfc') <div class="err">{{ $message }}</div> @enderror
              </div>
            </div>

            <div class="grid2 row">
              <div>
                <label>Calle</label>
                <input type="text" name="calle" value="{{ old('calle') }}">
                @error('calle') <div class="err">{{ $message }}</div> @enderror
              </div>
              <div>
                <label>Colonia</label>
                <input type="text" name="colonia" value="{{ old('colonia') }}">
                @error('colonia') <div class="err">{{ $message }}</div> @enderror
              </div>
            </div>

            <div class="grid3 row">
              <div>
                <label>Código postal</label>
                <input type="text" name="codigo_postal" value="{{ old('codigo_postal') }}">
                @error('codigo_postal') <div class="err">{{ $message }}</div> @enderror
              </div>
              <div>
                <label>Ciudad</label>
                <input type="text" name="ciudad" value="{{ old('ciudad') }}">
                @error('ciudad') <div class="err">{{ $message }}</div> @enderror
              </div>
              <div>
                <label>Estado</label>
                <input type="text" name="estado" value="{{ old('estado') }}">
                @error('estado') <div class="err">{{ $message }}</div> @enderror
              </div>
            </div>

            {{-- Estado (Activo / Inactivo) --}}
            <div class="row">
                <label for="activo">Estado</label>

                <div style="display:flex; align-items:center; gap:12px;">
                    {{-- siempre mandamos algo aunque no esté marcado --}}
                    <input type="hidden" name="activo" value="0">

                    <label class="switch">
                        <input type="checkbox"
                              id="activo"
                              name="activo"
                              value="1"
                              {{ old('activo', 1) ? 'checked' : '' }}> {{-- por defecto ACTIVO --}}
                        <span class="slider"></span>
                    </label>

                    <span class="hint">Activa o desactiva el estado del proveedor.</span>
                </div>

                @error('activo')
                    <div class="err">{{ $message }}</div>
                @enderror
            </div>

            <div class="grid2" style="margin-top:18px">
              <a href="{{ route('proveedores.index') }}" class="btn-cancel">Cancelar</a>
              <button type="submit" class="btn">Guardar</button>
            </div>
          </form>

        </div>
      </div>
    </div>
  </div>
</x-app-layout>
