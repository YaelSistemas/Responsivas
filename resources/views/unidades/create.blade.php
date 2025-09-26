<x-app-layout title="Nueva unidad de servicio">
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight text-center">
      Crear unidad de servicio
    </h2>
  </x-slot>

  <style>
    /* ====== Zoom responsivo: MISMA VISTA, SOLO MÁS “PEQUEÑA” EN MÓVIL ====== */
    .zoom-outer{ overflow-x:hidden; } /* evita scroll horizontal por el ancho compensado */
    .zoom-inner{
      --zoom: 1;                       /* valor por defecto en desktop */
      transform: scale(var(--zoom));
      transform-origin: top left;
      /* compensamos el ancho para que visualmente quepa todo sin recortar */
      width: calc(100% / var(--zoom));
    }
    /* Breakpoints (mismos que ya usaste en las otras vistas) */
    @media (max-width: 1024px){ .zoom-inner{ --zoom:.95; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }  /* tablets landscape */
    @media (max-width: 768px){  .zoom-inner{ --zoom:.90; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }  /* tablets/phones grandes */
    @media (max-width: 640px){  .zoom-inner{ --zoom:.70; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} } /* phones comunes */
    @media (max-width: 400px){  .zoom-inner{ --zoom:.55; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }  /* phones muy chicos */

    /* iOS: evita auto-zoom al enfocar inputs */
    @media (max-width: 768px){
      input, select, textarea{ font-size:16px; }
    }

    /* ====== Estilos propios ====== */
    .page-wrap{max-width:1100px;margin:0 auto}
    .form-container{max-width:700px;margin:0 auto;background:#fff;padding:24px;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,.1)}
    .form-group{margin-bottom:16px}
    .form-container label{display:block;margin-bottom:6px;color:#374151;font-weight:600}
    .form-container input,.form-container textarea,.form-container select{width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;font-size:14px}
    .form-container input:focus,.form-container textarea:focus,.form-container select:focus{outline:none;border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.12)}
    .hint{font-size:12px;color:#6b7280;margin-top:6px}
    .err{color:#dc2626;font-size:12px;margin-top:6px}
    .form-buttons{display:flex;gap:12px;justify-content:space-between;padding-top:20px}
    .btn-cancel{background:#dc2626;color:#fff;padding:8px 16px;border:none;border-radius:6px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}
    .btn-cancel:hover{background:#b91c1c}
    .btn-save{background:#16a34a;color:#fff;padding:8px 16px;border:none;border-radius:6px;font-weight:600;cursor:pointer}
    .btn-save:hover{background:#15803d}

    /* En móviles, apilar botones */
    @media (max-width: 480px){
      .form-buttons{flex-direction:column-reverse;align-items:stretch}
      .btn-cancel,.btn-save{width:100%}
    }
  </style>

  <!-- Envoltura de zoom: mantiene el layout, solo escala visualmente en móvil -->
  <div class="zoom-outer">
    <div class="zoom-inner">
      <div class="page-wrap py-6">
        <div class="form-container">
          @if ($errors->any())
            <div style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:8px;padding:12px;margin-bottom:14px;">
              <div style="font-weight:700;margin-bottom:6px;">Se encontraron errores:</div>
              <ul style="margin-left:18px;list-style:disc;">
                @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
              </ul>
            </div>
          @endif

          <form method="POST" action="{{ route('unidades.store') }}">
            @csrf

            <div class="form-group">
              <label for="nombre">Nombre <span class="hint">(requerido)</span></label>
              <input id="nombre" name="nombre" value="{{ old('nombre') }}" required autofocus>
              @error('nombre') <div class="err">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
              <label for="direccion">Dirección <span class="hint">(opcional)</span></label>
              <textarea id="direccion" name="direccion" rows="3">{{ old('direccion') }}</textarea>
              @error('direccion') <div class="err">{{ $message }}</div> @enderror
            </div>

            <div class="form-group" id="responsable-wrap" data-url="{{ route('api.colaboradores.buscar') }}">
              <label for="responsable_search">Responsable <span class="hint">(opcional)</span></label>

              @php
                $respNombre = old('responsable_name', '');
              @endphp

              <input type="hidden" name="responsable_id" id="responsable_id" value="{{ old('responsable_id','') }}">
              <input type="text" id="responsable_search" name="responsable_name"
                     value="{{ $respNombre }}" autocomplete="off"
                     placeholder="Escribe el nombre del colaborador…">

              <div id="responsable_suggestions" style="position:relative;margin-top:4px;"></div>
              @error('responsable_id') <div class="err">{{ $message }}</div> @enderror
            </div>

            <div class="form-buttons">
              <a href="{{ route('unidades.index') }}" class="btn-cancel">Cancelar</a>
              <button class="btn-save" type="submit">Crear</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  {{-- Autocomplete responsable --}}
  <script>
  (function(){
    const wrap   = document.getElementById('responsable-wrap');
    const url    = wrap.dataset.url;
    const box    = document.getElementById('responsable_search');
    const hid    = document.getElementById('responsable_id');
    const sug    = document.getElementById('responsable_suggestions');
    let ctl, t, activeIndex = -1;

    function clearList(){ sug.innerHTML = ''; activeIndex = -1; }
    function setHidden(id, text){ hid.value = id || ''; box.value = text || ''; clearList(); }

    function render(items){
      if(!items.length){ clearList(); return; }
      const ul = document.createElement('ul');
      ul.style.position = 'absolute';
      ul.style.left = '0'; ul.style.right = '0';
      ul.style.background = '#fff';
      ul.style.border = '1px solid #e5e7eb';
      ul.style.borderRadius = '6px';
      ul.style.boxShadow = '0 8px 20px rgba(0,0,0,.12)';
      ul.style.zIndex = '50';
      ul.style.listStyle = 'none';
      ul.style.margin = '0'; ul.style.padding = '4px 0';

      items.forEach((it, i)=>{
        const li = document.createElement('li');
        li.textContent = it.text;
        li.style.padding = '8px 12px';
        li.style.cursor  = 'pointer';
        li.addEventListener('mouseenter', ()=> setActive(i, ul));
        li.addEventListener('mouseleave', ()=> setActive(-1, ul));
        li.addEventListener('click', ()=> setHidden(it.id, it.text));
        ul.appendChild(li);
      });

      clearList();
      sug.appendChild(ul);
    }

    function setActive(idx, ul){
      const lis = ul.querySelectorAll('li');
      lis.forEach((li,j)=> li.style.background = (j===idx)? '#f3f4f6' : 'transparent');
      activeIndex = idx;
    }

    function load(q){
      if(ctl) ctl.abort();
      ctl = new AbortController();

      fetch(url + '?q=' + encodeURIComponent(q || ''), {
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        signal: ctl.signal
      })
      .then(r => r.json())
      .then(render)
      .catch(err => { if(err.name !== 'AbortError') console.error(err); });
    }

    function debouncedLoad(q){
      clearTimeout(t);
      t = setTimeout(()=> load(q), 200);
    }

    // Mostrar lista inicial (top 10) al enfocar o hacer clic, aun si está vacío
    box.addEventListener('focus', ()=> load(''));
    box.addEventListener('click', ()=> load(box.value.trim()));

    // Filtrar mientras se escribe (si está vacío, muestra top 10)
    box.addEventListener('input', ()=>{
      hid.value = ''; // si escribe, invalidamos selección previa
      const q = box.value.trim();
      debouncedLoad(q); // con q=='' cargará top 10
    });

    // Navegación con teclado
    box.addEventListener('keydown', (e)=>{
      const ul = sug.querySelector('ul');
      if(!ul) return;
      const lis = ul.querySelectorAll('li');

      if(e.key === 'ArrowDown'){
        e.preventDefault();
        setActive((activeIndex+1) % lis.length, ul);
      } else if(e.key === 'ArrowUp'){
        e.preventDefault();
        setActive((activeIndex-1+lis.length) % lis.length, ul);
      } else if(e.key === 'Enter'){
        if(activeIndex >= 0){
          e.preventDefault();
          lis[activeIndex].click();
        }
      } else if(e.key === 'Escape'){
        clearList();
      }
    });

    // Cierra si se hace clic fuera
    document.addEventListener('click', (e)=>{
      if(!wrap.contains(e.target)) clearList();
    });
  })();
  </script>
</x-app-layout>
