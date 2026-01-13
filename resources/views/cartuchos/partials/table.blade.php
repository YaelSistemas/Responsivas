{{-- resources/views/cartuchos/partials/table.blade.php --}}
@php
  use Illuminate\Support\Carbon;
  use Illuminate\Pagination\AbstractPaginator;

  $cartuchos = $cartuchos
      ?? ($rows ?? $paginator ?? $lista ?? $records ?? $items ?? $data ?? $collection ?? collect());

  $isPaginator = $cartuchos instanceof AbstractPaginator;
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
  .sr-only{ position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }

  /* ===== SKU badges por color ===== */
  .sku-badges{ display:flex; flex-wrap:wrap; gap:6px; justify-content:center; }
  .sku-badge{
    display:inline-flex; align-items:center; justify-content:center;
    padding:2px 8px;
    border-radius:999px;
    font-weight:800;
    font-size:12px;
    line-height:1.3;
    border:1px solid transparent;
    white-space:nowrap;
  }
  .sku-badge.bk{ background:#111827; color:#ffffff; border-color:#111827; } /* Negro */
  .sku-badge.y { background:#FEF3C7; color:#92400E; border-color:#d97706; } /* Amarillo */
  .sku-badge.c { background:#CFFAFE; color:#155E75; border-color:#0284c7; } /* Cyan */
  .sku-badge.m { background:#FCE7F3; color:#9D174D; border-color:#db2777; } /* Magenta */
  .sku-badge.otro{ background:#F3F4F6; color:#374151; border-color:#E5E7EB; } /* fallback */
</style>

<table class="tbl">
  <colgroup>
    <col class="c-folio">
    <col class="c-fecha">
    <col class="c-colab">
    <col class="c-unidad">
    <col class="c-equipo">
    <col class="c-sku">
    <col class="c-realizo">
    <col class="c-acc">
  </colgroup>

  <thead>
    <tr>
      <th>Folio</th>
      <th>Fecha solicitud</th>
      <th>Colaborador</th>
      <th>Unidad de servicio</th>
      <th>Equipo</th>
      <th>SKU</th>
      <th>Realizado por</th>
      <th>Acciones</th>
    </tr>
  </thead>

  <tbody>
    @forelse ($cartuchos as $c)
      @php
        $fechaSol = !empty($c->fecha_solicitud)
          ? ($c->fecha_solicitud instanceof \Illuminate\Support\Carbon
              ? $c->fecha_solicitud->format('d-m-Y')
              : Carbon::parse($c->fecha_solicitud)->format('d-m-Y'))
          : '';

        $col = $c->colaborador ?? null;
        $apellidos = $col?->apellido
                  ?? $col?->apellidos
                  ?? trim(($col?->apellido_paterno ?? $col?->primer_apellido ?? '')
                        .' '.($col?->apellido_materno ?? $col?->segundo_apellido ?? ''));
        $colNombre = trim(trim($col?->nombre ?? '').' '.trim($apellidos ?? '')) ?: ($col?->nombre ?? '—');

        // ✅ Unidad de servicio
        $unidadTxt = '—';
        if ($col) {
          $unidadTxt =
              $col?->unidadServicio?->nombre
              ?? $col?->unidad_servicio
              ?? $col?->unidad
              ?? '—';
        }

        $producto = $c->producto ?? null;
        $equipoTxt = trim(($producto?->nombre ?? '').' '.($producto?->marca ?? '').' '.($producto?->modelo ?? ''));
        if($equipoTxt === '') $equipoTxt = '—';

        // ✅ SKUs en badges (color desde producto.especificaciones->color)
        $badges = collect();

        $detalles = $c->detalles ?? collect();
        foreach ($detalles as $d) {
          $p = $d->producto ?? null;
          if (!$p) continue;

          $sku = trim((string)($p->sku ?? ''));
          if ($sku === '') continue;

          $esp = $p->especificaciones ?? null;

          // puede venir como string JSON, array, stdClass, collection
          if (is_string($esp)) {
            $esp = json_decode($esp, true);
          } elseif ($esp instanceof \Illuminate\Support\Collection) {
            $esp = $esp->toArray();
          } elseif ($esp instanceof \stdClass) {
            $esp = (array) $esp;
          }

          $colorTxt = '';
          if (is_array($esp)) {
            $colorTxt = (string)($esp['color'] ?? $esp['Color'] ?? '');
          }

          $u = strtoupper($colorTxt);

          $cc = 'otro';
          if (str_contains($u, '(Y)') || str_contains($u, 'YELLOW') || str_contains($u, 'AMARIL')) $cc = 'y';
          elseif (str_contains($u, '(C)') || str_contains($u, 'CIAN') || str_contains($u, 'CYAN')) $cc = 'c';
          elseif (str_contains($u, '(M)') || str_contains($u, 'MAGENTA')) $cc = 'm';
          elseif (str_contains($u, '(BK)') || str_contains($u, 'NEGRO') || str_contains($u, 'BLACK')) $cc = 'bk';

          $badges->push([
            'sku'   => $sku,
            'cc'    => $cc,
            'title' => $colorTxt ?: $sku,
          ]);
        }

        $badges = $badges->unique('sku')->values();
        $skuTxt = $badges->count() ? $badges->pluck('sku')->implode(', ') : '—';

        // ✅ realizado por
        $realizo = $c->realizadoPor?->name ?? '—';
      @endphp

      <tr>
        <td title="{{ $c->folio }}">{{ $c->folio }}</td>
        <td title="{{ $fechaSol }}">{{ $fechaSol }}</td>
        <td title="{{ $colNombre }}">{{ $colNombre }}</td>
        <td title="{{ $unidadTxt }}">{{ $unidadTxt }}</td>
        <td title="{{ $equipoTxt }}">{{ $equipoTxt }}</td>

        <td title="{{ $skuTxt }}">
          @if($badges->count())
            <div class="sku-badges">
              @foreach($badges as $b)
                <span class="sku-badge {{ $b['cc'] }}" title="{{ $b['title'] }}">
                  {{ $b['sku'] }}
                </span>
              @endforeach
            </div>
          @else
            —
          @endif
        </td>

        <td title="{{ $realizo }}">{{ $realizo }}</td>

        <td class="actions">
          <a href="{{ route('cartuchos.show', $c) }}" title="Ver">
            <i class="fa-solid fa-eye"></i><span class="sr-only">Ver</span>
          </a>

          @can('cartuchos.edit')
            <a href="{{ route('cartuchos.edit', $c) }}" title="Editar">
              <i class="fa-solid fa-pen"></i><span class="sr-only">Editar</span>
            </a>
          @endcan

          @can('cartuchos.delete')
            <form action="{{ route('cartuchos.destroy', $c) }}" method="POST" style="display:inline"
                  onsubmit="return confirm('¿Eliminar este registro de cartuchos?')">
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
        <td colspan="8" class="text-center text-gray-500 py-6">Sin resultados.</td>
      </tr>
    @endforelse
  </tbody>
</table>

@if ($isPaginator)
  <div class="p-3">
    {{ $cartuchos->withQueryString()->links() }}
  </div>
@endif
