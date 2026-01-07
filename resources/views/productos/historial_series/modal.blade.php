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

  .badge-blue { background:#dbeafe; color:#1e40af; }
</style>

@php
  // Mapas id => nombre (para que el historial no muestre IDs)
  $subsMap = \App\Models\Subsidiaria::query()->pluck('nombre', 'id')->toArray();
  $uniMap  = \App\Models\UnidadServicio::query()->pluck('nombre', 'id')->toArray();

  // Labels bonitos para los campos
  $labelsCampo = [
    'subsidiaria_id'      => 'Subsidiaria',
    'unidad_servicio_id'  => 'Unidad de servicio',
  ];
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
              case 'liberado_eliminacion':
                  $accionMostrar = 'Liberado por eliminación';
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
              'liberado_eliminacion' => 'removido',
              'baja'             => 'baja',
              default            => 'edicion',
          };

          $cambios = $log->cambios ?? [];
        @endphp

        @php
            $folioEliminadoGlobal = null;

            foreach ($historial as $h) {
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

                      {{-- Caso 1: La responsiva ya no existe y sí tenemos folio eliminado global --}}
                      @if(!$log->responsiva && $folioEliminadoGlobal)
                          <span class="text-red-600 font-semibold">
                              {{ $folioEliminadoGlobal }}
                          </span>

                      {{-- Caso 2: Responsiva existe normalmente --}}
                      @elseif($log->responsiva)
                          <a href="{{ route('responsivas.show', $log->responsiva_id) }}"
                            class="underline text-indigo-600">
                              {{ $log->responsiva->folio }}
                          </a>

                      {{-- Caso 3: No tenemos responsiva ni historial (muy raro) --}}
                      @else
                          <span class="text-gray-500">SIN FOLIO</span>
                      @endif

                  </span>
              @endif

              {{-- ====================== DEVOLUCIÓN (SIEMPRE MOSTRAR FOLIO) ====================== --}}
              @if($accion === 'devolucion')

                  @php
                      // Folio de devolución: buscar en todos los lugares posibles
                      $folioDev =
                          ($log->devolucion?->folio ?? null)                           // Existe en la BD
                          ?? ($cambios['devolucion_folio']['antes'] ?? null)          // Guardado en destroy()
                          ?? ($cambios['devolucion_folio'] ?? null)                   // Guardado directo
                          ?? ($cambios['folio_devolucion']['antes'] ?? null)          // Variante
                          ?? ($cambios['folio']['antes'] ?? null)                     // Otra variante
                          ?? null;
                  @endphp

                  <span class="ev-meta">· Devolución:

                      {{-- Caso 1: Si existe en BD --}}
                      @if($log->devolucion_id && $log->devolucion?->folio)
                          <a href="{{ route('devoluciones.show', $log->devolucion_id) }}"
                            class="underline text-indigo-600">
                              {{ $log->devolucion->folio }}
                          </a>

                      {{-- Caso 2: Eliminada pero SÍ tenemos folio --}}
                      @elseif($folioDev)
                          <span class="text-red-600 font-semibold">
                              {{ $folioDev }} (ELIMINADA)
                          </span>

                      {{-- Caso 3: Nunca tuvo folio --}}
                      @else
                          <span class="text-gray-500">SIN FOLIO</span>
                      @endif

                  </span>

              @endif

              {{-- ==================== SI SE ELIMINA UNA RESPONSIVA ==================== --}}
              @if($accion === 'liberado_eliminacion')

                  {{-- Mostrar el folio anterior de la responsiva --}}
                  @if(isset($cambios['responsiva_folio']['antes']))
                      <span class="ev-meta">· Responsiva:
                          <span class="text-red-600 font-semibold">
                              {{ $cambios['responsiva_folio']['antes'] }} (ELIMINADA)
                          </span>
                      </span>
                  @endif
                  {{-- NO mostrar devolución --}}
                  @php $omitDevolucion = true; @endphp
              @endif

              {{-- Mostrar SIN FOLIO solo cuando la acción es "devolucion" --}}
              @if($accion === 'devolucion' && !$log->devolucion_id)
                  <span class="ev-meta">· Devolución:
                      <span class="text-gray-500">SIN FOLIO</span>
                  </span>
              @endif

          </div>

          {{-- ================= ESTADO ESPECIAL DE ASIGNACIÓN ================= --}}
          @if($accion === 'asignacion')

              @php
                  // Motivo entregado REAL desde historial
                  $motivo = strtolower(
                      $cambios['motivo_entrega']['despues']
                      ?? $cambios['motivo_entrega']['antes']
                      ?? ''
                  );

                  // Si por alguna razón no se guardó, tomar del objeto responsiva (solo si existe)
                  if (!$motivo && $log->responsiva?->motivo_entrega) {
                      $motivo = strtolower($log->responsiva->motivo_entrega);
                  }

                  $motivoBonito = match($motivo) {
                      'prestamo_provisional' => 'Préstamo provisional',
                      'asignacion'           => 'Asignado',
                      default                => 'Asignado', // fallback seguro
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
                  // 1. Recuperar el motivo ANTERIOR y NUEVO si existen
                  $motivoAntes  = strtolower($cambios['motivo_entrega']['antes']   ?? '');
                  $motivoDespues = strtolower($cambios['motivo_entrega']['despues'] ?? '');
  
                  // 2. Si NO existían en cambios, usar estado_anterior / estado_nuevo reales
                  $estadoAnterior = $motivoAntes ?: strtolower(trim($log->estado_anterior ?? 'asignado'));
                  $estadoNuevo    = $motivoDespues ?: strtolower(trim($log->estado_nuevo ?? 'asignado'));
  
                  // 3. Mapeo bonito
                  $map = [
                      'prestamo_provisional' => 'Préstamo provisional',
                      'préstamo_provisional' => 'Préstamo provisional',
                      'prestamo'             => 'Préstamo provisional',
                      'asignacion'           => 'Asignado',
                      'asignado'             => 'Asignado',
                  ];
  
                  $textoAnterior = $map[$estadoAnterior] ?? ucfirst($estadoAnterior);
                  $textoNuevo    = $map[$estadoNuevo]    ?? ucfirst($estadoNuevo);
  
                  // 4. Badges
                  $badge = function($txt) {
                      $t = strtolower($txt);
                      return match(true) {
                          str_contains($t, 'préstamo'),
                          str_contains($t, 'prestamo') => 'badge-yellow',
  
                          str_contains($t, 'asignado') => 'badge-green',
  
                          default => 'badge-gray',
                      };
                  };
  
                  // 5. Detectar si se editaron campos extra
                  $cambioAsignadoA    = isset($cambios['asignado_a']);
                  $cambioEntregadoPor = isset($cambios['entregado_por']);
                  $cambioFecha        = isset($cambios['fecha_entrega']);
  
                  $mostrarTabla = $cambioAsignadoA || $cambioEntregadoPor || $cambioFecha;
              @endphp  
  
  
              {{-- === SIEMPRE mostrar estado arriba === --}}
              <div style="margin-top:.5rem;margin-bottom:.5rem;">
                  <span class="ev-meta" style="font-weight:600;">Estado:</span>
  
                  {{-- Si NO cambió el estado, mostrar solo 1 --}}
                  @if($textoAnterior === $textoNuevo)
                      <span class="badge {{ $badge($textoNuevo) }}">{{ $textoNuevo }}</span>
  
                  {{-- Si SÍ cambió, mostrar ambos --}}
                  @else
                      <span class="badge {{ $badge($textoAnterior) }}">{{ $textoAnterior }}</span>
                      →
                      <span class="badge {{ $badge($textoNuevo) }}">{{ $textoNuevo }}</span>
                  @endif
              </div>
  
              {{-- === TABLA === --}}
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

                        {{-- 1. ASIGNADO A --}}
                        @if($cambioAsignadoA)
                            <tr>
                                <td>Asignado a</td>
                                <td class="mono">{{ $cambios['asignado_a']['antes'] }}</td>
                                <td class="mono">{{ $cambios['asignado_a']['despues'] }}</td>
                            </tr>
                        @endif

                        {{-- 2. ENTREGADO POR --}}
                        @if($cambioEntregadoPor)
                            <tr>
                                <td>Entregado por</td>
                                <td class="mono">{{ $cambios['entregado_por']['antes'] }}</td>
                                <td class="mono">{{ $cambios['entregado_por']['despues'] }}</td>
                            </tr>
                        @endif

                        {{-- 3. FECHA ENTREGA --}}
                        @if($cambioFecha)
                            <tr>
                                <td>Fecha entrega</td>
                                <td class="mono">{{ $cambios['fecha_entrega']['antes'] }}</td>
                                <td class="mono">{{ $cambios['fecha_entrega']['despues'] }}</td>
                            </tr>
                        @endif

                        {{-- 4. SUBSIDIARIA — SOLO SI SE EDITÓ ASIGNADO A --}}
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
                  // Siempre debemos declararlo ANTES de usarlo
                  $logEliminacion = $historial->first(function($h) use ($log) {
                      return $h->accion === 'liberado_eliminacion'
                          && $h->responsiva_id === $log->responsiva_id;
                  });

                  // 1. Estado anterior
                  $estadoAnterior = strtolower($log->estado_anterior ?? '');

                  $estadoAnteriorBonito = match($estadoAnterior) {
                      'asignado'              => 'Asignado',
                      'prestamo_provisional'  => 'Préstamo provisional',
                      'baja_colaborador'      => 'Baja colaborador',
                      'renovacion'            => 'Renovación',
                      default                 => ucfirst($estadoAnterior ?: '—'),
                  };

                  // 2. Motivo de la devolución — SOLO desde devolución
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

                  // 3. Estado final
                  $estadoFinalBonito = "Disponible";

                  // Badge color
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

                  {{-- Estado final --}}
                  <span class="badge {{ $badge($estadoFinalBonito) }}">
                      {{ $estadoFinalBonito }}
                  </span>
              </div>
          @endif

          {{-- ====================== TABLA DE CAMBIOS ====================== --}}
          @if(!empty($cambios))

          {{-- === "removido_edicion" === --}}
          @if($accion === 'removido_edicion')

              {{-- === ESTADO LÓGICO INVERTIDO PARA LIBERADO POR EDICIÓN === --}}
              @php
                  // Motivo original antes de la edición
                  $motivoOriginal = strtolower($cambios['motivo_entrega']['antes'] ?? '');

                  $estadoBonito = match($motivoOriginal) {
                      'prestamo_provisional' => 'Préstamo provisional',
                      'asignacion'           => 'Asignado',
                      default                => 'Asignado', // fallback
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

                  {{-- Estado ORIGINAL --}}
                  <span class="badge {{ $badge($estadoBonito) }}">
                      {{ $estadoBonito }}
                  </span>

                  →

                  {{-- Estado FINAL --}}
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
          {{-- ============================================================= --}}

          {{-- "liberado_eliminacion" --}}
          @if($accion === 'liberado_eliminacion')

              @php
                  $esDevolucionEliminada = isset($cambios['devolucion_folio']); // <-- Detecta si fue devolución

                  // Buscar estado anterior
                  $estadoAnterior = strtolower(
                      $cambios['estado_anterior']['antes']
                      ?? $log->estado_anterior
                      ?? ($cambios['motivo_entrega']['antes'] ?? '')   // <-- NUEVO (necesario)
                      ?? ''
                  );

                  $estadoAnteriorBonito = match($estadoAnterior) {
                      'asignacion', 'asignado' => 'Asignado',
                      'prestamo_provisional'   => 'Préstamo provisional',
                      'baja_colaborador'       => 'Baja colaborador',
                      'renovacion'             => 'Renovación',
                      default                  => ucfirst($estadoAnterior ?: '—'),
                  };

                  // Badge helper
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

              {{-- ========================================================= --}}
              {{--   CASO 1: Eliminación de DEVOLUCIÓN → 3 pasos             --}}
              {{-- ========================================================= --}}
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

              {{-- ========================================================= --}}
              {{--   CASO 2: Eliminación de RESPONSIVA → SOLO 2 pasos         --}}
              {{-- ========================================================= --}}
              @else
                  <div style="margin-top:.5rem;margin-bottom:.5rem;">
                      <span class="ev-meta" style="font-weight:600;">Estado:</span>

                      {{-- Estado anterior (Asignado / Prestamo provisional) --}}
                      <span class="badge {{ $badge($estadoAnteriorBonito) }}">
                          {{ $estadoAnteriorBonito }}
                      </span>

                      →

                      {{-- Estado final --}}
                      <span class="badge badge-gray">Disponible</span>
                  </div>
              @endif


              {{-- ===== Tablas de cambios (se deja igual) ===== --}}
              <table class="diff-table">
                  <thead>
                      <tr><th>Campo</th><th>Valor anterior</th></tr>
                  </thead>
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
          {{-- ============================================================= --}}

          {{-- === VISTA ESPECIAL PARA DEVOLUCIÓN REAL === --}}
          @if($accion === 'devolucion')
              @php
                  // Buscar si existe un registro de eliminación relacionado para recuperar datos
                  $logEliminacion = $historial->first(function($h) use ($log) {
                      return $h->accion === 'liberado_eliminacion'
                          && $h->responsiva_id === $log->responsiva_id;
                  });

                  // Fecha devolución: primero cambios del propio log, luego relación, luego log de eliminación
                  $fechaDev = $cambios['fecha_devolucion']['antes']
                      ?? ($log->devolucion?->fecha_devolucion
                          ? \Carbon\Carbon::parse($log->devolucion->fecha_devolucion)->format('d-m-Y')
                          : (
                              $logEliminacion->cambios['fecha_devolucion']['antes']
                                  ?? 'SIN FECHA'
                          ));

                  // Subsidiaria: primero cambios del propio log, luego relación, luego log eliminación
                  $subsidiariaDev = $cambios['subsidiaria']['antes']
                      ?? ($log->devolucion?->responsiva?->colaborador?->subsidiaria?->descripcion
                          ?? (
                              $logEliminacion->cambios['subsidiaria']['antes']
                                  ?? 'SIN SUBSIDIARIA'
                          ));

                  // Actualizado por: viene del propio log de devolución
                  $actualizadoPor = $cambios['actualizado_por']['despues'] ?? '—';
              @endphp

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
                          ?? ($log->responsiva?->colaborador?->subsidiaria?->descripcion
                                  ?? 'SIN SUBSIDIARIA')
                      }}
                  </td>
                </tr>
            @endif

            @foreach($cambios as $campo => $valor)

                @if(in_array($campo, ['asignado_a','entregado_por','fecha_entrega','subsidiaria']))
                    @continue
                @endif

                {{-- ✅ Solo omitir unidad_servicio_id cuando la acción sea asignación --}}
                @if($accion === 'asignacion' && $campo === 'unidad_servicio_id')
                    @continue
                @endif

                @if(is_array($valor) && isset($valor['antes']) && isset($valor['despues']))
                    @php
                      $antes = $valor['antes'];
                      $despues = $valor['despues'];

                      $format = function($v) use ($campo, $subsMap, $uniMap) {

                            // ✅ Convertir IDs a nombres
                            if ($campo === 'subsidiaria_id') {
                                if (!$v) return 'Sin subsidiaria';
                                return $subsMap[(int)$v] ?? "ID: $v";
                            }

                            if ($campo === 'unidad_servicio_id') {
                                if (!$v) return 'Sin unidad de servicio';
                                return $uniMap[(int)$v] ?? "ID: $v";
                            }

                            // ---- tu lógica actual ----
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
