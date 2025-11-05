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
    padding: .85rem .6rem; border-left: 3px solid #e5e7eb;
    margin-left: .6rem; position: relative;
  }
  .timeline li::before {
    content: ""; position: absolute; left: -9px; top: 1.1rem;
    width: 10px; height: 10px; border-radius: 999px;
    background: #3b82f6;
  }
  .timeline li.creacion::before { background: #22c55e; } /* verde */
  .timeline li.eliminacion::before { background: #ef4444; } /* rojo */
  .timeline li.actualizacion::before { background: #3b82f6; } /* azul */

  .ev-head { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; }
  .ev-title { font-weight: 700; color: #111827; }
  .ev-meta { font-size: .80rem; color: #6b7280; }
  .diff-table { width: 100%; border-collapse: collapse; margin-top: .5rem; }
  .diff-table th,.diff-table td { border: 1px solid #e5e7eb; padding: .35rem .45rem; font-size: .88rem; }
  .mono { font-family: ui-monospace, Menlo, Consolas, monospace; }
</style>

<div class="colab-modal-backdrop" data-modal-backdrop>
  <div class="colab-modal" role="dialog" aria-modal="true" aria-label="Historial del colaborador">
    <header>
      <div>
        <div class="ev-title">Historial — {{ $colaborador->nombre }} {{ $colaborador->apellidos }}</div>
        <div class="ev-meta">ID #{{ $colaborador->id }}</div>
      </div>
      <button type="button" class="close" data-modal-close>✕</button>
    </header>

    <div class="content">
      @if($historiales->isEmpty())
        <p class="ev-meta">Sin historial registrado para este colaborador.</p>
      @else
        <ul class="timeline">
          @foreach($historiales as $log)
            @php
              $user = $log->usuario->name ?? 'Sistema';
              $fecha = optional($log->created_at)->format('d-m-Y H:i');
              $accion = strtolower($log->accion ?? 'actualización');
              $cambios = $log->cambios ?? [];
            @endphp
            <li class="{{ $accion }}">
              <div class="ev-head">
                <span class="ev-title">
                  {{ ucfirst($accion) }}
                </span>
                <span class="ev-meta">— {{ $user }} · {{ $fecha }}</span>
              </div>

              {{-- Mostrar cambios en tabla --}}
              @if(!empty($cambios))
                <table class="diff-table">
                  <thead>
                    <tr>
                      <th>Campo</th>
                      @if($accion === 'edición' || $accion === 'actualización')
                        <th>Valor anterior</th>
                        <th>Nuevo valor</th>
                      @else
                        <th colspan="2">Valor</th>
                      @endif
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($cambios as $campo => $valor)
                      @if(is_array($valor) && isset($valor['de'], $valor['a']))
                        <tr>
                          <td>{{ ucfirst(str_replace('_',' ', $campo)) }}</td>
                          <td class="mono text-gray-500">{{ $valor['de'] ?? '—' }}</td>
                          <td class="mono">{{ $valor['a'] ?? '—' }}</td>
                        </tr>
                      @else
                        <tr>
                          <td>{{ ucfirst(str_replace('_',' ', $campo)) }}</td>
                          <td class="mono" colspan="2">{{ $valor ?? '—' }}</td>
                        </tr>
                      @endif
                    @endforeach
                  </tbody>
                </table>
              @else
                <div class="ev-meta">Sin datos adicionales.</div>
              @endif
            </li>
          @endforeach
        </ul>
      @endif
    </div>
  </div>
</div>

