@extends('layouts.admin')

@section('title', 'Crear Empresa')

@section('content')
<style>
  /* ====== Zoom responsivo (igual que en roles create) ====== */
  .zoom-outer{ overflow-x:hidden; }
  .zoom-inner{
    --zoom:.92; transform:scale(var(--zoom)); transform-origin:top left; width:calc(100%/var(--zoom));
  }
  @media (max-width:1024px){ .zoom-inner{ --zoom:.95 } .page-wrap{max-width:94vw;padding:0 4vw} }
  @media (max-width:768px){  .zoom-inner{ --zoom:.92 } .page-wrap{max-width:94vw;padding:0 4vw} }
  @media (max-width:640px){  .zoom-inner{ --zoom:.85 } .page-wrap{max-width:94vw;padding:0 4vw} }
  @media (max-width:400px){  .zoom-inner{ --zoom:.80 } .page-wrap{max-width:94vw;padding:0 4vw} }
  @media (max-width:768px){ input,select,textarea,button{ font-size:16px } }

  /* Limitar ancho útil en desktop (igual criterio) */
  .page-wrap{ max-width:980px; margin:0 auto; }

  /* Botones estilo roles create */
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

  /* Tarjeta simple para el formulario */
  .card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:16px}
  .muted{color:#6b7280;font-size:.85rem}
</style>

<div class="zoom-outer">
  <div class="zoom-inner">
    <div class="page-wrap py-6">
      <h2 class="text-xl font-semibold text-center mb-6">Crear empresa</h2>

      <div class="card">
        <form method="POST" action="{{ route('admin.empresas.store') }}" class="space-y-5">
          @csrf

          {{-- Nombre --}}
          <div>
            <label class="block mb-1 font-medium">Nombre</label>
            <input
              type="text"
              name="nombre"
              value="{{ old('nombre') }}"
              class="w-full border rounded px-3 py-2"
              required
              autofocus
            >
            <small class="muted">Ej: Vysisa, Reprosisa, etc.</small>
            @error('nombre') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
          </div>

          {{-- Botones --}}
          <div class="flex gap-2 justify-end">
            <a href="{{ route('admin.empresas.index') }}" class="btn-cancelar">Cancelar</a>
            <button type="submit" class="btn-guardar">Guardar</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
