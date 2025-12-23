{{-- resources/views/proveedores/partials/table.blade.php --}}
@php
  use Illuminate\Pagination\AbstractPaginator;
  $proveedores = $proveedores ?? ($rows ?? $paginator ?? $items ?? collect());
  $isPaginator = $proveedores instanceof AbstractPaginator;
@endphp

<style>
  .tbl td.actions a,
  .tbl td.actions button{
    display:inline-flex; align-items:center; justify-content:center;
    gap:.35rem; padding:.25rem .45rem; border-radius:.375rem;
    text-decoration:none; border:1px solid transparent;
    background:#f9fafb; color:#1f2937;
  }
  .tbl td.actions a:hover,
  .tbl td.actions button:hover{
    background:#eef2ff; color:#1e40af;
  }
  .tbl td.actions .danger{
    background:#fef2f2; color:#991b1b;
  }
  .tbl td.actions .danger:hover{
    background:#fee2e2; color:#7f1d1d;
  }
  .sr-only{
    position:absolute; width:1px; height:1px; padding:0; margin:-1px;
    overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0;
  }

  /* Anchos de columnas: ahora hay 5 */
  .tbl col.c-nom{   width:22% }
  .tbl col.c-rfc{   width:12% }
  .tbl col.c-addr{  width:42% }
  .tbl col.c-estado{width:12% }
  .tbl col.c-acc{   width:12% }

  .tbl td.addr{ white-space:normal; line-height:1.15; }
  .tbl td.addr .ubicacion{
    display:block; color:#6b7280; margin-top:.15rem;
  }

  /* Pastillitas de estado */
  .badge-estado{
    display:inline-flex; align-items:center; justify-content:center;
    padding:.15rem .55rem; border-radius:999px;
    font-size:.75rem; font-weight:600;
  }
  .badge-activo{
    background:#dcfce7; color:#166534;   /* verde */
  }
  .badge-inactivo{
    background:#fee2e2; color:#991b1b;   /* rojo */
  }
</style>

<table class="tbl">
  <colgroup>
    <col class="c-nom">
    <col class="c-rfc">
    <col class="c-addr">
    <col class="c-estado"><!-- NUEVA COLUMNA -->
    <col class="c-acc">
  </colgroup>

  <thead>
    <tr>
      <th>Nombre</th>
      <th>RFC</th>
      <th>Dirección / Ubicación</th>
      <th>Estado</th>     {{-- NUEVA COLUMNA --}}
      <th>Acciones</th>
    </tr>
  </thead>

  <tbody>
  @forelse ($proveedores as $p)
    @php
      // Normalizar datos de dirección (igual que antes)
      $coloniaRaw = trim((string)($p->colonia ?? ''));
      $coloniaNorm = $coloniaRaw !== ''
        ? preg_replace('/^\s*(col(?:onia)?|col\.)[\s.:,-]*/i', '', $coloniaRaw)
        : '';

      $cpRaw = trim((string)($p->codigo_postal ?? ''));
      $cpNorm = $cpRaw !== ''
        ? preg_replace('/^\s*(c\s*\.?\s*p\s*\.?|cp)[\s.:,-]*/i', '', $cpRaw)
        : '';

      $direccion = trim(
        ($p->calle ?: '').
        ($coloniaNorm ? ( ($p->calle ? ', ' : '').'Col. '.$coloniaNorm) : '').
        ($cpNorm ? (', C.P. '.$cpNorm) : '')
      );

      $ubicacion = trim(
        ($p->ciudad ?: '').
        ($p->estado ? ( ($p->ciudad ? ', ' : '').$p->estado) : '')
      );

      $addrHtml = $direccion !== ''
        ? e($direccion).($ubicacion !== '' ? '<span class="ubicacion">'.e($ubicacion).'</span>' : '')
        : ($ubicacion !== '' ? e($ubicacion) : '—');
    @endphp
    <tr>
      <td title="{{ $p->nombre }}">{{ $p->nombre }}</td>
      <td title="{{ $p->rfc }}">{{ $p->rfc ?: '—' }}</td>
      <td class="addr">{!! $addrHtml !!}</td>

      {{-- ESTADO --}}
      <td>
        @if($p->activo)
          <span class="badge-estado badge-activo">Activo</span>
        @else
          <span class="badge-estado badge-inactivo">Inactivo</span>
        @endif
      </td>

      <td class="actions">
        @can('proveedores.edit')
          <a href="{{ route('proveedores.edit', $p) }}" title="Editar">
            <i class="fa-solid fa-pen"></i><span class="sr-only">Editar</span>
          </a>
        @endcan
        @can('proveedores.delete')
          <form action="{{ route('proveedores.destroy', $p) }}" method="POST" style="display:inline"
                onsubmit="return confirm('¿Eliminar este proveedor?')">
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
      <td colspan="5" class="text-center text-gray-500 py-6">Sin resultados.</td>
    </tr>
  @endforelse
  </tbody>
</table>

@if ($isPaginator)
  <div class="p-3">
    {{ $proveedores->withQueryString()->links() }}
  </div>
@endif
