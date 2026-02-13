<style>
  .colab-modal-backdrop {
    position: fixed; inset: 0;
    background: rgba(15, 23, 42, 0.5);
    display: flex; align-items: center; justify-content: center;
    z-index: 9998;
  }
  .colab-modal {
    background: #fff;
    width: min(850px, 92vw);
    max-height: 80vh;
    overflow: auto;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0,0,0,.25);
    z-index: 9999;
  }
  .colab-modal header {
    display: flex; align-items: center; justify-content: space-between;
    padding: .9rem 1rem;
    border-bottom: 1px solid #e5e7eb;
    background: #fff;
    position: sticky; top: 0; z-index: 1;
  }
  .colab-modal .close {
    border: 1px solid #e5e7eb;
    background: #fff; border-radius: 8px;
    padding: .25rem .55rem; cursor: pointer;
  }
  .colab-modal .close:hover { background: #f3f4f6; }
  .colab-modal .content { padding: 1rem; }

  .timeline { list-style: none; margin: 0; padding: 0; }
  .timeline li {
    padding: .7rem .6rem; border-left: 3px solid #e5e7eb;
    margin-left: .6rem; position: relative;
  }
  .timeline li::before {
    content: ""; position: absolute; left: -9px; top: 1rem;
    width: 10px; height: 10px; border-radius: 999px;
    background: #3b82f6;
  }
  .timeline li.creacion::before { background: #22c55e; }
  .timeline li.eliminacion::before { background: #ef4444; }
  .timeline li.actualizacion::before { background: #3b82f6; }

  .ev-head { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; }
  .ev-title { font-weight: 700; color: #111827; }
  .ev-meta { font-size: .80rem; color: #6b7280; }

  .diff-table { width: 100%; border-collapse: collapse; margin-top: .4rem; }
  .diff-table th, .diff-table td {
    border: 1px solid #e5e7eb;
    padding: 0.25rem 0.4rem;
    font-size: 0.82rem;
    line-height: 1.1rem;
    vertical-align: middle;
  }
  .diff-table th {
    background: #f9fafb;
    font-weight: 600;
    text-align: left;
  }

  .mono {
    font-family: ui-monospace, Menlo, Consolas, monospace;
    white-space: pre-line;
    word-break: break-word;
  }
</style>

<div id="prodModalOverlay" class="colab-modal-backdrop" data-modal-backdrop>
  <div class="colab-modal" role="dialog" aria-modal="true" aria-label="Historial del producto">
    <header>
      <div>
        <div class="ev-title">Historial — {{ $producto->nombre }}</div>
        <div class="ev-meta">
          ID #{{ $producto->id }} · {{ $producto->marca ?? 'Sin marca' }} {{ $producto->modelo ?? '' }}
        </div>
      </div>
      <button type="button" class="close" data-modal-close>✕</button>
    </header>

    <div class="content">
      @if($historial->isEmpty())
        <p class="ev-meta">Sin historial registrado para este producto.</p>
      @else
        <ul class="timeline">
          @foreach($historial->sortBy('id') as $log)
            @php
              $user  = $log->user->name ?? 'Sistema';
              $fecha = optional($log->created_at)->format('d-m-Y H:i');
              $accion = strtolower($log->accion ?? 'actualización');

              $datosAntes   = json_decode($log->datos_anteriores ?? '[]', true) ?: [];
              $datosDespues = json_decode($log->datos_nuevos ?? '[]', true) ?: [];

              // -------------------------
              // Detectar cambios
              // -------------------------
              $cambios = [];
              if (in_array($accion, ['actualización','actualizado'])) {
                foreach ($datosDespues as $campo => $nuevo) {
                  $anterior = $datosAntes[$campo] ?? null;
                  if ($anterior != $nuevo) {
                    $cambios[$campo] = ['de' => $anterior, 'a' => $nuevo];
                  }
                }
              } elseif (in_array($accion, ['creación','creado'])) {
                $cambios = $datosDespues;
              } elseif (in_array($accion, ['eliminación','eliminado'])) {
                $cambios = $datosAntes;
              }

              // -------------------------
              // Cambio activo (si lo modificaron)
              // -------------------------
              $cambioActivo = null;
              if (isset($cambios['activo']) && is_array($cambios['activo'])) {
                $cambioActivo = [
                  'de' => ($cambios['activo']['de'] ?? 0) ? 'Activo' : 'Inactivo',
                  'a'  => ($cambios['activo']['a'] ?? 0) ? 'Activo' : 'Inactivo',
                ];
                unset($cambios['activo']);
              }

              // -------------------------
              // Estado ACTUAL del evento (aunque no haya cambiado)
              // prioridad: datos_nuevos.activo -> datos_anteriores.activo -> producto.activo
              // -------------------------
              $activoEventoRaw = $datosDespues['activo'] ?? $datosAntes['activo'] ?? ($producto->activo ?? 1);
              $activoEvento = (int)($activoEventoRaw ?? 1) ? 'Activo' : 'Inactivo';
              $activoEventoColor = $activoEvento === 'Activo' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';

              // -------------------------
              // Tipo del EVENTO (log)
              // -------------------------
              $tipoEvento = strtolower(trim(
                $datosDespues['tipo'] ?? $datosAntes['tipo'] ?? ($producto->tipo ?? '')
              ));
              $tipoEvento = str_replace(['multifuncional','telefono','tv'], ['impresora','celular','pantalla'], $tipoEvento);

              // -------------------------
              // Alias
              // -------------------------
              $aliasCampos = [
                'unidad_medida' => 'unidad',
                'unidadmedida'  => 'unidad',
                'especificacion' => 'especificaciones',
                'especificacion_tecnica' => 'especificaciones',
              ];

              // -------------------------
              // Orden EXACTO por tipo
              // -------------------------
              $hardwareTipos = ['equipo_pc','celular','impresora','monitor','pantalla','periferico'];

              if (in_array($tipoEvento, $hardwareTipos)) {
                $ordenCampos = ['nombre','marca','modelo','tipo'];
              } elseif ($tipoEvento === 'consumible') {
                $ordenCampos = ['nombre','marca','modelo','sku','unidad','color','tipo'];
              } else {
                $ordenCampos = ['nombre','marca','modelo','tipo'];
              }

              // -------------------------
              // Normalizar keys + filtrar/ordenar
              // -------------------------
              $cambiosNormalizados = collect($cambios)->mapWithKeys(function($val, $key) use ($aliasCampos) {
                $k = strtolower(trim($key));
                $k = $aliasCampos[$k] ?? $k;
                return [$k => $val];
              });

              $esCreacion = in_array($accion, ['creación','creado']);

              // helper color desde especificaciones
              $extraColorFromSpecs = function($spec) {
                if (is_string($spec)) {
                  $try = json_decode($spec, true);
                  if (json_last_error() === JSON_ERROR_NONE) $spec = $try;
                }
                if (is_array($spec)) return $spec['color'] ?? $spec['Color'] ?? null;
                return null;
              };

              if ($esCreacion) {
                $otrosCambios = collect($ordenCampos)->mapWithKeys(function($campo) use ($datosDespues, $extraColorFromSpecs) {
                  if ($campo === 'color') {
                    $directo = $datosDespues['color'] ?? null;
                    if (!is_null($directo) && $directo !== '') return ['color' => $directo];

                    $spec = $datosDespues['especificaciones'] ?? $datosDespues['especificacion'] ?? null;
                    return ['color' => $extraColorFromSpecs($spec)];
                  }
                  return [$campo => $datosDespues[$campo] ?? null];
                });
              } else {
                $otrosCambios = $cambiosNormalizados
                  ->filter(fn($val, $campo) => in_array($campo, $ordenCampos))
                  ->sortBy(fn($val, $campo) => array_search($campo, $ordenCampos));

                // consumible: si cambió especificaciones, revisa si el color interno cambió
                if ($tipoEvento === 'consumible' && $otrosCambios->has('especificaciones') && !$otrosCambios->has('color')) {
                  $deSpec = $cambiosNormalizados['especificaciones']['de'] ?? null;
                  $aSpec  = $cambiosNormalizados['especificaciones']['a']  ?? null;

                  $deColor = $extraColorFromSpecs($deSpec);
                  $aColor  = $extraColorFromSpecs($aSpec);

                  if ($deColor != $aColor && (!is_null($deColor) || !is_null($aColor))) {
                    $otrosCambios = $otrosCambios->put('color', ['de' => $deColor, 'a' => $aColor]);
                    $otrosCambios = $otrosCambios->sortBy(fn($val, $campo) => array_search($campo, $ordenCampos));
                  }
                }
              }

              $tieneCambios = (collect($otrosCambios)->filter(fn($v) => $v !== null && $v !== '')->count() > 0);
            @endphp

            @if($tieneCambios || $cambioActivo || in_array($accion, ['actualización','actualizado']))
              <li class="{{ $accion }}">
                <div class="ev-head">
                  @if($esCreacion)
                    <span class="ev-title">Creación</span>
                  @elseif(in_array($accion, ['eliminación','eliminado']))
                    <span class="ev-title">Eliminación</span>
                  @else
                    <span class="ev-title">Actualización</span>
                  @endif
                  <span class="ev-meta">— {{ $user }} · {{ $fecha }}</span>
                </div>

                {{-- ✅ Estado SIEMPRE: creación y actualización --}}
                @if($cambioActivo)
                  @php
                    $colorDe = $cambioActivo['de'] === 'Activo' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
                    $colorA  = $cambioActivo['a'] === 'Activo' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
                  @endphp
                  <p class="ev-meta mt-1">
                    Estado:
                    <span class="px-2 py-1 rounded {{ $colorDe }}">{{ $cambioActivo['de'] }}</span>
                    →
                    <span class="px-2 py-1 rounded {{ $colorA }}">{{ $cambioActivo['a'] }}</span>
                  </p>
                @elseif(in_array($accion, ['creación','creado','actualización','actualizado']))
                  <p class="ev-meta mt-1">
                    Estado:
                    <span class="px-2 py-1 rounded {{ $activoEventoColor }}">{{ $activoEvento }}</span>
                  </p>
                @endif

                {{-- Tabla --}}
                @php
                  $collectionCambios = $otrosCambios instanceof \Illuminate\Support\Collection ? $otrosCambios : collect($otrosCambios);

                  $toPretty = function($v) {
                    if (is_string($v)) {
                      $try = json_decode($v, true);
                      if (json_last_error() === JSON_ERROR_NONE) return $try;
                    }
                    return $v;
                  };
                @endphp

                @if($collectionCambios->isNotEmpty())
                  <table class="diff-table">
                    <thead>
                      <tr>
                        <th>Campo</th>
                        @if(in_array($accion, ['actualización','actualizado']))
                          <th>Valor anterior</th>
                          <th>Nuevo valor</th>
                        @else
                          <th colspan="2">Valor</th>
                        @endif
                      </tr>
                    </thead>
                    <tbody>
                      @foreach($collectionCambios as $campo => $valor)
                        @if(is_array($valor) && array_key_exists('de',$valor) && array_key_exists('a',$valor))
                          @php
                            $de = $toPretty($valor['de'] ?? null);
                            $a  = $toPretty($valor['a'] ?? null);
                          @endphp
                          <tr>
                            <td>{{ ucfirst(str_replace('_',' ', $campo)) }}</td>
                            <td class="mono text-gray-500">
                              @if(is_array($de))
                                {{ json_encode($de, JSON_UNESCAPED_UNICODE) }}
                              @else
                                {{ $de ?? '—' }}
                              @endif
                            </td>
                            <td class="mono">
                              @if(is_array($a))
                                {{ json_encode($a, JSON_UNESCAPED_UNICODE) }}
                              @else
                                {{ $a ?? '—' }}
                              @endif
                            </td>
                          </tr>
                        @else
                          @php $val = $toPretty($valor); @endphp
                          <tr>
                            <td>{{ ucfirst(str_replace('_',' ', $campo)) }}</td>
                            <td class="mono" colspan="2">
                              @if(is_array($val))
                                {{ json_encode($val, JSON_UNESCAPED_UNICODE) }}
                              @else
                                {{ $val ?? '—' }}
                              @endif
                            </td>
                          </tr>
                        @endif
                      @endforeach
                    </tbody>
                  </table>
                @endif
              </li>
            @endif
          @endforeach
        </ul>
      @endif
    </div>
  </div>
</div>
