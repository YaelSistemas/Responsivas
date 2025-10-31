<x-app-layout title="Editar devolución">
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight text-center">Editar devolución</h2>
  </x-slot>

  <style>
    .zoom-outer{ overflow-x:hidden; }
    .zoom-inner{
      --zoom: 1;
      transform: scale(var(--zoom));
      transform-origin: top left;
      width: calc(100% / var(--zoom));
    }
    @media (max-width:1024px){ .zoom-inner{ --zoom:.95; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }
    @media (max-width:768px){  .zoom-inner{ --zoom:.90; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }
    @media (max-width:640px){  .zoom-inner{ --zoom:.70; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }
    @media (max-width:400px){  .zoom-inner{ --zoom:.55; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }
    @media (max-width:768px){ input, select, textarea{ font-size:16px; } }

    .wrap{max-width:880px;margin:0 auto;background:#fff;padding:24px;border-radius:10px;box-shadow:0 2px 6px rgba(0,0,0,.08)}
    .row{margin-bottom:16px}
    select,textarea,input{width:100%;padding:8px;border:1px solid #d1d5db;border-radius:8px}
    .err{color:#dc2626;font-size:12px;margin-top:6px}
    .btn{background:#2563eb;color:#fff;padding:10px 16px;border-radius:8px;font-weight:600;border:none}
    .btn:hover{background:#1e40af}
    .btn-cancel{background:#dc2626;color:#fff;padding:10px 16px;border-radius:8px;text-decoration:none}
    .grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
    .section-sep{display:flex;align-items:center;margin:22px 0 14px}
    .section-sep .line{flex:1;height:1px;background:#e5e7eb}
    .section-sep .label{margin:0 10px;font-size:12px;color:#6b7280;letter-spacing:.06em;text-transform:uppercase;font-weight:700;white-space:nowrap}
    table.tbl{width:100%;border-collapse:collapse;margin-top:8px}
    table.tbl th, table.tbl td{border:1px solid #e5e7eb;padding:6px 8px;text-align:center;font-size:14px}
    table.tbl th{background:#f9fafb;font-weight:600}
    .grid3 {display: grid;grid-template-columns: repeat(3, 1fr);gap: 16px;}
    @media (max-width:768px){ .grid3 {grid-template-columns: 1fr;} }

    /* 🔶 Notificación tipo advertencia */
    .alert-warning {
      background: #fff7ed;
      border: 1px solid #fdba74;
      color: #92400e;
      border-radius: 8px;
      padding: 12px;
      margin-bottom: 16px;
      font-weight: 500;
      opacity: 0;
      transition: opacity .4s ease;
    }
    .alert-warning.show {
      opacity: 1;
    }
  </style>

  <div class="zoom-outer">
    <div class="zoom-inner">
      <div class="py-6">
        <div class="wrap">

          {{-- 🔶 Contenedor para advertencia dinámica --}}
          <div id="warningBox" class="alert-warning" style="display:none;"></div>

          @if ($errors->any())
            <div style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:8px;padding:12px;margin-bottom:14px;">
              <b>Revisa los campos:</b>
              <ul style="margin-left:18px;list-style:disc;">
                @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
              </ul>
            </div>
          @endif

          <form method="POST" action="{{ route('devoluciones.update', $devolucion->id) }}">
            @csrf
            @method('PUT')

            {{-- ======= Datos ======= --}}
            <div class="section-sep"><div class="line"></div><div class="label">Datos</div><div class="line"></div></div>

            <div class="grid2 row">
              {{-- Columna izquierda --}}
              <div>
                <label>Responsiva</label>
                <select name="responsiva_id" id="responsivaSelect" required disabled>
                  <option value="">— Selecciona una responsiva —</option>
                  @foreach($responsivas as $r)
                    <option value="{{ $r->id }}" 
                      {{ $devolucion->responsiva_id == $r->id ? 'selected' : '' }}>
                      {{ $r->folio }} — {{ $r->colaborador->nombre }} {{ $r->colaborador->apellidos ?? '' }}
                    </option>
                  @endforeach
                </select>
                <input type="hidden" name="responsiva_id" value="{{ $devolucion->responsiva_id }}">
                @error('responsiva_id') <div class="err">{{ $message }}</div> @enderror

                <div style="margin-top:12px">
                  <label>Fecha de devolución</label>
                  <input type="date" name="fecha_devolucion" value="{{ $devolucion->fecha_devolucion ? \Illuminate\Support\Carbon::parse($devolucion->fecha_devolucion)->format('Y-m-d') : '' }}" required>
                  @error('fecha_devolucion') <div class="err">{{ $message }}</div> @enderror
                </div>
              </div>

              {{-- Columna derecha --}}
              <div>
                <label>Motivo de devolución</label>
                <select name="motivo" required>
                  <option value="" disabled>— Selecciona —</option>
                  <option value="baja_colaborador" {{ $devolucion->motivo == 'baja_colaborador' ? 'selected' : '' }}>Baja de colaborador</option>
                  <option value="renovacion" {{ $devolucion->motivo == 'renovacion' ? 'selected' : '' }}>Renovación</option>
                </select>
                @error('motivo') <div class="err">{{ $message }}</div> @enderror
              </div>
            </div>

            {{-- ======= Productos ======= --}}
            <div class="section-sep"><div class="line"></div><div class="label">Productos</div><div class="line"></div></div>

            <div id="productosContainer" class="row">
                <label>Selecciona los productos devueltos</label>

                <table class="tbl" id="productosTable">
                    <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Descripción / Color</th>
                        <th>Marca</th>
                        <th>Modelo</th>
                        <th>Serie</th>
                        <th style="width:60px;">Devolver</th> {{-- ✅ Mover aquí al final --}}
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($detallesActuales as $d)
                        @php
                        $p = $d->producto;
                        $s = $d->serie;
                        $checked = in_array($s->id, $seriesSeleccionadas) ? 'checked' : '';
                        @endphp
                        <tr>
                        <td>{{ $p->nombre }}</td>
                        <td>{{ $p->descripcion ?? '-' }}</td>
                        <td>{{ $p->marca ?? '-' }}</td>
                        <td>{{ $p->modelo ?? '-' }}</td>
                        <td>{{ $s?->serie ?? '-' }}</td>
                        <td>
                            <input
                            type="checkbox"
                            name="productos[{{ $p->id }}]"
                            value="{{ $s->id }}"
                            {{ $checked }}
                            >
                        </td>
                        </tr>
                    @empty
                        <tr>
                        <td colspan="6" class="text-center text-gray-500">Sin productos asignados actualmente a esta responsiva.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>

                @error('productos') <div class="err">{{ $message }}</div> @enderror
            </div>

            {{-- ======= Firmas ======= --}}
            <div class="section-sep"><div class="line"></div><div class="label">Firmas</div><div class="line"></div></div>

            <div class="grid3 row firmas-grid">
              {{-- Recibió --}}
              <div>
                <label>Recibió (usuario admin)</label>
                <select name="recibi_id" required>
                  <option value="">— Selecciona —</option>
                  @foreach($admins as $a)
                    <option value="{{ $a->id }}" {{ $devolucion->recibi_id == $a->id ? 'selected' : '' }}>
                      {{ $a->name }}
                    </option>
                  @endforeach
                </select>
                @error('recibi_id') <div class="err">{{ $message }}</div> @enderror
              </div>

              {{-- Entregó --}}
              <div>
                <label>Entregó (colaborador)</label>
                <select name="entrego_colaborador_id" required>
                  <option value="">— Selecciona —</option>
                  @foreach($colaboradores as $c)
                    <option value="{{ $c->id }}" {{ $devolucion->entrego_colaborador_id == $c->id ? 'selected' : '' }}>
                      {{ $c->nombre }} {{ $c->apellidos }}
                    </option>
                  @endforeach
                </select>
                @error('entrego_colaborador_id') <div class="err">{{ $message }}</div> @enderror
              </div>

              {{-- Psitio --}}
              <div>
                <label>Psitio (colaborador)</label>
                <select name="psitio_colaborador_id" required>
                  <option value="">— Selecciona —</option>
                  @foreach($colaboradores as $c)
                    <option value="{{ $c->id }}" {{ $devolucion->psitio_colaborador_id == $c->id ? 'selected' : '' }}>
                      {{ $c->nombre }} {{ $c->apellidos }}
                    </option>
                  @endforeach
                </select>
                @error('psitio_colaborador_id') <div class="err">{{ $message }}</div> @enderror
              </div>
            </div>

            {{-- ======= Botones ======= --}}
            <div class="grid2 row">
              <a href="{{ route('devoluciones.index') }}" class="btn-cancel">Cancelar</a>
              <button type="submit" class="btn">Actualizar devolución</button>
            </div>
          </form>

        </div>
      </div>
    </div>
  </div>

  {{-- 🔶 Notificación tipo advertencia superior --}}
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const selectEntrego = document.querySelector('select[name="entrego_colaborador_id"]');
      const warningBox = document.getElementById('warningBox');
      const originalColaborador = "{{ $devolucion->responsiva?->colaborador?->id }}";
      const nombreOriginal = "{{ $devolucion->responsiva?->colaborador?->nombre }} {{ $devolucion->responsiva?->colaborador?->apellidos }}";
      const folio = "{{ $devolucion->responsiva?->folio }}";

      if (!selectEntrego || !originalColaborador) return;

      selectEntrego.addEventListener('change', () => {
        const seleccionado = selectEntrego.value;
        if (seleccionado && seleccionado !== originalColaborador) {
          warningBox.innerHTML = `
            <b>Advertencia:</b> El colaborador seleccionado no coincide con el de la responsiva <b>${folio}</b>
            (${nombreOriginal}).
          `;
          warningBox.style.display = 'block';
          warningBox.classList.add('show');

          // Quitarla automáticamente en 3 segundos
          setTimeout(() => {
            warningBox.classList.remove('show');
            setTimeout(() => warningBox.style.display = 'none', 400);
          }, 5000);
        }
      });
    });
  </script>

  {{-- 🔘 Seleccionar/Deseleccionar todos --}}
  <script>
    document.addEventListener('DOMContentLoaded', () => {
        const toggle = document.getElementById('chkAll');
        if (!toggle) return;
        toggle.addEventListener('change', (e) => {
        document.querySelectorAll('#productosTable tbody input[type="checkbox"]').forEach(chk => {
            chk.checked = e.target.checked;
        });
        });
    });
  </script>
</x-app-layout>
