<style> 
  .resp-modal-backdrop {
    position: fixed; inset: 0;
    background: rgba(15, 23, 42, 0.5);
    display: flex; align-items: center; justify-content: center;
    z-index: 9998;
  }

  .resp-modal {
    background: #fff;
    width: min(900px, 92vw);
    max-height: 80vh;
    overflow: auto;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0,0,0,.25);
    z-index: 9999;
  }

  .resp-modal header {
    display: flex; align-items: center; justify-content: space-between;
    padding: .9rem 1rem;
    border-bottom: 1px solid #e5e7eb;
    background: #fff;
    position: sticky; top: 0; z-index: 1;
  }

  .resp-modal .close {
    border: 1px solid #e5e7eb;
    background: #fff; border-radius: 8px;
    padding: .25rem .55rem; cursor: pointer;
  }
  .resp-modal .close:hover { background: #f3f4f6; }

  .resp-modal .content { padding: 1rem; }

  .timeline { list-style: none; margin: 0; padding: 0; }
  .timeline li {
    padding: .85rem .6rem;
    border-left: 3px solid #e5e7eb;
    margin-left: .6rem;
    position: relative;
  }
  .timeline li::before {
    content: "";
    position: absolute;
    left: -9px;
    top: 1.1rem;
    width: 10px; height: 10px;
    border-radius: 999px;
    background: #3b82f6;
  }

  .timeline li.asignacion::before { background:#22c55e; }
  .timeline li.edicion_asignacion::before { background:#3b82f6; }
  .timeline li.removido_edicion::before { background:#ef4444; }

  .ev-head { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; }
  .ev-title { font-weight: 700; color: #111827; }
  .ev-meta { font-size: .80rem; color: #6b7280; }

  .badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 9999px;
    font-size: 12px;
    font-weight: 600;
  }
  .badge-asignacion { color:#166534; background:#dcfce7; border-color:#86efac; }
  .badge-prestamo   { color:#92400e; background:#fef3c7; border-color:#fcd34d; }
  .badge-fallo      { color:#991b1b; background:#fee2e2; border-color:#fecaca; }
  .badge-default    { color:#374151; background:#f3f4f6; border-color:#d1d5db; }

  .diff-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: .5rem;
  }
  .diff-table th, .diff-table td {
    border: 1px solid #e5e7eb;
    padding: .35rem .45rem;
    font-size: .88rem;
  }

  .diff-section-title {
    background: #f3f4f6;
    font-weight: bold;
    text-align: center;
    padding: .5rem .45rem;
    border: 1px solid #d1d5db;
    font-size: .9rem;
  }

  .mono { font-family: ui-monospace, Menlo, Consolas, monospace; }
</style>


<div id="respModalOverlay" class="resp-modal-backdrop" data-modal-backdrop>
  <div class="resp-modal" role="dialog" aria-modal="true" aria-label="Historial de responsiva">
    <header>
      <div>
        <div class="ev-title">Historial — Responsiva #{{ $responsiva->folio }}</div>
        <div class="ev-meta">ID #{{ $responsiva->id }}</div>
      </div>
      <button type="button" class="close" data-modal-close>✕</button>
    </header>

    <div class="content">
      @if($historial->isEmpty())
        <p class="ev-meta">Sin historial registrado para esta responsiva.</p>
      @else
        <ul class="timeline">

          @php
              $motivoAcumulado = null;
          @endphp

          @foreach($historial as $log)

            @php
              $accion  = strtolower($log->accion ?? '');
              $fecha   = optional($log->created_at)->format('d-m-Y H:i');
              $user    = $log->usuario->name ?? 'Sistema';
              $cambios = $log->cambios ?? [];

              /* Detectar si es creación */
              $esCreacion = str_contains($accion, 'crea') || str_contains($accion, 'asignacion');

              /* Agrupación de claves */
              $generalKeys   = ['colaborador_id','fecha_solicitud','fecha_entrega','subsidiaria','unidad'];
              $firmasKeys    = ['user_id','recibi_colaborador_id','autoriza_user_id'];
              $productosKeys = ['productos','series','detalles_productos'];

              /* Separar por grupos */
              $cambiosGeneral   = [];
              $cambiosFirmas    = [];
              $cambiosProductos = [];

              foreach($cambios as $campo => $valor){
                  if(in_array($campo,$generalKeys)) $cambiosGeneral[$campo] = $valor;
                  elseif(in_array($campo,$firmasKeys)) $cambiosFirmas[$campo] = $valor;
                  elseif(in_array($campo,$productosKeys)) $cambiosProductos[$campo] = $valor;
              }

              /* Normalizar estructura */
              $normalizar = function($grupo){
                  $limpio = [];
                  foreach($grupo as $key => $val){
                      if(!is_array($val)){
                          $limpio[$key] = [
                              'antes' => $val,
                              'despues' => $val
                          ];
                      } else {
                          $limpio[$key] = [
                              'antes'   => $val['antes']   ?? '—',
                              'despues' => $val['despues'] ?? '—',
                          ];
                      }
                  }
                  return $limpio;
              };

              $cambiosGeneral   = $normalizar($cambiosGeneral);
              $cambiosFirmas    = $normalizar($cambiosFirmas);
              $cambiosProductos = $normalizar($cambiosProductos);

              /* Helpers */
              if(!function_exists('mostrarFechaLocal')){
                  function mostrarFechaLocal($f){
                      if(!$f || $f==='—') return '—';
                      try { return \Carbon\Carbon::parse($f)->format('d-m-Y'); }
                      catch(\Exception $e){ return $f; }
                  }
              }

              if(!function_exists('colabNameLocal')){
                  function colabNameLocal($id){
                      if(!$id) return '—';
                      $c = \App\Models\Colaborador::find($id);
                      return $c ? "{$c->nombre} {$c->apellidos}" : $id;
                  }
              }

              if(!function_exists('userNameLocal')){
                  function userNameLocal($id){
                      if(!$id) return '—';
                      return \App\Models\User::find($id)->name ?? $id;
                  }
              }

              /* ===================== MOTIVO HISTÓRICO ===================== */

              /**
               * Reglas:
               * - Recorremos el historial en orden ASC (más viejo → más nuevo).
               * - $motivoAcumulado guarda el motivo vigente hasta ese log.
               * - Si el log trae motivo_entrega, actualizamos el acumulado.
               * - Si no lo trae, usamos el acumulado anterior (no el motivo actual de la BD).
               */

              // 1) Inicializar el acumulado solo una vez
              if ($motivoAcumulado === null) {

                  // Si este log ya trae motivo_entrega, lo usamos como arranque
                  if (isset($cambios['motivo_entrega'])) {
                      $mot = $cambios['motivo_entrega'];

                      if (is_array($mot)) {
                          // creación o actualización con ['antes','despues']
                          $motivoAcumulado = $mot['despues']
                                          ?? $mot['antes']
                                          ?? $responsiva->motivo_entrega;
                      } else {
                          // logs viejos donde guardaste solo string
                          $motivoAcumulado = $mot;
                      }
                  } else {
                      // fallback: motivo actual de la responsiva
                      $motivoAcumulado = $responsiva->motivo_entrega;
                  }
              }

              // 2) Por defecto, el motivo mostrado es el acumulado que traíamos
              $motivoLog = $motivoAcumulado;

              // 3) Si ESTE log trae motivo_entrega, lo aplicamos como nuevo estado
              if (isset($cambios['motivo_entrega'])) {
                  $mot = $cambios['motivo_entrega'];

                  if (is_array($mot)) {
                      $motivoLog = $mot['despues'] ?? $mot['antes'] ?? $motivoLog;
                  } else {
                      $motivoLog = $mot;
                  }
              }

              // 4) Actualizamos acumulado para los logs siguientes
              $motivoAcumulado = $motivoLog;

              // 5) Normalizamos para la badge
              $motivoNormalizado = strtolower(str_replace(['_','-'],' ',$motivoLog));

              $badgeClass = match($motivoNormalizado){
                  'asignacion' => 'badge-asignacion',
                  'prestamo provisional','préstamo provisional','prestamo_provisional' => 'badge-prestamo',
                  'fallo' => 'badge-fallo',
                  default => 'badge-default'
              };
            @endphp


            <li class="{{ $accion }}">

              {{-- CABECERA --}}
              <div class="ev-head">
                <span class="ev-title">{{ $esCreacion ? 'Creación' : 'Edición' }}</span>
                <span class="ev-meta">— {{ $user }} · {{ $fecha }}</span>
              </div>

              <div style="margin: 6px 0 10px 0;">
                <strong class="ev-meta">Motivo: </strong>
                <span class="badge {{ $badgeClass }}">
                  {{ ucfirst($motivoLog ?? '—') }}
                </span>
              </div>


              {{-- ================= TABLA ================= --}}
              <table class="diff-table">


                {{-- ========== GENERAL ========== --}}
                @if($esCreacion || count($cambiosGeneral))
                    <tr>
                      <td colspan="{{ $esCreacion ? 2 : 3 }}" class="diff-section-title">General</td>
                    </tr>
                @endif

                @php
                  $generalCampos = [
                      'colaborador_id' => 'Colaborador',
                      'subsidiaria'    => 'Subsidiaria',
                      'unidad'         => 'Unidad',
                      'fecha_solicitud'=> 'Fecha solicitud',
                      'fecha_entrega'  => 'Fecha entrega',
                  ];
                @endphp

                @foreach($generalCampos as $campo => $label)
                  @php
                    $tieneCambio = isset($cambiosGeneral[$campo]);

                    // 👉 Para CREACIÓN, intentamos usar SIEMPRE el valor guardado en el LOG
                    if ($esCreacion) {

                        if (isset($cambiosGeneral[$campo])) {
                            // En creación nos interesa el snapshot "despues" (o "antes" si solo viene ese)
                            $valorBase = $cambiosGeneral[$campo]['despues']
                                      ?? $cambiosGeneral[$campo]['antes']
                                      ?? '—';
                        } else {
                            // Fallback para logs viejos que no tengan snapshot
                            $valorBase = match($campo){
                                'colaborador_id' => colabNameLocal($responsiva->colaborador_id),
                                'subsidiaria'    => optional(optional($responsiva->colaborador)->subsidiaria)->descripcion ?? $responsiva->subsidiaria,
                                'unidad'         => optional(optional($responsiva->colaborador)->unidadServicio)->nombre ?? $responsiva->unidad,
                                'fecha_solicitud'=> mostrarFechaLocal($responsiva->fecha_solicitud),
                                'fecha_entrega'  => mostrarFechaLocal($responsiva->fecha_entrega),
                                default => '—'
                            };
                        }

                        if ($campo === 'colaborador_id') {
                            $valorBase = colabNameLocal($valorBase);
                        }

                        if (in_array($campo, ['fecha_solicitud','fecha_entrega'])) {
                            $valorBase = mostrarFechaLocal($valorBase);
                        }
                    }

                    // 👉 Para EDICIONES, seguimos usando ANTES / DESPUÉS
                    if ($tieneCambio && !$esCreacion) {
                        $antes   = $cambiosGeneral[$campo]['antes'];
                        $despues = $cambiosGeneral[$campo]['despues'];

                        if ($campo === 'colaborador_id') {
                            $antes   = colabNameLocal($antes);
                            $despues = colabNameLocal($despues);
                        }

                        if (in_array($campo, ['fecha_solicitud','fecha_entrega'])) {
                            $antes   = mostrarFechaLocal($antes);
                            $despues = mostrarFechaLocal($despues);
                        }
                    }
                  @endphp

                  @if($tieneCambio || $esCreacion)
                    <tr>
                      <td>{{ $label }}</td>

                      @if($esCreacion)
                        <td class="mono" colspan="2">{{ $valorBase }}</td>
                      @else
                        <td class="mono text-gray-500">{{ $antes }}</td>
                        <td class="mono">{{ $despues }}</td>
                      @endif
                    </tr>
                  @endif
                @endforeach

                {{-- ========== PRODUCTOS ========== --}}
                @if($esCreacion || count($cambiosProductos))
                    <tr>
                      <td colspan="{{ $esCreacion ? 2 : 3 }}" class="diff-section-title">Productos</td>
                    </tr>

                    <tr>
                      <td colspan="{{ $esCreacion ? 2 : 3 }}">
                        <table class="diff-table" style="margin:0">
                          <tr>
                            <th>Nombre</th>
                            <th>Marca</th>
                            <th>Modelo</th>
                            <th>Serie</th>
                          </tr>

                          @if($esCreacion)
                              {{-- MOSTRAR SNAPSHOT DEL LOG (NO EL ESTADO ACTUAL) --}}
                              @if(isset($cambios['detalles_productos']) && is_array($cambios['detalles_productos']))
                                  @foreach($cambios['detalles_productos'] as $p)
                                      <tr>
                                          <td class="mono">{{ $p['nombre'] ?? '—' }}</td>
                                          <td class="mono">{{ $p['marca'] ?? '—' }}</td>
                                          <td class="mono">{{ $p['modelo'] ?? '—' }}</td>
                                          <td class="mono">{{ $p['serie'] ?? '—' }}</td>
                                      </tr>
                                  @endforeach
                              @endif
                          @else
                              {{-- USAR SNAPSHOT SI EXISTE --}}
                              @if(isset($cambios['detalles_productos']) && is_array($cambios['detalles_productos']))
                                  @foreach($cambios['detalles_productos'] as $p)
                                      <tr>
                                          <td class="mono">{{ $p['nombre'] ?? '—' }}</td>
                                          <td class="mono">{{ $p['marca'] ?? '—' }}</td>
                                          <td class="mono">{{ $p['modelo'] ?? '—' }}</td>
                                          <td class="mono">{{ $p['serie'] ?? '—' }}</td>
                                      </tr>
                                  @endforeach

                              @else
                                  {{-- BACKUP: PRODUCTOS ACTUALES --}}
                                  @foreach($responsiva->detalles as $d)
                                      @php $p = $d->producto; @endphp
                                      <tr>
                                          <td class="mono">{{ $p->nombre ?? '—' }}</td>
                                          <td class="mono">{{ $p->marca ?? '—' }}</td>
                                          <td class="mono">{{ $p->modelo ?? '—' }}</td>
                                          <td class="mono">{{ $d->serie->serie ?? '—' }}</td>
                                      </tr>
                                  @endforeach
                              @endif
                          @endif
                        </table>
                      </td>
                    </tr>
                @endif

                {{-- ================= CAMBIOS EN PRODUCTOS (AGREGADOS / REMOVIDOS) ================= --}}
                @if(isset($cambios['productos']) && is_array($cambios['productos']) && count($cambios['productos']))
                    <tr>
                        <td colspan="{{ $esCreacion ? 2 : 3 }}" class="diff-section-title">Cambios en series</td>
                    </tr>

                    <tr>
                        <td colspan="{{ $esCreacion ? 2 : 3 }}">
                            <table class="diff-table" style="margin:0">
                                <tr>
                                    <th>Nombre</th>
                                    <th>Marca</th>
                                    <th>Modelo</th>
                                    <th>Serie</th>
                                    <th>Acción</th>
                                </tr>

                                @foreach($cambios['productos'] as $prod)
                                    @php
                                        $accionSerie = strtolower($prod['accion'] ?? '');
                                    @endphp
                                    <tr>
                                        <td class="mono">{{ $prod['nombre'] ?? '—' }}</td>
                                        <td class="mono">{{ $prod['marca']  ?? '—' }}</td>
                                        <td class="mono">{{ $prod['modelo'] ?? '—' }}</td>
                                        <td class="mono">{{ $prod['serie']  ?? '—' }}</td>
                                        <td>
                                            @if($accionSerie === 'agregado')
                                                <span class="badge badge-asignacion">AGREGADO</span>
                                            @elseif($accionSerie === 'removido')
                                                <span class="badge badge-fallo">REMOVIDO</span>
                                            @else
                                                <span class="badge badge-default">{{ strtoupper($prod['accion'] ?? '-') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>
                @endif

                {{-- ========== FIRMAS ========== --}}
                @if($esCreacion || count($cambiosFirmas))
                    <tr>
                      <td colspan="3" class="diff-section-title">Firmas</td>
                    </tr>
                @endif

                {{-- ENTREGÓ --}}
                @php
                  $entBase = $responsiva->entrego->name ?? '—';
                  $entAntes = $cambiosFirmas['user_id']['antes'] ?? $entBase;
                  $entDesp  = $cambiosFirmas['user_id']['despues'] ?? $entBase;
                @endphp

                @if($esCreacion || isset($cambiosFirmas['user_id']))
                    <tr>
                      <td>Entregó</td>
                      @if($esCreacion)
                        <td class="mono" colspan="2">{{ $entBase }}</td>
                      @else
                        <td class="mono text-gray-500">{{ userNameLocal($entAntes) }}</td>
                        <td class="mono">{{ userNameLocal($entDesp) }}</td>
                      @endif
                    </tr>
                @endif


                {{-- RECIBIÓ --}}
                @php
                  $recBase = $responsiva->recibi
                      ? $responsiva->recibi->nombre.' '.$responsiva->recibi->apellidos
                      : '—';

                  $recAntes = $cambiosFirmas['recibi_colaborador_id']['antes'] ?? $recBase;
                  $recDesp  = $cambiosFirmas['recibi_colaborador_id']['despues'] ?? $recBase;
                @endphp

                @if($esCreacion || isset($cambiosFirmas['recibi_colaborador_id']))
                    <tr>
                      <td>Recibió</td>
                      @if($esCreacion)
                        <td class="mono" colspan="2">{{ $recBase }}</td>
                      @else
                        <td class="mono text-gray-500">{{ colabNameLocal($recAntes) }}</td>
                        <td class="mono">{{ colabNameLocal($recDesp) }}</td>
                      @endif
                    </tr>
                @endif


                {{-- AUTORIZÓ --}}
                @php
                  $autBase = $responsiva->autoriza->name ?? '—';
                  $autAntes = $cambiosFirmas['autoriza_user_id']['antes'] ?? $autBase;
                  $autDesp  = $cambiosFirmas['autoriza_user_id']['despues'] ?? $autBase;
                @endphp

                @if($esCreacion || isset($cambiosFirmas['autoriza_user_id']))
                    <tr>
                      <td>Autorizó</td>
                      @if($esCreacion)
                        <td class="mono" colspan="2">{{ $autBase }}</td>
                      @else
                        <td class="mono text-gray-500">{{ userNameLocal($autAntes) }}</td>
                        <td class="mono">{{ userNameLocal($autDesp) }}</td>
                      @endif
                    </tr>
                @endif


              </table>
            </li>

          @endforeach

        </ul>
      @endif
    </div>
    
  </div>
</div>
