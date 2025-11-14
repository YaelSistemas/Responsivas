{{-- resources/views/productos/historial_series/modal.blade.php --}}

<style>
  .colab-modal-backdrop {
    position: fixed; inset: 0;
    background: rgba(15, 23, 42, 0.5);
    display: flex; align-items: center; justify-content: center;
    z-index: 9998;
  }
  .colab-modal {
    background: #fff;
    width: min(900px, 92vw);
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
    padding: .85rem .6rem; border-left: 3px solid #e5e7eb;
    margin-left: .6rem; position: relative;
  }
  .timeline li::before {
    content: "";
    position: absolute; left: -9px; top: 1.1rem;
    width: 10px; height: 10px; border-radius: 999px;
    background: #3b82f6;
  }

  /* Colores existentes */
  .timeline li.asignacion::before { background: #3b82f6; }
  .timeline li.devolucion::before { background: #22c55e; }
  .timeline li.edicion::before    { background: #a855f7; }
  .timeline li.baja::before       { background: #ef4444; }

  /* NUEVO PUNTO DE COLOR PARA "removido_edicion" */
  .timeline li.removido::before   { background: #6366f1; }

  .ev-head { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; }
  .ev-title { font-weight: 700; color: #111827; }
  .ev-meta { font-size: .80rem; color: #6b7280; }
  .diff-table { width: 100%; border-collapse: collapse; margin-top: .5rem; }
  .diff-table th, .diff-table td {
    border: 1px solid #e5e7eb; padding: .35rem .45rem; font-size: .88rem;
  }
  .mono { font-family: ui-monospace, Menlo, Consolas, monospace; }

  .badge {
    padding: 2px 8px; border-radius: 8px;
    font-size: .75rem; font-weight: 600; display: inline-block;
  }
  .badge-gray { background: #e5e7eb; color: #374151; }
  .badge-green { background: #d1fae5; color: #065f46; }
  .badge-yellow { background: #fef9c3; color: #b45309; }
  .badge-red { background: #fee2e2; color: #b91c1c; }
  .badge-orange { background: #ffedd5; color: #c2410c; }
</style>

<div class="colab-modal-backdrop" data-modal-backdrop>
  <div class="colab-modal" role="dialog" aria-modal="true">

    <header>
      <div>
        <div class="ev-title">Historial — Serie {{ $serie->serie }}</div>
        <div class="ev-meta">
          Producto: {{ $serie->producto->nombre ?? '' }} (ID #{{ $serie->id }})
        </div>
      </div>
      <button type="button" class="close" data-modal-close>✕</button>
    </header>

    <div class="content">
      @if($historial->isEmpty())
        <p class="ev-meta">Sin historial registrado para esta serie.</p>
      @else
        <ul class="timeline">

        @foreach($historial as $log)
        @php
          $user  = $log->usuario->name ?? 'Sistema';
          $fecha = $log->created_at?->format('d-m-Y H:i');
          $accion = strtolower($log->accion);

          /* NUEVA LÓGICA DE NOMBRES */
          switch ($accion) {
              case 'edicion':
                  $accionMostrar = 'Actualización';
                  break;
              case 'removido_edicion':
                  $accionMostrar = 'Liberado por edición';
                  break;
              default:
                  $accionMostrar = ucfirst($accion);
          }

          /* NUEVA CLASE PARA EL COLOR */
          $accionClass = match($accion) {
              'creacion'         => 'edicion',
              'asignacion'       => 'asignacion',
              'devolucion'       => 'devolucion',
              'removido_edicion' => 'removido',
              'baja'             => 'baja',
              default            => 'edicion',
          };

          $cambios = $log->cambios ?? [];
        @endphp

        <li class="{{ $accionClass }}">
          <div class="ev-head">
              <span class="ev-title">{{ $accionMostrar }}</span>
              <span class="ev-meta">— {{ $user }} · {{ $fecha }}</span>

              {{-- ORDEN ESPECIAL SOLO PARA DEVOLUCIÓN REAL --}}
              @if($accion === 'devolucion')

                  @if($log->devolucion_id)
                      <span class="ev-meta">· Devolución:
                        <a href="{{ route('devoluciones.show', $log->devolucion_id) }}"
                           class="underline text-indigo-600">
                           {{ $log->devolucion?->folio ?? 'SIN FOLIO' }}
                        </a>
                      </span>
                  @endif

                  @if($log->responsiva_id)
                      <span class="ev-meta">· Responsiva:
                        <a href="{{ route('responsivas.show', $log->responsiva_id) }}"
                           class="underline text-indigo-600">
                           {{ $log->responsiva?->folio ?? 'SIN FOLIO' }}
                        </a>
                      </span>
                  @endif

              @else
                  {{-- ORDEN NORMAL --}}
                  @if($log->responsiva_id)
                      <span class="ev-meta">· Responsiva:
                        <a href="{{ route('responsivas.show', $log->responsiva_id) }}"
                           class="underline text-indigo-600">
                           {{ $log->responsiva?->folio ?? 'SIN FOLIO' }}
                        </a>
                      </span>
                  @endif

                  @if($log->devolucion_id)
                      <span class="ev-meta">· Devolución:
                        <a href="{{ route('devoluciones.show', $log->devolucion_id) }}"
                           class="underline text-indigo-600">
                           {{ $log->devolucion?->folio ?? 'SIN FOLIO' }}
                        </a>
                      </span>
                  @endif
              @endif
          </div>

          {{-- ================= ESTADO ESPECIAL DE ASIGNACIÓN ================= --}}
          @if($accion === 'asignacion')

              @php
                  // Motivo según responsiva
                  $motivo = strtolower($log->responsiva?->motivo_entrega ?? '');

                  // Texto visible
                  $motivoBonito = match($motivo) {
                      'prestamo_provisional' => 'Préstamo provisional',
                      'asignacion'           => 'Asignado',
                      default                => ucfirst($log->estado_nuevo ?? 'Asignado'),
                  };

                  // COLOR DEL BADGE (solo 3 estados)
                  $badge = function ($estado) {
                      $e = strtolower($estado);

                      return match(true) {
                          str_contains($e, 'asignado') => 'badge-green',      // Verde
                          str_contains($e, 'préstamo'),
                          str_contains($e, 'prestamo') => 'badge-yellow',     // Amarillo
                          default                      => 'badge-gray',       // Gris
                      };
                  };
              @endphp

              <div style="margin-top:.5rem;margin-bottom:.5rem;">
                  <span class="ev-meta" style="font-weight:600;">Estado:</span>

                  <span class="badge {{ $badge($log->estado_anterior) }}">
                      {{ ucfirst($log->estado_anterior ?? '—') }}
                  </span>

                  →

                  <span class="badge {{ $badge($motivoBonito) }}">
                      {{ $motivoBonito }}
                  </span>
              </div>

          @endif

          {{-- ====================== ESTADO ESPECIAL PARA DEVOLUCIÓN ====================== --}}
          @if($accion === 'devolucion')
              @php
                  // 1. Estado anterior (de la asignación)
                  $estadoAnterior = strtolower($log->estado_anterior ?? '');

                  $estadoAnteriorBonito = match($estadoAnterior) {
                      'asignado'              => 'Asignado',
                      'prestamo_provisional'  => 'Préstamo provisional',
                      'baja_colaborador'      => 'Baja colaborador',
                      'renovacion'            => 'Renovación',
                      default                 => ucfirst($estadoAnterior ?: '—'),
                  };


                  // 2. Motivo de la devolución (tabla devoluciones.motivo)
                  $motivo = strtolower(
                      $log->motivo_devolucion
                      ?? ($log->cambios['motivo_devolucion'] ?? null)
                      ?? $log->motivo
                      ?? $log->devolucion?->motivo
                      ?? ''
                  );

                  $motivoBonito = match($motivo) {
                      'baja_colaborador' => 'Baja colaborador',
                      'renovacion'       => 'Renovación',
                      default            => ucfirst($motivo ?: '—'),
                  };

                  // 3. Estado final → SIEMPRE "Disponible"
                  $estadoFinalBonito = "Disponible";

                  // Badge colors
                  $badge = fn($txt) => match(true) {
                      str_contains(strtolower($txt), 'asignado') => 'badge-green',
                      str_contains(strtolower($txt), 'préstamo'),
                      str_contains(strtolower($txt), 'prestamo') => 'badge-yellow',
                      str_contains(strtolower($txt), 'renovación'),
                      str_contains(strtolower($txt), 'renovacion') => 'badge-orange',
                      str_contains(strtolower($txt), 'baja') => 'badge-red',
                      default => 'badge-gray',
                  };
              @endphp

              <div style="margin-top:.5rem;margin-bottom:.5rem;">
                  <span class="ev-meta" style="font-weight:600;">Estado:</span>

                  {{-- Estado anterior --}}
                  <span class="badge {{ $badge($estadoAnteriorBonito) }}">
                      {{ $estadoAnteriorBonito }}
                  </span>

                  →

                  {{-- Motivo de la devolución --}}
                  <span class="badge {{ $badge($motivoBonito) }}">
                      {{ $motivoBonito }}
                  </span>

                  →

                  {{-- Estado final (Disponible) --}}
                  <span class="badge {{ $badge($estadoFinalBonito) }}">
                      {{ $estadoFinalBonito }}
                  </span>
              </div>
          @endif

          {{-- ====================== TABLA DE CAMBIOS ====================== --}}
          @if(!empty($cambios))

          {{-- === NUEVA VISTA PARA "removido_edicion" === --}}
          @if($accion === 'removido_edicion')

              <table class="diff-table">
                  <thead>
                      <tr>
                          <th>Campo</th>
                          <th>Valor anterior</th>
                      </tr>
                  </thead>
                  <tbody>

                      @if(isset($cambios['removido_de']))
                        <tr>
                          <td>Removido de</td>
                          <td class="mono">{{ $cambios['removido_de']['antes'] ?? '—' }}</td>
                        </tr>
                      @endif

                      @if(isset($cambios['actualizado_por']))
                        <tr>
                          <td>Actualizado por</td>
                          <td class="mono">{{ $cambios['actualizado_por']['despues'] ?? '—' }}</td>
                        </tr>
                      @endif

                  </tbody>
              </table>

              @continue
          @endif
          {{-- ============================================================= --}}

          {{-- === VISTA ESPECIAL PARA DEVOLUCIÓN REAL === --}}
          @if($accion === 'devolucion')
              <table class="diff-table">
                  <thead>
                      <tr>
                          <th>Campo</th>
                          <th>Valor anterior</th>
                      </tr>
                  </thead>
                  <tbody>

                      @if(isset($cambios['removido_de']))
                        <tr>
                          <td>Removido de</td>
                          <td class="mono">{{ $cambios['removido_de']['antes'] ?? '—' }}</td>
                        </tr>
                      @endif

                      @if(isset($cambios['actualizado_por']))
                        <tr>
                          <td>Actualizado por</td>
                          <td class="mono">{{ $cambios['actualizado_por']['despues'] ?? '—' }}</td>
                        </tr>
                      @endif

                      <tr>
                        <td>Fecha devolución</td>
                        <td class="mono">
                          {{ $log->devolucion?->fecha_devolucion
                                ? \Carbon\Carbon::parse($log->devolucion->fecha_devolucion)->format('d-m-Y')
                                : 'SIN FECHA' }}
                        </td>
                      </tr>

                      <tr>
                        <td>Subsidiaria</td>
                        <td class="mono">
                          {{ $log->devolucion?->responsiva?->colaborador?->subsidiaria?->descripcion
                                ?? 'SIN SUBSIDIARIA' }}
                        </td>
                      </tr>

                  </tbody>
              </table>

              @continue
          @endif
          {{-- ============================================================= --}}

          {{-- === CREACIÓN === --}}
          @if($accion === 'creacion')

              @php
                $spec = $cambios['especificaciones_base']
                      ?? $cambios['especificaciones']
                      ?? null;

                $descripcionProducto = $serie->producto->descripcion ?? null;
              @endphp

              <table class="diff-table">
                <thead>
                    <tr><th>Campo</th><th>Valor</th></tr>
                </thead>
                <tbody>

                    @if(!$spec && $descripcionProducto)
                    <tr><td>Descripción</td><td class="mono">{{ $descripcionProducto }}</td></tr>
                    @endif

                    @if($spec)
                        @if(isset($spec['color']))
                          <tr><td>Color</td><td class="mono">{{ $spec['color'] }}</td></tr>
                        @endif

                        @if(isset($spec['ram_gb']))
                          <tr><td>RAM</td><td class="mono">{{ $spec['ram_gb'] }} GB</td></tr>
                        @endif

                        @if(isset($spec['procesador']))
                          <tr><td>Procesador</td><td class="mono">{{ $spec['procesador'] }}</td></tr>
                        @endif

                        @if(isset($spec['almacenamiento']))
                          <tr>
                            <td>Almacenamiento</td>
                            <td class="mono">
                              {{ $spec['almacenamiento']['tipo'] ?? '' }} —
                              {{ $spec['almacenamiento']['capacidad_gb'] ?? '' }} GB
                            </td>
                          </tr>
                        @endif
                    @endif

                    @if(isset($cambios['serie']))
                      <tr><td>Serie</td><td class="mono">{{ $cambios['serie'] }}</td></tr>
                    @endif

                </tbody>
              </table>

          @else
          {{-- === EDICIÓN GENERAL === --}}
          <table class="diff-table">
            <thead>
              <tr>
                <th>Campo</th>

                @if($accion !== 'asignacion')
                    <th>Valor anterior</th>
                @endif

                <th>Nuevo valor</th>
              </tr>
            </thead>

            <tbody>

            @if($accion === 'asignacion')
                <tr><td>Asignado a</td><td class="mono">{{ $cambios['asignado_a']['despues'] ?? '—' }}</td></tr>
                <tr><td>Entregado por</td><td class="mono">{{ $cambios['entregado_por']['despues'] ?? '—' }}</td></tr>

                <tr>
                  <td>Fecha entrega</td>
                  <td class="mono">
                    {{ $log->responsiva?->fecha_entrega
                        ? \Carbon\Carbon::parse($log->responsiva->fecha_entrega)->format('d-m-Y')
                        : 'SIN FECHA' }}
                  </td>
                </tr>

                <tr>
                  <td>Subsidiaria</td>
                  <td class="mono">
                    {{ $log->responsiva?->colaborador?->subsidiaria?->descripcion ?? 'SIN SUBSIDIARIA' }}
                  </td>
                </tr>
            @endif

            @foreach($cambios as $campo => $valor)

                @if(in_array($campo, ['asignado_a','entregado_por','fecha_entrega','subsidiaria']))
                    @continue
                @endif

                @if(is_array($valor) && isset($valor['antes']) && isset($valor['despues']))
                    @php
                      $antes = $valor['antes'];
                      $despues = $valor['despues'];

                      $format = function($v) use ($campo) {
                          if(is_array($v)) {
                              if(isset($v['color'])) return $v['color'];
                              if(isset($v['ram_gb'])) return $v['ram_gb'].' GB';
                              if(isset($v['procesador'])) return $v['procesador'];

                              if(isset($v['tipo']) || isset($v['capacidad_gb'])) {
                                  return ($v['tipo'] ?? '').' — '.($v['capacidad_gb'] ?? '').' GB';
                              }

                              return json_encode($v, JSON_UNESCAPED_UNICODE);
                          }

                          if($campo === 'ram_gb' && is_numeric($v)) {
                              return $v . ' GB';
                          }

                          return $v ?? '—';
                      };
                    @endphp

                    <tr>
                        <td>{{ ucfirst(str_replace('_',' ', $campo)) }}</td>

                        @if($accion !== 'asignacion')
                          <td class="mono text-gray-500">{{ $format($antes) }}</td>
                        @endif

                        <td class="mono">{{ $format($despues) }}</td>
                    </tr>
                @endif

            @endforeach

            </tbody>
          </table>
          @endif {{-- Fin edición general --}}

          @else
            <div class="ev-meta">Sin cambios registrados.</div>
          @endif {{-- Fin if cambios --}}

        </li>
        @endforeach

        </ul>
      @endif
    </div>

  </div>
</div>
