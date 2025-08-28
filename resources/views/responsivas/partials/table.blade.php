<div id="tabla-responsivas">
  <table class="tbl w-full">
    <thead>
      <tr>
        <th style="width:140px">Folio</th>
        <th>Colaborador</th>
        <th>Entregado por</th>
        <th style="width:140px">Fecha</th>
        <th style="width:110px">Ítems</th>
        <th style="width:120px">Acciones</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($rows as $r)
        <tr>
          <td class="font-semibold">{{ $r->folio }}</td>
          <td>{{ $r->colaborador?->nombre ?? '—' }}</td>
          <td>{{ $r->usuario?->name ?? '—' }}</td>
          <td>{{ $r->fecha_entrega }}</td>
          <td class="font-semibold">{{ (int)($r->detalles_count ?? 0) }}</td>
          <td>
            <a href="{{ route('responsivas.show',$r) }}" class="text-indigo-600 hover:underline">Ver</a>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="6" class="text-center text-gray-500 py-6">Sin registros</td>
        </tr>
      @endforelse
    </tbody>
  </table>

  <div class="mt-4">
    {{ $rows->links() }}
  </div>
</div>
