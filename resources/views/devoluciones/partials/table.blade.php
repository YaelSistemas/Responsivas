@php
  use Illuminate\Support\Carbon;
  use Illuminate\Pagination\AbstractPaginator;

  $devoluciones = $devoluciones
      ?? ($rows ?? $paginator ?? $lista ?? $records ?? $items ?? $data ?? $collection ?? collect());

  $isPaginator = $devoluciones instanceof AbstractPaginator;

  // 🔹 Ordenar por folio descendente (OES-0010, OES-0009, etc.)
  $devoluciones = $devoluciones->sortByDesc(function ($d) {
      return (int) preg_replace('/\D/', '', $d->folio);
  });
@endphp

<style>
  /* ===== Botones de acción ===== */
  .tbl td.actions a,
  .tbl td.actions button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .35rem;
    padding: .25rem .45rem;
    border-radius: .375rem;
    text-decoration: none;
    border: 1px solid transparent;
    background: #f9fafb;
    color: #1f2937;
  }
  .tbl td.actions a:hover,
  .tbl td.actions button:hover { background:#eef2ff;color:#1e40af; }
  .tbl td.actions .danger { background:#fef2f2;color:#991b1b; }
  .tbl td.actions .danger:hover { background:#fee2e2;color:#7f1d1d; }

  .sr-only {
    position:absolute;width:1px;height:1px;padding:0;margin:-1px;
    overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;
  }

  /* Alinear columnas de texto */
  .tbl td.c-colab, .tbl th.c-colab,
  .tbl td.c-psitio, .tbl th.c-psitio {
    text-align: left !important;
  }

  /* ===== Badges de motivo ===== */
  .badge {
    display:inline-block;
    padding:.18rem .55rem;
    border-radius:9999px;
    font-weight:700;
    font-size:.75rem;
    line-height:1;
    border:1px solid transparent;
  }
  .badge-red { background:#fee2e2; color:#991b1b; border-color:#fecaca; }     /* Baja colaborador */
  .badge-orange { background:#ffedd5; color:#9a3412; border-color:#fdba74; } /* Renovación */
</style>

<table class="tbl">
  <colgroup>
    <col class="c-folio">
    <col class="c-colab">
    <col class="c-area">
    <col class="c-motivo">
    <col class="c-resp">
    <col class="c-equipo">
    <col class="c-fdev">
    <col class="c-psitio">
    <col class="c-acc">
  </colgroup>

  <thead>
    <tr>
      <th>Folio</th>
      <th>Colaborador</th>
      <th>Unidad</th>
      <th>Motivo</th>
      <th>Responsiva</th>
      <th>Productos devueltos</th>
      <th>Fecha devolución</th>
      <th>Recibido en Sitio</th>
      <th>Acciones</th>
    </tr>
  </thead>

  <tbody>
    @forelse ($devoluciones as $d)
      @php
        $resp = $d->responsiva;
        $col  = $resp?->colaborador;
        $colNombre = trim(($col?->nombre ?? '') . ' ' . ($col?->apellidos ?? ''));

        // Unidad o área del colaborador
        $unidad = $col?->unidadServicio?->nombre ?? $col?->area?->nombre ?? '-';

        // Productos devueltos
        $productos = $d->productos ?? collect();
        $productosTxt = $productos->map(fn($p) =>
          trim(($p->nombre ?? '') . ' ' . ($p->marca ?? '') . ' ' . ($p->modelo ?? ''))
        )->filter()->implode(', ');
        if (empty($productosTxt)) $productosTxt = '-';

        $fechaDev = $d->fecha_devolucion
            ? Carbon::parse($d->fecha_devolucion)->format('d/m/Y')
            : '-';
      @endphp

      <tr>
        <td><strong>{{ $d->folio }}</strong></td>
        <td class="c-colab">{{ $colNombre }}</td>
        <td>{{ $unidad }}</td>
        <td>
          @if($d->motivo === 'baja_colaborador')
            <span class="badge badge-red">Baja Colaborador</span>
          @elseif($d->motivo === 'renovacion')
            <span class="badge badge-orange">Renovación</span>
          @else
            {{ ucfirst($d->motivo ?? '-') }}
          @endif
        </td>
        <td>{{ $resp?->folio ?? '-' }}</td>
        <td style="white-space:normal">{{ $productosTxt }}</td>
        <td>{{ $fechaDev }}</td>
        <td class="c-psitio">{{ $d->psitioColaborador?->nombre_completo ?? '-' }}</td>

        <td class="actions">
          <a href="{{ route('devoluciones.show', $d) }}" title="Ver">
              <i class="fa-solid fa-eye"></i><span class="sr-only">Ver</span>
          </a>
          <a href="{{ route('devoluciones.pdf', $d) }}" title="PDF" target="_blank" rel="noopener">
              <i class="fa-solid fa-file-pdf"></i><span class="sr-only">PDF</span>
          </a>
          <a href="{{ route('devoluciones.edit', $d) }}" title="Editar">
              <i class="fa-solid fa-pen"></i><span class="sr-only">Editar</span>
          </a>
          <form action="{{ route('devoluciones.destroy', $d) }}" method="POST" style="display:inline"
                onsubmit="return confirm('¿Eliminar esta devolución?')">
              @csrf @method('DELETE')
              <button type="submit" class="danger" title="Eliminar">
                <i class="fa-solid fa-trash"></i><span class="sr-only">Eliminar</span>
              </button>
          </form>
        </td>
      </tr>
    @empty
      <tr>
        <td colspan="10" class="text-center text-gray-500 py-6">Sin devoluciones registradas.</td>
      </tr>
    @endforelse
  </tbody>
</table>

@if ($devoluciones instanceof \Illuminate\Pagination\AbstractPaginator)
  <div class="p-3">
    {{ $devoluciones->withQueryString()->links() }}
  </div>
@endif
