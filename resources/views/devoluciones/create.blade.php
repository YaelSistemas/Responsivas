{{-- resources/views/devoluciones/create.blade.php --}}
<x-app-layout title="Nueva devolución">
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight text-center">Registrar devolución</h2>
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
    .grid3 {display:grid;grid-template-columns:repeat(3,1fr);gap:16px;}
    @media (max-width:768px){ .grid3{grid-template-columns:1fr;} }

    /* 🔶 Notificación tipo advertencia */
    .alert-warning {
      background:#fff7ed;
      border:1px solid #fdba74;
      color:#92400e;
      border-radius:8px;
      padding:12px;
      margin-bottom:16px;
      font-weight:500;
      opacity:0;
      transition:opacity .4s ease;
    }
    .alert-warning.show { opacity:1; }

    /* === Tom Select: MISMO estilo que el resto de inputs === */
    .ts-wrapper.single .ts-control {
      border-radius:8px;
      border:1px solid #d1d5db;
      padding:6px 8px;
      min-height:38px;
      box-shadow:none;
      background:#ffffff;
    }
    .ts-wrapper.single .ts-control:hover {
      border-color:#9ca3af;
    }
    .ts-wrapper.single .ts-control:focus-within {
      border-color:#2563eb;
      box-shadow:0 0 0 3px rgba(37,99,235,.12);
    }

    /* Dropdown arriba de todo, fondo blanco y scroll interno */
    .ts-dropdown {
      z-index:9999 !important;
      max-height:none;
      overflow:visible;
      background:#ffffff;
      border:1px solid #d1d5db;
      box-shadow:0 10px 15px -3px rgba(0,0,0,.1);
    }
    .ts-dropdown .ts-dropdown-content {
      max-height:220px;
      overflow-y:auto;
      background:#ffffff;
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

          <form method="POST" action="{{ route('devoluciones.store') }}">
            @csrf

            {{-- ======= Datos ======= --}}
            <div class="section-sep"><div class="line"></div><div class="label">Datos</div><div class="line"></div></div>

            <div class="grid2 row">
              {{-- Columna izquierda: Responsiva + Fecha debajo --}}
              <div>
                <label>Responsiva</label>
                <select name="responsiva_id" id="responsivaSelect" required>
                  <option value="">— Selecciona una responsiva —</option>
                  @foreach($responsivas as $r)
                    <option value="{{ $r->id }}">
                      {{ $r->folio }} — {{ $r->colaborador->nombre }} {{ $r->colaborador->apellidos ?? '' }}
                    </option>
                  @endforeach
                </select>
                @error('responsiva_id') <div class="err">{{ $message }}</div> @enderror

                <div style="margin-top:12px">
                  <label>Fecha de devolución</label>
                  <input type="date" name="fecha_devolucion" required>
                  @error('fecha_devolucion') <div class="err">{{ $message }}</div> @enderror
                </div>
              </div>

              {{-- Columna derecha: Motivo de devolución --}}
              <div>
                <label>Motivo de devolución</label>
                <select name="motivo" id="motivoSelect" required>
                  <option value="" disabled {{ old('motivo') ? '' : 'selected' }}>— Selecciona —</option>
                  <option value="baja_colaborador" {{ old('motivo')==='baja_colaborador' ? 'selected' : '' }}>Baja de colaborador</option>
                  <option value="renovacion" {{ old('motivo')==='renovacion' ? 'selected' : '' }}>Renovación</option>
                </select>
                @error('motivo') <div class="err">{{ $message }}</div> @enderror
              </div>
            </div>

            {{-- ======= Productos ======= --}}
            <div class="section-sep"><div class="line"></div><div class="label">Productos</div><div class="line"></div></div>

            <div id="productosContainer" class="row hidden">
              <label>Selecciona los productos a devolver</label>
              <table class="tbl" id="productosTable">
                <thead>
                  <tr>
                    <th>Producto</th>
                    <th>Descripción / Color</th>
                    <th>Marca</th>
                    <th>Modelo</th>
                    <th>Serie</th>
                    <th>Devolver</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
              @error('productos') <div class="err">{{ $message }}</div> @enderror
            </div>

            {{-- ======= Firmas ======= --}}
            <div class="section-sep"><div class="line"></div><div class="label">Firmas</div><div class="line"></div></div>

            <div class="grid3 row firmas-grid">
              {{-- Recibió (admin) --}}
              <div>
                <label>Recibió (usuario admin)</label>
                <select name="recibi_id" id="recibiSelect" required>
                  <option value="">— Selecciona —</option>
                  @foreach($admins as $a)
                    <option value="{{ $a->id }}" {{ isset($adminDefault) && $adminDefault == $a->id ? 'selected' : '' }}>
                      {{ $a->name }}
                    </option>
                  @endforeach
                </select>
                @error('recibi_id') <div class="err">{{ $message }}</div> @enderror
              </div>

              {{-- Entregó (colaborador) --}}
              <div>
                <label>Entregó (colaborador)</label>
                <select name="entrego_colaborador_id" id="entregoSelect" required>
                  <option value="">— Selecciona —</option>
                  @foreach($colaboradores as $c)
                    <option value="{{ $c->id }}">{{ $c->nombre }} {{ $c->apellidos }}</option>
                  @endforeach
                </select>
                @error('entrego_colaborador_id') <div class="err">{{ $message }}</div> @enderror
              </div>

              {{-- Psitio (colaborador) --}}
              <div>
                <label>Psitio (colaborador)</label>
                <select name="psitio_colaborador_id" id="psitioSelect" required>
                  <option value="">— Selecciona —</option>
                  @foreach($colaboradores as $c)
                    <option value="{{ $c->id }}">{{ $c->nombre }} {{ $c->apellidos }}</option>
                  @endforeach
                </select>
                @error('psitio_colaborador_id') <div class="err">{{ $message }}</div> @enderror
              </div>
            </div>

            {{-- ======= Botones ======= --}}
            <div class="grid2 row">
              <a href="{{ route('devoluciones.index') }}" class="btn-cancel">Cancelar</a>
              <button type="submit" class="btn">Guardar devolución</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  {{-- ===== JS para cargar productos y autocompletar entregó ===== --}}
  <script>
    const responsivaSelect   = document.getElementById('responsivaSelect');
    const entregoSelect      = document.getElementById('entregoSelect');
    const productosContainer = document.getElementById('productosContainer');
    const productosTable     = document.querySelector('#productosTable tbody');
    const responsivas        = @json($responsivas);
    const warningBox         = document.getElementById('warningBox');

    let currentRespColaboradorId = null;

    function showWarning(resp) {
        if (!warningBox) return;

        warningBox.innerHTML =
          `<b>Advertencia:</b> El colaborador seleccionado no coincide con el de la responsiva <b>${resp.folio}</b> (${resp.colaborador.nombre} ${resp.colaborador.apellidos ?? ''}).`;
        warningBox.style.display = 'block';
        warningBox.classList.add('show');

        setTimeout(() => {
            warningBox.classList.remove('show');
            setTimeout(() => warningBox.style.display = 'none', 400);
        }, 5000);
    }

    function renderProductos(resp) {
        productosTable.innerHTML = '';

        if (!resp || !resp.detalles || !resp.detalles.length) {
            productosContainer.classList.add('hidden');
            return;
        }

        productosContainer.classList.remove('hidden');

        resp.detalles.forEach(d => {
            const p      = d.producto || {};
            const nombre = p.nombre || 'Producto sin nombre';
            const desc   = p.descripcion || '-';
            const marca  = p.marca || '-';
            const modelo = p.modelo || '-';
            const serie  = d.serie?.serie || d.producto_serie_id || '-';

            productosTable.innerHTML += `
                <tr>
                  <td>${nombre}</td>
                  <td>${desc}</td>
                  <td>${marca}</td>
                  <td>${modelo}</td>
                  <td>${serie}</td>
                  <td>
                    <input type="checkbox" name="productos[${p.id}][]" value="${d.producto_serie_id}" class="h-4 w-4">
                  </td>
                </tr>`;
        });
    }

    // Cuando cambia la responsiva
    responsivaSelect.addEventListener('change', e => {
        const id   = e.target.value;
        const resp = responsivas.find(r => r.id == id) || null;

        currentRespColaboradorId = resp && resp.colaborador ? String(resp.colaborador.id) : null;

        renderProductos(resp);

        // Autocompletar "Entregó (colaborador)" con el colaborador de la responsiva
        if (currentRespColaboradorId) {
            if (entregoSelect && entregoSelect.tomselect) {
                entregoSelect.tomselect.setValue(currentRespColaboradorId);
            } else if (entregoSelect) {
                entregoSelect.value = currentRespColaboradorId;
            }
        }
    });

    // Si cambian manualmente "Entregó", mostramos advertencia si no coincide
    entregoSelect.addEventListener('change', () => {
        if (!currentRespColaboradorId) return;

        const seleccionado = entregoSelect.value;
        const resp = responsivas.find(
          r => r.colaborador && String(r.colaborador.id) === currentRespColaboradorId
        );

        if (resp && seleccionado && seleccionado !== currentRespColaboradorId) {
            showWarning(resp);
        }
    });
  </script>

  @push('styles')
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css">
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

        // Responsiva
        if (document.getElementById('responsivaSelect')) {
          new TomSelect('#responsivaSelect', {
            ...baseConfig,
            placeholder: 'Selecciona una responsiva…',
          });
        }

        // Motivo de devolución (para que tenga el mismo diseño)
        if (document.getElementById('motivoSelect')) {
          new TomSelect('#motivoSelect', {
            ...baseConfig,
            placeholder: 'Selecciona motivo…',
          });
        }

        // Recibió (admin)
        if (document.getElementById('recibiSelect')) {
          new TomSelect('#recibiSelect', {
            ...baseConfig,
            placeholder: 'Selecciona quién recibe…',
          });
        }

        // Entregó (colaborador)
        if (document.getElementById('entregoSelect')) {
          new TomSelect('#entregoSelect', {
            ...baseConfig,
            placeholder: 'Selecciona colaborador que entrega…',
          });
        }

        // Psitio (colaborador)
        if (document.getElementById('psitioSelect')) {
          new TomSelect('#psitioSelect', {
            ...baseConfig,
            placeholder: 'Selecciona colaborador (psitio)…',
          });
        }
      });
    </script>
  @endpush

</x-app-layout>
