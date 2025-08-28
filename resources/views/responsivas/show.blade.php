<x-app-layout title="Responsiva {{ $responsiva->folio }}">
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight text-center">
      Responsiva {{ $responsiva->folio }}
    </h2>
  </x-slot>

  @php
    $col = $responsiva->colaborador;
    // Área/Depto/Sede: evita renderizar objetos/arrays
    $areaDepto = $col?->area ?? $col?->departamento ?? $col?->sede ?? '';
    if (is_object($areaDepto)) {
      $areaDepto = $areaDepto->nombre ?? $areaDepto->name ?? $areaDepto->descripcion ?? (string) $areaDepto;
    } elseif (is_array($areaDepto)) {
      $areaDepto = implode(' ', array_filter($areaDepto));
    }

    $detalles = $responsiva->detalles ?? collect();
    $minRows  = 6;
    $faltan   = max(0, $minRows - $detalles->count());
  @endphp

  <style>
    .sheet { max-width: 940px; margin: 0 auto; }
    .doc { background:#fff; border:1px solid #111; border-radius:6px; padding:18px; box-shadow:0 2px 6px rgba(0,0,0,.08); }
    .print-btn { margin-bottom:14px; }

    .tbl { width:100%; border-collapse:collapse; table-layout:fixed; }
    .tbl th, .tbl td {
      border:1px solid #111; padding:6px 8px; font-size:12px; line-height:1.15;
      vertical-align:middle; overflow-wrap:anywhere; word-break:break-word;
    }
    .tbl th{ font-weight:700; text-transform:uppercase; background:#f8fafc; }

    /* Encabezado grande (arriba) */
    .hero .logo-cell{ width:28%; text-align:center; }
    .hero .logo-cell img{ max-width:120px; max-height:48px; }
    .hero .title-cell{ text-align:center; }
    .title-main{ font-weight:700; }
    .title-sub{ font-size:12px; text-transform:uppercase; letter-spacing:.3px; }
    .hero td{ height:120px; }

    /* Bloque de metadatos estilo ejemplo (dos filas) */
    .meta-wrap{ margin-top:8px; }
    .meta .label{ font-weight:700; font-size:11px; text-transform:uppercase; }
    .meta .val{ font-size:12px; }

    .blk{ margin-top:10px; font-size:12px; }
    .blk b{ font-weight:700; }

    .equipos th{ text-align:center; }
    .equipos td{ height:28px; }

    .firmas{ margin-top:16px; }
    .firmas td{ height:46px; }
    .firma-nombre{ font-size:11px; text-transform:uppercase; text-align:center; }

    @media print {
      .print-btn{ display:none; }
      .sheet{ max-width:none; }
      .doc{ box-shadow:none; border:1px solid #000; }
      @page{ size:A4 portrait; margin:10mm; }
    }
  </style>

  <div class="py-6 sheet">
    <button class="btn btn-primary print-btn" onclick="window.print()"
            style="background:#2563eb;color:#fff;padding:8px 12px;border-radius:6px">
      Imprimir
    </button>

    <div class="doc">
      {{-- ENCABEZADO (logo + título centrado) --}}
      <table class="tbl hero">
        <colgroup>
          <col style="width:28%"><col>
        </colgroup>
        <tr>
          <td class="logo-cell">
            @php $logo = asset('img/logo.png'); @endphp
            <img src="{{ $logo }}" alt="LOGO">
            <div style="font-size:11px; margin-top:4px;">LOGO</div>
          </td>
          <td class="title-cell">
            <div class="title-main">Laravel</div>
            <div class="title-sub">Departamento de Sistemas</div>
            <div class="title-sub">Formato de Responsiva</div>
          </td>
        </tr>
      </table>

      {{-- METADATOS: fila 1 (No. de salida / Fecha solicitud / Nombre usuario) --}}
      <table class="tbl meta" style="margin-top:6px">
        <colgroup>
          <col style="width:13%"><!-- label 1 -->
          <col style="width:17%"><!-- value 1 -->
          <col style="width:14%"><!-- label 2 -->
          <col style="width:12%"><!-- value 2 -->
          <col style="width:18%"><!-- label 3 -->
          <col style="width:26%"><!-- value 3 -->
        </colgroup>
        <tr>
          <td class="label">No. de salida</td>
          <td class="val">{{ $responsiva->folio }}</td>

          <td class="label">Fecha de solicitud</td>
          <td class="val">&nbsp;</td>

          <td class="label">Nombre del usuario</td>
          <td class="val">{{ $col?->nombre }}</td>
        </tr>
      </table>

      {{-- METADATOS: fila 2 (Área/Depto/Sede / Motivo de entrega / Préstamo provisional / Asignación X) --}}
      <table class="tbl meta" style="border-top:none">
        <colgroup>
          <col style="width:18%"><!-- label área -->
          <col style="width:20%"><!-- val área -->
          <col style="width:16%"><!-- label motivo -->
          <col style="width:12%"><!-- val motivo -->
          <col style="width:18%"><!-- préstamo provisional (label) -->
          <col style="width:12%"><!-- asignación (label) -->
          <col style="width:4%"><!-- X -->
        </colgroup>
        <tr>
          <td class="label">Área/Departamento/Sede</td>
          <td class="val">{{ $areaDepto }}</td>

          <td class="label">Motivo de entrega</td>
          <td class="val">&nbsp;</td>

          <td class="label">Préstamo provisional</td>
          <td class="label">Asignación</td>
          <td class="val" style="text-align:center">X</td>
        </tr>
      </table>

      {{-- TEXTOS --}}
      <div class="blk">
        <b>Por medio de la presente hago constar que:</b>
        <span>Se hace entrega de equipo y accesorios.</span>
      </div>

      <div class="blk">
        <span>Recibí de:</span>
        <b>Laravel</b>
        <span>el siguiente equipo para uso exclusivo del desempeño de mis actividades laborales asignadas,
          el cual se reserva el derecho de retirar cuando así lo considere necesario la empresa.</span>
      </div>

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
              $p   = $d->producto;
              $s   = $d->serie;
              // Si es impresora, usa la descripción del producto; si no, déjala vacía.
              $des = ($p?->tipo === 'impresora') ? ($p?->descripcion ?? '') : '';
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

      {{-- FIRMAS --}}
      <table class="tbl firmas">
        <colgroup><col><col><col></colgroup>
        <tbody>
          <tr><td></td><td></td><td></td></tr>
          <tr>
            <td class="firma-nombre">
              ENTREGÓ<br><span style="font-size:10px">Departamento de Sistemas</span>
            </td>
            <td class="firma-nombre">
              RECIBÍ<br><span style="font-size:10px">{{ $col?->nombre }}</span>
            </td>
            <td class="firma-nombre">
              AUTORIZÓ<br><span style="font-size:10px">&nbsp;</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</x-app-layout>
