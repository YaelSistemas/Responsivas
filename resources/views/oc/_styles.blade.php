<style>
  /* ====== Zoom responsivo (envoltura de la hoja) ====== */
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

  /* ====== Tabla base ====== */
  .tbl { width:100%; border-collapse:collapse; table-layout:fixed; }
  .tbl th, .tbl td { border:1px solid #111; padding:6px 8px; font-size:12px; line-height:1.15; vertical-align:middle; }
  .center { text-align:center; }
  .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace; }

  /* ===== Encabezado ===== */
  .hero { table-layout: fixed; --row-h: 36px; }
  .hero tr { height: var(--row-h); }
  .hero .logo-cell { width:28%; }
  .hero .logo-box{ height: calc(var(--row-h) * 4); display:flex; align-items:center; justify-content:center; }
  .hero .logo-cell img{ max-width:220px; max-height:105px; display:block; }
  .title-row{ text-align:center; }
  .title-main{ font-weight:800; font-size:14px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .title-sub{ font-weight:700; font-size:12px; text-transform:uppercase; letter-spacing:.3px; }
  .rightcell{ padding:8px; font-size:12px; line-height:1.2; text-align:left; }
  .rightcell b{ font-weight:700; }

  /* ===== PROVEEDOR / ORDEN ===== */
  .meta-grid { margin-top:8px; }
  .meta-grid th{ font-weight:700; text-transform:uppercase; background:none; text-align:left; }
  .meta-grid td:nth-child(3), .meta-grid th:nth-child(3) { text-align:center; }

  /* Bloques de proveedor (bordes fusionados) */
  .prov-f2, .prov-f3, .prov-f4 { border-right:0 !important; border-bottom:0 !important; }
  .prov-f3, .prov-f4, .prov-f5 { border-top:0 !important; }
  .prov-f5 { border-right:0 !important; }
  .prov-c2-r2, .prov-c2-r3, .prov-c2-r4, .prov-c2-r5 { border-left:0 !important; }
  .prov-c2-r2, .prov-c2-r3, .prov-c2-r4 { border-bottom:0 !important; }
  .prov-c2-r3, .prov-c2-r4, .prov-c2-r5 { border-top:0 !important; }

  .tbl td.order-no { font-weight:700 !important; color:#c62828; font-size:14px; letter-spacing: .2px; }

  /* ===== Tabla de partidas ===== */
  .items { margin-top:12px; }
  .items .center { text-align:center; }
  .items tr.data   { height: 26px; }
  .items tr.spacer { height: 12px; }
  .items tr.spacer td { padding-top: 0; padding-bottom: 0; line-height: 1; }
  .items tr.r2 td.c1 { border-bottom:0 !important; }
  .items tr.data td,
  .items tr.spacer td { border-top:0 !important; border-bottom:0 !important; }
  .items tr.summary td,
  .items tr.summary th { border-top:0 !important; border-bottom:0 !important; }
  .items tr.summary.last td,
  .items tr.summary.last th { border-top:0 !important; border-bottom:1px solid #111 !important; }
  .items tr.extra td { border:1px solid #111 !important; height:20px; }
  .items td.qty, .items td.unit { text-align:center; }
  .items td.money,
  .items td.money-total { padding-left:6px; padding-right:6px; }
  .items td.money > .mwrap,
  .items td.money-total > .mwrap { display:flex; align-items:center; justify-content:space-between; width:100%; gap:6px; }
  .items td.money > .mwrap .sym,
  .items td.money-total > .mwrap .sym { flex:0 0 auto; min-width:1.2em; text-align:left; }
  .items td.money > .mwrap .val,
  .items td.money-total > .mwrap .val { flex:1 1 auto; text-align:right; font-variant-numeric: tabular-nums; }
  .items tr:nth-child(n+2) > td:nth-child(2) { border-right:0 !important; }
  .items tr:nth-child(n+2) > td:nth-child(3) { border-left:0  !important; }

  .items .xsmall   { font-size:10px; }
  .items .xxsmall  { font-size:9px; }
  .items .xxxsmall { font-size:8px; }
  .items .bigger3  { font-size:16px; font-weight:700; }

  /* ====== Celda de imagen (ocupa col 4-5, filas 6 a 8) ====== */
  .footer-img-td{
    background-repeat: no-repeat;
    background-position: center center;
    background-size: contain;
    height: 120px;
    border-left: 1px solid #111 !important;
    border-right: 1px solid #111 !important;
    border-top: 0 !important;
    border-bottom: 1px solid #111 !important;
    padding:0 !important;
  }

  /* ====== Print/PDF: neutraliza zoom y fija página ====== */
  @media print {
    .zoom-inner{ transform:none !important; width:auto !important; }
    .sheet{ max-width:none !important; }
    .doc{ border:0 !important; border-radius:0 !important; box-shadow:none !important; padding:0 !important; }
    @page{ size:A4 portrait; margin:10mm; }
    html, body{ margin:0 !important; padding:0 !important; }
    .title-main{ font-size:12.5px; }
    .title-sub{ font-size:10.5px; }
    .hero .logo-box{ height:90px; }
    .hero .logo-cell img{ max-height:70px; max-width:160px; }
    .tbl th, .tbl td{ padding:4px 6px; font-size:10.5px; }
    .footer-img-td{ height:100px; background-size: contain; }
  }
</style>
