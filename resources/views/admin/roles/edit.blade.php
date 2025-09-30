@extends('layouts.admin')

@section('title', 'Editar Rol')

@section('content')
<style>
  /* ====== Zoom responsivo (igual que create) ====== */
  .zoom-outer{ overflow-x:hidden; }
  .zoom-inner{
    --zoom:.92; transform:scale(var(--zoom)); transform-origin:top left; width:calc(100%/var(--zoom));
  }
  @media (max-width:1024px){ .zoom-inner{ --zoom:.95 } .page-wrap{max-width:94vw;padding:0 4vw} }
  @media (max-width:768px){  .zoom-inner{ --zoom:.92 } .page-wrap{max-width:94vw;padding:0 4vw} }
  @media (max-width:640px){  .zoom-inner{ --zoom:.85 } .page-wrap{max-width:94vw;padding:0 4vw} }
  @media (max-width:400px){  .zoom-inner{ --zoom:.80 } .page-wrap{max-width:94vw;padding:0 4vw} }
  @media (max-width:768px){ input,select,textarea,button{ font-size:16px } }

  /* Ancho útil */
  .page-wrap{ max-width:980px; margin:0 auto; }

  /* Botones / secciones */
  .btn-cancelar{
    background:#dc2626;color:#fff;padding:8px 16px;border:none;border-radius:6px;
    font-weight:600;text-decoration:none;text-align:center;transition:background .3s;
  }
  .btn-cancelar:hover{ background:#b91c1c; }
  .btn-guardar{
    background:#16a34a;color:#fff;padding:8px 16px;border:none;border-radius:6px;
    font-weight:600;cursor:pointer;transition:background .3s;
  }
  .btn-guardar:hover{ background:#15803d; }

  .muted{color:#6b7280;font-size:.85rem}
  .section{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px}
  .section + .section{margin-top:14px}
  .section-h{display:flex;align-items:center;gap:10px;margin-bottom:10px}
  .section-title{font-weight:700}
  .divider{height:1px;background:#e5e7eb;margin:10px 0}

  /* 2 columnas (como create) -> 1 solo en <480px */
  .col-grid{ display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
  @media (max-width:480px){ .col-grid{ grid-template-columns:1fr } }

  .card{background:#fafafa;border:1px dashed #e5e7eb;border-radius:10px;padding:10px}
  .card-title{font-weight:600;margin-bottom:6px}
  .perm-list label{display:inline-flex;align-items:center;gap:.5rem;margin:.2rem 0}
</style>

<div class="zoom-outer">
  <div class="zoom-inner">
    <div class="page-wrap max-w-4xl mx-auto py-6">
      <h2 class="text-xl font-semibold text-center mb-6">Editar rol</h2>

      <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Slug interno (único) --}}
        <div>
          <label class="block mb-1 font-medium">Nombre del rol</label>
          <input type="text" name="name" value="{{ old('name', $role->name) }}" class="w-full border rounded px-3 py-2" required>
          <small class="muted">Debe ser único. Ej: administrador, supervisor.</small>
          @error('name') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
        </div>

        {{-- Nombre público --}}
        <div>
          <label class="block mb-1 font-medium">Nombre a Mostrar</label>
          <input type="text" name="display_name" value="{{ old('display_name', $role->display_name) }}" class="w-full border rounded px-3 py-2">
          @error('display_name') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
        </div>

        {{-- Descripción --}}
        <div>
          <label class="block mb-1 font-medium">Descripción (opcional)</label>
          <textarea name="description" rows="3" class="w-full border rounded px-3 py-2">{{ old('description', $role->description) }}</textarea>
          @error('description') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
        </div>

        {{-- ====== Permisos (idéntico al create) ====== --}}
        @php
          // seleccionados = old() o los permisos actuales del rol
          $sel = old('permissions', $rolePermissions ?? []);
        @endphp

        <div class="section">
          <div class="section-h">
            <div class="section-title">Permisos</div>
            <label class="ml-auto inline-flex items-center gap-2 text-sm">
              <input id="checkAll" type="checkbox">
              <span>Marcar todos</span>
            </label>
          </div>
          <div class="divider"></div>

          {{-- === RH === --}}
          <div class="section">
            <div class="section-h">
              <div class="section-title">RH</div>
              <label class="ml-auto inline-flex items-center gap-2 text-sm">
                <input class="check-section" data-target=".sec-rh" type="checkbox">
                <span>Marcar sección</span>
              </label>
            </div>

            <div class="col-grid sec-rh">
              {{-- Izquierda: colaboradores, unidades, subsidiarias --}}
              <div class="card">
                <div class="card-title">Colaboradores</div>
                <div class="perm-list">
                  @foreach(($groups['colaboradores'] ?? []) as $p)
                    <label>
                      <input class="perm" type="checkbox" name="permissions[]"
                             value="{{ $p['name'] }}" {{ in_array($p['name'], $sel) ? 'checked' : '' }}>
                      <span>{{ $p['label'] }}</span>
                    </label><br>
                  @endforeach
                </div>

                <div class="divider"></div>
                <div class="card-title">Unidades de servicio</div>
                <div class="perm-list">
                  @foreach(($groups['unidades'] ?? []) as $p)
                    <label>
                      <input class="perm" type="checkbox" name="permissions[]"
                             value="{{ $p['name'] }}" {{ in_array($p['name'], $sel) ? 'checked' : '' }}>
                      <span>{{ $p['label'] }}</span>
                    </label><br>
                  @endforeach
                </div>

                <div class="divider"></div>
                <div class="card-title">Subsidiarias</div>
                <div class="perm-list">
                  @foreach(($groups['subsidiarias'] ?? []) as $p)
                    <label>
                      <input class="perm" type="checkbox" name="permissions[]"
                             value="{{ $p['name'] }}" {{ in_array($p['name'], $sel) ? 'checked' : '' }}>
                      <span>{{ $p['label'] }}</span>
                    </label><br>
                  @endforeach
                </div>
              </div>

              {{-- Derecha: áreas, puestos --}}
              <div class="card">
                <div class="card-title">Áreas</div>
                <div class="perm-list">
                  @foreach(($groups['areas'] ?? []) as $p)
                    <label>
                      <input class="perm" type="checkbox" name="permissions[]"
                             value="{{ $p['name'] }}" {{ in_array($p['name'], $sel) ? 'checked' : '' }}>
                      <span>{{ $p['label'] }}</span>
                    </label><br>
                  @endforeach
                </div>

                <div class="divider"></div>
                <div class="card-title">Puestos</div>
                <div class="perm-list">
                  @foreach(($groups['puestos'] ?? []) as $p)
                    <label>
                      <input class="perm" type="checkbox" name="permissions[]"
                             value="{{ $p['name'] }}" {{ in_array($p['name'], $sel) ? 'checked' : '' }}>
                      <span>{{ $p['label'] }}</span>
                    </label><br>
                  @endforeach
                </div>
              </div>
            </div>
          </div>

          {{-- === Productos === --}}
          <div class="section">
            <div class="section-h">
              <div class="section-title">Productos</div>
              <label class="ml-auto inline-flex items-center gap-2 text-sm">
                <input class="check-section" data-target=".sec-productos" type="checkbox">
                <span>Marcar sección</span>
              </label>
            </div>

            <div class="sec-productos">
              <div class="card">
                <div class="card-title">Productos</div>
                <div class="perm-list">
                  @foreach(($groups['productos'] ?? []) as $p)
                    <label>
                      <input class="perm" type="checkbox" name="permissions[]"
                             value="{{ $p['name'] }}" {{ in_array($p['name'], $sel) ? 'checked' : '' }}>
                      <span>{{ $p['label'] }}</span>
                    </label><br>
                  @endforeach
                </div>
              </div>
            </div>
          </div>

          {{-- === Formatos === --}}
          <div class="section">
            <div class="section-h">
              <div class="section-title">Formatos</div>
              <label class="ml-auto inline-flex items-center gap-2 text-sm">
                <input class="check-section" data-target=".sec-formatos" type="checkbox">
                <span>Marcar sección</span>
              </label>
            </div>

            <div class="sec-formatos">
              <div class="card">
                <div class="card-title">Responsivas</div>
                <div class="perm-list">
                  @foreach(($groups['responsivas'] ?? []) as $p)
                    <label>
                      <input class="perm" type="checkbox" name="permissions[]"
                             value="{{ $p['name'] }}" {{ in_array($p['name'], $sel) ? 'checked' : '' }}>
                      <span>{{ $p['label'] }}</span>
                    </label><br>
                  @endforeach
                </div>
              </div>
            </div>
          </div>

          @error('permissions')   <div class="text-red-600 text-sm mt-2">{{ $message }}</div> @enderror
          @error('permissions.*') <div class="text-red-600 text-sm mt-2">{{ $message }}</div> @enderror
        </div>

        <div class="flex gap-2 justify-end">
          <a href="{{ route('admin.roles.index') }}" class="btn-cancelar">Cancelar</a>
          <button type="submit" class="btn-guardar">Actualizar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  // Marcar TODOS
  document.getElementById('checkAll')?.addEventListener('change', function(){
    document.querySelectorAll('input.perm').forEach(cb => cb.checked = this.checked);
    document.querySelectorAll('.check-section').forEach(s => s.checked = this.checked);
  });

  // Marcar por sección
  document.querySelectorAll('.check-section').forEach(secChk => {
    secChk.addEventListener('change', function(){
      const targetSel = this.getAttribute('data-target');
      if(!targetSel) return;
      document.querySelectorAll(targetSel + ' input.perm').forEach(cb => cb.checked = this.checked);

      // sincroniza el global (indeterminate si hay mezcla)
      const all = [...document.querySelectorAll('input.perm')];
      if(!all.length) return;
      const allChecked = all.every(x => x.checked);
      const anyChecked = all.some(x => x.checked);
      const global = document.getElementById('checkAll');
      if(global){
        global.indeterminate = !allChecked && anyChecked;
        global.checked = allChecked;
      }
    });
  });

  // Estado inicial del “Marcar todos”
  (function syncGlobal(){
    const all = [...document.querySelectorAll('input.perm')];
    if(!all.length) return;
    const allChecked = all.every(x => x.checked);
    const anyChecked = all.some(x => x.checked);
    const global = document.getElementById('checkAll');
    if(global){
      global.indeterminate = !allChecked && anyChecked;
      global.checked = allChecked;
    }
  })();
</script>
@endsection
