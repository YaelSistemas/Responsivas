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
  /* CENTRAR encabezados y datos */
  .tbl th,
  .tbl td{
    text-align:center !important;
    vertical-align: middle !important;
  }

  /* que no brinque el contenido */
  .tbl td.actions{
    white-space: nowrap;
  }

  /* Botones de acciones */
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

  /* Botón azul */
  .tbl td.actions a.btn-primary{ background:#2563eb !important; color:#fff !important; border-color:#2563eb !important; }
  .tbl td.actions a.btn-primary:hover{ background:#1e4ed8 !important; border-color:#1e4ed8 !important; color:#fff !important; }

  /* separador visual interno "—" */
  .action-sep{ display:inline-block; margin:0 .5rem; color:#9ca3af; font-weight:700; }

  /* leyenda pequeña */
  .hint{ display:block; font-size:12px; color:#6b7280; margin-top:2px; line-height:1.1; }

  /* Badge */
  .badge{display:inline-flex;align-items:center;padding:.12rem .45rem;border-radius:999px;
    font-size:11px;font-weight:700;line-height:1;border:1px solid transparent;margin-top:4px; }
  .badge-gray{ background:#f3f4f6; color:#374151; border-color:#e5e7eb; }
  .badge-green{ background:#dcfce7; color:#166534; border-color:#86efac; }
  .badge-yellow{ background:#fef9c3; color:#854d0e; border-color:#fde047; }
  .badge-red{ background:#fee2e2; color:#991b1b; border-color:#fecaca; }
</style>

<table class="tbl">
  <thead>
    <tr>
      <th>Folio</th>
      <th>Colaborador</th>
      <th>Fecha de Salida</th>
      <th>Equipos</th>
      <th>Fecha Devolución</th>
      <th>Entregado por</th>
      <th>Salida</th>
      <th>Devolución</th>
    </tr>
  </thead>

  <tbody>
    @forelse($rows as $r)
      @php
        // Fecha de salida (tomada de fecha_solicitud)
        $fechaSalida = !empty($r->fecha_solicitud)
          ? Carbon::parse($r->fecha_solicitud)->format('d/m/Y')
          : '—';

        // Fecha entrega (la usamos como "tentativa" cuando NO hay devolución)
        $fechaEntregaTent = !empty($r->fecha_entrega)
          ? Carbon::parse($r->fecha_entrega)->format('d/m/Y')
          : '—';

        // Colaborador
        $colNombre = trim(($r->colaborador->nombre ?? '—').' '.($r->colaborador->apellidos ?? ''));

        // Equipos: nombre + marca + modelo (solo tipo celular/telefono)
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

        // Devolución ligada a esta responsiva (celular)
        $devo = $r->devolucion
            ?? (method_exists($r, 'devoluciones') ? $r->devoluciones->sortByDesc('id')->first() : null)
            ?? \App\Models\Devolucion::where('responsiva_id', $r->id)->orderByDesc('id')->first();

        // Fecha devolución a mostrar
        // - si NO hay devolución: usar fecha_entrega (tentativa)
        // - si hay devolución: usar fecha_devolucion de devolución
        $fechaDevolucionShow = '—';
        $fechaDevolucionIsTentativa = false;

        if ($devo) {
          $fechaDevolucionShow = !empty($devo->fecha_devolucion)
            ? Carbon::parse($devo->fecha_devolucion)->format('d/m/Y')
            : '—';
        } else {
          $fechaDevolucionShow = $fechaEntregaTent;
          $fechaDevolucionIsTentativa = true;
        }

        // ✅ Badge de desempeño vs fecha tentativa (solo si hay devolución y hay fecha tentativa)
        $delayBadgeText  = null;
        $delayBadgeClass = null;

        if ($devo && !empty($devo->fecha_devolucion) && !empty($r->fecha_entrega)) {
          $tent = Carbon::parse($r->fecha_entrega)->startOfDay();         // fecha tentativa
          $real = Carbon::parse($devo->fecha_devolucion)->startOfDay();   // fecha real devolución

          // positivo = días tarde, negativo = días antes
          $diffDays = $tent->diffInDays($real, false);

          if ($diffDays < 0) {
            $n = abs($diffDays);
            $delayBadgeText  = $n === 1 ? "1 día antes" : "{$n} días antes";
            $delayBadgeClass = 'badge-green';
          } elseif ($diffDays === 0) {
            $delayBadgeText  = "A tiempo";
            $delayBadgeClass = 'badge-green';
          } elseif ($diffDays <= 2) {
            $delayBadgeText  = $diffDays === 1 ? "1 día tarde" : "{$diffDays} días tarde";
            $delayBadgeClass = 'badge-yellow';
          } else {
            $delayBadgeText  = "{$diffDays} días tarde";
            $delayBadgeClass = 'badge-red';
          }
        }

        // helpers para separador "—"
        $canSalidaRight = auth()->user()?->can('celulares.edit') || auth()->user()?->can('celulares.delete');
        $canDevoRight   = auth()->user()?->can('celulares.edit') || auth()->user()?->can('celulares.delete');
      @endphp

      <tr>
        <td class="font-semibold">{{ $r->folio }}</td>
        <td title="{{ $colNombre }}">{{ $colNombre }}</td>

        {{-- Fecha de salida (fecha_solicitud) --}}
        <td>{{ $fechaSalida }}</td>

        <td title="{{ $equiposTxt }}">{{ $equiposTxt }}</td>

        {{-- Fecha devolución (tentativa o real) --}}
        <td>
          {{ $fechaDevolucionShow }}

          @if($fechaDevolucionIsTentativa && $fechaDevolucionShow !== '—')
            <div>
              <span class="badge badge-gray">Fecha tentativa</span>
            </div>
          @endif

          @if(!$fechaDevolucionIsTentativa && !empty($delayBadgeText))
            <div>
              <span class="badge {{ $delayBadgeClass }}">{{ $delayBadgeText }}</span>
            </div>
          @endif
        </td>

        <td>{{ $r->usuario->name ?? '—' }}</td>

        {{-- SALIDA: show + pdf — edit + delete (misma celda) --}}
        <td class="actions">
          {{-- izquierda --}}
          @can('celulares.view')
            <a href="{{ route('responsivas.show', $r) }}" title="Ver salida">
              <i class="fa-solid fa-eye"></i><span class="sr-only">Ver</span>
            </a>
            <a href="{{ route('responsivas.pdf', $r) }}" title="PDF salida" target="_blank" rel="noopener">
              <i class="fa-solid fa-file-pdf"></i><span class="sr-only">PDF</span>
            </a>
          @endcan

          {{-- separador solo si hay algo a la derecha --}}
          @if($canSalidaRight && auth()->user()?->can('celulares.view'))
            <span class="action-sep">—</span>
          @endif

          {{-- derecha --}}
          @can('celulares.edit')
            <a href="{{ route('responsivas.edit', $r) }}" title="Editar salida">
              <i class="fa-solid fa-pen"></i><span class="sr-only">Editar</span>
            </a>
          @endcan

          @can('celulares.delete')
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

        {{-- DEVOLUCIÓN: si no existe -> botón azul; si existe -> show + pdf — edit + delete --}}
        <td class="actions">
          @if(!$devo)
            @can('celulares.create')
              <a href="{{ route('celulares.devoluciones.create', ['responsiva_id' => $r->id]) }}"
                 class="btn-primary"
                 title="Crear devolución">
                <span>+ Nueva devolución</span>
              </a>
            @endcan
          @else
            {{-- izquierda --}}
            @can('celulares.view')
              <a href="{{ route('devoluciones.show', $devo) }}" title="Ver devolución">
                <i class="fa-solid fa-eye"></i><span class="sr-only">Ver</span>
              </a>
              <a href="{{ route('devoluciones.pdf', $devo) }}" title="PDF devolución" target="_blank" rel="noopener">
                <i class="fa-solid fa-file-pdf"></i><span class="sr-only">PDF</span>
              </a>
            @endcan

            {{-- separador solo si hay algo a la derecha --}}
            @if($canDevoRight && auth()->user()?->can('celulares.view'))
              <span class="action-sep">—</span>
            @endif

            {{-- derecha --}}
            @can('celulares.edit')
              <a href="{{ route('devoluciones.edit', $devo) }}" title="Editar devolución">
                <i class="fa-solid fa-pen"></i><span class="sr-only">Editar</span>
              </a>
            @endcan

            @can('celulares.delete')
              <form action="{{ route('devoluciones.destroy', $devo) }}?from=cel" method="POST" style="display:inline"
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
        <td colspan="8" style="padding:1rem;color:#6b7280;text-align:center;">
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
