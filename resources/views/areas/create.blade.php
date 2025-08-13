<x-app-layout title="Crear Área">
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight text-center">Crear área</h2>
  </x-slot>

  <style>
    .form-container{max-width:700px;margin:0 auto;background:#fff;padding:24px;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,.1)}
    .form-group{margin-bottom:16px}
    .form-container input,.form-container textarea{width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;font-size:14px}
    .form-buttons{display:flex;justify-content:space-between;padding-top:20px}
    .btn-cancel{background:#dc2626;color:#fff;padding:8px 16px;border:none;border-radius:6px;font-weight:600;text-decoration:none}
    .btn-cancel:hover{background:#b91c1c}
    .btn-save{background:#16a34a;color:#fff;padding:8px 16px;border:none;border-radius:6px;font-weight:600;cursor:pointer}
    .btn-save:hover{background:#15803d}
  </style>

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

      <form method="POST" action="{{ route('areas.store') }}">
        @csrf

        <div class="form-group">
          <label>Nombre</label>
          <input name="nombre" value="{{ old('nombre') }}" required>
          @error('nombre') <div style="color:#dc2626;font-size:12px;margin-top:6px">{{ $message }}</div> @enderror
        </div>

        <div class="form-group">
          <label>Descripción (opcional)</label>
          <textarea name="descripcion" rows="3">{{ old('descripcion') }}</textarea>
          @error('descripcion') <div style="color:#dc2626;font-size:12px;margin-top:6px">{{ $message }}</div> @enderror
        </div>

        <div class="form-buttons">
          <a href="{{ route('areas.index') }}" class="btn-cancel">Cancelar</a>
          <button class="btn-save" type="submit">Crear</button>
        </div>
      </form>
    </div>
  </div>
</x-app-layout>
