<x-app-layout title="Editar serie {{ $s->serie }}">
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight text-center">
      Editar características – {{ $s->producto->nombre }} (Serie: {{ $s->serie }})
    </h2>
  </x-slot>

  @php
    // Efectivos (producto + overrides)
    $eff  = (array) ($s->specs ?? []);
    // Overrides ya guardados en esta serie
    $over = (array) ($s->especificaciones ?? []);
  @endphp

  <style>
    .box{max-width:760px;margin:0 auto;background:#fff;padding:24px;border-radius:10px;box-shadow:0 2px 6px rgba(0,0,0,.08)}
    .grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    label{display:block;margin-bottom:6px;color:#111827;font-weight:600}
    .inp{width:100%;padding:8px;border:1px solid #d1d5db;border-radius:8px}
    .hint{font-size:12px;color:#6b7280}
    .err{color:#dc2626;font-size:12px;margin-top:6px}
    .actions{display:flex;justify-content:space-between;gap:10px;margin-top:14px}
    .btn-save{background:#16a34a;color:#fff;padding:10px 16px;border:none;border-radius:8px;font-weight:700;cursor:pointer}
    .btn-save:hover{background:#15803d}
    .btn-cancel{background:#f3f4f6;border:1px solid #e5e7eb;color:#374151;padding:10px 16px;border-radius:8px;font-weight:700;text-decoration:none}
  </style>

  <div class="py-6">
    <div class="box space-y-4">

      @if ($errors->any())
        <div style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:8px;padding:12px;">
          <b>Revisa los campos:</b>
          <ul style="margin-left:18px;list-style:disc;">
            @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
          </ul>
        </div>
      @endif

      <form method="POST" action="{{ route('series.update', $s) }}">
        @csrf @method('PUT')

        <p class="hint">
          Solo cambia lo que <b>difiera</b> del producto base. Si dejas un campo vacío, se usará el valor del producto.
        </p>

        <div class="grid2">
          {{-- Color --}}
          <div>
            <label>Color</label>
            <input
              class="inp"
              name="spec[color]"
              value="{{ old('spec.color', data_get($over,'color')) }}"
              placeholder="{{ data_get($eff,'color') }}">
            @error('spec.color') <div class="err">{{ $message }}</div> @enderror
          </div>

          {{-- RAM --}}
          <div>
            <label>RAM (GB)</label>
            <input
              class="inp"
              type="number" min="1" step="1"
              name="spec[ram_gb]"
              value="{{ old('spec.ram_gb', data_get($over,'ram_gb')) }}"
              placeholder="{{ data_get($eff,'ram_gb') }}">
            @error('spec.ram_gb') <div class="err">{{ $message }}</div> @enderror
          </div>

          {{-- Almacenamiento tipo --}}
          <div>
            <label>Almacenamiento (tipo)</label>
            @php
              $curTipoOver = old('spec.almacenamiento.tipo', data_get($over,'almacenamiento.tipo'));
              $effTipo     = strtoupper((string) data_get($eff,'almacenamiento.tipo'));
            @endphp
            <select class="inp" name="spec[almacenamiento][tipo]">
              <option value="">
                usar del producto {{ $effTipo ? "($effTipo)" : '' }}
              </option>
              @foreach (['ssd'=>'SSD','hdd'=>'HDD','m2'=>'M.2'] as $k=>$v)
                <option value="{{ $k }}" @selected($curTipoOver===$k)>{{ $v }}</option>
              @endforeach
            </select>
            @error('spec.almacenamiento.tipo') <div class="err">{{ $message }}</div> @enderror
          </div>

          {{-- Almacenamiento capacidad --}}
          <div>
            <label>Almacenamiento (capacidad GB)</label>
            <input
              class="inp"
              type="number" min="1" step="1"
              name="spec[almacenamiento][capacidad_gb]"
              value="{{ old('spec.almacenamiento.capacidad_gb', data_get($over,'almacenamiento.capacidad_gb')) }}"
              placeholder="{{ data_get($eff,'almacenamiento.capacidad_gb') }}">
            @error('spec.almacenamiento.capacidad_gb') <div class="err">{{ $message }}</div> @enderror
          </div>

          {{-- CPU (fila completa) --}}
          <div style="grid-column:1/-1">
            <label>Procesador</label>
            <input
              class="inp"
              name="spec[procesador]"
              value="{{ old('spec.procesador', data_get($over,'procesador')) }}"
              placeholder="{{ data_get($eff,'procesador') }}">
            @error('spec.procesador') <div class="err">{{ $message }}</div> @enderror
          </div>
        </div>

        <div class="actions">
          {{-- Puedes cambiar a route('productos.series', $s->producto) si prefieres volver a la lista --}}
          <a href="{{ url()->previous() }}" class="btn-cancel">Cancelar</a>
          <button class="btn-save" type="submit">Guardar</button>
        </div>
      </form>

      {{-- Valores efectivos informativos --}}
      <div class="hint">
        <b>Valores efectivos (producto + overrides):</b><br>
        Color: {{ data_get($eff,'color') ?: '—' }} |
        RAM: {{ data_get($eff,'ram_gb') ? (int) data_get($eff,'ram_gb').' GB' : '—' }} |
        Almacenamiento: {{ strtoupper((string) data_get($eff,'almacenamiento.tipo')) ?: '—' }}
        {{ data_get($eff,'almacenamiento.capacidad_gb') ? (int) data_get($eff,'almacenamiento.capacidad_gb').' GB' : '' }} |
        CPU: {{ data_get($eff,'procesador') ?: '—' }}
      </div>

    </div>
  </div>
</x-app-layout>
