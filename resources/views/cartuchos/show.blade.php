{{-- resources/views/cartuchos/show.blade.php --}}
<x-app-layout title="Salida de Consumibles {{ $cartucho->folio ?? ('#'.$cartucho->id) }}">
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight text-center">
      Entrega de Consumibles {{ $cartucho->folio ?? ('#'.$cartucho->id) }}
    </h2>
  </x-slot>

  @php
    // ===== Empresa / logo (mismo criterio que responsivas) =====
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

    // ===== Datos base =====
    $col = $cartucho->colaborador;

    // Nombre colaborador (compatible)
    $apellidos = $col?->apellido
              ?? $col?->apellidos
              ?? trim(($col?->apellido_paterno ?? $col?->primer_apellido ?? '').' '.($col?->apellido_materno ?? $col?->segundo_apellido ?? ''));
    $nombreCompleto = trim(trim($col?->nombre ?? '').' '.trim($apellidos ?? '')) ?: ($col?->nombre ?? '');

    // Unidad de servicio (compatible)
    $unidadServicio = $col?->unidadServicio
                    ?? $col?->unidad_servicio
                    ?? $col?->unidad_de_servicio
                    ?? $col?->unidad
                    ?? $col?->servicio
                    ?? '';
    if (is_object($unidadServicio)) {
      $unidadServicio = $unidadServicio->nombre ?? $unidadServicio->name ?? $unidadServicio->descripcion ?? (string) $unidadServicio;
    } elseif (is_array($unidadServicio)) {
      $unidadServicio = implode(' ', array_filter($unidadServicio));
    }

    // Equipo (impresora/multi) = producto del cartucho
    $equipoTxt = trim(($cartucho->producto?->nombre ?? '').' '.($cartucho->producto?->marca ?? '').' '.($cartucho->producto?->modelo ?? ''));

    // Realizado por (usuario)
    $realizoTxt = $cartucho->realizadoPor?->name
               ?? $cartucho->firmaRealizoUser?->name
               ?? '';

    // Fecha solicitud
    $fechaSolicitudFmt = '';
    if (!empty($cartucho->fecha_solicitud)) {
      $fs = $cartucho->fecha_solicitud;
      $fechaSolicitudFmt = $fs instanceof \Illuminate\Support\Carbon
          ? $fs->format('d-m-Y')
          : \Illuminate\Support\Carbon::parse($fs)->format('d-m-Y');
    }

    // ===== FIRMAS (como responsivas) =====
    $entregoUser = $cartucho->realizadoPor ?? $cartucho->firmaRealizoUser ?? null;
    $entregoNombre = $realizoTxt;

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

    $firmaEntrego = $firmaUrlFor($entregoUser);

    $firmaRecibio = $cartucho->firma_colaborador_url
                 ?? $cartucho->firma_colaborador_path
                 ?? $cartucho->firma_recibio_url
                 ?? $cartucho->firma_recibio_path
                 ?? null;

    if ($firmaRecibio && !str_starts_with($firmaRecibio, 'http') && !str_starts_with($firmaRecibio, 'data:')) {
        $rel = ltrim($firmaRecibio, '/');

        // Si guardaste "public/..." (Storage::disk('public')->put suele devolver esto)
        if (str_starts_with($rel, 'public/')) {
            $rel = 'storage/' . substr($rel, 7); // quita "public/"
        }

        // Si guardaste "cartuchos/firmas/..." sin el prefijo storage/
        if (!str_starts_with($rel, 'storage/')) {
            $rel = 'storage/' . $rel;
        }

        $firmaRecibio = asset($rel);
    }

    // ===== CONTROL UI DE FIRMA =====
    $yaFirmo = !empty($cartucho->firma_colaborador_path)
            || !empty($cartucho->firma_colaborador_url)
            || !empty($cartucho->firma_recibio_path)
            || !empty($cartucho->firma_recibio_url);

    // Si en BD guardas hash/expira (como te pasé), aquí solo mostramos info (opcional)
    $tieneLinkActivo = !empty($cartucho->firma_token_expires_at)
                    && \Illuminate\Support\Carbon::parse($cartucho->firma_token_expires_at)->isFuture();
  @endphp

  <style>
    /* ====== Zoom responsivo (igual idea que responsivas) ====== */
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

    /* ====== Hoja ====== */
    .sheet { max-width: 940px; margin: 0 auto; }
    .doc { background:#fff; border:1px solid #111; border-radius:6px; padding:18px; box-shadow:0 2px 6px rgba(0,0,0,.08); }

    /* ===== Acciones (igual estilo que responsivas) ===== */
    .actions{ display:flex; gap:10px; margin-bottom:14px; flex-wrap:wrap; align-items:center; }
    .actions .spacer{ flex:1 1 auto; }
    .btn { padding:8px 12px; border-radius:6px; font-weight:600; border:1px solid transparent; }
    .btn-primary { background:#2563eb; color:#fff; }
    .btn-secondary { background:#f3f4f6; color:#111; border-color:#d1d5db; }
    .btn-danger{ background:#dc2626; color:#fff; border-color:#b91c1c; }
    .btn:hover { filter:brightness(.97); }

    .tbl { width:100%; border-collapse:collapse; table-layout:fixed; }
    .tbl th, .tbl td {
      border:1px solid #111;
      padding:6px 8px;
      font-size:12px;
      line-height:1.15;
      vertical-align:middle;
      overflow-wrap:anywhere;
      word-break:break-word;
    }
    .tbl th{ font-weight:700; text-transform:uppercase; background:#f8fafc; }

    .meta .label{ font-weight:700; font-size:11px; text-transform:uppercase; padding-right:12px; }
    .meta .val{ font-size:12px; }
    .center { text-align:center; }
    .nowrap{ white-space:nowrap; word-break:normal; overflow-wrap:normal; }

    .hero .logo-cell{ width:28%; }
    .hero .logo-box{ height:120px; display:flex; align-items:center; justify-content:center; }
    .hero .logo-cell img{ max-width:200px; max-height:90px; display:block; }
    .hero .title-row{ text-align:center; }
    .title-main{ font-weight:800; font-size:14px; }
    .title-sub{ font-weight:700; font-size:12px; text-transform:uppercase; letter-spacing:.3px; }

    .tbl.meta + .tbl.meta{ margin-top:0; }
    .tbl.meta.no-border-top tr:first-child > th,
    .tbl.meta.no-border-top tr:first-child > td{ border-top:0 !important; }

    .ellipsis{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}

    /* ===== FIX: en metadatos NO cortar palabras ni encimar ===== */
    .tbl.meta td, .tbl.meta th{overflow-wrap: normal !important;word-break: normal !important;}
    .tbl.meta .label{white-space: nowrap; }
    .tbl.meta .val{white-space: nowrap;overflow: hidden;text-overflow: ellipsis;}
    .tbl.meta .center{white-space: nowrap;overflow: hidden;text-overflow: ellipsis;}
    .meta .label{font-weight:700;font-size:10px;text-transform:uppercase;padding-right:10px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .meta .val{font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}

    /* Texto */
    .texto-constancia{margin-top:10px;font-size:12px;line-height:1.35;text-align:justify;}
    .texto-compromiso{margin-top:10px;font-size:12px;line-height:1.35;text-align:justify;}

    /* Tabla Productos */
    .tabla-productos{ margin-top:10px; }
    .tabla-productos th{ text-align:center; }
    .tabla-productos .qty{ width:14%; text-align:center; font-weight:700; }
    .tabla-productos .desc{ width:86%; text-align:center; }

    /* Colores de Cartuchos */
    .color-tag{ font-weight:700; }
    .color-yellow{ color:#d4a400; }
    .color-cyan{ color:#0070c0; }
    .color-magenta{ color:#b000b5; }
    .color-black{ color:#000; }

    /* ===== Firmas ===== */
    .firmas-wrap{ margin-top:18px; display:flex; gap:30px; justify-content:space-between; align-items:flex-start; }
    .firma-col{ width:48%; text-align:center; }
    .firma-titulo{ font-weight:800; text-transform:uppercase; font-size:13px; margin-bottom:6px; }
    .firma-sub{ font-size:10px; text-transform:uppercase; margin-bottom:10px; }
    .firma-espacio{ height:60px; display:flex; align-items:center; justify-content:center; position:relative; }
    .firma-img{ max-height:60px; max-width:95%; display:block; margin:0 auto; mix-blend-mode:multiply; }
    .firma-nombre{ font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:6px; }
    .firma-linea{ width:70%; margin:0 auto; border-top:1px solid #111; }
    .firma-pie{ font-size:9px; text-transform:uppercase; margin-top:6px; }

    @media (max-width:640px){
      .firmas-wrap{ gap:18px; }
      .firma-col{ width:49%; }
      .firma-linea{ width:86%; }
    }

    /* ===== Print (ocultar acciones y quitar zoom) ===== */
    @media print{
      .zoom-inner{ transform:none !important; width:auto !important; }
      body * { visibility: hidden !important; }
      #printable, #printable * { visibility: visible !important; }
      .actions{ display:none !important; }
      .sheet{ max-width:none !important; }
      #printable { position:static; width:100% !important; margin:0 !important; }
      .doc{ border:0 !important; border-radius:0 !important; box-shadow:none !important; padding:0 !important; }
      @page{ size:A4 portrait; margin:4mm; }
      .tbl th, .tbl td{ padding:4px 6px; font-size:10px; }
      html, body{ margin:0 !important; padding:0 !important; }
    }
  </style>

  <div class="zoom-outer">
    <div class="zoom-inner">
      <div class="py-6 sheet">

        {{-- ===== BOTONES / ACCIONES (IGUAL QUE RESPONSIVAS) ===== --}}
        <div class="actions">
          {{-- IZQUIERDA: volver + PDF --}}
          <a href="{{ url('/cartuchos') }}" class="btn btn-secondary">← Consumibles</a>

          {{-- Si ya tienes route para PDF, úsalo. Si no existe, deja comentado hasta crearlo. --}}
          @if(\Illuminate\Support\Facades\Route::has('cartuchos.pdf'))
            <a href="{{ route('cartuchos.pdf', $cartucho) }}"
               class="btn btn-primary"
               target="_blank" rel="noopener">
              Descargar PDF
            </a>
          @else
            <button type="button" class="btn btn-primary" onclick="window.print()">Imprimir / Guardar PDF</button>
          @endif

          {{-- ✅ Firmar en sitio (solo si no ha firmado) --}}
          @if(!$yaFirmo && \Illuminate\Support\Facades\Route::has('cartuchos.firmarEnSitio'))
            <button type="button" class="btn btn-secondary" id="btnOpenFirma">
              Firmar en sitio
            </button>
          @endif
          
          {{-- ✅ Generar link de firma (solo si no ha firmado) --}}
          @if(!$yaFirmo && \Illuminate\Support\Facades\Route::has('cartuchos.link'))
            <form method="POST" action="{{ route('cartuchos.link', $cartucho) }}" style="display:inline">
              @csrf
              <button type="submit" class="btn btn-secondary">
                Generar link
              </button>
            </form>
          @endif

          <div class="spacer"></div>

          {{-- ✅ Borrar firma (solo si ya hay firma) --}}
          @if($yaFirmo && \Illuminate\Support\Facades\Route::has('cartuchos.firma.destroy'))
            <form method="POST"
                  action="{{ route('cartuchos.firma.destroy', $cartucho) }}"
                  onsubmit="return confirm('¿Seguro que deseas borrar la firma del colaborador?');"
                  style="display:inline">
              @csrf
              @method('DELETE')
              <button type="submit" class="btn btn-danger">Borrar firma</button>
            </form>
          @endif

        </div>

        <div id="printable" class="doc">

          {{-- ===== ENCABEZADO ===== --}}
          <table class="tbl hero">
            <colgroup><col style="width:28%"><col></colgroup>
            <tr>
              <td class="logo-cell" rowspan="3">
                <div class="logo-box"><img src="{{ $logo }}" alt=""></div>
              </td>
              <td class="title-row title-main">Grupo Vysisa</td>
            </tr>
            <tr><td class="title-row title-sub">Departamento de Sistemas</td></tr>
            <tr><td class="title-row title-sub">Formato de Entrega de Consumibles</td></tr>
          </table>

          {{-- ===== METADATOS 1 ===== --}}
          <table class="tbl meta" style="margin-top:6px">
            <colgroup>
              <col style="width:13%"><col style="width:17%">
              <col style="width:15%"><col style="width:11%">
              <col style="width:14%"><col style="width:30%">
            </colgroup>
            <tr>
              <td class="label">No. de salida</td>
              <td class="val center">{{ $cartucho->folio ?? ('#'.$cartucho->id) }}</td>

              <td class="label nowrap">Fecha de solicitud</td>
              <td class="val nowrap center">{{ $fechaSolicitudFmt }}</td>

              <td class="label">Colaborador</td>
              <td class="val center" title="{{ $nombreCompleto }}">{{ $nombreCompleto }}</td>
            </tr>
          </table>

          {{-- ===== METADATOS 2 ===== --}}
          <table class="tbl meta no-border-top">
            <colgroup>
              <col style="width:8%"><col style="width:29%">
              <col style="width:15%"><col style="width:10%">
              <col style="width:12%"><col style="width:26%">
            </colgroup>
            <tr>
              <td class="label">Equipo</td>
              <td class="val center ellipsis" title="{{ $equipoTxt }}">{{ $equipoTxt }}</td>

              <td class="label">Unidad de servicio</td>
              <td class="val center ellipsis" title="{{ $unidadServicio }}">{{ $unidadServicio }}</td>

              <td class="label">Realizado por</td>
              <td class="val center ellipsis" title="{{ $realizoTxt }}">{{ $realizoTxt }}</td>
            </tr>
          </table>

          <br>

          <p class="texto-constancia">
            Por medio de la presente hago constar que se realiza la entrega de los siguientes consumibles para el desempeño de mis actividades laborales.
          </p>

          <br>

          {{-- ===== TABLA PRODUCTOS ===== --}}
          <table class="tbl tabla-productos">
            <colgroup>
              <col style="width:20%">
              <col style="width:55%">
              <col style="width:25%">
            </colgroup>
            <thead>
              <tr>
                <th>Cantidad</th>
                <th>Descripción</th>
                <th>SKU</th>
              </tr>
            </thead>

            @php
              $detalles = $cartucho->detalles ?? collect();
              $rows = max(5, $detalles->count());
            @endphp

            <tbody>
              @for($i = 0; $i < $rows; $i++)
                @php
                  $d = $detalles[$i] ?? null;

                  $qty = '';
                  $sku = '';
                  $descBase = '';
                  $colorText = '';
                  $colorClass = '';

                  if ($d) {
                    $p = $d->producto;

                    $qty = (int) ($d->cantidad ?? 0);
                    $sku = trim((string) ($p?->sku ?? ''));

                    $descBase = trim(
                      ($p?->nombre ?? '')
                      .' '.($p?->marca ?? '')
                      .' '.($p?->modelo ?? '')
                    );

                    $rawSpecs = $p?->especificaciones ?? null;
                    $specArr = [];
                    if (is_array($rawSpecs)) {
                      $specArr = $rawSpecs;
                    } elseif (is_string($rawSpecs) && $rawSpecs !== '') {
                      $tmp = json_decode($rawSpecs, true);
                      if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) $specArr = $tmp;
                    }

                    $rawColor = trim((string)($specArr['color'] ?? $specArr['Color'] ?? ''));
                    $rawColor = preg_replace('/\s*\([^)]*\)\s*/', '', $rawColor);
                    $rawColor = trim($rawColor);

                    $key = mb_strtolower($rawColor, 'UTF-8');

                    if ($key !== '') {
                      if ($key === 'yellow')  { $colorText = 'Amarillo'; $colorClass = 'color-yellow'; }
                      elseif ($key === 'magenta'){ $colorText = 'Magenta'; $colorClass = 'color-magenta'; }
                      elseif ($key === 'cian' || $key === 'cyan'){ $colorText = 'Azul'; $colorClass = 'color-cyan'; }
                      elseif ($key === 'black'){ $colorText = 'Negro'; $colorClass = 'color-black'; }
                      else { $colorText = ucfirst($rawColor); $colorClass = ''; }
                    }
                  }
                @endphp

                <tr>
                  <td class="qty">{{ $d ? $qty : '' }}</td>

                  <td class="desc">
                    @if($d)
                      {{ $descBase }}
                      @if($colorText !== '')
                        - <span class="color-tag {{ $colorClass }}">{{ $colorText }}</span>
                      @endif
                    @else
                      &nbsp;
                    @endif
                  </td>

                  <td class="center nowrap">
                    @if($d)
                      {{ $sku }}
                    @else
                      &nbsp;
                    @endif
                  </td>
                </tr>
              @endfor
            </tbody>
          </table>

          <br>

          <p class="texto-compromiso">
            El colaborador se compromete a utilizar los consumibles conforme a las políticas internas.
            Cualquier uso indebido o extravío deberá reportarse de inmediato al Departamento de Sistemas.
          </p>

          <br><br><br>

          {{-- ===== FIRMAS ===== --}}
          <div class="firmas-wrap">
            <div class="firma-col">
              <div class="firma-titulo">ENTREGÓ</div>
              <div class="firma-sub">DEPARTAMENTO DE SISTEMAS</div>

              <div class="firma-espacio">
                @if($firmaEntrego)
                  <img class="firma-img" src="{{ $firmaEntrego }}" alt="Firma entregó">
                @endif
              </div>

              <div class="firma-nombre">{{ $entregoNombre ?: ' ' }}</div>
              <div class="firma-linea"></div>
              <div class="firma-pie">NOMBRE Y FIRMA</div>
            </div>

            <div class="firma-col">
              <div class="firma-titulo">RECIBIÓ</div>
              <div class="firma-sub">RECIBÍ DE CONFORMIDAD USUARIO</div>

              <div class="firma-espacio">
                @if(!empty($firmaRecibio))
                  <img class="firma-img" src="{{ $firmaRecibio }}" alt="Firma recibió">
                @endif
              </div>

              <div class="firma-nombre">{{ $nombreCompleto ?: ' ' }}</div>
              <div class="firma-linea"></div>
              <div class="firma-pie">NOMBRE Y FIRMA</div>
            </div>
          </div>

        </div>{{-- /#printable --}}

      </div>
    </div>
  </div>

  {{-- =========================
| MODAL: LINK DE FIRMA (como responsivas)
========================= --}}
@php $linkFirma = session('firma_link'); @endphp

@if($linkFirma)
  <div id="linkModal"
       style="position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:999999; display:flex; align-items:center; justify-content:center; padding:16px;">
    <div style="width:min(760px, 96vw); background:#fff; border-radius:12px; border:1px solid #111; padding:16px; box-shadow:0 10px 30px rgba(0,0,0,.25);">
      <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; margin-bottom:10px;">
        <div style="font-weight:900; text-transform:uppercase;">Enlace de firma</div>
        <button type="button" class="btn btn-secondary" id="btnCloseLink">Cerrar</button>
      </div>

      <div style="font-size:12px; color:#374151; margin-bottom:6px;">Link</div>
      <input id="firmaLinkInput"
             value="{{ $linkFirma }}"
             readonly
             style="width:100%; padding:10px 12px; border:1px solid #111; border-radius:8px; font-size:13px;" />

      <div style="display:flex; gap:10px; margin-top:12px; flex-wrap:wrap;">
        <a class="btn btn-primary" href="{{ $linkFirma }}" target="_blank" rel="noopener">Abrir link</a>

        <button type="button" class="btn btn-secondary" id="btnCopyLink">Copiar link</button>

        <a class="btn btn-secondary"
           href="mailto:?subject=Firma%20de%20cartucho%20{{ $cartucho->folio ?? ('#'.$cartucho->id) }}&body={{ rawurlencode($linkFirma) }}">
          Enviar por correo (Outlook)
        </a>
      </div>

      <div style="margin-top:10px; font-size:12px; color:#6b7280;">
        Nota: usa <b>mailto:</b> y abrirá tu cliente de correo predeterminado.
      </div>
    </div>
  </div>

  <script>
    (function () {
      const modal = document.getElementById('linkModal');
      const btnClose = document.getElementById('btnCloseLink');
      const btnCopy = document.getElementById('btnCopyLink');
      const input = document.getElementById('firmaLinkInput');

      function closeModal(){ if(modal) modal.style.display = 'none'; }

      if(btnClose) btnClose.addEventListener('click', closeModal);
      if(modal) modal.addEventListener('click', (e) => { if(e.target === modal) closeModal(); });

      if(btnCopy && input){
        btnCopy.addEventListener('click', async () => {
          try{
            await navigator.clipboard.writeText(input.value);
            btnCopy.textContent = 'Copiado ✅';
            setTimeout(() => btnCopy.textContent = 'Copiar link', 1200);
          }catch(e){
            input.select();
            document.execCommand('copy');
            btnCopy.textContent = 'Copiado ✅';
            setTimeout(() => btnCopy.textContent = 'Copiar link', 1200);
          }
        });
      }
    })();
  </script>
@endif


  {{-- =========================
| MODAL FIRMA EN SITIO
========================= --}}
@if(!$yaFirmo && \Illuminate\Support\Facades\Route::has('cartuchos.firmarEnSitio'))
  <div id="firmaModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:999999;">
    <div style="max-width:760px; margin:6vh auto; background:#fff; border-radius:10px; padding:16px; border:1px solid #111;">
      <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; margin-bottom:10px;">
        <div style="font-weight:800; text-transform:uppercase;">Firma del colaborador (Recibió)</div>
        <button type="button" class="btn btn-secondary" id="btnCloseFirma">Cerrar</button>
      </div>

      <div style="border:1px solid #111; border-radius:8px; overflow:hidden;">
        <canvas id="firmaCanvas" style="width:100%; height:220px; display:block;"></canvas>
      </div>

      <div style="display:flex; gap:10px; margin-top:12px; flex-wrap:wrap;">
        <button type="button" class="btn btn-secondary" id="btnClearFirma">Limpiar</button>

        <form method="POST" action="{{ route('cartuchos.firmarEnSitio', $cartucho) }}" id="firmaForm">
          @csrf
          <input type="hidden" name="firma_data" id="firmaData">
          <button type="submit" class="btn btn-primary">Guardar firma</button>
        </form>
      </div>

      <div style="margin-top:10px; font-size:12px; color:#374151;">
        Firma para: <b>{{ $nombreCompleto }}</b>
      </div>
    </div>
  </div>
@endif

{{-- =========================
| JS FIRMA EN SITIO (sin librerías)
========================= --}}
@if(!$yaFirmo && \Illuminate\Support\Facades\Route::has('cartuchos.firmarEnSitio'))
<script>
(function(){
  const btnOpen  = document.getElementById('btnOpenFirma');
  const modal    = document.getElementById('firmaModal');
  const btnClose = document.getElementById('btnCloseFirma');
  const btnClear = document.getElementById('btnClearFirma');
  const canvas   = document.getElementById('firmaCanvas');
  const form     = document.getElementById('firmaForm');
  const input    = document.getElementById('firmaData');

  if(!btnOpen || !modal || !canvas || !form || !input) return;

  const ctx = canvas.getContext('2d');
  let drawing = false;
  let last = {x:0, y:0};

  function resizeCanvas(){
    // Ajusta el tamaño real al tamaño visible
    const rect = canvas.getBoundingClientRect();
    const ratio = window.devicePixelRatio || 1;
    canvas.width  = Math.floor(rect.width * ratio);
    canvas.height = Math.floor(rect.height * ratio);
    ctx.scale(ratio, ratio);

    // fondo blanco (para PDF)
    ctx.fillStyle = "#fff";
    ctx.fillRect(0,0,rect.width,rect.height);

    // estilo trazo
    ctx.strokeStyle = "#111";
    ctx.lineWidth = 2;
    ctx.lineCap = "round";
    ctx.lineJoin = "round";
  }

  function getPos(e){
    const rect = canvas.getBoundingClientRect();
    const isTouch = e.touches && e.touches[0];
    const clientX = isTouch ? e.touches[0].clientX : e.clientX;
    const clientY = isTouch ? e.touches[0].clientY : e.clientY;
    return { x: clientX - rect.left, y: clientY - rect.top };
  }

  function start(e){
    e.preventDefault();
    drawing = true;
    last = getPos(e);
  }

  function move(e){
    if(!drawing) return;
    e.preventDefault();
    const p = getPos(e);
    ctx.beginPath();
    ctx.moveTo(last.x, last.y);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
    last = p;
  }

  function end(e){
    if(!drawing) return;
    e.preventDefault();
    drawing = false;
  }

  btnOpen.addEventListener('click', () => {
    modal.style.display = 'block';
    // al abrir, dimensiona
    setTimeout(resizeCanvas, 10);
  });

  btnClose.addEventListener('click', () => modal.style.display = 'none');
  modal.addEventListener('click', (e) => { if(e.target === modal) modal.style.display = 'none'; });

  btnClear.addEventListener('click', () => {
    const rect = canvas.getBoundingClientRect();
    ctx.fillStyle = "#fff";
    ctx.fillRect(0,0,rect.width,rect.height);
  });

  // mouse
  canvas.addEventListener('mousedown', start);
  canvas.addEventListener('mousemove', move);
  window.addEventListener('mouseup', end);

  // touch
  canvas.addEventListener('touchstart', start, {passive:false});
  canvas.addEventListener('touchmove', move, {passive:false});
  canvas.addEventListener('touchend', end, {passive:false});

  form.addEventListener('submit', (e) => {
    // Exportar como PNG base64
    input.value = canvas.toDataURL('image/png');
  });

})();
</script>
@endif

</x-app-layout>
