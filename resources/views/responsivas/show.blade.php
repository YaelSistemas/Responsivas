{{-- resources/views/responsivas/show.blade.php --}}
<x-app-layout title="Responsiva {{ $responsiva->folio }}">
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight text-center">
      Responsiva {{ $responsiva->folio }}
    </h2>
  </x-slot>

  @php
    $col = $responsiva->colaborador;

    // Área/Depto/Sede (por compatibilidad)
    $areaDepto = $col?->area ?? $col?->departamento ?? $col?->sede ?? '';
    if (is_object($areaDepto)) {
      $areaDepto = $areaDepto->nombre ?? $areaDepto->name ?? $areaDepto->descripcion ?? (string) $areaDepto;
    } elseif (is_array($areaDepto)) {
      $areaDepto = implode(' ', array_filter($areaDepto));
    }

    // Unidad de servicio del colaborador (preferida para mostrar)
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
      $unidadServicio = $areaDepto; // fallback
    }

    // ===== LOGO y nombre de la empresa activa (para encabezado) =====
    $empresaId     = (int) session('empresa_activa', auth()->user()?->empresa_id);
    $empresaNombre = config('app.name', 'Laravel');
    $logo = asset('images/logos/default.png'); // fallback

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

    // Motivo de entrega
    $motivo          = $responsiva->motivo_entrega; // 'asignacion' | 'prestamo_provisional' | null
    $isAsignacion    = $motivo === 'asignacion';
    $isPrestamoProv  = $motivo === 'prestamo_provisional';

    // Detalles y lista de productos para la frase (SOLO NOMBRE)
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

    // ========= Razón social para "Recibí de:" =========
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

    // Nombre completo (nombre + apellidos)
    $apellidos = $col?->apellido
              ?? $col?->apellidos
              ?? trim(($col?->apellido_paterno ?? $col?->primer_apellido ?? '').' '.($col?->apellido_materno ?? $col?->segundo_apellido ?? ''));
    $nombreCompleto = trim(trim($col?->nombre ?? '').' '.trim($apellidos ?? '')) ?: ($col?->nombre ?? '');

    // Nombres de firmantes opcionales
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

    // === Formato de fechas (d-m-Y) ===
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

    // ========= Firmas (busca por ID, slug del nombre o nombre exacto) =========
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
    .sheet { max-width: 940px; margin: 0 auto; }
    .doc { background:#fff; border:1px solid #111; border-radius:6px; padding:18px; box-shadow:0 2px 6px rgba(0,0,0,.08); }
    .actions { display:flex; gap:10px; margin-bottom:14px; flex-wrap:wrap; }
    .btn { padding:8px 12px; border-radius:6px; font-weight:600; border:1px solid transparent; }
    .btn-primary { background:#2563eb; color:#fff; }
    .btn-secondary { background:#f3f4f6; color:#111; border-color:#d1d5db; }
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

  <div class="py-6 sheet">
    <div class="actions">
      <a href="{{ url('/responsivas') }}" class="btn btn-secondary">← Responsivas</a>
      <a href="{{ route('responsivas.pdf', $responsiva) }}" class="btn btn-primary">Descargar PDF</a>

      @if (empty($responsiva->firma_colaborador_path))
        <button type="button" class="btn btn-secondary" onclick="openFirma()">
          Firmar en sitio
        </button>

        <form method="POST" action="{{ route('responsivas.emitirFirma', $responsiva) }}" style="display:inline">
          @csrf
          <button class="btn btn-secondary">Generar/renovar link de firma</button>
        </form>
      @endif

      @if (session('firma_link'))
        <div style="margin-top:8px">
          <small>Link de firma: <a href="{{ session('firma_link') }}" target="_blank" rel="noopener">{{ session('firma_link') }}</a></small>
        </div>
      @endif
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

      <br><br>

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

            $specS = $s->specs ?? $s->especificaciones ?? null;
            if (is_string($specS)) { $tmp = json_decode($specS, true); if (json_last_error() === JSON_ERROR_NONE) $specS = $tmp; }
            $specP = $p->specs ?? $p->especificaciones ?? null;
            if (is_string($specP)) { $tmp = json_decode($specP, true); if (json_last_error() === JSON_ERROR_NONE) $specP = $tmp; }

            $des = '';
            if (($p->tipo ?? null) === 'equipo_pc') {
                $color = '';
                if (is_array($specS)) $color = $specS['color'] ?? '';
                if (!$color && is_array($specP)) $color = $specP['color'] ?? '';
                $des = $color !== '' ? $color : ($p->descripcion ?? '');
            } else {
                $des = $p->descripcion ?? '';
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
        {{-- Fila 1: Entregó / Recibió --}}
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

        {{-- Fila 2: Autorizó (centrado debajo) --}}
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

      {{-- Modal firma en sitio (reemplaza tu bloque actual) --}}
      @if (empty($responsiva->firma_colaborador_path))
        <div id="modalFirmar"
            class="fixed inset-0 hidden items-center justify-center bg-black/50 p-4"
            style="z-index:60;"
            onclick="closeFirma()">
          <div class="bg-white rounded-lg p-4 w-[640px] max-w-[92vw]"
              onclick="event.stopPropagation()">
            <h3 class="text-lg font-semibold mb-3">Firmar en sitio</h3>

            <form id="formFirmaEnSitio" method="POST" action="{{ route('responsivas.firmarEnSitio', $responsiva) }}">
              @csrf
              <input type="hidden" name="firma" id="firmaData">

              <div class="border border-dashed rounded p-2 mb-2" style="border-color:#94a3b8">
                <canvas id="canvasFirma" width="560" height="180" style="width:100%;height:180px;touch-action:none;"></canvas>
              </div>

              <div style="display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" class="btn btn-secondary" id="btnLimpiar">Limpiar</button>
                <button type="button" class="btn btn-secondary" onclick="closeFirma()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Firmar y guardar</button>
              </div>
            </form>
          </div>
        </div>

        <script>
        function openFirma(){
          const m = document.getElementById('modalFirmar');
          m.classList.remove('hidden'); m.classList.add('flex');
          document.documentElement.classList.add('overflow-hidden');
          document.body.classList.add('overflow-hidden');
          // recalcular tamaño cuando se abre
          setTimeout(resizeCanvas, 50);
        }
        function closeFirma(){
          const m = document.getElementById('modalFirmar');
          m.classList.remove('flex'); m.classList.add('hidden');
          document.documentElement.classList.remove('overflow-hidden');
          document.body.classList.remove('overflow-hidden');
        }
        document.addEventListener('keydown', e => { if(e.key === 'Escape') closeFirma(); });

        // ====== Canvas firma (mouse + touch) ======
        const c   = document.getElementById('canvasFirma');
        const ctx = c.getContext('2d', { willReadFrequently: true });
        c.style.touchAction = 'none'; // evita scroll/zoom gestual sobre el canvas
        c.style.pointerEvents = 'auto';
        let drawing = false, hasStrokes = false, lastX = 0, lastY = 0;

        function resizeCanvas(){
          const DPR = window.devicePixelRatio || 1;
          const rect = c.getBoundingClientRect();
          // Mantén la altura visual del estilo y ajusta resolución interna
          c.width  = Math.max(1, Math.floor(rect.width  * DPR));
          c.height = Math.max(1, Math.floor(rect.height * DPR));
          ctx.setTransform(DPR, 0, 0, DPR, 0, 0);
          ctx.lineWidth = 2;
          ctx.lineCap = 'round';
          ctx.lineJoin = 'round';
          ctx.strokeStyle = '#111';
          ctx.clearRect(0, 0, c.width, c.height);
        }
        window.addEventListener('resize', resizeCanvas);
        // si entras por primera vez sin abrir modal, lo inicializamos igual
        setTimeout(resizeCanvas, 0);

        function relPos(clientX, clientY){
          const r = c.getBoundingClientRect();
          return [clientX - r.left, clientY - r.top];
        }

        // Mouse
        c.addEventListener('mousedown', e => {
          drawing = true; hasStrokes = true;
          [lastX, lastY] = relPos(e.clientX, e.clientY);
          e.preventDefault();
        });
        c.addEventListener('mousemove', e => {
          if(!drawing) return;
          const [x, y] = relPos(e.clientX, e.clientY);
          ctx.beginPath(); ctx.moveTo(lastX, lastY); ctx.lineTo(x, y); ctx.stroke();
          lastX = x; lastY = y; e.preventDefault();
        });
        window.addEventListener('mouseup', () => { drawing = false; });

        // Touch
        c.addEventListener('touchstart', e => {
          if(!e.touches.length) return;
          drawing = true; hasStrokes = true;
          const t = e.touches[0];
          [lastX, lastY] = relPos(t.clientX, t.clientY);
          e.preventDefault();
        }, { passive:false });
        c.addEventListener('touchmove', e => {
          if(!drawing || !e.touches.length) return;
          const t = e.touches[0];
          const [x, y] = relPos(t.clientX, t.clientY);
          ctx.beginPath(); ctx.moveTo(lastX, lastY); ctx.lineTo(x, y); ctx.stroke();
          lastX = x; lastY = y; e.preventDefault();
        }, { passive:false });
        window.addEventListener('touchend', () => { drawing = false; });

        // Botón limpiar
        document.getElementById('btnLimpiar').addEventListener('click', () => {
          ctx.clearRect(0,0,c.width,c.height);
          hasStrokes = false;
        });

        // Envío
        document.getElementById('formFirmaEnSitio').addEventListener('submit', (ev) => {
          if(!hasStrokes){
            ev.preventDefault();
            alert('Por favor dibuja tu firma antes de continuar.');
            return;
          }
          document.getElementById('firmaData').value = c.toDataURL('image/png');
        });
      </script>
      @endif

    </div> <!-- /#printable -->
  </div>
</x-app-layout>
