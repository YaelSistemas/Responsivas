{{-- resources/views/celulares/responsivas/partials/table.blade.php --}}
@php
  use Illuminate\Support\Carbon;
  use Illuminate\Pagination\AbstractPaginator;

  $rows = $rows ?? ($responsivas ?? ($paginator ?? $lista ?? $records ?? $items ?? $data ?? $collection ?? collect()));
  $isPaginator = $rows instanceof AbstractPaginator;

  // Tipos que consideraremos como "celular/teléfono"
  $celTypes = ['celular','telefono','teléfono','phone','movil','móvil'];
@endphp

<style>
  /* ✅ CENTRAR encabezados y datos */
  .tbl th,
  .tbl td{
    text-align:center !important;
    vertical-align: middle !important;
  }

  /* (opcional) para que los íconos no brinquen de línea */
  .tbl td.actions{
    white-space: nowrap;
  }

  /* Botones de acciones (igual que responsivas) */
  .tbl td.actions a,
  .tbl td.actions button{
    display:inline-flex; align-items:center; justify-content:center;
    gap:.35rem; padding:.25rem .45rem; border-radius:.375rem;
    text-decoration:none; border:1px solid transparent; background:#f9fafb; color:#1f2937;
  }
  .tbl td.actions a:hover,
  .tbl td.actions button:hover{ background:#eef2ff; color:#1e40af; }
  .tbl td.actions .danger{ background:#fef2f2; color:#991b1b; }
  .tbl td.actions .danger:hover{ background:#fee2e2; color:#7f1d1d; }

  .sr-only{ position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
</style>

<table class="tbl">
  <thead>
    <tr>
      <th>Folio</th>
      <th>Colaborador</th>
      <th>Entregado por</th>
      <th>Fecha entrega</th>
      <th>Equipos</th>

      <th>Salida</th>
      <th>Acciones salida</th>

      <th>Devolución</th>
      <th>Acciones devolución</th>
    </tr>
  </thead>

  <tbody>
    @forelse($rows as $r)
      @php
        // Fecha entrega (d/m/Y)
        $fechaEnt = !empty($r->fecha_entrega)
          ? (Carbon::parse($r->fecha_entrega)->format('d/m/Y'))
          : '—';

        // Colaborador
        $colNombre = trim(($r->colaborador->nombre ?? '—').' '.($r->colaborador->apellidos ?? ''));

        // ✅ Equipos: nombre + marca + modelo (solo tipo celular/telefono)
        $det = collect($r->detalles ?? []);
        $equipos = $det
          ->filter(function($d) use ($celTypes){
            $tipo = strtolower((string)($d->producto->tipo ?? ''));
            return in_array($tipo, $celTypes, true);
          })
          ->map(function($d){
            $p = $d->producto ?? null;
            $nombre = trim((string)($p->nombre ?? ''));
            $marca  = trim((string)($p->marca ?? ''));
            $modelo = trim((string)($p->modelo ?? ''));
            return trim($nombre.' '.$marca.' '.$modelo);
          })
          ->filter()
          ->unique()
          ->values();

        // Mostrar máximo 2 y luego +n
        $equiposTxt = $equipos->take(2)->implode(', ');
        if ($equipos->count() > 2) $equiposTxt .= ' +'.($equipos->count() - 2);
        $equiposTxt = $equiposTxt !== '' ? $equiposTxt : '—';

        // ✅ Devolución ligada a esta responsiva (celular)
        $devo = $r->devolucion
            ?? (method_exists($r, 'devoluciones') ? $r->devoluciones->sortByDesc('id')->first() : null)
            ?? \App\Models\Devolucion::where('responsiva_id', $r->id)->orderByDesc('id')->first();
      @endphp

      <tr>
        <td class="font-semibold">{{ $r->folio }}</td>
        <td title="{{ $colNombre }}">{{ $colNombre }}</td>
        <td>{{ $r->usuario->name ?? '—' }}</td>
        <td>{{ $fechaEnt }}</td>
        <td title="{{ $equiposTxt }}">{{ $equiposTxt }}</td>

        {{-- ✅ SALIDA: show + pdf --}}
        <td class="actions">
          @can('responsivas.view')
            <a href="{{ route('responsivas.show', $r) }}" title="Ver salida">
              <i class="fa-solid fa-eye"></i><span class="sr-only">Ver</span>
            </a>
            <a href="{{ route('responsivas.pdf', $r) }}" title="PDF salida" target="_blank" rel="noopener">
              <i class="fa-solid fa-file-pdf"></i><span class="sr-only">PDF</span>
            </a>
          @endcan
        </td>

        {{-- ✅ ACCIONES SALIDA: edit + delete --}}
        <td class="actions">
          @can('responsivas.edit')
            <a href="{{ route('responsivas.edit', $r) }}" title="Editar salida">
              <i class="fa-solid fa-pen"></i><span class="sr-only">Editar</span>
            </a>
          @endcan

          @can('responsivas.delete')
            <form action="{{ route('responsivas.destroy', $r) }}" method="POST" style="display:inline"
                  onsubmit="return confirm('¿Eliminar esta responsiva?')">
              @csrf @method('DELETE')
              <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
              <button type="submit" class="danger" title="Eliminar salida">
                <i class="fa-solid fa-trash"></i><span class="sr-only">Eliminar</span>
              </button>
            </form>
          @endcan
        </td>

        {{-- ✅ DEVOLUCIÓN: show + pdf (solo si existe). Si NO existe: NO mostrar nada --}}
        <td class="actions">
          @if($devo)
            @can('devoluciones.view')
              <a href="{{ route('devoluciones.show', $devo) }}" title="Ver devolución">
                <i class="fa-solid fa-eye"></i><span class="sr-only">Ver</span>
              </a>
              <a href="{{ route('devoluciones.pdf', $devo) }}" title="PDF devolución" target="_blank" rel="noopener">
                <i class="fa-solid fa-file-pdf"></i><span class="sr-only">PDF</span>
              </a>
            @endcan
          @endif
        </td>

        {{-- ✅ ACCIONES DEVOLUCIÓN:
             - Si NO existe: botón CREAR devolución
             - Si existe: edit + delete --}}
        <td class="actions">
          @if(!$devo)
            @can('devoluciones.create')
              <a href="{{ route('devoluciones.create', ['responsiva_id' => $r->id]) }}" title="Crear devolución">
                <i class="fa-solid fa-plus"></i><span class="sr-only">Crear devolución</span>
              </a>
            @endcan
          @else
            @can('devoluciones.edit')
              <a href="{{ route('devoluciones.edit', $devo) }}" title="Editar devolución">
                <i class="fa-solid fa-pen"></i><span class="sr-only">Editar</span>
              </a>
            @endcan

            @can('devoluciones.delete')
              <form action="{{ route('devoluciones.destroy', $devo) }}" method="POST" style="display:inline"
                    onsubmit="return confirm('¿Eliminar esta devolución?')">
                @csrf @method('DELETE')
                <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                <button type="submit" class="danger" title="Eliminar devolución">
                  <i class="fa-solid fa-trash"></i><span class="sr-only">Eliminar</span>
                </button>
              </form>
            @endcan
          @endif
        </td>
      </tr>
    @empty
      <tr>
        <td colspan="9" style="padding:1rem;color:#6b7280;text-align:center;">
          No hay responsivas de celulares aún.
        </td>
      </tr>
    @endforelse
  </tbody>
</table>

@if ($isPaginator)
  <div class="p-3">
    {{ $rows->withQueryString()->links() }}
  </div>
@endif
