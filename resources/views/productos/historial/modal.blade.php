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

  pre.json-block {
    background: #f8fafc;
    border-radius: 6px;
    border: 1px solid #e5e7eb;
    padding: .4rem .6rem;
    font-size: .8rem;
    line-height: 1.1rem;
    overflow-x: auto;
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
              $user = $log->user->name ?? 'Sistema';
              $fecha = optional($log->created_at)->format('d-m-Y H:i');
              $accion = strtolower($log->accion ?? 'actualización');
              $datosAntes = json_decode($log->datos_anteriores ?? '[]', true);
              $datosDespues = json_decode($log->datos_nuevos ?? '[]', true);

              // Detectar cambios
              $cambios = [];
              if ($accion === 'actualización' || $accion === 'actualizado') {
                foreach ($datosDespues as $campo => $nuevo) {
                  $anterior = $datosAntes[$campo] ?? null;
                  if ($anterior != $nuevo) {
                    $cambios[$campo] = ['de' => $anterior, 'a' => $nuevo];
                  }
                }
              } elseif ($accion === 'creación' || $accion === 'creado') {
                $cambios = $datosDespues ?? [];
              } elseif ($accion === 'eliminación' || $accion === 'eliminado') {
                $cambios = $datosAntes ?? [];
              }

               // Detectar si cambió el campo 'activo'
               $cambioActivo = null;
               if (isset($cambios['activo'])) {
                   $cambioActivo = [
                   'de' => ($cambios['activo']['de'] ?? 0) ? 'Activo' : 'Inactivo',
                   'a'  => ($cambios['activo']['a'] ?? 0) ? 'Activo' : 'Inactivo',
                   ];
                   unset($cambios['activo']); // lo quitamos para que no salga también en la tabla
               }   
               $soloCambioActivo = $cambioActivo && empty($cambios);
              
              // === 🔧 Determinar campos visibles según tipo y tracking ===
              $tipo = strtolower(trim($producto->tipo ?? ''));
              $tracking = strtolower(trim($producto->tracking ?? ''));
  
              // Normalizar texto del tracking
              $tracking = preg_replace('/\s+/', ' ', $tracking);
              $tracking = str_replace(
                ['(', ')', '[', ']', '{', '}', 'á', 'é', 'í', 'ó', 'ú', 'ü'],
                ['', '', '', '', '', '', 'a', 'e', 'i', 'o', 'u', 'u'],
                $tracking
                );
              $tracking = trim($tracking);
  
              // === Mapeo de nombres equivalentes ===
              $aliasCampos = [
                  'unidad_medida' => 'unidad',
                  'especificacion' => 'especificaciones',
                  'especificacion_tecnica' => 'especificaciones',
                  'sku' => 'sku',
                  'stock_inicial' => 'stock',
                ];

                if ($tipo === 'equipo_pc') {
                    $mostrarCampos = ['nombre', 'marca', 'modelo', 'tipo', 'especificaciones'];
                } elseif (in_array($tipo, ['impresora','celular', 'pantalla', 'periferico', 'monitor'])) {
                    $mostrarCampos = ['nombre', 'marca', 'modelo', 'tipo', 'descripcion'];
                } elseif ($tipo === 'consumible') {
                    $mostrarCampos = ['nombre', 'marca', 'modelo', 'tipo', 'especificaciones', 'sku', 'unidad', 'stock'];
                } elseif ($tipo === 'otro') {
                    if (str_contains($tracking, 'cantidad')) {
                        $mostrarCampos = ['nombre', 'marca', 'modelo', 'tipo', 'descripcion', 'sku', 'unidad', 'stock'];
                    } elseif (str_contains($tracking, 'serial')) {
                        $mostrarCampos = ['nombre', 'marca', 'modelo', 'tipo', 'descripcion'];
                    } else {
                        $mostrarCampos = ['nombre', 'marca', 'modelo', 'tipo'];
                    }
                } else {
                    $mostrarCampos = ['nombre', 'marca', 'modelo', 'tipo'];
                }
                
              // 🔍 Filtro mejorado con soporte a alias
              $otrosCambios = collect($cambios)->filter(function ($val, $key) use ($mostrarCampos, $aliasCampos) {
              $campo = strtolower($key);
              $campoEquivalente = $aliasCampos[$campo] ?? $campo;
              return in_array($campoEquivalente, $mostrarCampos);
              });  

              // 🧩 Ordenar los campos según el orden definido en $mostrarCampos
              $otrosCambios = $otrosCambios->sortBy(function ($val, $key) use ($mostrarCampos, $aliasCampos) {
              $campo = strtolower($key);
              $campoEquivalente = $aliasCampos[$campo] ?? $campo;
              return array_search($campoEquivalente, $mostrarCampos);
              });

              $esCreacion = in_array($accion, ['creación','creado']);

              // Filtrar solo los campos relevantes
              $otrosCambios = collect($cambios)->filter(function ($val, $key) use ($mostrarCampos) {
                  return in_array(strtolower($key), $mostrarCampos);
              });

              $esCreacion = in_array($accion, ['creación','creado']);
            @endphp

            {{-- ⚙️ Mostrar solo si hay cambios reales --}}
            @if($accion !== 'actualización' && $accion !== 'actualizado' || !empty($cambios) || $cambioActivo)
            <li class="{{ $accion }}">
                <div class="ev-head">
                @if($esCreacion)
                  <span class="ev-title">Creación</span>
                @elseif($accion === 'eliminación' || $accion === 'eliminado')
                  <span class="ev-title">Eliminación</span>
                @else
                  <span class="ev-title">Actualización</span>
                @endif
                <span class="ev-meta">— {{ $user }} · {{ $fecha }}</span>
              </div>

              {{-- 🟢 Mostrar estado (solo creación o cambio de activo) --}}
  @if(($accion === 'creación' || $accion === 'creado') && isset($datosDespues['activo']))
    @php
      $estado = ($datosDespues['activo'] ?? 1) ? 'Activo' : 'Inactivo';
      $color = $estado === 'Activo' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
    @endphp
    <p class="ev-meta mt-1">Estado: <span class="px-2 py-1 rounded {{ $color }}">{{ $estado }}</span></p>
  @elseif($cambioActivo)
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
  @endif
  
              {{-- 🔹 Tabla de cambios relevantes --}}
              @if($otrosCambios->isNotEmpty())
                <table class="diff-table">
                  <thead>
                    <tr>
                      <th>Campo</th>
                      @if($accion === 'actualización' || $accion === 'actualizado')
                        <th>Valor anterior</th>
                        <th>Nuevo valor</th>
                      @else
                        <th colspan="2">Valor</th>
                      @endif
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($otrosCambios as $campo => $valor)
                      @if(is_array($valor) && isset($valor['de'], $valor['a']))
                        <tr>
                          <td>{{ ucfirst(str_replace('_',' ', $campo)) }}</td>
                          <td class="mono text-gray-500">
                            @if(is_array($valor['de']))
                              <pre class="json-block">{{ json_encode($valor['de'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                            @else
                              {{ $valor['de'] ?? '—' }}
                            @endif
                          </td>
                          <td class="mono">
                            @if(is_array($valor['a']))
                              <pre class="json-block">{{ json_encode($valor['a'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                            @else
                              {{ $valor['a'] ?? '—' }}
                            @endif
                          </td>
                        </tr>
                      @else
                        <tr>
                          <td>{{ ucfirst(str_replace('_',' ', $campo)) }}</td>
                          <td class="mono" colspan="2">
                            @if(is_array($valor))
                              <pre class="json-block">{{ json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                            @else
                              {{ $valor ?? '—' }}
                            @endif
                          </td>
                        </tr>
                      @endif
                    @endforeach
                  </tbody>
                </table>
                @elseif($accion !== 'actualización' && $accion !== 'actualizado')
                    <div class="ev-meta">Sin datos relevantes en esta acción.</div>
                @endif
            </li>
            @endif
          @endforeach
        </ul>
      @endif
    </div>
  </div>
</div>
