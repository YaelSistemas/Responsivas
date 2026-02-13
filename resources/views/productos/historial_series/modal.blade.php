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

  .timeline li.asignacion::before { background: #3b82f6; }
  .timeline li.devolucion::before { background: #22c55e; }
  .timeline li.edicion::before    { background: #a855f7; }
  .timeline li.baja::before       { background: #ef4444; }
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
  .badge-blue { background:#dbeafe; color:#1e40af; }
</style>

@php
  // ✅ Orden: Creación arriba, y lo demás hacia abajo (más viejo -> más nuevo)
  $historialOrdenado = $historial->sortBy('id');

  // Mapas id => nombre
  $subsMap = \App\Models\Subsidiaria::query()->pluck('nombre', 'id')->toArray();
  $uniMap  = \App\Models\UnidadServicio::query()->pluck('nombre', 'id')->toArray();

  // Labels bonitos para campos base
  $labelsCampo = [
    'subsidiaria_id'      => 'Subsidiaria',
    'unidad_servicio_id'  => 'Unidad de servicio',
  ];

  // ===== Helpers para ESPECIFICACIONES (diff interno) =====
  $toArr = function ($v) {
    if (is_string($v)) {
      $try = json_decode($v, true);
      if (json_last_error() === JSON_ERROR_NONE) return $try;
    }
    return $v;
  };

  $flatten = function ($arr, $prefix = '') use (&$flatten) {
    $out = [];
    if (!is_array($arr)) return $out;

    foreach ($arr as $k => $v) {
      $key = $prefix === '' ? $k : ($prefix . '.' . $k);
      if (is_array($v)) {
        $out = array_merge($out, $flatten($v, $key));
      } else {
        $out[$key] = $v;
      }
    }
    return $out;
  };

  // Etiquetas bonitas para algunas rutas comunes (lo demás sale como "Especificaciones: X")
  $labelsSpec = [
    'color' => 'Color',
    'ram_gb' => 'RAM',
    'procesador' => 'Procesador',
    'almacenamiento.tipo' => 'Almacenamiento (tipo)',
    'almacenamiento.capacidad_gb' => 'Almacenamiento (capacidad GB)',
  ];

  $fmtVal = function($k, $v) {
    if ($v === null || $v === '') return '—';
    if ($k === 'ram_gb' && is_numeric($v)) return $v.' GB';
    if ($k === 'almacenamiento.capacidad_gb' && is_numeric($v)) return $v.' GB';
    return (string)$v;
  };
@endphp

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
      @if($historialOrdenado->isEmpty())
        <p class="ev-meta">Sin historial registrado para esta serie.</p>
      @else
        <ul class="timeline">

        @foreach($historialOrdenado as $log)
          @php
            $user  = $log->usuario->name ?? 'Sistema';
            $fecha = $log->created_at?->format('d-m-Y H:i');
            $accion = strtolower($log->accion);

            // Nombre bonito
            switch ($accion) {
              case 'edicion':
                $accionMostrar = 'Actualización';
                break;
              case 'removido_edicion':
                $accionMostrar = 'Liberado por edición';
                break;
              case 'liberado_eliminacion':
                $accionMostrar = 'Liberado por eliminación';
                break;
              default:
                $accionMostrar = ucfirst($accion);
            }

            // Clase (color)
            $accionClass = match($accion) {
              'creacion'              => 'edicion',
              'asignacion'            => 'asignacion',
              'devolucion'            => 'devolucion',
              'removido_edicion'      => 'removido',
              'liberado_eliminacion'  => 'removido',
              'baja'                  => 'baja',
              default                 => 'edicion',
            };

            $cambios = $log->cambios ?? [];
          @endphp

          @php
            // Para cuando eliminaron la responsiva y ya no existe: recuperamos folio anterior global
            $folioEliminadoGlobal = null;

            foreach ($historialOrdenado as $h) {
              if (
                $h->accion === 'liberado_eliminacion' &&
                isset($h->cambios['responsiva_folio']['antes'])
              ) {
                $folioEliminadoGlobal = $h->cambios['responsiva_folio']['antes'];
              }
            }
          @endphp

          <li class="{{ $accionClass }}">
            <div class="ev-head">
              <span class="ev-title">{{ $accionMostrar }}</span>
              <span class="ev-meta">— {{ $user }} · {{ $fecha }}</span>

              {{-- ====================== RESPONSIVA ====================== --}}
              @if($log->responsiva_id)
                <span class="ev-meta">· Responsiva:

                  @if(!$log->responsiva && $folioEliminadoGlobal)
                    <span class="text-red-600 font-semibold">
                      {{ $folioEliminadoGlobal }}
                    </span>
                  @elseif($log->responsiva)
                    <a href="{{ route('responsivas.show', $log->responsiva_id) }}"
                      class="underline text-indigo-600">
                      {{ $log->responsiva->folio }}
                    </a>
                  @else
                    <span class="text-gray-500">SIN FOLIO</span>
                  @endif

                </span>
              @endif

              {{-- ====================== DEVOLUCIÓN (SIEMPRE MOSTRAR FOLIO) ====================== --}}
              @if($accion === 'devolucion')
                @php
                  $folioDev =
                    ($log->devolucion?->folio ?? null)
                    ?? ($cambios['devolucion_folio']['antes'] ?? null)
                    ?? ($cambios['devolucion_folio'] ?? null)
                    ?? ($cambios['folio_devolucion']['antes'] ?? null)
                    ?? ($cambios['folio']['antes'] ?? null)
                    ?? null;
                @endphp

                <span class="ev-meta">· Devolución:
                  @if($log->devolucion_id && $log->devolucion?->folio)
                    <a href="{{ route('devoluciones.show', $log->devolucion_id) }}"
                      class="underline text-indigo-600">
                      {{ $log->devolucion->folio }}
                    </a>
                  @elseif($folioDev)
                    <span class="text-red-600 font-semibold">
                      {{ $folioDev }} (ELIMINADA)
                    </span>
                  @else
                    <span class="text-gray-500">SIN FOLIO</span>
                  @endif
                </span>
              @endif

              {{-- ==================== SI SE ELIMINA UNA RESPONSIVA ==================== --}}
              @if($accion === 'liberado_eliminacion')
                @if(isset($cambios['responsiva_folio']['antes']))
                  <span class="ev-meta">· Responsiva:
                    <span class="text-red-600 font-semibold">
                      {{ $cambios['responsiva_folio']['antes'] }} (ELIMINADA)
                    </span>
                  </span>
                @endif
              @endif

              {{-- Mostrar SIN FOLIO solo cuando la acción es devolucion --}}
              @if($accion === 'devolucion' && !$log->devolucion_id)
                <span class="ev-meta">· Devolución:
                  <span class="text-gray-500">SIN FOLIO</span>
                </span>
              @endif
            </div>

            {{-- ================= ESTADO ESPECIAL DE ASIGNACIÓN ================= --}}
            @if($accion === 'asignacion')
              @php
                $motivo = strtolower(
                  $cambios['motivo_entrega']['despues']
                  ?? $cambios['motivo_entrega']['antes']
                  ?? ''
                );

                if (!$motivo && $log->responsiva?->motivo_entrega) {
                  $motivo = strtolower($log->responsiva->motivo_entrega);
                }

                $motivoBonito = match($motivo) {
                  'prestamo_provisional' => 'Préstamo provisional',
                  'asignacion'           => 'Asignado',
                  default                => 'Asignado',
                };

                $badge = function ($estado) {
                  $e = strtolower($estado);
                  return match(true) {
                    str_contains($e, 'asignado') => 'badge-green',
                    str_contains($e, 'préstamo'),
                    str_contains($e, 'prestamo') => 'badge-yellow',
                    default                      => 'badge-gray',
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

              @php
                $unidadAntesRaw = $cambios['unidad_servicio_id']['antes'] ?? ($cambios['unidad']['antes'] ?? null);
                $unidadDespRaw  = $cambios['unidad_servicio_id']['despues'] ?? ($cambios['unidad']['despues'] ?? null);

                $unidadAntesTxt = is_numeric($unidadAntesRaw)
                  ? ($uniMap[(int)$unidadAntesRaw] ?? "ID: $unidadAntesRaw")
                  : ($unidadAntesRaw ?: 'Sin unidad');

                $unidadDespTxt = is_numeric($unidadDespRaw)
                  ? ($uniMap[(int)$unidadDespRaw] ?? "ID: $unidadDespRaw")
                  : ($unidadDespRaw ?: 'Sin unidad');
              @endphp

              @if($unidadAntesRaw !== null || $unidadDespRaw !== null)
                @if($unidadAntesTxt !== $unidadDespTxt)
                  <div style="margin-top:.25rem;margin-bottom:.5rem;">
                    <span class="ev-meta" style="font-weight:600;">Unidad de servicio:</span>
                    <span class="badge badge-blue">{{ $unidadAntesTxt }}</span>
                    →
                    <span class="badge badge-blue">{{ $unidadDespTxt }}</span>
                  </div>
                @endif
              @endif
            @endif

            {{-- ================= ESTADO ESPECIAL PARA EDICIÓN DE ASIGNACIÓN ================= --}}
            @if($accion === 'edicion_asignacion')
              @php
                $motivoAntes  = strtolower($cambios['motivo_entrega']['antes']   ?? '');
                $motivoDespues = strtolower($cambios['motivo_entrega']['despues'] ?? '');

                $estadoAnterior = $motivoAntes ?: strtolower(trim($log->estado_anterior ?? 'asignado'));
                $estadoNuevo    = $motivoDespues ?: strtolower(trim($log->estado_nuevo ?? 'asignado'));

                $map = [
                  'prestamo_provisional' => 'Préstamo provisional',
                  'préstamo_provisional' => 'Préstamo provisional',
                  'prestamo'             => 'Préstamo provisional',
                  'asignacion'           => 'Asignado',
                  'asignado'             => 'Asignado',
                ];

                $textoAnterior = $map[$estadoAnterior] ?? ucfirst($estadoAnterior);
                $textoNuevo    = $map[$estadoNuevo]    ?? ucfirst($estadoNuevo);

                $badge = function($txt) {
                  $t = strtolower($txt);
                  return match(true) {
                    str_contains($t, 'préstamo'),
                    str_contains($t, 'prestamo') => 'badge-yellow',
                    str_contains($t, 'asignado') => 'badge-green',
                    default => 'badge-gray',
                  };
                };

                $cambioAsignadoA    = isset($cambios['asignado_a']);
                $cambioEntregadoPor = isset($cambios['entregado_por']);
                $cambioFecha        = isset($cambios['fecha_entrega']);
                $mostrarTabla = $cambioAsignadoA || $cambioEntregadoPor || $cambioFecha;
              @endphp

              <div style="margin-top:.5rem;margin-bottom:.5rem;">
                <span class="ev-meta" style="font-weight:600;">Estado:</span>
                @if($textoAnterior === $textoNuevo)
                  <span class="badge {{ $badge($textoNuevo) }}">{{ $textoNuevo }}</span>
                @else
                  <span class="badge {{ $badge($textoAnterior) }}">{{ $textoAnterior }}</span>
                  →
                  <span class="badge {{ $badge($textoNuevo) }}">{{ $textoNuevo }}</span>
                @endif
              </div>

              @if($mostrarTabla)
                <table class="diff-table">
                  <thead>
                    <tr>
                      <th>Campo</th>
                      <th>Antes</th>
                      <th>Después</th>
                    </tr>
                  </thead>
                  <tbody>
                    @if($cambioAsignadoA)
                      <tr>
                        <td>Asignado a</td>
                        <td class="mono">{{ $cambios['asignado_a']['antes'] }}</td>
                        <td class="mono">{{ $cambios['asignado_a']['despues'] }}</td>
                      </tr>
                    @endif

                    @if($cambioEntregadoPor)
                      <tr>
                        <td>Entregado por</td>
                        <td class="mono">{{ $cambios['entregado_por']['antes'] }}</td>
                        <td class="mono">{{ $cambios['entregado_por']['despues'] }}</td>
                      </tr>
                    @endif

                    @if($cambioFecha)
                      <tr>
                        <td>Fecha entrega</td>
                        <td class="mono">{{ $cambios['fecha_entrega']['antes'] }}</td>
                        <td class="mono">{{ $cambios['fecha_entrega']['despues'] }}</td>
                      </tr>
                    @endif

                    @if($cambioAsignadoA)
                      <tr>
                        <td>Subsidiaria</td>
                        <td class="mono">{{ $cambios['subsidiaria']['antes'] ?? '—' }}</td>
                        <td class="mono">{{ $cambios['subsidiaria']['despues'] ?? '—' }}</td>
                      </tr>
                    @endif
                  </tbody>
                </table>
              @endif

              @php
                $unidadAntesRaw = $cambios['unidad_servicio_id']['antes'] ?? ($cambios['unidad']['antes'] ?? null);
                $unidadDespRaw  = $cambios['unidad_servicio_id']['despues'] ?? ($cambios['unidad']['despues'] ?? null);

                $unidadAntesTxt = is_numeric($unidadAntesRaw)
                  ? ($uniMap[(int)$unidadAntesRaw] ?? "ID: $unidadAntesRaw")
                  : ($unidadAntesRaw ?: 'Sin unidad');

                $unidadDespTxt = is_numeric($unidadDespRaw)
                  ? ($uniMap[(int)$unidadDespRaw] ?? "ID: $unidadDespRaw")
                  : ($unidadDespRaw ?: 'Sin unidad');
              @endphp

              @if($unidadAntesRaw !== null || $unidadDespRaw !== null)
                @if($unidadAntesTxt !== $unidadDespTxt)
                  <div style="margin-top:.25rem;margin-bottom:.5rem;">
                    <span class="ev-meta" style="font-weight:600;">Unidad de servicio:</span>
                    <span class="badge badge-blue">{{ $unidadAntesTxt }}</span>
                    →
                    <span class="badge badge-blue">{{ $unidadDespTxt }}</span>
                  </div>
                @endif
              @endif

              @continue
            @endif

            {{-- ====================== ESTADO ESPECIAL PARA DEVOLUCIÓN ====================== --}}
            @if($accion === 'devolucion')
              @php
                $logEliminacion = $historialOrdenado->first(function($h) use ($log) {
                  return $h->accion === 'liberado_eliminacion'
                    && $h->responsiva_id === $log->responsiva_id;
                });

                $estadoAnterior = strtolower($log->estado_anterior ?? '');
                $estadoAnteriorBonito = match($estadoAnterior) {
                  'asignado'              => 'Asignado',
                  'prestamo_provisional'  => 'Préstamo provisional',
                  'baja_colaborador'      => 'Baja colaborador',
                  'renovacion'            => 'Renovación',
                  default                 => ucfirst($estadoAnterior ?: '—'),
                };

                $motivo = strtolower(
                  $cambios['motivo_devolucion']['antes']
                  ?? $log->motivo
                  ?? $log->devolucion?->motivo
                  ?? ($logEliminacion->cambios['motivo_devolucion']['antes'] ?? null)
                  ?? ''
                );

                $motivoBonito = match($motivo) {
                  'baja_colaborador' => 'Baja colaborador',
                  'renovacion'       => 'Renovación',
                  default            => ucfirst($motivo ?: '—'),
                };

                $estadoFinalBonito = "Disponible";

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
                <span class="badge {{ $badge($estadoAnteriorBonito) }}">{{ $estadoAnteriorBonito }}</span>
                →
                <span class="badge {{ $badge($motivoBonito) }}">{{ $motivoBonito }}</span>
                →
                <span class="badge {{ $badge($estadoFinalBonito) }}">{{ $estadoFinalBonito }}</span>
              </div>
            @endif

            {{-- ====================== TABLA DE CAMBIOS ====================== --}}
            @if(!empty($cambios))

              {{-- === removido_edicion === --}}
              @if($accion === 'removido_edicion')
                @php
                  $motivoOriginal = strtolower($cambios['motivo_entrega']['antes'] ?? '');
                  $estadoBonito = match($motivoOriginal) {
                    'prestamo_provisional' => 'Préstamo provisional',
                    'asignacion'           => 'Asignado',
                    default                => 'Asignado',
                  };

                  $badge = fn($txt) => match(true) {
                    str_contains(strtolower($txt), 'préstamo'),
                    str_contains(strtolower($txt), 'prestamo') => 'badge-yellow',
                    str_contains(strtolower($txt), 'asignado') => 'badge-green',
                    default => 'badge-gray',
                  };
                @endphp

                <div style="margin-top:.5rem;margin-bottom:.5rem;">
                  <span class="ev-meta" style="font-weight:600;">Estado:</span>
                  <span class="badge {{ $badge($estadoBonito) }}">{{ $estadoBonito }}</span>
                  →
                  <span class="badge badge-gray">Disponible</span>
                </div>

                <table class="diff-table">
                  <thead>
                    <tr>
                      <th>Campo</th>
                      <th>Valor anterior</th>
                    </tr>
                  </thead>
                  <tbody>
                    @if(isset($cambios['asignado_a']))
                      <tr>
                        <td>Removido de</td>
                        <td class="mono">{{ $cambios['asignado_a']['antes'] ?? '—' }}</td>
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

              {{-- === liberado_eliminacion === --}}
              @if($accion === 'liberado_eliminacion')
                @php
                  $esDevolucionEliminada = isset($cambios['devolucion_folio']);

                  $estadoAnterior = strtolower(
                    $cambios['estado_anterior']['antes']
                    ?? $log->estado_anterior
                    ?? ($cambios['motivo_entrega']['antes'] ?? '')
                    ?? ''
                  );

                  $estadoAnteriorBonito = match($estadoAnterior) {
                    'asignacion', 'asignado' => 'Asignado',
                    'prestamo_provisional'   => 'Préstamo provisional',
                    'baja_colaborador'       => 'Baja colaborador',
                    'renovacion'             => 'Renovación',
                    default                  => ucfirst($estadoAnterior ?: '—'),
                  };

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

                @if($esDevolucionEliminada)
                  @php
                    $motivoDev = $cambios['motivo_devolucion']['antes'] ?? '';
                    $motivoBonito = match($motivoDev) {
                      'baja_colaborador' => 'Baja colaborador',
                      'renovacion'       => 'Renovación',
                      default            => ucfirst($motivoDev ?: '—'),
                    };
                  @endphp

                  <div style="margin-top:.5rem;margin-bottom:.5rem;">
                    <span class="ev-meta" style="font-weight:600;">Estado:</span>
                    <span class="badge {{ $badge('Disponible') }}">Disponible</span>
                    →
                    <span class="badge {{ $badge($motivoBonito) }}">{{ $motivoBonito }}</span>
                    →
                    <span class="badge {{ $badge($estadoAnteriorBonito) }}">{{ $estadoAnteriorBonito }}</span>
                  </div>
                @else
                  <div style="margin-top:.5rem;margin-bottom:.5rem;">
                    <span class="ev-meta" style="font-weight:600;">Estado:</span>
                    <span class="badge {{ $badge($estadoAnteriorBonito) }}">{{ $estadoAnteriorBonito }}</span>
                    →
                    <span class="badge badge-gray">Disponible</span>
                  </div>
                @endif

                <table class="diff-table">
                  <thead><tr><th>Campo</th><th>Valor anterior</th></tr></thead>
                  <tbody>
                    @if(isset($cambios['asignado_a']))
                      <tr>
                        <td>Removido de</td>
                        <td class="mono">{{ $cambios['asignado_a']['antes'] ?? '—' }}</td>
                      </tr>
                    @endif
                    @if(isset($cambios['eliminado_por']))
                      <tr>
                        <td>Eliminado por</td>
                        <td class="mono">{{ $cambios['eliminado_por']['despues'] ?? '—' }}</td>
                      </tr>
                    @endif
                  </tbody>
                </table>

                @continue
              @endif

              {{-- === devolución REAL (tabla propia) === --}}
              @if($accion === 'devolucion')
                @php
                  $logEliminacion = $historialOrdenado->first(function($h) use ($log) {
                    return $h->accion === 'liberado_eliminacion'
                      && $h->responsiva_id === $log->responsiva_id;
                  });

                  $fechaDev = $cambios['fecha_devolucion']['antes']
                    ?? ($log->devolucion?->fecha_devolucion
                      ? \Carbon\Carbon::parse($log->devolucion->fecha_devolucion)->format('d-m-Y')
                      : ($logEliminacion->cambios['fecha_devolucion']['antes'] ?? 'SIN FECHA'));

                  $subsidiariaDev = $cambios['subsidiaria']['antes']
                    ?? ($log->devolucion?->responsiva?->colaborador?->subsidiaria?->descripcion
                      ?? ($logEliminacion->cambios['subsidiaria']['antes'] ?? 'SIN SUBSIDIARIA'));

                  $actualizadoPor = $cambios['actualizado_por']['despues'] ?? '—';
                @endphp

                <table class="diff-table">
                  <thead><tr><th>Campo</th><th>Valor anterior</th></tr></thead>
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
                        <td class="mono">{{ $actualizadoPor }}</td>
                      </tr>
                    @endif
                    <tr>
                      <td>Fecha devolución</td>
                      <td class="mono">{{ $fechaDev }}</td>
                    </tr>
                    <tr>
                      <td>Subsidiaria</td>
                      <td class="mono">{{ $subsidiariaDev }}</td>
                    </tr>
                  </tbody>
                </table>

                @continue
              @endif

              {{-- === CREACIÓN === --}}
              @if($accion === 'creacion')
                @php
                  $spec = $cambios['especificaciones_base']
                    ?? $cambios['especificaciones']
                    ?? null;

                  $descripcionProducto = $serie->producto->descripcion ?? null;
                @endphp

                <table class="diff-table">
                  <thead><tr><th>Campo</th><th>Valor</th></tr></thead>
                  <tbody>
                    @if(!$spec && $descripcionProducto)
                      <tr><td>Descripción</td><td class="mono">{{ $descripcionProducto }}</td></tr>
                    @endif

                    @if($spec)
                      @php $specArr = is_array($spec) ? $spec : $toArr($spec); @endphp
                      @if(is_array($specArr))
                        @foreach($flatten($specArr) as $k => $v)
                          <tr>
                            <td>{{ $labelsSpec[$k] ?? ('Especificaciones: ' . ucfirst(str_replace(['_','.'],' ', $k))) }}</td>
                            <td class="mono">{{ $fmtVal($k, $v) }}</td>
                          </tr>
                        @endforeach
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
                          {{
                            $cambios['fecha_entrega']['antes']
                            ?? ($log->responsiva?->fecha_entrega
                              ? \Carbon\Carbon::parse($log->responsiva->fecha_entrega)->format('d-m-Y')
                              : 'SIN FECHA')
                          }}
                        </td>
                      </tr>

                      <tr>
                        <td>Subsidiaria</td>
                        <td class="mono">
                          {{
                            $cambios['subsidiaria']['antes']
                            ?? ($log->responsiva?->colaborador?->subsidiaria?->descripcion ?? 'SIN SUBSIDIARIA')
                          }}
                        </td>
                      </tr>
                    @endif

                    @foreach($cambios as $campo => $valor)
                      @if(in_array($campo, ['asignado_a','entregado_por','fecha_entrega','subsidiaria']))
                        @continue
                      @endif

                      @if($accion === 'asignacion' && $campo === 'unidad_servicio_id')
                        @continue
                      @endif

                      @if(is_array($valor) && array_key_exists('antes',$valor) && array_key_exists('despues',$valor))
                        @php
                          $antes = $toArr($valor['antes']);
                          $despues = $toArr($valor['despues']);
                          $esSpecs = in_array($campo, ['especificaciones', 'especificaciones_base']);
                        @endphp

                        {{-- ✅ ESPECIFICACIONES: mostrar todos los subcampos editados --}}
                        @if($esSpecs)
                          @php
                            $aFlat = $flatten(is_array($antes) ? $antes : []);
                            $dFlat = $flatten(is_array($despues) ? $despues : []);

                            $keys = array_unique(array_merge(array_keys($aFlat), array_keys($dFlat)));
                            sort($keys);

                            $diff = [];
                            foreach ($keys as $k) {
                              $va = $aFlat[$k] ?? null;
                              $vd = $dFlat[$k] ?? null;
                              if ($va != $vd) {
                                $diff[$k] = ['antes' => $va, 'despues' => $vd];
                              }
                            }
                          @endphp

                          @if(!empty($diff))
                            @foreach($diff as $k => $vals)
                              <tr>
                                <td>{{ $labelsSpec[$k] ?? ('Especificaciones: ' . ucfirst(str_replace(['_','.'],' ', $k))) }}</td>
                                @if($accion !== 'asignacion')
                                  <td class="mono text-gray-500">{{ $fmtVal($k, $vals['antes']) }}</td>
                                @endif
                                <td class="mono">{{ $fmtVal($k, $vals['despues']) }}</td>
                              </tr>
                            @endforeach
                          @endif

                          @continue
                        @endif

                        {{-- ✅ Caso normal (no especificaciones) --}}
                        @php
                          $format = function($v) use ($campo, $subsMap, $uniMap, $toArr) {
                            if ($campo === 'subsidiaria_id') {
                              if (!$v) return 'Sin subsidiaria';
                              return $subsMap[(int)$v] ?? "ID: $v";
                            }
                            if ($campo === 'unidad_servicio_id') {
                              if (!$v) return 'Sin unidad de servicio';
                              return $uniMap[(int)$v] ?? "ID: $v";
                            }

                            $v = $toArr($v);
                            if (is_array($v)) return json_encode($v, JSON_UNESCAPED_UNICODE);
                            return $v ?? '—';
                          };
                        @endphp

                        <tr>
                          <td>{{ $labelsCampo[$campo] ?? ucfirst(str_replace('_',' ', $campo)) }}</td>
                          @if($accion !== 'asignacion')
                            <td class="mono text-gray-500">{{ $format($antes) }}</td>
                          @endif
                          <td class="mono">{{ $format($despues) }}</td>
                        </tr>
                      @endif
                    @endforeach
                  </tbody>
                </table>
              @endif {{-- Fin edición general/creación --}}
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
