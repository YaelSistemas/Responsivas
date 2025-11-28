{{-- resources/views/oc/historial/modal.blade.php --}}
@php
  /** @var \App\Models\OrdenCompra $oc */
  /** @var \Illuminate\Support\Collection|\App\Models\OcLog[] $logs */

  function oc_event_title($log) {
      $t = $log->type;
      return match ($t) {
          'created'             => 'Creación de la OC',
          'updated'             => 'Edición de la OC',
          'state_changed', 'status_changed' => 'Cambio de estado',
          'recepcion_changed' => 'Cambio de recepción',
          'item_added'          => 'Partida agregada',
          'item_updated'        => 'Partida actualizada',
          'item_removed'        => 'Partida eliminada',
          'attachment_added'    => 'Adjunto agregado',
          'attachment_deleted', 'attachment_removed' => 'Adjunto eliminado',
          default               => ucfirst(str_replace('_',' ', (string)$t)),
      };
  }
@endphp

<style>
  .oc-modal-backdrop{
    position:fixed; inset:0; background:rgba(15,23,42,.5);
    display:flex; align-items:center; justify-content:center; z-index:9998;
  }
  .oc-modal{
    background:#fff; width:min(900px, 92vw); max-height:82vh; overflow:auto;
    border-radius:12px; box-shadow:0 20px 60px rgba(0,0,0,.25); z-index:9999;
  }
  .oc-modal header{
    display:flex; align-items:center; justify-content:space-between;
    padding:.9rem 1rem; border-bottom:1px solid #e5e7eb;
    position:sticky; top:0; background:#fff; z-index:1;
  }
  .oc-modal .close{
    border:1px solid #e5e7eb; background:#fff; border-radius:8px; padding:.25rem .55rem; cursor:pointer;
  }
  .oc-modal .close:hover{ background:#f3f4f6 }
  .oc-modal .content{ padding:1rem; }
  .timeline{ list-style:none; margin:0; padding:0; }
  .timeline li{
    padding: .85rem .6rem; border-left:3px solid #e5e7eb; margin-left: .6rem; position:relative;
  }
  .timeline li::before{
    content:""; position:absolute; left:-9px; top:1.1rem; width:10px; height:10px; border-radius:999px; background:#6366f1;
  }
  .ev-head{ display:flex; gap:.5rem; align-items:center; flex-wrap:wrap; }
  .ev-title{ font-weight:700; color:#111827; }
  .ev-meta{ font-size:.80rem; color:#6b7280; }
  .diff-table{ width:100%; border-collapse:collapse; margin-top:.5rem; }
  .diff-table th,.diff-table td{ border:1px solid #e5e7eb; padding:.35rem .45rem; font-size:.88rem; }
  .badge{
    display:inline-block; padding:.12rem .45rem; border-radius:9999px; font-size:.75rem; font-weight:700;
  }
  .b-blue{ background:#eff6ff; color:#1e40af; border:1px solid #bfdbfe }
  .b-green{ background:#dcfce7; color:#166534; border:1px solid #bbf7d0 }
  .b-red{ background:#fee2e2; color:#991b1b; border:1px solid #fecaca }
  .mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono","Courier New", monospace; }
  .b-gray{ background:#f3f4f6; color:#374151; border:1px solid #d1d5db; }
</style>

<div class="oc-modal-backdrop" data-modal-backdrop>
  <div class="oc-modal" role="dialog" aria-modal="true" aria-label="Historial de OC">
    <header>
      <div>
        <div class="ev-title">Historial — OC {{ $oc->numero_orden }}</div>
        <div class="ev-meta">ID #{{ $oc->id }}</div>
      </div>
      <button type="button" class="close" data-modal-close>✕</button>
    </header>

    <div class="content">
      @if($logs->isEmpty())
        <p class="ev-meta">Sin eventos.</p>
      @else
        <ul class="timeline">
          @foreach($logs->sortBy('id') as $log)
            @php
              $d = $log->data ?? [];
              $userName = $log->user->nombre ?? $log->user->name ?? 'Sistema';
              $when = optional($log->created_at)->format('d-m-Y H:i');
            @endphp
            <li>
              <div class="ev-head">
                <span class="ev-title">{{ oc_event_title($log) }}</span>
                <span class="ev-meta">— {{ $userName }} · {{ $when }}</span>
              </div>

              {{-- CONTENIDO SEGÚN TIPO --}}
              @switch($log->type)

                @case('created')
                  <div class="ev-meta">Orden creada con los siguientes datos:</div>
                  <table class="diff-table">
                    <tbody>
                      <tr><th>No. de orden</th><td class="mono">{{ $d['numero_orden'] ?? '' }}</td></tr>
                      <tr><th>Fecha</th><td>{{ $d['fecha'] ?? '' }}</td></tr>
                      <tr><th>Solicitante</th><td>{{ $d['solicitante'] ?? '' }}</td></tr>
                      <tr><th>Proveedor</th><td>{{ $d['proveedor'] ?? '' }}</td></tr>
                      <tr><th>Descripción</th><td>{{ $d['descripcion'] ?? '' }}</td></tr>
                      <tr><th>IVA %</th><td>{{ $d['iva_porcentaje'] ?? '' }}</td></tr>
                      <tr><th>Subtotal</th><td class="mono">{{ $d['subtotal'] ?? '' }}</td></tr>
                      <tr><th>IVA</th><td class="mono">{{ $d['iva'] ?? '' }}</td></tr>
                      <tr><th>Total</th><td class="mono">{{ $d['total'] ?? '' }}</td></tr>
                      <tr><th>Notas</th><td>{{ $d['notas'] ?? '' }}</td></tr>
                    </tbody>
                  </table>

                  @if(!empty($d['items']) && is_array($d['items']))
                    <div class="ev-meta" style="margin-top:.5rem;">Partidas:</div>
                    <table class="diff-table">
                      <thead>
                        <tr>
                          <th>#</th><th>Cant</th><th>U/M</th><th>Concepto</th><th>Moneda</th><th>Precio</th><th>Importe</th>
                        </tr>
                      </thead>
                      <tbody>
                        @foreach($d['items'] as $idx=>$it)
                          <tr>
                            <td class="mono">{{ $idx+1 }}</td>
                            <td class="mono">{{ $it['cantidad'] ?? '' }}</td>
                            <td>{{ $it['unidad'] ?? ($it['um'] ?? '') }}</td>
                            <td>{{ $it['concepto'] ?? '' }}</td>
                            <td>{{ $it['moneda'] ?? '' }}</td>
                            <td class="mono">{{ $it['precio'] ?? '' }}</td>
                            <td class="mono">{{ $it['importe'] ?? ($it['subtotal'] ?? '') }}</td>
                          </tr>
                        @endforeach
                      </tbody>
                    </table>
                  @endif
                @break

                @case('updated')
                @php
                    $changes = is_array($d) ? $d : [];
                    $labels = [
                    'numero_orden' => 'No. de orden',
                    'fecha'        => 'Fecha',
                    'solicitante'  => 'Solicitante',
                    'proveedor'    => 'Proveedor',
                    'descripcion'  => 'Descripción',
                    'notas'        => 'Notas',
                    'factura'      => 'Factura',
                    'estado'       => 'Estado',
                    'solicitante_id' => 'Solicitante',
                    'proveedor_id'   => 'Proveedor',
                    'iva_porcentaje' => 'IVA %',
                    'subtotal'       => 'Subtotal',
                    'iva_monto'      => 'IVA',
                    'monto'          => 'Total',
                    ];
                @endphp

                <div class="ev-meta">Datos actualizados:</div>

                @if(!empty($changes))
                    <table class="diff-table">
                    <thead><tr><th>Campo</th><th>De</th><th>A</th></tr></thead>
                    <tbody>
                        @foreach($changes as $field => $chg)
                        @php
                            $from = is_array($chg) ? ($chg['from'] ?? '') : '';
                            $to   = is_array($chg) ? ($chg['to']   ?? '') : '';
                        @endphp
                        <tr>
                            <td class="mono">{{ $labels[$field] ?? $field }}</td>
                            <td class="mono">
                            @if(is_array($from)) {{ json_encode($from, JSON_UNESCAPED_UNICODE) }} @else {{ $from }} @endif
                            </td>
                            <td class="mono">
                            @if(is_array($to))   {{ json_encode($to,   JSON_UNESCAPED_UNICODE) }} @else {{ $to }} @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    </table>
                @else
                    <div class="ev-meta">Sin detalle de cambios.</div>
                @endif
                @break

                @case('state_changed')
                @case('status_changed')
                  @php
                    $from = (string)($d['from'] ?? '');
                    $to   = (string)($d['to']   ?? '');

                    $color = function ($v) {
                        return match (strtolower($v)) {
                            'pagada'    => 'b-green',
                            'cancelada' => 'b-red',
                            default     => 'b-blue',
                        };
                    };

                    $clsFrom = $color($from);
                    $clsTo   = $color($to);
                  @endphp

                  <div class="ev-meta">
                    Estado:
                    <span class="badge {{ $clsFrom }}">{{ ucfirst($from) }}</span>
                    → <span class="badge {{ $clsTo }}">{{ ucfirst($to) }}</span>
                  </div>
                @break

                @case('recepcion_changed')
                    @php
                        $from = strtolower($d['from'] ?? '');
                        $to   = strtolower($d['to'] ?? '');

                        $color = function ($v) {
                            return match ($v) {
                                'recibido'      => 'b-green',
                                'sin recepcion' => 'b-gray',
                                default         => 'b-gray',
                            };
                        };

                        $clsFrom = $color($from);
                        $clsTo   = $color($to);
                    @endphp

                    <div class="ev-meta">
                        Recepción:
                        <span class="badge {{ $clsFrom }}">{{ ucfirst($from) }}</span>
                        →
                        <span class="badge {{ $clsTo }}">{{ ucfirst($to) }}</span>
                    </div>
                @break

                @case('item_added')
                  <div class="ev-meta" style="margin-bottom:.4rem;">Partida agregada:</div>

                  <table class="diff-table">
                    <thead>
                      <tr>
                        <th>Cant</th>
                        <th>U/M</th>
                        <th>Concepto</th>
                        <th>Moneda</th>
                        <th>Precio</th>
                        <th>Importe</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td class="mono">{{ $d['cantidad'] ?? '' }}</td>
                        <td>{{ $d['um'] ?? '' }}</td>
                        <td>{{ $d['concepto'] ?? '' }}</td>
                        <td>{{ $d['moneda'] ?? '' }}</td>
                        <td class="mono">{{ $d['precio'] ?? '' }}</td>
                        <td class="mono">{{ $d['importe'] ?? '' }}</td>
                      </tr>
                    </tbody>
                  </table>

                  {{-- Si existe snapshot de todas las partidas, mostrar la tabla completa --}}
                  @if(!empty($d['items']) && is_array($d['items']))
                    <div class="ev-meta" style="margin-top:.5rem;">Partidas totales actuales:</div>
                    <table class="diff-table">
                      <thead>
                        <tr>
                          <th>#</th>
                          <th>Cant</th>
                          <th>U/M</th>
                          <th>Concepto</th>
                          <th>Moneda</th>
                          <th>Precio</th>
                          <th>Importe</th>
                        </tr>
                      </thead>
                      <tbody>
                        @foreach($d['items'] as $idx=>$it)
                          <tr>
                            <td class="mono">{{ $idx+1 }}</td>
                            <td class="mono">{{ $it['cantidad'] ?? '' }}</td>
                            <td>{{ $it['unidad'] ?? ($it['um'] ?? '') }}</td>
                            <td>{{ $it['concepto'] ?? '' }}</td>
                            <td>{{ $it['moneda'] ?? '' }}</td>
                            <td class="mono">{{ $it['precio'] ?? '' }}</td>
                            <td class="mono">{{ $it['importe'] ?? ($it['subtotal'] ?? '') }}</td>
                          </tr>
                        @endforeach
                      </tbody>
                    </table>
                  @endif
                @break

                @case('item_updated')
                  <div class="ev-meta">Partida: {{ $d['concepto'] ?? ('ID '.$d['id'] ?? '') }}</div>
                  @if(!empty($d['changes']) && is_array($d['changes']))
                    <table class="diff-table">
                      <thead><tr><th>Campo</th><th>De</th><th>A</th></tr></thead>
                      <tbody>
                        @foreach($d['changes'] as $field=>$chg)
                          <tr>
                            <td class="mono">{{ $field }}</td>
                            <td class="mono">{{ $chg['from'] ?? '' }}</td>
                            <td class="mono">{{ $chg['to'] ?? '' }}</td>
                          </tr>
                        @endforeach
                      </tbody>
                    </table>
                  @endif
                @break

                @case('item_removed')
                  <div class="ev-meta">Partida eliminada:</div>
                  <table class="diff-table">
                    <tbody>
                      <tr><th>Cantidad</th><td class="mono">{{ $d['cantidad'] ?? '' }}</td></tr>
                      <tr><th>U/M</th><td>{{ $d['um'] ?? '' }}</td></tr>
                      <tr><th>Concepto</th><td>{{ $d['concepto'] ?? '' }}</td></tr>
                      <tr><th>Moneda</th><td>{{ $d['moneda'] ?? '' }}</td></tr>
                      <tr><th>Precio</th><td class="mono">{{ $d['precio'] ?? '' }}</td></tr>
                      <tr><th>Importe</th><td class="mono">{{ $d['importe'] ?? '' }}</td></tr>
                    </tbody>
                  </table>
                @break

                @case('attachment_added')
                  <div class="ev-meta">
                    Archivo agregado:
                    <span class="mono">{{ $d['name'] ?? '' }}</span>
                    <span class="ev-meta">({{ $d['mime'] ?? 'archivo' }}, {{ isset($d['size']) ? number_format($d['size']/1024,1) . ' KB' : '' }})</span>
                  </div>
                @break

                @case('attachment_removed')
                @case('attachment_deleted')
                  <div class="ev-meta">
                    Archivo eliminado:
                    <span class="mono">{{ $d['name'] ?? '' }}</span>
                    <span class="ev-meta">({{ $d['mime'] ?? 'archivo' }}, {{ isset($d['size']) ? number_format($d['size']/1024,1) . ' KB' : '' }})</span>
                  </div>
                @break

                @default
                  @if(!empty($d))
                    <pre class="mono" style="background:#f8fafc;border:1px solid #e5e7eb;padding:.5rem;border-radius:8px;white-space:pre-wrap">{{ json_encode($d, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
                  @else
                    <div class="ev-meta">Sin datos adicionales.</div>
                  @endif
              @endswitch
            </li>
          @endforeach
        </ul>
      @endif
    </div>
  </div>
</div>

<script>
  (function(){
    const bd = document.querySelector('[data-modal-backdrop]');
    const btn = document.querySelector('[data-modal-close]');
    function close(){ if(bd) bd.remove(); }
    if(btn) btn.addEventListener('click', close);
    if(bd) bd.addEventListener('click', (e)=>{ if(e.target === bd) close(); });
    document.addEventListener('keydown', (e)=>{ if(e.key === 'Escape') close(); }, {once:true});
  })();
</script>
