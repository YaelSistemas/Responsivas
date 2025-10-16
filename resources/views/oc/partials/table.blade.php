@php
  use Illuminate\Support\Carbon;
  use Illuminate\Pagination\AbstractPaginator;

  $ocs = $ocs ?? ($rows ?? $paginator ?? $items ?? collect());
  $isPaginator = $ocs instanceof AbstractPaginator;
  $isAdmin = auth()->user()?->hasRole('Administrador');
@endphp

<style>
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

  .is-disabled{ opacity:.55; cursor:not-allowed; pointer-events:none; }
  .sr-only{ position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
  .tbl td.desc{ white-space:normal; overflow:visible; text-overflow:unset; line-height:1.15; }
  .muted{ color:#6b7280; }

  /* ===== SELECT como píldora (sin flecha, tamaño fijo) ===== */
  .tag-select{
    appearance:none; -webkit-appearance:none; -moz-appearance:none;
    background-image:none;
    display:block; margin:0 auto; box-sizing:border-box;
    border-radius:9999px; cursor:pointer;
    font-size:.75rem; font-weight:600; line-height:1.1;
    width:100px; height:28px;                     /* tamaño fijo */
    padding:.20rem 1.25rem .20rem .60rem;         /* espacio a la derecha */
    text-align-last:center; border:1px solid transparent;
  }
  .tag-select::-ms-expand{ display:none; }

  /* SPAN de solo lectura con el MISMO tamaño que el select */
  .tag-readonly{
    display:inline-block; box-sizing:border-box;
    width:100px; height:28px;                     /* mismo tamaño */
    padding:.20rem 1.25rem .20rem .60rem;
    border-radius:9999px; font-size:.75rem; font-weight:600; line-height:1.1;
    text-align:center; border:1px solid transparent;
  }

  /* Colores */
  .tag-blue  { background:#e0f2fe; color:#075985; border-color:#bae6fd; }  /* Abierta */
  .tag-green { background:#dcfce7; color:#166534; border-color:#bbf7d0; }  /* Pagada */
  .tag-red   { background:#fee2e2; color:#991b1b; border-color:#fecaca; }  /* Cancelada */

  .tag-select:focus{ outline:none; box-shadow:0 0 0 3px rgba(59,130,246,.25); }

  /* Columna Estado compacta y centrada */
  .tbl col.c-estado { width:7% }
  .tbl td.estado{
    text-align:center; padding-left:.35rem; padding-right:.35rem;
  }
</style>

<table class="tbl">
  <colgroup>
    <col class="c-no">
    <col class="c-fecha">
    <col class="c-soli">
    <col class="c-prov">
    <col class="c-conceptos">
    <col class="c-desc">
    <col class="c-monto">
    <col class="c-fact">
    <col class="c-estado">
    @if($isAdmin)
      <col class="c-creo">
      <col class="c-edito">
    @endif
    <col class="c-acc">
  </colgroup>

  <thead>
    <tr>
      <th>No. orden</th>
      <th>Fecha</th>
      <th>Solicitante</th>
      <th>Proveedor</th>
      <th>Conceptos</th>
      <th>Descripción</th>
      <th>Monto</th>
      <th>Factura</th>
      <th>Estado</th>
      @if($isAdmin)
        <th>Creada por</th>
        <th>Editada por</th>
      @endif
      <th>Acciones</th>
    </tr>
  </thead>

  <tbody>
  @forelse ($ocs as $oc)
    @php
      $f = $oc->fecha
        ? ($oc->fecha instanceof \Illuminate\Support\Carbon
            ? $oc->fecha->format('d-m-Y')
            : Carbon::parse($oc->fecha)->format('d-m-Y'))
        : '';

      $sol = $oc->solicitante ?? null;
      $apellidos = $sol?->apellido
          ?? $sol?->apellidos
          ?? trim(($sol?->apellido_paterno ?? $sol?->primer_apellido ?? '')
                 .' '.($sol?->apellido_materno ?? $sol?->segundo_apellido ?? ''));
      $solNombre = trim(trim($sol?->nombre ?? '').' '.trim($apellidos ?? ''));

      $prov = $oc->proveedor;
      $provNombre = $prov?->nombre ?? '—';

      $conceptos = collect($oc->detalles ?? [])
        ->pluck('concepto')
        ->filter(fn($c) => filled($c))
        ->map(fn($c) => trim($c))
        ->unique()
        ->implode(', ');

      $monto = is_numeric($oc->monto) ? number_format($oc->monto, 2) : ($oc->monto ?? '0.00');
      $fact  = $oc->factura ?: '—';
      $desc  = filled($oc->descripcion) ? trim($oc->descripcion) : '—';

      $creadorNombre = $oc->creator?->nombre ?? $oc->creator?->name ?? '—';
      $editorNombre  = $oc->updater?->nombre ?? $oc->updater?->name ?? '—';

      $creadaEn = optional($oc->created_at)->format('d-m-Y H:i');
      $editadaEn = optional($oc->updated_at)->format('d-m-Y H:i');

      $puedeCambiar = auth()->user()->hasAnyRole(['Administrador', 'Compras Superior']);
      $canView   = auth()->user()->can('oc.view');
      $canEdit   = auth()->user()->can('oc.edit');
      $canDelete = auth()->user()->can('oc.delete');
    @endphp

    <tr data-row-id="{{ $oc->id }}">
      <td title="{{ $oc->numero_orden }}">{{ $oc->numero_orden }}</td>
      <td title="{{ $f }}">{{ $f }}</td>
      <td title="{{ $solNombre }}">{{ $solNombre }}</td>
      <td title="{{ $provNombre }}">{{ $provNombre ?: '—' }}</td>
      <td class="desc" title="{{ $conceptos }}">{{ $conceptos }}</td>
      <td class="desc" title="{{ $desc }}">{{ $desc }}</td>
      <td title="{{ $monto }}">${{ $monto }}</td>
      <td title="{{ $fact }}">{{ $fact }}</td>

      {{-- Estado --}}
      <td class="estado">
        @if ($puedeCambiar)
          <select data-estado
                  class="tag-select {{ $oc->estado_class }}"
                  data-url="{{ route('oc.estado', $oc) }}"
                  title="Cambiar estado">
            @foreach (\App\Models\OrdenCompra::ESTADOS as $opt)
              <option value="{{ $opt }}" {{ $oc->estado === $opt ? 'selected' : '' }}>
                {{ ucfirst($opt) }}
              </option>
            @endforeach
          </select>
        @else
          <span class="tag-readonly {{ $oc->estado_class }}">{{ $oc->estado_label }}</span>
        @endif
      </td>

      @if($isAdmin)
        <td title="Creada: {{ $creadaEn }}">{{ $creadorNombre }}</td>
        <td title="Editada: {{ $editadaEn }}">{{ $editorNombre }}</td>
      @endif

      <td class="actions">
        @can('oc.view')
          <a href="{{ route('oc.show', $oc) }}" title="Ver">
            <i class="fa-solid fa-eye"></i><span class="sr-only">Ver</span>
          </a>
        @endcan
        @can('oc.edit')
          <a href="{{ route('oc.edit', $oc) }}" title="Editar">
            <i class="fa-solid fa-pen"></i><span class="sr-only">Editar</span>
          </a>
        @endcan
        @can('oc.delete')
          <form action="{{ route('oc.destroy', $oc) }}" method="POST" style="display:inline"
                onsubmit="return confirm('¿Eliminar esta orden?')">
            @csrf @method('DELETE')
            <button type="submit" class="danger" title="Eliminar">
              <i class="fa-solid fa-trash"></i><span class="sr-only">Eliminar</span>
            </button>
          </form>
        @endcan

        @if (!($canView || $canEdit || $canDelete))
          <span class="muted">—</span>
        @endif
      </td>
    </tr>
  @empty
    <tr>
      <td colspan="{{ $isAdmin ? 12 : 10 }}" class="text-center text-gray-500 py-6">Sin resultados.</td>
    </tr>
  @endforelse
  </tbody>
</table>

@if ($isPaginator)
  <div class="p-3">
    {{ $ocs->withQueryString()->links() }}
  </div>
@endif
