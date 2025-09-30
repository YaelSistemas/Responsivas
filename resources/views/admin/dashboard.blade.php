@extends('layouts.admin')

@section('title', 'Panel de Administración')

@section('content')
<style>
  /* ====== Zoom responsivo: MISMA VISTA, SOLO ESCALA EN MÓVIL ====== */
  .zoom-outer{ overflow-x:hidden; }
  .zoom-inner{
    --zoom: 1;                       /* desktop */
    transform: scale(var(--zoom));
    transform-origin: top left;
    width: calc(100% / var(--zoom)); /* compensa el ancho */
  }
  /* Breakpoints (ajusta si quieres) */
  @media (max-width: 1024px){ .zoom-inner{ --zoom:.95; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }  /* tablets landscape */
  @media (max-width: 768px){  .zoom-inner{ --zoom:.90; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }  /* tablets/phones grandes */
  @media (max-width: 640px){  .zoom-inner{ --zoom:.70; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} } /* phones comunes */
  @media (max-width: 400px){  .zoom-inner{ --zoom:.55; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }  /* phones muy chicos */

  /* iOS: evita auto-zoom al enfocar inputs/botones */
  @media (max-width:768px){ input, select, textarea, button { font-size:16px; } }
</style>

<div class="zoom-outer">
  <div class="zoom-inner">
    <div class="page-wrap py-6">
      <h1 class="text-xl font-semibold">Bienvenido al panel de administración</h1>
    </div>
  </div>
</div>
@endsection
