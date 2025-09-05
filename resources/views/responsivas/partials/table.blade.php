{{-- resources/views/responsivas/partials/table.blade.php --}}
@php
  use Illuminate\Support\Carbon;
  use Illuminate\Pagination\AbstractPaginator;

  // Robustez: toma $responsivas si existe, o cae a otros nombres comunes.
  $responsivas = $responsivas
      ?? ($rows ?? $paginator ?? $lista ?? $records ?? $items ?? $data ?? $collection ?? collect());

  $isPaginator = $responsivas instanceof AbstractPaginator;
@endphp

<style>
  /* pequeños estilos para los iconos de acciones */
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
  {{-- Distribución de anchos para que COLABORADOR y EQUIPO queden parecidos --}}
  <colgroup>
    <col class="c-folio">     {{-- 9%  --}}
    <col class="c-fsol">      {{-- 9%  --}}
    <col class="c-colab">     {{-- 17% --}}
    <col class="c-area">      {{-- 10% --}}
    <col class="c-motivo">    {{-- 11% --}}
    <col class="c-equipo">    {{-- 17% --}}
    <col class="c-entrega">   {{-- 9%  --}}
    <col class="c-fent">      {{-- 9%  --}}
    <col class="c-items">     {{-- 4%  --}}
    <col class="c-acc">       {{-- 5%  --}}
  </colgroup>

  <thead>
    <tr>
      <th>Folio</th>
      <th>Fecha solicitud</th>
      <th>Colaborador</th>
      <th>Área</th>
      <th>Motivo</th>
      <th>Equipo</th>
      <th>Entrega por</th>
      <th>Fecha entrega</th>
      <th>Items</th>
      <th>Acciones</th>
    </tr>
  </thead>
  <tbody>
    @forelse ($responsivas as $r)
      @php
        // Fechas d-m-Y (sin hora)
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

        // Área/Depto/Sede
        $areaObj = $col?->area ?? $col?->departamento ?? $col?->sede ?? null;
        if (is_object($areaObj)) {
          $areaTxt = $areaObj->nombre ?? $areaObj->name ?? $areaObj->descripcion ?? (string)$areaObj;
        } elseif (is_array($areaObj)) {
          $areaTxt = implode(' ', array_filter($areaObj));
        } else {
          $areaTxt = (string)($areaObj ?? '');
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

        // Entrega por
        $entregoNombre = '';
        if (!empty($r->entrego) && !is_string($r->entrego)) {
          $entregoNombre = $r->entrego->name ?? '';
        } elseif (!empty($r->entrego_user_id) && class_exists(\App\Models\User::class)) {
          $entregoNombre = \App\Models\User::find($r->entrego_user_id)?->name ?? '';
        }
      @endphp

      <tr>
        <td title="{{ $r->folio }}">{{ $r->folio }}</td>
        <td title="{{ $fechaSol }}">{{ $fechaSol }}</td>
        <td title="{{ $colNombre }}">{{ $colNombre }}</td>
        <td title="{{ $areaTxt }}">{{ $areaTxt }}</td>
        <td>
          @if($motivoTxt)
            <span class="badge {{ $motivoClase }}">{{ $motivoTxt }}</span>
          @endif
        </td>
        <td title="{{ $equipoTxt }}">{{ $equipoTxt }}</td>
        <td title="{{ $entregoNombre }}">{{ $entregoNombre }}</td>
        <td title="{{ $fechaEnt }}">{{ $fechaEnt }}</td>
        <td>{{ $nItems }}</td>
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
      <tr><td colspan="10" class="text-center text-gray-500 py-6">Sin resultados.</td></tr>
    @endforelse
  </tbody>
</table>

@if ($isPaginator)
  <div class="p-3">
    {{ $responsivas->withQueryString()->links() }}
  </div>
@endif
