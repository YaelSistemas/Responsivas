<x-app-layout title="Editar serie {{ $serie->serie }}">
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight text-center">
      Editar descripción – {{ $producto->nombre }} (Serie: {{ $serie->serie }})
    </h2>
  </x-slot>

  <style>
    /* ====== Zoom responsivo (misma vista, solo escala en móvil) ====== */
    .zoom-outer{ overflow-x:hidden; }
    .zoom-inner{
      --zoom: 1;
      transform: scale(var(--zoom));
      transform-origin: top left;
      width: calc(100% / var(--zoom));
    }
    @media (max-width: 1024px){ .zoom-inner{ --zoom:.95; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }
    @media (max-width: 768px){  .zoom-inner{ --zoom:.90; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }
    @media (max-width: 640px){  .zoom-inner{ --zoom:.70; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }
    @media (max-width: 400px){  .zoom-inner{ --zoom:.55; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }

    @media (max-width: 768px){ input, select, textarea{ font-size:16px; } }

    /* ====== Estilos de la vista (mismo look & feel que edit.blade) ====== */
    .box{max-width:760px;margin:0 auto;background:#fff;padding:24px;border-radius:10px;box-shadow:0 2px 6px rgba(0,0,0,.08)}
    label{display:block;margin-bottom:6px;color:#111827;font-weight:600}
    .inp{width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px}
    .hint{font-size:12px;color:#6b7280}
    .err{color:#dc2626;font-size:12px;margin-top:6px}
    .actions{display:flex;justify-content:space-between;gap:10px;margin-top:14px;flex-wrap:wrap}
    .btn-save{background:#16a34a;color:#fff;padding:10px 16px;border:none;border-radius:8px;font-weight:700;cursor:pointer}
    .btn-save:hover{background:#15803d}
    .btn-cancel{background:#f3f4f6;border:1px solid #e5e7eb;color:#374151;padding:10px 16px;border-radius:8px;font-weight:700;text-decoration:none}
  </style>

  <div class="zoom-outer">
    <div class="zoom-inner">
      <div class="py-6">
        <div class="box space-y-4">

          @if ($errors->any())
            <div style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:8px;padding:12px;">
              <b>Revisa los campos:</b>
              <ul style="margin-left:18px;list-style:disc;">
                @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
              </ul>
            </div>
          @endif

          <form id="serieForm" method="POST" action="{{ route('productos.series.update', [$serie->producto, $serie]) }}">
            @csrf @method('PUT')

            <p class="hint">
              Esta serie no maneja especificaciones técnicas como un equipo de cómputo.
              Aquí puedes registrar una <b>descripción</b> o notas internas (ej. “con cable USB”, “tóner nuevo”, “raspón en tapa”, etc.).
            </p>

            <div>
              <label>Descripción / notas</label>
              <textarea class="inp" name="descripcion" id="descripcion" rows="6"
                        placeholder="Escribe la descripción o notas…">{{ old('descripcion', $descripcion ?? $serie->observaciones) }}</textarea>
              @error('descripcion') <div class="err">{{ $message }}</div> @enderror
            </div>

            <div class="actions">
              <a href="{{ route('productos.series', $producto) }}" class="btn-cancel">Cancelar</a>
              <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <button type="button" class="btn-cancel" id="btn-clear">Limpiar</button>
                <button class="btn-save" type="submit" id="btn-submit">Guardar</button>
              </div>
            </div>
          </form>

          <div class="hint">
            <b>Producto:</b> {{ $producto->nombre }} &middot;
            <b>Serie:</b> {{ $serie->serie }} &middot;
            <b>Estado:</b> {{ ucfirst($serie->estado) }}
          </div>

        </div>
      </div>
    </div>
  </div>

  <script>
    (function(){
      const form     = document.getElementById('serieDescForm');
      const desc     = document.getElementById('descripcion');
      const btnClear = document.getElementById('btn-clear');
      const btnSave  = document.getElementById('btn-submit');

      // Focus inicial
      desc?.focus();

      // Limpiar campo
      btnClear?.addEventListener('click', ()=>{ desc.value=''; desc.focus(); });

      // Previene doble envío
      let sending = false;
      form?.addEventListener('submit', (e)=>{
        if(sending){ e.preventDefault(); return; }
        sending = true; btnSave.disabled = true; btnSave.style.opacity = .7;
      });

      // Atajos
      document.addEventListener('keydown', (e)=>{
        if((e.ctrlKey||e.metaKey) && e.key.toLowerCase()==='s'){ e.preventDefault(); btnSave?.click(); }
        if(e.key==='Escape'){ e.preventDefault(); window.location.href = "{{ route('productos.series', $producto) }}"; }
      });

      // Aviso si hay cambios sin guardar
      let dirty=false;
      form?.addEventListener('input', ()=> dirty=true);
      window.addEventListener('beforeunload', (e)=>{ if(dirty && !sending){ e.preventDefault(); e.returnValue=''; }});
    })();
  </script>
</x-app-layout>
