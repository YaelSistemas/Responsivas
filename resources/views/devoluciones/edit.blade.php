<x-app-layout title="Editar devolución">
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight text-center">Editar devolución</h2>
  </x-slot>

  @php
    $isCel = \Illuminate\Support\Str::startsWith((string)($devolucion->responsiva?->folio ?? ''), 'CEL-');
  @endphp

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
    .alert-warning { background: #fff7ed; border: 1px solid #fdba74; color: #92400e; border-radius: 8px; 
      padding: 12px; margin-bottom: 16px; font-weight: 500; opacity: 0; transition: opacity .4s ease; }
    .alert-warning.show { opacity: 1; }

    /* === "fake TomSelect" para inputs/readonly (igual que create) === */
    .ts-like{ border-radius: 8px; border: 1px solid #d1d5db; padding: 6px 8px; min-height: 38px; width: 100%; 
      background: #ffffff; box-shadow: none; display:flex; align-items:center; }
    .ts-like:hover{ border-color:#9ca3af; }
    .ts-like:focus,
    .ts-like:focus-within{ border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.12); outline:none; }

    /* para inputs date/text que usen el look */
    input.ts-like-input{ border:none !important; padding:0 !important; margin:0 !important; width:100%; 
      background:transparent !important; outline:none !important; font-size:14px; }

    /* Solo “apagamos” el TEXTO, no el cuadro */
    .ts-like.is-readonly{ background:#ffffff; border-color:#d1d5db; cursor:default; }
    .ts-like.is-readonly span{ color:#9ca3af; font-weight:400; }

    /* sin efecto azul de focus en readonly */
    .ts-like.is-readonly:focus,
    .ts-like.is-readonly:focus-within{ border-color:#d1d5db; box-shadow:none; }

    /* (opcional) texto más chico para valores fijos */
    .ts-like.small-text span{ font-size:13px; line-height:1.2; }

    /* === Tom Select: MISMO estilo que el resto de inputs (igual que create) === */
    .ts-wrapper.single .ts-control{ border-radius:8px; border:1px solid #d1d5db; padding:6px 8px; min-height:38px; box-shadow:none; background:#ffffff; }
    .ts-wrapper.single .ts-control:hover{ border-color:#9ca3af; }
    .ts-wrapper.single .ts-control:focus-within{ border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.12); }
    
    /* Dropdown arriba de todo, fondo blanco y scroll interno */
    .ts-dropdown{ z-index:9999 !important; max-height:none; overflow:visible; background:#ffffff; border:1px solid #d1d5db; box-shadow:0 10px 15px -3px rgba(0,0,0,.1); }
    .ts-dropdown .ts-dropdown-content{ max-height:220px; overflow-y:auto; background:#ffffff; }

    /* Subtítulo en labels (gris y más chico) */
    .label-sub{ font-size:12px; color:#9ca3af; font-weight:500; }
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
                <div class="ts-like is-readonly small-text">
                  <span>
                    {{ $devolucion->responsiva?->folio ?? '—' }}
                    — {{ $devolucion->responsiva?->colaborador?->nombre ?? '' }} {{ $devolucion->responsiva?->colaborador?->apellidos ?? '' }}
                  </span>
                </div>
                <input type="hidden" name="responsiva_id" value="{{ $devolucion->responsiva_id }}">
                @error('responsiva_id') <div class="err">{{ $message }}</div> @enderror

                <div style="margin-top:12px">
                  <label>Fecha de devolución</label>
                  <div class="ts-like">
                    <input type="date" name="fecha_devolucion" value="{{ $devolucion->fecha_devolucion ? \Illuminate\Support\Carbon::parse($devolucion->fecha_devolucion)->format('Y-m-d') : '' }}"
                      required class="ts-like-input">
                  </div>
                  @error('fecha_devolucion') <div class="err">{{ $message }}</div> @enderror
                </div>
              </div>

              {{-- Columna derecha --}}
              <div>
                <label>Motivo de devolución</label>

                @if($isCel)
                  <div class="ts-like is-readonly small-text">
                    <span>Resguardo</span>
                  </div>
                  <input type="hidden" name="motivo" value="resguardo">
                @else
                  {{-- ✅ NORMAL: igual que siempre --}}
                  <select name="motivo" id="motivoSelect" required>
                    <option value="" disabled>— Selecciona —</option>
                    <option value="baja_colaborador" {{ $devolucion->motivo == 'baja_colaborador' ? 'selected' : '' }}>Baja de colaborador</option>
                    <option value="renovacion" {{ $devolucion->motivo == 'renovacion' ? 'selected' : '' }}>Renovación</option>
                  </select>
                @endif

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
                        <th style="width:60px;">Devolver</th>
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
                                name="productos[{{ $p->id }}][]"
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
                <label>Recibió <span class="label-sub">(usuario admin)</span></label>

                @if(!empty($lockRecibi) && $lockRecibi)
                  {{-- ✅ SOLO CELULARES: Isidro logueado (no admin) => NO puede editar.
                      Mostramos el valor actual de la devolución (Gustavo/Yael/Isidro) --}}
                  <div class="ts-like is-readonly small-text">
                    <span>{{ optional($admins->firstWhere('id', $devolucion->recibi_id))->name ?? 'Sin asignar' }}</span>
                  </div>
                  <input type="hidden" name="recibi_id" value="{{ $devolucion->recibi_id }}">
                @else
                  {{-- ✅ Admin (o cualquier otro usuario distinto a Isidro) => editable
                      En celulares: $admins YA incluye a Isidro (lo agregamos en el controller) --}}
                  <select name="recibi_id" id="recibi_id" required>
                    <option value="">— Selecciona —</option>
                    @foreach($admins as $a)
                      <option value="{{ $a->id }}" @selected((string)$devolucion->recibi_id === (string)$a->id)>
                        {{ $a->name }}
                      </option>
                    @endforeach
                  </select>
                @endif

                @error('recibi_id') <div class="err">{{ $message }}</div> @enderror
              </div>

              {{-- Entregó --}}
              <div>
                <label>Entregó <span class="label-sub">(colaborador)</span></label>
                <select name="entrego_colaborador_id" id="entrego_colaborador_id" required>
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
                <label>Personal en Sitio <span class="label-sub">(colaborador)</span></label>
                <select name="psitio_colaborador_id" id="psitio_colaborador_id" required>
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
              <a href="{{ $isCel ? route('celulares.responsivas.index') : route('devoluciones.index') }}" class="btn-cancel">Cancelar</a>
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

          // Quitarla automáticamente en 5 segundos
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

  @push('styles')
      <link rel="stylesheet"
            href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css">
      <style>
          .ts-dropdown {
              max-height: 260px;
              overflow-y: auto;
              z-index: 9999 !important;
          }

          .ts-dropdown .ts-dropdown-content {
              max-height: inherit;
              overflow-y: auto;
          }
      </style>
  @endpush

  @push('scripts')
      <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
      <script>
          document.addEventListener('DOMContentLoaded', () => {
              const baseConfig = {
                  allowEmptyOption: true,
                  maxOptions: 5000,
                  sortField: { field: 'text', direction: 'asc' },
                  plugins: ['dropdown_input'],
                  dropdownParent: 'body',
                  onDropdownOpen: function () {
                      const rect = this.control.getBoundingClientRect();
                      const espacioAbajo = window.innerHeight - rect.bottom - 10;
                      const dropdown = this.dropdown;

                      if (dropdown) {
                          const minimo = 160;
                          const maximo = 260;
                          let alto = Math.max(minimo, Math.min(espacioAbajo, maximo));
                          dropdown.style.maxHeight = alto + 'px';
                      }
                  }
              };

              const recibiEl = document.getElementById('recibi_id');
              if (recibiEl) {
                new TomSelect('#recibi_id', {
                  ...baseConfig,
                  placeholder: 'Selecciona quién recibe…',
                });
              }

              if (document.getElementById('entrego_colaborador_id')) {
                  new TomSelect('#entrego_colaborador_id', {
                      ...baseConfig,
                      placeholder: 'Selecciona colaborador que entrega…',
                  });
              }

              if (document.getElementById('psitio_colaborador_id')) {
                  new TomSelect('#psitio_colaborador_id', {
                      ...baseConfig,
                      placeholder: 'Selecciona colaborador de sitio…',
                  });
              }
          });
      </script>
  @endpush

</x-app-layout>
