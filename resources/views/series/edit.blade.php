<x-app-layout title="Editar serie {{ $s->serie }}">
  <x-slot name="header"><h2 class="font-semibold text-xl">Editar características – {{ $s->producto->nombre }} (Serie: {{ $s->serie }})</h2></x-slot>

  <div class="max-w-3xl mx-auto bg-white p-6 rounded shadow space-y-4">
    <form method="POST" action="{{ route('series.update',$s) }}">
      @csrf @method('PUT')

      <p class="text-sm text-gray-600">
        Solo cambia lo que <b>difiera</b> del producto base.
        Si dejas un campo vacío, se usará el valor del producto.
      </p>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label>Color</label>
          <input class="inp" name="spec[color]" value="{{ old('spec.color', data_get($s->especificaciones,'color')) }}">
        </div>

        <div>
          <label>RAM (GB)</label>
          <input class="inp" type="number" min="1" name="spec[ram_gb]" value="{{ old('spec.ram_gb', data_get($s->especificaciones,'ram_gb')) }}">
        </div>

        <div>
          <label>Almacenamiento (tipo)</label>
          <select class="inp" name="spec[almacenamiento][tipo]">
            @php $cur = old('spec.almacenamiento.tipo', data_get($s->especificaciones,'almacenamiento.tipo')); @endphp
            <option value="">(usar del producto)</option>
            <option value="ssd" @selected($cur==='ssd')>SSD</option>
            <option value="hdd" @selected($cur==='hdd')>HDD</option>
            <option value="m2"  @selected($cur==='m2')>M.2</option>
          </select>
        </div>

        <div>
          <label>Almacenamiento (capacidad GB)</label>
          <input class="inp" type="number" min="1" name="spec[almacenamiento][capacidad_gb]" value="{{ old('spec.almacenamiento.capacidad_gb', data_get($s->especificaciones,'almacenamiento.capacidad_gb')) }}">
        </div>

        <div class="col-span-2">
          <label>Procesador</label>
          <input class="inp" name="spec[procesador]" value="{{ old('spec.procesador', data_get($s->especificaciones,'procesador')) }}">
        </div>
      </div>

      <div class="flex justify-between pt-4">
        <a href="{{ route('series.show',$s) }}" class="btn-cancel">Cancelar</a>
        <button class="btn-save" type="submit">Guardar</button>
      </div>
    </form>

    <hr>
    <div class="text-sm">
      <b>Valores efectivos (producto + overrides):</b><br>
      Color: {{ $s->color ?? '—' }} |
      RAM: {{ $s->ram_gb ? $s->ram_gb.' GB' : '—' }} |
      Almacenamiento: {{ $s->alm_tipo ?? '—' }} {{ $s->alm_capacidad_gb ? $s->alm_capacidad_gb.' GB' : '' }} |
      CPU: {{ $s->cpu ?? '—' }}
    </div>
  </div>
</x-app-layout>
