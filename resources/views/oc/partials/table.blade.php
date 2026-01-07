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

  /* Colores (unificados con borde) */
  .tag-blue  { background:#e0f2fe; color:#075985; border:1px solid #bae6fd; }  /* Abierta */
  .tag-green { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }  /* Pagada */
  .tag-red   { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }  /* Cancelada */

  .tag-select:focus{ outline:none; box-shadow:0 0 0 3px rgba(59,130,246,.25); }

  .tag-gray{
    background:#f3f4f6;
    color:#6b7280;
    border:1px solid #e5e7eb;
  }

  /* Columna Estado compacta y centrada */
  .tbl col.c-estado { width:7% }
  .tbl col.c-recepcion { width:10% !important; }
  .tbl td.estado{
    text-align:center; padding-left:.35rem; padding-right:.35rem;
    padding-top:.4rem; padding-bottom:.4rem;
  }

  /* ===== Factura / Clip ===== */
  .tbl td.factura{ text-align:center; }
  .clip-btn{
    display:inline-flex; align-items:center; justify-content:center;
    gap:.25rem; border-radius:9999px; border:1px solid transparent;
    padding:.25rem .55rem; line-height:1; font-weight:600;
    transition:all .2s ease; background:#f3f4f6; color:#9ca3af; border-color:#e5e7eb;
    cursor:pointer;
  }
  .clip-btn .clip-count{ font-size:.8rem; line-height:1; }
  .clip-btn.has-adj{
    background:#eef2ff; color:#3730a3; border-color:#c7d2fe;
  }
  .clip-btn.has-adj:hover{ background:#e0e7ff; }
  .clip-btn.no-adj{ /* gris */ }

  /* === FIX para que no se corte "Sin recepcion" === */
  .tbl td.estado {
      white-space: normal !important;
      overflow: visible !important;
      text-overflow: initial !important;
  }
  /* Aumentar espacio para que quepa “Sin recepcion” */
  .tag-select, .tag-readonly {
      padding-left: .80rem !important;
      padding-right: 1.40rem !important;
      width: 120px !important;   /* <- un poco más ancho */
  }
</style>

<table class="tbl">
  <colgroup>
    <col class="c-no">
    <col class="c-fecha">
    <col class="c-soli">
    <col class="c-prov">
    {{-- <col class="c-conceptos"> --}}
    <col class="c-desc">
    <col class="c-monto">
    <col class="c-fact">
    <col class="c-estado">
    <col class="c-recepcion">
    @if($isAdmin)
      <col class="c-hist">
    @endif
    <col class="c-acc">
  </colgroup>

  <thead>
    <tr>
      <th>No. orden</th>
      <th>Fecha</th>
      <th>Solicitante</th>
      <th>Proveedor</th>
      {{-- <th>Conceptos</th> --}}
      <th>Descripción</th>
      <th>Monto</th>
      <th>Adjuntos</th>
      <th>Estado</th>
      <th>Recepción</th>
      @if($isAdmin)
        <th>Historial</th>
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

      /*$conceptos = collect($oc->detalles ?? [])
        ->pluck('concepto')
        ->filter(fn($c) => filled($c))
        ->map(fn($c) => trim($c))
        ->unique()
        ->implode(', ');*/

      $monto = is_numeric($oc->monto) ? number_format($oc->monto, 2) : ($oc->monto ?? '0.00');
      $fact  = $oc->factura ?: '—';
      $desc  = filled($oc->descripcion) ? trim($oc->descripcion) : '—';

      $creadorNombre = $oc->creator?->nombre ?? $oc->creator?->name ?? '—';
      $editorNombre  = $oc->updater?->nombre ?? $oc->updater?->name ?? '—';

      $creadaEn = optional($oc->created_at)->format('d-m-Y H:i');
      $editadaEn = optional($oc->updated_at)->format('d-m-Y H:i');

      $puedeCambiar = auth()->user()->hasAnyRole(['Administrador', 'Compras Superior', 'Compras', 'Compras IVA']);
      $puedeAdjuntar = $puedeCambiar || auth()->user()->can('oc.edit');

      // contador con withCount() o fallback
      $adjCount = $oc->adjuntos_count ?? (method_exists($oc, 'adjuntos') ? $oc->adjuntos()->count() : 0);
      $hasAdj = $adjCount > 0;

      $canView   = auth()->user()->can('oc.view');
      $canEdit   = auth()->user()->can('oc.edit');
      $canDelete = auth()->user()->can('oc.delete');
    @endphp

    <tr data-row-id="{{ $oc->id }}">
      <td title="{{ $oc->numero_orden }}">{{ $oc->numero_orden }}</td>
      <td title="{{ $f }}">{{ $f }}</td>
      <td title="{{ $solNombre }}">{{ $solNombre }}</td>
      <td title="{{ $provNombre }}">{{ $provNombre ?: '—' }}</td>
      {{-- <td class="desc" title="{{ $conceptos }}">{{ $conceptos }}</td> --}}
      <td class="desc" title="{{ $desc }}">{{ $desc }}</td>
      <td title="{{ $monto }}">${{ $monto }}</td>

      {{-- ===== Factura + Clip ===== --}}
      <td class="factura">
        <button
          type="button"
          class="clip-btn {{ $hasAdj ? 'has-adj' : 'no-adj' }}"
          data-open-adjuntos="{{ route('oc.adjuntos.modal', $oc) }}"
          title="{{ $hasAdj ? 'Ver adjuntos' : 'Sin archivos adjuntos (clic para agregar)' }}"
        >
          📎
          @if($hasAdj)
            <span class="clip-count">{{ $adjCount }}</span>
          @endif
        </button>
      </td>

      {{-- ===== Estado ===== --}}
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

      {{-- ===== Recepción ===== --}}
      <td class="estado">
        @if ($puedeCambiar)
          <select data-recepcion
                  class="tag-select {{ $oc->recepcion_class }}"
                  data-url="{{ route('oc.recepcion', $oc) }}"
                  title="Cambiar recepción">
            @foreach (\App\Models\OrdenCompra::RECEPCIONES as $opt)
              <option value="{{ $opt }}" {{ $oc->recepcion === $opt ? 'selected' : '' }}>
                {{ ucfirst(str_replace('_', ' ', $opt)) }}
              </option>
            @endforeach
          </select>
        @else
          <span class="tag-readonly {{ $oc->recepcion_class }}">
            {{ $oc->recepcion_label }}
          </span>
        @endif
      </td>

      @if($isAdmin)
        <td class="text-center">
          <button type="button"
                  class="btn btn-sm"
                  data-open-historial="{{ route('oc.historial.modal', $oc) }}"
                  title="Ver historial">
                    Historial
          </button>
        </td>

      @endif

      <td class="actions">
        @can('oc.view')
          <a href="{{ route('oc.show', $oc) }}" title="Ver">
            <i class="fa-solid fa-eye"></i><span class="sr-only">Ver</span>
          </a>
          <a href="{{ route('oc.pdf.open', $oc) }}" title="Ver PDF" target="_blank">
            <i class="fa-solid fa-file-pdf"></i><span class="sr-only">PDF</span>
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
