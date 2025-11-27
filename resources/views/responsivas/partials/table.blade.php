{{-- resources/views/responsivas/partials/table.blade.php --}}
@php
  use Illuminate\Support\Carbon;
  use Illuminate\Pagination\AbstractPaginator;

  // Toma $responsivas si existe, o cae a otros nombres comunes:
  $responsivas = $responsivas
      ?? ($rows ?? $paginator ?? $lista ?? $records ?? $items ?? $data ?? $collection ?? collect());

  $isPaginator = $responsivas instanceof AbstractPaginator;
@endphp

<style>
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
</style>

<table class="tbl">
  {{-- Anchos: deben coincidir con el CSS del index --}}
  <colgroup>
    <col class="c-folio">   {{-- 8%  --}}
    <col class="c-fsol">    {{-- 9%  --}}
    <col class="c-colab">   {{-- 20% --}}
    <col class="c-area">    {{-- 9%  --}}
    <col class="c-motivo">  {{-- 10% --}}
    <col class="c-equipo">  {{-- 20% --}}
    <col class="c-entrega"> {{-- 7%  --}}
    <col class="c-fent">    {{-- 9%  --}}
    <col class="c-hist">   {{-- 4%  --}}
    <col class="c-acc">     {{-- 4%  --}}
  </colgroup>

  <thead>
    <tr>
      <th>Folio</th>
      <th>Fecha solicitud</th>
      <th>Colaborador</th>
      <th>Unidad de servicio</th> {{-- ← cambiada etiqueta --}}
      <th>Motivo</th>
      <th>Equipo</th>
      <th>Entrega por</th>
      <th>Fecha entrega</th>
      <th>Historial</th>
      <th>Acciones</th>
    </tr>
  </thead>

  <tbody>
    @forelse ($responsivas as $r)
      @php
        // Fechas (d-m-Y)
        $fechaSol = !empty($r->fecha_solicitud)
          ? ($r->fecha_solicitud instanceof \Illuminate\Support\Carbon
              ? $r->fecha_solicitud->format('d-m-Y')
              : Carbon::parse($r->fecha_solicitud)->format('d-m-Y'))
          : '';

        $fechaEnt = !empty($r->fecha_entrega)
          ? ($r->fecha_entrega instanceof \Illuminate\Support\Carbon
              ? $r->fecha_entrega->format('d-m-Y')
              : Carbon::parse($r->fecha_entrega)->format('d-m-Y'))
          : '';

        // Colaborador (nombre + apellidos)
        $col = $r->colaborador ?? null;
        $apellidos = $col?->apellido
                  ?? $col?->apellidos
                  ?? trim(($col?->apellido_paterno ?? $col?->primer_apellido ?? '')
                        .' '.($col?->apellido_materno ?? $col?->segundo_apellido ?? ''));
        $colNombre = trim(trim($col?->nombre ?? '').' '.trim($apellidos ?? '')) ?: ($col?->nombre ?? '');

        // ===== Unidad de servicio (con fallbacks a Área/Depto/Sede) =====
        $unidadServicio = $col?->unidad_servicio
                        ?? $col?->unidadServicio
                        ?? $col?->unidad
                        ?? $col?->servicio
                        ?? '';

        if (is_object($unidadServicio)) {
          $unidadTxt = $unidadServicio->nombre
                    ?? $unidadServicio->name
                    ?? $unidadServicio->descripcion
                    ?? (string) $unidadServicio;
        } elseif (is_array($unidadServicio)) {
          $unidadTxt = implode(' ', array_filter($unidadServicio));
        } else {
          $unidadTxt = (string) $unidadServicio;
        }

        // Si sigue vacío, usar Área/Departamento/Sede como respaldo
        if ($unidadTxt === '') {
          $areaObj = $col?->area ?? $col?->departamento ?? $col?->sede ?? null;
          if (is_object($areaObj)) {
            $unidadTxt = $areaObj->nombre ?? $areaObj->name ?? $areaObj->descripcion ?? (string)$areaObj;
          } elseif (is_array($areaObj)) {
            $unidadTxt = implode(' ', array_filter($areaObj));
          } else {
            $unidadTxt = (string)($areaObj ?? '');
          }
        }

        // Motivo + badge
        $motivo = $r->motivo_entrega;
        $motivoTxt = $motivo === 'asignacion'
          ? 'Asignado'
          : ($motivo === 'prestamo_provisional' ? 'Préstamo provisional' : '');
        $motivoClase = $motivo === 'asignacion' ? 'badge-green' : 'badge-yellow';

        // Equipo (nombres únicos, hasta 2 + “+n”)
        $det = collect($r->detalles ?? []);
        $nItems = $det->count();
        $nombres = $det->map(fn($d)=> trim($d->producto->nombre ?? ''))
                       ->filter()->unique()->values();
        $equipoTxt = $nombres->take(2)->implode(', ');
        if ($nombres->count() > 2) $equipoTxt .= ' +'.($nombres->count()-2);

        // Entrega por (usuario eager-loaded en el index)
        $entregoNombre = $r->usuario->name ?? '';
      @endphp

      <tr>
        <td title="{{ $r->folio }}">{{ $r->folio }}</td>
        <td title="{{ $fechaSol }}">{{ $fechaSol }}</td>
        <td title="{{ $colNombre }}">{{ $colNombre }}</td>
        <td title="{{ $unidadTxt }}">{{ $unidadTxt }}</td> {{-- ← ahora imprime la unidad --}}
        <td>
          @if($motivoTxt)
            <span class="badge {{ $motivoClase }}">{{ $motivoTxt }}</span>
          @endif
        </td>
        <td title="{{ $equipoTxt }}">{{ $equipoTxt }}</td>
        <td title="{{ $entregoNombre }}">{{ $entregoNombre }}</td>
        <td title="{{ $fechaEnt }}">{{ $fechaEnt }}</td>
        <td class="text-center">
          <button type="button"
                  class="btn btn-sm"
                  onclick="openHistorialResponsiva({{ $r->id }})"
                  title="Ver historial">
            Historial
          </button>
        </td>
        <td class="actions">
          <a href="{{ route('responsivas.show', $r) }}" title="Ver">
            <i class="fa-solid fa-eye"></i><span class="sr-only">Ver</span>
          </a>
          <a href="{{ route('responsivas.pdf', $r) }}" title="PDF" target="_blank" rel="noopener">
            <i class="fa-solid fa-file-pdf"></i><span class="sr-only">PDF</span>
          </a>
          @can('responsivas.edit')
            <a href="{{ route('responsivas.edit', $r) }}" title="Editar">
              <i class="fa-solid fa-pen"></i><span class="sr-only">Editar</span>
            </a>
          @endcan
          @can('responsivas.delete')
            <form action="{{ route('responsivas.destroy', $r) }}" method="POST" style="display:inline"
                  onsubmit="return confirm('¿Eliminar esta responsiva?')">
              @csrf @method('DELETE')
              <button type="submit" class="danger" title="Eliminar">
                <i class="fa-solid fa-trash"></i><span class="sr-only">Eliminar</span>
              </button>
            </form>
          @endcan
        </td>
      </tr>
    @empty
      <tr>
        <td colspan="10" class="text-center text-gray-500 py-6">Sin resultados.</td>
      </tr>
    @endforelse
  </tbody>
</table>

@if ($isPaginator)
  <div class="p-3">
    {{ $responsivas->withQueryString()->links() }}
  </div>
@endif
