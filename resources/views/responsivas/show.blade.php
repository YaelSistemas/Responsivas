{{-- resources/views/responsivas/show.blade.php --}}
<x-app-layout title="Responsiva {{ $responsiva->folio }}">
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight text-center">
      Responsiva {{ $responsiva->folio }}
    </h2>
  </x-slot>

  @php
    $col = $responsiva->colaborador;

    // Área/Depto/Sede (compatibilidad)
    $areaDepto = $col?->area ?? $col?->departamento ?? $col?->sede ?? '';
    if (is_object($areaDepto)) {
      $areaDepto = $areaDepto->nombre ?? $areaDepto->name ?? $areaDepto->descripcion ?? (string) $areaDepto;
    } elseif (is_array($areaDepto)) {
      $areaDepto = implode(' ', array_filter($areaDepto));
    }

    // Unidad de servicio (preferida)
    $unidadServicio = $col?->unidad_servicio
                    ?? $col?->unidadServicio
                    ?? $col?->unidad_de_servicio
                    ?? $col?->unidad
                    ?? $col?->servicio
                    ?? '';
    if (is_object($unidadServicio)) {
      $unidadServicio = $unidadServicio->nombre ?? $unidadServicio->name ?? $unidadServicio->descripcion ?? (string) $unidadServicio;
    } elseif (is_array($unidadServicio)) {
      $unidadServicio = implode(' ', array_filter($unidadServicio));
    }
    if ($unidadServicio === '' && $areaDepto !== '') {
      $unidadServicio = $areaDepto;
    }

    // Empresa / logo
    $empresaId     = (int) session('empresa_activa', auth()->user()?->empresa_id);
    $empresaNombre = config('app.name', 'Laravel');
    $logo = asset('images/logos/default.png');
    if (class_exists(\App\Models\Empresa::class) && $empresaId) {
      $emp = \App\Models\Empresa::find($empresaId);
      if ($emp) {
        $empresaNombre = $emp->nombre ?? $empresaNombre;
        if (!empty($emp->logo_url) && filter_var($emp->logo_url, FILTER_VALIDATE_URL)) {
          $logo = $emp->logo_url;
        } else {
          $candidates = [];
          if (!empty($emp->logo_url))  $candidates[] = ltrim($emp->logo_url, '/');
          if (!empty($emp->logo))      $candidates[] = 'images/logos/'.ltrim($emp->logo, '/');
          if (!empty($emp->logo_path)) $candidates[] = ltrim($emp->logo_path, '/');
          $slug = \Illuminate\Support\Str::slug($empresaNombre, '-');
          foreach (['png','jpg','jpeg','webp','svg'] as $ext) {
            $candidates[] = "images/logos/{$slug}.{$ext}";
            $candidates[] = "images/logos/empresa-{$empresaId}.{$ext}";
            $candidates[] = "images/logos/{$empresaId}.{$ext}";
          }
          foreach ($candidates as $rel) {
            if (file_exists(public_path($rel))) { $logo = asset($rel); break; }
          }
        }
      }
    }

    // Motivo
    $motivo          = $responsiva->motivo_entrega;
    $isAsignacion    = $motivo === 'asignacion';
    $isPrestamoProv  = $motivo === 'prestamo_provisional';

    // Detalles / frases
    $detalles = $responsiva->detalles ?? collect();
    $minRows  = 6;
    $faltan   = max(0, $minRows - $detalles->count());

    $productosLista = $detalles->map(function ($d) {
      $nombre = trim($d->producto->nombre ?? '');
      return $nombre !== '' ? $nombre : null;
    })->filter()->unique()->values()->all();

    $fraseEntrega = $productosLista
      ? ('Se hace entrega de '.implode(', ', $productosLista).'.')
      : 'Se hace entrega de equipo y accesorios.';

    // Razón social emisor
    $emisorRazon = $empresaNombre;
    if ($col) {
      $sub = $col->subsidiaria ?? $col?->subsidiary ?? null;
      if (!$sub && !empty($col->subsidiaria_id) && class_exists(\App\Models\Subsidiaria::class)) {
        $sub = \App\Models\Subsidiaria::find($col->subsidiaria_id);
      }
      if ($sub) {
        $emisorRazon = $sub->razon_social
                      ?? $sub->descripcion
                      ?? $sub->nombre_fiscal
                      ?? $sub->razon
                      ?? $sub->nombre
                      ?? $emisorRazon;
      } elseif (isset($col->empresa)) {
        $emisorRazon = $col->empresa->razon_social
                    ?? $col->empresa->descripcion
                    ?? $col->empresa->nombre_fiscal
                    ?? $col->empresa->nombre
                    ?? $emisorRazon;
      }
    }

    // Nombre colaborador
    $apellidos = $col?->apellido
              ?? $col?->apellidos
              ?? trim(($col?->apellido_paterno ?? $col?->primer_apellido ?? '').' '.($col?->apellido_materno ?? $col?->segundo_apellido ?? ''));
    $nombreCompleto = trim(trim($col?->nombre ?? '').' '.trim($apellidos ?? '')) ?: ($col?->nombre ?? '');

    // Firmantes
    $entregoNombre = '';
    if (!empty($responsiva->entrego) && !is_string($responsiva->entrego)) {
      $entregoNombre = $responsiva->entrego->name ?? '';
    } elseif (!empty($responsiva->entrego_user_id) && class_exists(\App\Models\User::class)) {
      $entregoNombre = \App\Models\User::find($responsiva->entrego_user_id)?->name ?? '';
    }

    $autorizaNombre = '';
    if (!empty($responsiva->autoriza) && !is_string($responsiva->autoriza)) {
      $autorizaNombre = $responsiva->autoriza->name ?? '';
    } elseif (!empty($responsiva->autoriza_user_id) && class_exists(\App\Models\User::class)) {
      $autorizaNombre = \App\Models\User::find($responsiva->autoriza_user_id)?->name ?? '';
    }

    // Fechas
    $fechaSolicitudFmt = '';
    if (!empty($responsiva->fecha_solicitud)) {
      $fs = $responsiva->fecha_solicitud;
      $fechaSolicitudFmt = $fs instanceof \Illuminate\Support\Carbon
          ? $fs->format('d-m-Y')
          : \Illuminate\Support\Carbon::parse($fs)->format('d-m-Y');
    }

    $fechaEntregaFmt = '';
    if (!empty($responsiva->fecha_entrega)) {
      $fe = $responsiva->fecha_entrega;
      $fechaEntregaFmt = $fe instanceof \Illuminate\Support\Carbon
          ? $fe->format('d-m-Y')
          : \Illuminate\Support\Carbon::parse($fe)->format('d-m-Y');
    }

    // Firmas (ruta por id/slug/nombre)
    $firmaUrlFor = function ($user) {
      if (!$user) return null;
      $id   = $user->id ?? null;
      $name = trim($user->name ?? '');
      $cands = [];
      foreach (['png','jpg','jpeg','webp','svg'] as $ext) {
        if ($id)   $cands[] = "storage/firmas/{$id}.{$ext}";
        if ($name !== '') {
          $slug = \Illuminate\Support\Str::slug($name);
          $cands[] = "storage/firmas/{$slug}.{$ext}";
          $cands[] = "storage/firmas/{$name}.{$ext}";
        }
      }
      foreach ($cands as $rel) {
        if (file_exists(public_path($rel))) return asset($rel);
      }
      return null;
    };

    $firmaEntrego  = $firmaUrlFor($responsiva->entrego ?? null);
    $firmaAutoriza = $firmaUrlFor($responsiva->autoriza ?? null);
  @endphp

  <style>
    /* ====== Zoom responsivo (para la hoja) ====== */
    .zoom-outer{ overflow-x:hidden; }
    .zoom-inner{
      --zoom: 1;
      transform: scale(var(--zoom));
      transform-origin: top left;
      width: calc(100% / var(--zoom));
    }
    @media (max-width: 1024px){ .zoom-inner{ --zoom:.95; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }
    @media (max-width: 768px){  .zoom-inner{ --zoom:.90; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }
    @media (max-width: 640px){  .zoom-inner{ --zoom:.60; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }
    @media (max-width: 400px){  .zoom-inner{ --zoom:.55; } .page-wrap{max-width:94vw;padding-left:4vw;padding-right:4vw;} }

    /* iOS: evita auto-zoom en inputs/botones del modal */
    @media (max-width:768px){ input, select, textarea, button { font-size:16px; } }

    /* ====== Estilos de la hoja ====== */
    .sheet { max-width: 940px; margin: 0 auto; }
    .doc { background:#fff; border:1px solid #111; border-radius:6px; padding:18px; box-shadow:0 2px 6px rgba(0,0,0,.08); }

    /* acciones: ahora con align-items y spacer */
    .actions{ display:flex; gap:10px; margin-bottom:14px; flex-wrap:wrap; align-items:center; }
    .actions .spacer{ flex:1 1 auto; }

    .btn { padding:8px 12px; border-radius:6px; font-weight:600; border:1px solid transparent; }
    .btn-primary { background:#2563eb; color:#fff; }
    .btn-secondary { background:#f3f4f6; color:#111; border-color:#d1d5db; }
    .btn-danger{ background:#dc2626; color:#fff; border-color:#b91c1c; }
    .btn:hover { filter:brightness(.97); }

    .tbl { width:100%; border-collapse:collapse; table-layout:fixed; }
    .tbl th, .tbl td { border:1px solid #111; padding:6px 8px; font-size:12px; line-height:1.15; vertical-align:middle; overflow-wrap:anywhere; word-break:break-word; }
    .tbl th{ font-weight:700; text-transform:uppercase; background:#f8fafc; }
    .no-border-top { border-top:none; }

    .nowrap{ white-space:nowrap; word-break:normal; overflow-wrap:normal; }
    .center { text-align:center; }

    .hero .logo-cell{ width:28%; }
    .hero .logo-box{ height:120px; display:flex; align-items:center; justify-content:center; }
    .hero .logo-cell img{ max-width:200px; max-height:90px; display:block; }
    .hero .title-row{ text-align:center; }
    .title-main{ font-weight:800; font-size:14px; }
    .title-sub{ font-weight:700; font-size:12px; text-transform:uppercase; letter-spacing:.3px; }

    .meta .label{ font-weight:700; font-size:11px; text-transform:uppercase; padding-right:12px; }
    .meta .val{ font-size:12px; }
    .mark-x{ font-weight:700; }

    .blk{ margin-top:10px; font-size:12px; }
    .equipos th{ text-align:center; }
    .equipos td{ height:28px; text-align:center; }
    .fecha-entrega{ width:280px; margin-left:auto; }
    .fecha-entrega .label{ width:55%; }
    .fecha-entrega .val{ width:45%; }

    .firmas-wrap{ margin-top:16px; }
    .firma-row{ display:flex; gap:32px; justify-content:space-between; align-items:flex-start; }
    .sign{ flex:1; }
    .sign-title{ text-align:center; text-transform:uppercase; font-weight:700; margin-bottom:6px; }
    .sign-sub{ text-align:center; font-size:10px; text-transform:uppercase; margin:-2px 0 8px; }
    .sign-space{ height:56px; display:flex; align-items:center; justify-content:center; }
    .firma-img{ max-height:56px; max-width:100%; display:block; margin:0 auto; mix-blend-mode:multiply; }
    .sign-inner{ width:55%; margin:0 auto; }
    .sign-name{ text-align:center; font-size:11px; text-transform:uppercase; margin-bottom:2px; }
    .sign-line{ border-top:1px solid #111; height:1px; }
    .sign-caption{ text-align:center; font-size:10px; text-transform:uppercase; margin-top:4px; }
    .sign-inner.sm{ width:42%; }

    @media print {
      .zoom-inner{ transform:none !important; width:auto !important; }
      body * { visibility: hidden !important; }
      #printable, #printable * { visibility: visible !important; }
      .actions{ display:none !important; }
      .sheet{ max-width:none !important; }
      #printable { position:static; width:100% !important; margin:0 !important; }
      .doc{ border:0 !important; border-radius:0 !important; box-shadow:none !important; padding:0 !important; }
      @page{ size:A4 portrait; margin:4mm; }
      .title-main{ font-size:12px; }
      .title-sub{ font-size:10px; }
      .logo-box{ height:90px; }
      .hero .logo-cell img{ max-height:70px; max-width:160px; }
      .tbl th, .tbl td{ padding:4px 6px; font-size:10px; }
      .equipos td{ height:22px; }
      .meta .label, .meta .val{ font-size:10px; }
      .blk{ margin-top:6px; font-size:11px; }
      br{ display:none !important; }
      .hero, .meta, .equipos, .firmas-wrap { page-break-inside: avoid; }
      html, body{ margin:0 !important; padding:0 !important; }
    }

    .tbl.meta + .tbl.meta{ margin-top:0; }
    .tbl.meta.no-border-top tr:first-child > th,
    .tbl.meta.no-border-top tr:first-child > td{ border-top:0 !important; }
  </style>

  {{-- ====== ZOOM WRAPPER PARA LA HOJA ====== --}}
  <div class="zoom-outer">
    <div class="zoom-inner">
      <div class="py-6 sheet">
        <div class="actions">
          {{-- IZQUIERDA: botones normales --}}
          <a href="{{ url('/responsivas') }}" class="btn btn-secondary">← Responsivas</a>
          <a href="{{ route('responsivas.pdf', $responsiva) }}" class="btn btn-primary" target="_blank" rel="noopener">
            Descargar PDF
          </a>

          @if (empty($responsiva->firma_colaborador_path))
            @can('responsivas.edit')
              <button type="button" class="btn btn-secondary" onclick="openFirma()">Firmar en sitio</button>

              <form method="POST" action="{{ route('responsivas.emitirFirma', $responsiva) }}" style="display:inline">
                @csrf
                <button class="btn btn-secondary">Generar/renovar link de firma</button>
              </form>
            @endcan
          @endif

          {{-- DERECHA: botón rojo borrar firma --}}
          @can('responsivas.edit')
            @if (!empty($responsiva->firma_colaborador_path) || !empty($responsiva->firma_colaborador_url))
              <form method="POST"
                    action="{{ route('responsivas.firma.destroy', $responsiva) }}"
                    onsubmit="return confirm('¿Seguro que deseas borrar la firma del colaborador?');"
                    style="display:inline; margin-left:auto;">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger">
                  Borrar firma de colaborador
                </button>
              </form>
            @endif
          @endcan
        </div>

        <div id="printable" class="doc">
          {{-- ENCABEZADO --}}
          <table class="tbl hero">
            <colgroup><col style="width:28%"><col></colgroup>
            <tr>
              <td class="logo-cell" rowspan="3">
                <div class="logo-box"><img src="{{ $logo }}" alt=""></div>
              </td>
              <td class="title-row title-main">Grupo Vysisa</td>
            </tr>
            <tr><td class="title-row title-sub">Departamento de Sistemas</td></tr>
            <tr><td class="title-row title-sub">Formato de Responsiva</td></tr>
          </table>

          {{-- METADATOS 1 --}}
          <table class="tbl meta" style="margin-top:6px">
            <colgroup>
              <col style="width:13%"><col style="width:17%">
              <col style="width:16%"><col style="width:10%">
              <col style="width:18%"><col style="width:26%">
            </colgroup>
            <tr>
              <td class="label">No. de salida</td>
              <td class="val center">{{ $responsiva->folio }}</td>
              <td class="label nowrap">Fecha de solicitud</td>
              <td class="val nowrap center">{{ $fechaSolicitudFmt }}</td>
              <td class="label">Nombre del usuario</td>
              <td class="val center">{{ $nombreCompleto }}</td>
            </tr>
          </table>

          {{-- METADATOS 2 --}}
          <table class="tbl meta no-border-top">
            <colgroup>
              <col style="width:18%"><col style="width:20%">
              <col style="width:16%"><col style="width:14%"><col style="width:4%">
              <col style="width:20%"><col style="width:4%">
            </colgroup>
            <tr>
              <td class="label">ÁREA/DEPARTAMENTO</td>
              <td class="val center">{{ $unidadServicio }}</td>
              <td class="label">Motivo de entrega</td>
              <td class="label">Asignación</td>
              <td class="val center mark-x">{{ $isAsignacion ? 'X' : '' }}</td>
              <td class="label">Préstamo provisional</td>
              <td class="val center mark-x">{{ $isPrestamoProv ? 'X' : '' }}</td>
            </tr>
          </table>

          <br>

          {{-- TEXTOS --}}
          <div class="blk">
            <b>Por medio de la presente hago constar que:</b>
            <span>{{ $fraseEntrega }}</span>
          </div>

          <br>

          <div class="blk">
            <span>Recibí de: </span><b>{{ $emisorRazon }}</b>
            <span> el siguiente equipo para uso exclusivo del desempeño de mis actividades laborales asignadas,
              el cual se reserva el derecho de retirar cuando así lo considere necesario la empresa.</span>
          </div>

          <div class="blk"><span>Consta de las siguientes características</span></div>

          {{-- TABLA DE EQUIPOS --}}
          <table class="tbl equipos" style="margin-top:8px">
            <thead>
              <tr>
                <th style="width:20%">Equipo</th>
                <th style="width:28%">Descripción</th>
                <th style="width:16%">Marca</th>
                <th style="width:16%">Modelo</th>
                <th>Número de serie</th>
              </tr>
            </thead>
            <tbody>
            @foreach($detalles as $d)
              @php
                $p = $d->producto;
                $s = $d->serie;

                $specS = $s->especificaciones;
                if (is_string($specS)) { $tmp = json_decode($specS, true); if (json_last_error() === JSON_ERROR_NONE) $specS = $tmp; }
                $specP = $p->especificaciones;
                if (is_string($specP)) { $tmp = json_decode($specP, true); if (json_last_error() === JSON_ERROR_NONE) $specP = $tmp; }

                if (($p->tipo ?? null) === 'equipo_pc') {
                    $colorSerie = data_get($specS, 'color');
                    $colorProd  = data_get($specP, 'color');
                    $des = filled($colorSerie) ? $colorSerie : (filled($colorProd) ? $colorProd : ($p->descripcion ?? ''));
                } else {
                    $descSerie = data_get($specS, 'descripcion');
                    $des = filled($descSerie) ? $descSerie : ($p->descripcion ?? '');
                }
              @endphp

              <tr>
                <td style="text-transform:uppercase">{{ $p?->nombre }}</td>
                <td>{{ $des }}</td>
                <td>{{ $p?->marca }}</td>
                <td>{{ $p?->modelo }}</td>
                <td>{{ $s?->serie }}</td>
              </tr>
            @endforeach

            @for($i=0; $i<$faltan; $i++)
              <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td></tr>
            @endfor
            </tbody>
          </table>

          <div class="blk" style="margin-top:10px">
            Los daños ocasionados por el mal manejo o imprudencia, así como el robo o pérdida total o parcial a causa de
            negligencia o descuido, serán mi responsabilidad y asumo las consecuencias que de esto deriven.
          </div>

          {{-- FECHA DE ENTREGA --}}
          <table class="tbl meta fecha-entrega" style="margin-top:12px">
            <colgroup><col class="label"><col class="val"></colgroup>
            <tr>
              <td class="label nowrap">Fecha de entrega</td>
              <td class="val nowrap center">{{ $fechaEntregaFmt }}</td>
            </tr>
          </table>

          {{-- FIRMAS --}}
          <div class="firmas-wrap">
            <div class="firma-row">
              <div class="sign">
                <div class="sign-title">ENTREGÓ</div>
                <div class="sign-sub">Departamento de Sistemas</div>
                <div class="sign-space">
                  @if($firmaEntrego)
                    <img class="firma-img" src="{{ $firmaEntrego }}" alt="Firma entregó">
                  @endif
                </div>
                <div class="sign-inner">
                  <div class="sign-name">{{ $entregoNombre }}</div>
                  <div class="sign-line"></div>
                  <div class="sign-caption">Nombre y firma</div>
                </div>
              </div>

              <div class="sign">
                <div class="sign-title">RECIBIÓ</div>
                <div class="sign-sub">Recibí de conformidad Usuario</div>
                <div class="sign-space" style="position:relative;">
                  @if($responsiva->firma_colaborador_url)
                    <img
                      src="{{ $responsiva->firma_colaborador_url }}"
                      alt="Firma colaborador"
                      style="position:absolute;left:50%;top:50%;transform:translate(-50%,-55%);
                             max-width:180px;max-height:70px;opacity:.9;">
                  @endif
                </div>
                <div class="sign-inner">
                  <div class="sign-name">{{ $nombreCompleto }}</div>
                  <div class="sign-line"></div>
                  <div class="sign-caption">Nombre y firma</div>
                </div>
              </div>
            </div>

            <div class="firma-row" style="justify-content:center; margin-top:16px;">
              <div class="sign" style="flex:0 0 60%; max-width:60%;">
                <div class="sign-title">AUTORIZÓ</div>
                <div class="sign-space">
                  @if($firmaAutoriza)
                    <img class="firma-img" src="{{ $firmaAutoriza }}" alt="Firma autorizó">
                  @endif
                </div>
                <div class="sign-inner sm">
                  <div class="sign-name">{{ $autorizaNombre }}</div>
                  <div class="sign-line"></div>
                  <div class="sign-caption">Nombre y firma</div>
                </div>
              </div>
            </div>
          </div>

        </div> {{-- /#printable --}}
      </div>
    </div>
  </div>

  {{-- ========= MODAL: LINK DE FIRMA ========= --}}
  @php
    $firmaLink = session('firma_link');
    $colEmail = $col->email
              ?? $col->correo
              ?? $col->email_personal
              ?? $col->email_laboral
              ?? null;
    $subjectFirma = rawurlencode("Firma de responsiva {$responsiva->folio}");
    $bodyFirma = rawurlencode(
      "Hola {$nombreCompleto},\n\n".
      "Te comparto el enlace para firmar tu responsiva {$responsiva->folio}:\n{$firmaLink}\n\n".
      "Si tienes dudas, responde este mensaje.\n\nSaludos."
    );
  @endphp

  <div id="modalLinkFirma"
       class="fixed inset-0 {{ $firmaLink ? 'flex' : 'hidden' }} items-center justify-center bg-black/50 p-4"
       style="z-index:70;"
       onclick="closeLinkFirma()">
    <div class="bg-white rounded-lg p-4 w-[560px] max-w-[94vw]" style="border:1px solid #111" onclick="event.stopPropagation()">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
        <h3 class="text-lg font-semibold" style="margin:0">Enlace de firma</h3>
        <div style="margin-left:auto"></div>
        <button class="btn btn-secondary" type="button" onclick="closeLinkFirma()">Cerrar</button>
      </div>

      <div style="display:grid;gap:10px">
        <div>
          <label class="text-sm text-gray-600">Link</label>
          <input id="firmaLinkInput" type="text"
                 value="{{ $firmaLink ?? '' }}"
                 readonly
                 style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px">
        </div>

        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <a id="btnAbrirFirma" class="btn btn-primary"
             href="{{ $firmaLink ?? '#' }}" target="_blank" rel="noopener"
             {{ $firmaLink ? '' : 'aria-disabled=true style=pointer-events:none;opacity:.6' }}>
            Abrir link
          </a>

          <button type="button" class="btn btn-secondary" onclick="copyFirmaLink()">Copiar link</button>

          {{-- SOLO Outlook/cliente por defecto (mailto) --}}
          <a class="btn btn-secondary"
             href="mailto:{{ urlencode($colEmail ?? '') }}?subject={{ $subjectFirma }}&body={{ $bodyFirma }}"
             {{ $firmaLink ? '' : 'aria-disabled=true style=pointer-events:none;opacity:.6' }}>
            Enviar por correo (Outlook)
          </a>
        </div>

        <small class="text-gray-500">
          Nota: “Enviar por correo (Outlook)” usa <code>mailto:</code>. En Windows abrirá tu cliente de correo por defecto (Outlook de escritorio si está predeterminado).
        </small>
      </div>
    </div>
  </div>
  {{-- ========= /MODAL: LINK DE FIRMA ========= --}}

  {{-- ============= MODAL DE FIRMA (FUERA DE .zoom-inner PARA EVITAR transform) ============= --}}
  @can('responsivas.edit')
    @if (empty($responsiva->firma_colaborador_path))
      <div id="modalFirmar"
           class="fixed inset-0 hidden items-center justify-center bg-black/50 p-4"
           style="z-index:60;"
           onclick="closeFirma()">
        <div id="panelFirma"
             class="bg-white rounded-lg p-4 w-[640px] max-w-[92vw]"
             onclick="event.stopPropagation()">
          <h3 class="text-lg font-semibold mb-3">Firmar en sitio</h3>

          <form id="formFirmaEnSitio" method="POST" action="{{ route('responsivas.firmarEnSitio', $responsiva) }}">
            @csrf
            <input type="hidden" name="firma" id="firmaData">

            <div class="border border-dashed rounded p-2 mb-2" style="border-color:#94a3b8">
              <canvas id="canvasFirma"
                      width="560" height="180"
                      style="width:100%;height:180px;touch-action:none;"></canvas>
            </div>

            <div style="display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;">
              <button type="button" class="btn btn-secondary" id="btnLimpiar">Limpiar</button>
              <button type="button" class="btn btn-secondary" onclick="closeFirma()">Cancelar</button>
              <button type="submit" class="btn btn-primary">Firmar y guardar</button>
            </div>
          </form>
        </div>
      </div>
    @endif
  @endcan>

  <script>
    /* ===== Modal Link de firma ===== */
    function openLinkFirma(){
      const m = document.getElementById('modalLinkFirma');
      if (!m) return;
      m.classList.remove('hidden'); m.classList.add('flex');
      document.documentElement.classList.add('overflow-hidden');
      document.body.classList.add('overflow-hidden');
      const inp = document.getElementById('firmaLinkInput');
      if (inp && inp.value) { inp.focus(); inp.select(); }
    }
    function closeLinkFirma(){
      const m = document.getElementById('modalLinkFirma');
      if (!m) return;
      m.classList.remove('flex'); m.classList.add('hidden');
      document.documentElement.classList.remove('overflow-hidden');
      document.body.classList.remove('overflow-hidden');
    }
    function copyFirmaLink(){
      const inp = document.getElementById('firmaLinkInput');
      if (!inp || !inp.value) return;
      inp.select(); inp.setSelectionRange(0, 99999);
      (navigator.clipboard ? navigator.clipboard.writeText(inp.value) : Promise.reject())
        .then(()=> alert('Link copiado al portapapeles'))
        .catch(()=>{
          document.execCommand('copy');
          alert('Link copiado al portapapeles');
        });
    }
    document.addEventListener('DOMContentLoaded', () => {
      const inp = document.getElementById('firmaLinkInput');
      if (inp && inp.value) { inp.focus(); inp.select(); }
    });

    // ===== Abrir / cerrar modal de firma en sitio =====
    function openFirma(){
      const m = document.getElementById('modalFirmar');
      m.classList.remove('hidden'); m.classList.add('flex');
      document.documentElement.classList.add('overflow-hidden');
      document.body.classList.add('overflow-hidden');

      applyPanelSize();
      requestAnimationFrame(()=> requestAnimationFrame(resizeCanvas));
    }
    function closeFirma(){
      const m = document.getElementById('modalFirmar');
      m.classList.remove('flex'); m.classList.add('hidden');
      document.documentElement.classList.remove('overflow-hidden');
      document.body.classList.remove('overflow-hidden');
    }
    document.addEventListener('keydown', e => { if(e.key === 'Escape') { closeFirma(); closeLinkFirma(); } });

    // ===== Panel/canvas responsivo por tamaño de pantalla =====
    function applyPanelSize(){
      const panel = document.getElementById('panelFirma');
      const can   = document.getElementById('canvasFirma');

      if (window.innerWidth <= 640){
        panel.className = "bg-white rounded-lg p-6 w-[min(1600px,98vw)] max-h-[96vh] overflow-auto";
        can.width  = 1400; can.height = 420;
        can.style.height = '420px';
      } else {
        panel.className = "bg-white rounded-lg p-6 w-[min(1600px,98vw)] max-h-[96vh] overflow-auto";
        can.width  = 1400; can.height = 520;
        can.style.height = '200px';
      }
      can.style.width = '100%';
      can.style.touchAction = 'none';
    }

    // ===== Canvas firma (modal fuera de .zoom-inner) =====
    const c   = document.getElementById('canvasFirma');
    const ctx = c.getContext('2d', { willReadFrequently: true });

    c.style.pointerEvents = 'auto';
    c.style.display       = 'block';

    let drawing = false, hasStrokes = false, lastX = 0, lastY = 0;

    function resizeCanvas(){
      const DPR  = window.devicePixelRatio || 1;
      const rect = c.getBoundingClientRect();
      c.width  = Math.max(1, Math.round(rect.width  * DPR));
      c.height = Math.max(1, Math.round(rect.height * DPR));
      ctx.setTransform(DPR, 0, 0, DPR, 0, 0);
      ctx.lineWidth   = 2;
      ctx.lineCap     = 'round';
      ctx.lineJoin    = 'round';
      ctx.strokeStyle = '#111';
      ctx.clearRect(0, 0, c.width, c.height);
    }

    function relPosFromEvent(ev){
      const rect = c.getBoundingClientRect();
      const evX = (ev.clientX ?? (ev.touches && ev.touches[0]?.clientX) ?? 0);
      const evY = (ev.clientY ?? (ev.touches && ev.touches[0]?.clientY) ?? 0);
      let x = evX - rect.left;
      let y = evY - rect.top;
      x = Math.max(0, Math.min(x, rect.width  - 0.001));
      y = Math.max(0, Math.min(y, rect.height - 0.001));
      return [x, y];
    }

    // Mouse
    c.addEventListener('mousedown', e => {
      e.preventDefault();
      drawing = true; hasStrokes = true;
      [lastX, lastY] = relPosFromEvent(e);
      ctx.beginPath(); ctx.moveTo(lastX, lastY); ctx.lineTo(lastX + 0.01, lastY); ctx.stroke();
    });
    c.addEventListener('mousemove', e => {
      if(!drawing) return;
      e.preventDefault();
      const [x, y] = relPosFromEvent(e);
      ctx.beginPath(); ctx.moveTo(lastX, lastY); ctx.lineTo(x, y); ctx.stroke();
      lastX = x; lastY = y;
    });
    window.addEventListener('mouseup', () => { drawing = false; });

    // Touch
    c.addEventListener('touchstart', e => {
      if(!e.touches.length) return;
      e.preventDefault();
      drawing = true; hasStrokes = true;
      [lastX, lastY] = relPosFromEvent(e);
      ctx.beginPath(); ctx.moveTo(lastX, lastY); ctx.lineTo(lastX + 0.01, lastY); ctx.stroke();
    }, { passive:false });

    c.addEventListener('touchmove', e => {
      if(!drawing || !e.touches.length) return;
      e.preventDefault();
      const [x, y] = relPosFromEvent(e);
      ctx.beginPath(); ctx.moveTo(lastX, lastY); ctx.lineTo(x, y); ctx.stroke();
      lastX = x; lastY = y;
    }, { passive:false });

    window.addEventListener('touchend', () => { drawing = false; });

    // Recalcular en cambios de viewport
    window.addEventListener('resize', () => { applyPanelSize(); requestAnimationFrame(resizeCanvas); });
    if (window.visualViewport){
      visualViewport.addEventListener('resize', () => requestAnimationFrame(resizeCanvas));
      visualViewport.addEventListener('scroll', () => requestAnimationFrame(resizeCanvas));
    }

    // Limpiar
    const btnLimpiar = document.getElementById('btnLimpiar');
    if (btnLimpiar) {
      btnLimpiar.addEventListener('click', () => {
        ctx.clearRect(0,0,c.width,c.height);
        hasStrokes = false;
      });
    }

    // Envío
    const formFirma = document.getElementById('formFirmaEnSitio');
    if (formFirma) {
      formFirma.addEventListener('submit', (ev) => {
        if(!hasStrokes){
          ev.preventDefault();
          alert('Por favor dibuja tu firma antes de continuar.');
          return;
        }
        document.getElementById('firmaData').value = c.toDataURL('image/png');
      });
    }
  </script>
</x-app-layout>
