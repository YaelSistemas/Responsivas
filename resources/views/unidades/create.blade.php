<x-app-layout title="Nueva unidad de servicio">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight text-center">
            Crear unidad de servicio
        </h2>
    </x-slot>

    <style>
        .form-container{max-width:700px;margin:0 auto;background:#fff;padding:24px;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,.1)}
        .form-group{margin-bottom:16px}
        .form-container input,.form-container textarea, .form-container select{width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;font-size:14px}
        .hint{font-size:12px;color:#6b7280;margin-top:6px}
        .err{color:#dc2626;font-size:12px;margin-top:6px}
        .form-buttons{display:flex;justify-content:space-between;padding-top:20px}
        .btn-cancel{background:#dc2626;color:#fff;padding:8px 16px;border:none;border-radius:6px;font-weight:600;text-decoration:none}
        .btn-cancel:hover{background:#b91c1c}
        .btn-save{background:#16a34a;color:#fff;padding:8px 16px;border:none;border-radius:6px;font-weight:600;cursor:pointer}
        .btn-save:hover{background:#15803d}
    </style>

    <div class="py-6">
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
                    <label>Nombre <span class="hint">(requerido)</span></label>
                    <input name="nombre" value="{{ old('nombre') }}" required autofocus>
                    @error('nombre') <div class="err">{{ $message }}</div> @enderror
                </div>

                <div class="form-group">
                    <label>Dirección <span class="hint">(opcional)</span></label>
                    <textarea name="direccion" rows="3">{{ old('direccion') }}</textarea>
                    @error('direccion') <div class="err">{{ $message }}</div> @enderror
                </div>

                <div class="form-group" id="responsable-wrap" data-url="{{ route('api.colaboradores.buscar') }}">
                    <label>Responsable <span class="hint">(opcional)</span></label>

                    @php
                        $respNombre = '';
                        if (old('responsable_name')) {
                            $respNombre = old('responsable_name');
                        } elseif (isset($unidad) && $unidad->responsable) {
                            $respNombre = trim($unidad->responsable->nombre.' '.$unidad->responsable->apellidos);
                        }
                    @endphp

                    <input type="hidden" name="responsable_id" id="responsable_id"
                        value="{{ old('responsable_id', isset($unidad)? $unidad->responsable_id : '') }}">

                    <input type="text" id="responsable_search" name="responsable_name"
                        value="{{ $respNombre }}" autocomplete="off"
                        placeholder="Escribe el nombre del colaborador…">

                    <div id="responsable_suggestions"
                        style="position:relative;margin-top:4px;"></div>

                    @error('responsable_id') <div class="err">{{ $message }}</div> @enderror
                </div>

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

                <div class="form-buttons">
                    <a href="{{ route('unidades.index') }}" class="btn-cancel">Cancelar</a>
                    <button class="btn-save" type="submit">Crear</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
