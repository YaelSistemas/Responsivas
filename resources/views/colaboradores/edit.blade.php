<x-app-layout title="Editar Colaborador"> 
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight text-center">Editar colaborador</h2>
    </x-slot>

    <style>
        .form-container{max-width:700px;margin:0 auto;background:#fff;padding:24px;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,.1)}
        .form-group{margin-bottom:16px}
        .form-container input,
        .form-container select{width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;font-size:14px}
        .form-buttons{display:flex;justify-content:space-between;padding-top:20px}
        .btn-cancel{background:#dc2626;color:#fff;padding:8px 16px;border:none;border-radius:6px;font-weight:600;text-decoration:none}
        .btn-cancel:hover{background:#b91c1c}
        .btn-save{background:#16a34a;color:#fff;padding:8px 16px;border:none;border-radius:6px;font-weight:600;cursor:pointer}
        .btn-save:hover{background:#15803d}
        .hint{font-size:12px;color:#6b7280;margin-top:6px}
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

            <form method="POST" action="{{ route('colaboradores.update', $colaborador) }}">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label>Nombre</label>
                    <input name="nombre" value="{{ old('nombre', $colaborador->nombre) }}" required>
                    @error('nombre') <div class="hint" style="color:#dc2626">{{ $message }}</div> @enderror
                </div>

                <div class="form-group">
                    <label>Apellidos</label>
                    <input name="apellidos" value="{{ old('apellidos', $colaborador->apellidos) }}" required>
                    @error('apellidos') <div class="hint" style="color:#dc2626">{{ $message }}</div> @enderror
                </div>

                {{-- Subsidiaria --}}
                <div class="form-group">
                    <label>Empresa</label>
                    <select name="subsidiaria_id" required>
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
                    <label>Unidad de servicio</label>
                    <select name="unidad_servicio_id">
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
                    <label>Área / Departamento / Sede</label>
                    <select name="area_id">
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
                    <label>Puesto</label>
                    <select name="puesto_id">
                        <option value="">-- Selecciona --</option>
                        @foreach($puestos as $id => $nombre)
                            <option value="{{ $id }}" {{ old('puesto_id', $colaborador->puesto_id) == $id ? 'selected' : '' }}>
                                {{ $nombre }}
                            </option>
                        @endforeach
                    </select>
                    @error('puesto_id') <div class="hint" style="color:#dc2626">{{ $message }}</div> @enderror
                </div>

                <div class="form-buttons">
                    <a href="{{ route('colaboradores.index') }}" class="btn-cancel">Cancelar</a>
                    <button class="btn-save" type="submit">Actualizar</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
