<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraDetalle;
use App\Models\Colaborador;
use App\Models\Proveedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Str;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Services\OrdenCompraFolioService;
use App\Models\OcLog;

class OrdenCompraController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('permission:oc.view',   only: ['index','show','pdfOpen','pdfDownload']),
            new Middleware('permission:oc.create', only: ['create','store']),
            new Middleware('permission:oc.edit',   only: ['edit','update']),
            new Middleware('permission:oc.delete', only: ['destroy']),
        ];
    }

    /** Empresa activa (multi-tenant) */
    protected function tenantId(): int
    {
        return (int) (session('empresa_activa') ?? Auth::user()->empresa_id);
    }

    /* ===================== Listado ===================== */

    public function index(Request $request)
    {
        $tenantId = $this->tenantId();
        $q        = trim($request->query('q', ''));
        $perPage  = (int) $request->query('per_page', 50);
        if ($perPage <= 0)  $perPage = 10;
        if ($perPage > 100) $perPage = 100;

        $tableOC  = (new OrdenCompra)->getTable();
        $tableCol = (new Colaborador)->getTable();

        $query = OrdenCompra::with([
            'solicitante',
            'proveedor',
            'detalles:id,orden_compra_id,concepto',
            'creator:id,name',   
            'updater:id,name',   
        ])->where('empresa_tenant_id', $tenantId);

        if ($q !== '') {
            $like = "%{$q}%";

            $query->where(function ($qq) use ($like, $tableOC, $tableCol) {
                $qq->where('numero_orden', 'like', $like)
                   ->orWhere('factura', 'like', $like);

                if (Schema::hasColumn($tableOC, 'descripcion')) {
                    $qq->orWhere('descripcion', 'like', $like);
                }
                if (Schema::hasColumn($tableOC, 'proveedor')) {
                    $qq->orWhere('proveedor', 'like', $like);
                }

                $qq->orWhereHas('proveedor', function ($p) use ($like) {
                    $p->where('nombre', 'like', $like)
                      ->orWhere('rfc', 'like', $like);
                });

                $qq->orWhereHas('solicitante', function ($s) use ($like, $tableCol) {
                    $s->where(function ($w) use ($like, $tableCol) {
                        $w->where('nombre', 'like', $like);

                        foreach ([
                            'apellido', 'apellidos',
                            'apellido_paterno', 'apellido_materno',
                            'primer_apellido', 'segundo_apellido',
                        ] as $col) {
                            if (Schema::hasColumn($tableCol, $col)) {
                                $w->orWhere($col, 'like', $like);
                            }
                        }
                    });
                });

                $qq->orWhereHas('detalles', function ($d) use ($like) {
                    $d->where('concepto', 'like', $like);
                });
            });
        }

         $ocs = $query
        ->select($tableOC.'.*')
        ->addSelect(DB::raw("
            COALESCE($tableOC.seq, CAST(SUBSTRING_INDEX($tableOC.numero_orden,'-',-1) AS UNSIGNED)) as ordnum
        "))
        ->orderByDesc('ordnum')
        ->orderByDesc('fecha')
        ->paginate($perPage);

        if ($request->ajax() && $request->boolean('partial')) {
            return response()->view('oc.partials.table', ['ocs' => $ocs], 200);
        }

        return view('oc.index', [
            'ocs'     => $ocs,
            'q'       => $q,
            'perPage' => $perPage,
        ]);
    }

    /* ===================== Crear ===================== */

    public function create(OrdenCompraFolioService $folios)
    {
        $tenantId = $this->tenantId();

        // 🔹 Solo colaboradores ACTIVOS del tenant actual
        $colaboradores = Colaborador::where('empresa_tenant_id', $tenantId)
            ->where('activo', 1) // ← 🔸 filtro agregado
            ->orderBy('nombre')
            ->orderBy('apellidos')
            ->get(['id', 'nombre', 'apellidos']);

        // 🔹 Proveedores ACTIVOS del tenant actual
        $proveedores = Proveedor::where('empresa_tenant_id', $tenantId)
            ->where('activo', 1)          // 👈 solo activos
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'rfc', 'ciudad', 'estado']);

        // 🔹 Datos del usuario autenticado y prefijo de folio
        $user   = auth()->user();
        $prefix = $this->makePrefix($user);

        // 🔹 Alinear contador al máximo actual en base de datos
        $folios->reconcileToDbMax($tenantId);

        // 🔹 Sugerir el siguiente folio visible
        $nextSeq        = $folios->peekNext($tenantId);
        $numeroSugerido = sprintf('%s-%04d', $prefix, $nextSeq);

        return view('oc.create', compact('colaboradores', 'proveedores', 'numeroSugerido'));
    }

    public function store(Request $request, OrdenCompraFolioService $folios)
    {
        $tenantId = $this->tenantId();
        $tabla    = (new OrdenCompra)->getTable();

        // ✅ Validación cabecera
        $data = $request->validate([
            'fecha'           => ['required', 'date'],
            'solicitante_id'  => ['required', 'exists:colaboradores,id'],
            'proveedor_id'    => ['required', 'exists:proveedores,id'],
            'descripcion'     => ['nullable', 'string'],
            'notas'           => ['nullable','string','max:2000'],
            'monto'           => ['nullable', 'numeric', 'min:0'],
            'factura'         => ['nullable', 'string', 'max:100'],
            'numero_orden'    => ['nullable', 'string', 'max:50', Rule::unique($tabla, 'numero_orden')],
            'iva_porcentaje'  => ['nullable','numeric','min:0','max:100'],

            // ✅ ISR (retención)
            'isr_enabled'     => ['nullable','boolean'],
            'isr_pct'         => ['nullable','numeric','min:0','max:100'],
            'isr'             => ['nullable','numeric','min:0'],     // monto ISR (auto o manual)
            'isr_manual'      => ['nullable','numeric','min:0'],     // hidden cuando es manual
        ]);

        // ✅ Validación partidas
        $request->validate([
            'items'            => ['required','array','min:1'],
            'items.*.cantidad' => ['required','numeric','min:0.001'],
            'items.*.unidad'   => ['required','string','max:50'],
            'items.*.concepto' => ['required','string','max:500'],
            'items.*.moneda'   => ['required','string','max:10'],
            'items.*.precio'   => ['required','numeric','min:0'],
        ]);

        $user   = auth()->user();
        $prefix = $this->makePrefix($user);

        $orden = DB::transaction(function () use ($tenantId, $tabla, $data, $request, $prefix) {

            /* ===================== BLOQUE CRÍTICO (folios) ===================== */
            $counter = DB::table('oc_counters')->where('tenant_id', $tenantId)->lockForUpdate()->first();
            if (!$counter) {
                DB::table('oc_counters')->insert([
                    'tenant_id'  => $tenantId,
                    'last_seq'   => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $counter = (object)['last_seq' => 0];
            }

            $maxDb = (int) DB::table($tabla)
                ->where('empresa_tenant_id', $tenantId)
                ->selectRaw("MAX(CAST(SUBSTRING_INDEX(numero_orden,'-',-1) AS UNSIGNED)) AS m")
                ->value('m');

            $currSeq = max((int)$counter->last_seq, $maxDb);
            $nextSeq = $currSeq + 1;

            $isAdmin  = auth()->user()->hasRole('Administrador') || auth()->user()->can('oc.edit_prefix');
            $override = null;
            $reqSeq   = null;

            if ($isAdmin && !empty($data['numero_orden'])) {
                $override = $data['numero_orden'];
                if (preg_match('/(\d+)$/', $override, $m)) {
                    $reqSeq = (int)$m[1];
                }
            }

            $seq     = null;
            $noOrden = null;

            if ($override !== null && $reqSeq !== null) {
                $yaExiste = DB::table($tabla)
                    ->where('empresa_tenant_id', $tenantId)
                    ->where('numero_orden', $override)
                    ->exists();

                if ($yaExiste) {
                    throw ValidationException::withMessages([
                        'numero_orden' => "El número {$override} ya está en uso por otra orden.",
                    ]);
                }

                if ($reqSeq >= $nextSeq) {
                    DB::table('oc_counters')
                        ->where('tenant_id', $tenantId)
                        ->update(['last_seq' => $reqSeq, 'updated_at' => now()]);

                    $seq     = $reqSeq;
                    $noOrden = $override;
                } else {
                    $seq     = null;
                    $noOrden = $override;
                }
            } else {
                DB::table('oc_counters')
                    ->where('tenant_id', $tenantId)
                    ->update(['last_seq' => $nextSeq, 'updated_at' => now()]);

                $seq     = $nextSeq;
                $noOrden = sprintf('%s-%04d', $prefix, $seq);
            }
            /* =================== FIN BLOQUE CRÍTICO =================== */

            // =====================
            // Cabecera
            // =====================
            $payload = $data;
            $payload['empresa_tenant_id'] = $tenantId;
            $payload['numero_orden']      = $noOrden;

            if (\Schema::hasColumn($tabla, 'seq')) {
                $payload['seq'] = $seq;
            }

            if (\Schema::hasColumn($tabla, 'proveedor')) {
                $prov = \App\Models\Proveedor::where('empresa_tenant_id', $tenantId)
                    ->findOrFail($payload['proveedor_id']);
                $payload['proveedor'] = $prov->nombre;
            }

            // =====================
            // Normalización IVA%
            // =====================
            if (\Schema::hasColumn($tabla, 'iva_porcentaje')) {
                if ($data['iva_porcentaje'] === null || $data['iva_porcentaje'] === '') {
                    $payload['iva_porcentaje'] = null; // IVA manual (en tu lógica: IVA% vacío)
                } else {
                    $payload['iva_porcentaje'] = (float)$data['iva_porcentaje'];
                }
            }

            // =====================
            // Normalización ISR (cabecera)
            // =====================
            $isrEnabled   = (int) $request->boolean('isr_enabled', false);
            $isrPctReqRaw = $request->input('isr_pct');
            $isrPctReq    = ($isrPctReqRaw === null || $isrPctReqRaw === '') ? 0 : (float)$isrPctReqRaw;

            $isrMontoReqRaw = $request->input('isr'); // input readonly (auto) o editable (manual)
            $isrMontoReq    = ($isrMontoReqRaw === null || $isrMontoReqRaw === '') ? 0 : (float)$isrMontoReqRaw;

            $isrManualHidden = $request->input('isr_manual'); // hidden cuando manual

            // Si ISR no está activo, forzar ceros
            if (!$isrEnabled) {
                $isrPctReq = 0;
                $isrMontoReq = 0;
                $isrManualHidden = null;
            }

            if (\Schema::hasColumn($tabla, 'isr_enabled')) $payload['isr_enabled'] = $isrEnabled;
            if (\Schema::hasColumn($tabla, 'isr_pct'))     $payload['isr_pct']     = $isrPctReq;
            if (\Schema::hasColumn($tabla, 'isr_monto'))   $payload['isr_monto']   = $isrMontoReq;
            if (\Schema::hasColumn($tabla, 'isr_manual'))  $payload['isr_manual']  = $isrManualHidden;

            $payload['created_by'] = Auth::id();

            /** @var \App\Models\OrdenCompra $oc */
            $oc = \App\Models\OrdenCompra::create($payload);

            /* ============================================================
            PARTIDAS + IVA MANUAL (tu lógica actual, intacta)
            ============================================================ */

            // IVA%
            $ivaPctOC = $data['iva_porcentaje'] === null || $data['iva_porcentaje'] === ''
                ? 0
                : (float)$data['iva_porcentaje'];

            // IVA manual (hidden)
            $ivaManual = $request->input('iva_manual');

            // Permisos IVA manual
            $canManual = auth()->user()->hasRole('Administrador')
                    || auth()->user()->hasRole('Compras IVA');

            if (!$canManual) {
                $ivaManual = 0;
            }

            // Usar IVA manual solo si IVA% = 0 y el valor es numérico
            $usarIvaManual = ($ivaPctOC == 0 && is_numeric($ivaManual));

            // 1) Subtotales
            $subtotalOC = 0;
            $temp = [];

            foreach ($request->items as $row) {
                $cantidad = (float) ($row['cantidad'] ?? 0);
                $precio   = (float) ($row['precio'] ?? 0);
                $subtotal = round($cantidad * $precio, 4);

                $temp[] = [
                    'cantidad' => $cantidad,
                    'unidad'   => $row['unidad'] ?? null,
                    'concepto' => $row['concepto'],
                    'moneda'   => $row['moneda'],
                    'precio'   => $precio,
                    'subtotal' => $subtotal,
                ];

                $subtotalOC += $subtotal;
            }

            // 2) Crear filas con IVA correcto + ISR proporcional
            foreach ($temp as $d) {

                // ===== IVA fila =====
                if ($usarIvaManual) {
                    $ivaMonto = $subtotalOC > 0
                        ? round(($d['subtotal'] / $subtotalOC) * (float)$ivaManual, 4)
                        : 0;

                    $ivaPctFila = 0;
                } else {
                    $ivaPctFila = $ivaPctOC;
                    $ivaMonto   = round($d['subtotal'] * ($ivaPctOC / 100), 4);
                }

                // ===== ISR fila =====
                $isrPctFila   = 0;
                $isrMontoFila = 0;

                if ($isrEnabled) {
                    if ($isrPctReq > 0) {
                        // automático por % en cada fila
                        $isrPctFila   = $isrPctReq;
                        $isrMontoFila = round($d['subtotal'] * ($isrPctReq / 100), 4);
                    } else {
                        // manual: repartir proporcional al subtotal
                        $isrPctFila = 0;
                        $isrMontoFila = $subtotalOC > 0
                            ? round(($d['subtotal'] / $subtotalOC) * (float)$isrMontoReq, 4)
                            : 0;
                    }
                }

                // Total fila (Subtotal + IVA - ISR)
                $totalFila = round($d['subtotal'] + $ivaMonto - $isrMontoFila, 4);

                $detallePayload = [
                    'orden_compra_id' => $oc->id,
                    'cantidad'        => $d['cantidad'],
                    'unidad'          => $d['unidad'],
                    'concepto'        => $d['concepto'],
                    'moneda'          => $d['moneda'],
                    'precio'          => $d['precio'],
                    'importe'         => $d['subtotal'],
                    'iva_pct'         => $ivaPctFila,
                    'iva_monto'       => $ivaMonto,
                    'subtotal'        => $d['subtotal'],
                    'total'           => $totalFila,
                ];

                // Guardar ISR en detalles solo si existen columnas
                if (\Schema::hasColumn((new OrdenCompraDetalle)->getTable(), 'isr_pct')) {
                    $detallePayload['isr_pct'] = $isrPctFila;
                }
                if (\Schema::hasColumn((new OrdenCompraDetalle)->getTable(), 'isr_monto')) {
                    $detallePayload['isr_monto'] = $isrMontoFila;
                }

                OrdenCompraDetalle::create($detallePayload);
            }

            // ===== IVA cabecera =====
            if ($usarIvaManual) {
                $ivaMontoOC = (float)$ivaManual;
            } else {
                $ivaMontoOC = round($subtotalOC * ($ivaPctOC / 100), 4);
            }

            // ===== ISR cabecera =====
            $isrMontoOC = 0;
            if ($isrEnabled) {
                if ($isrPctReq > 0) {
                    $isrMontoOC = round($subtotalOC * ($isrPctReq / 100), 4);
                } else {
                    $isrMontoOC = round((float)$isrMontoReq, 4);
                }
            }

            // ===== Totales cabecera (2 decimales, lo que SIEMPRE se mostrará) =====
            $subtotal2 = round($subtotalOC, 2);
            $iva2      = round($ivaMontoOC, 2);
            $isr2      = round($isrMontoOC, 2);

            $totalOC2  = round($subtotal2 + $iva2 - $isr2, 2);

            // Guardar cabecera calculada (redondeada)
            if (\Schema::hasColumn($tabla, 'subtotal'))   $oc->subtotal   = $subtotal2;
            if (\Schema::hasColumn($tabla, 'iva_monto'))  $oc->iva_monto  = $iva2;

            // ISR cabecera (si existen columnas)
            if (\Schema::hasColumn($tabla, 'isr_enabled')) $oc->isr_enabled = $isrEnabled;
            if (\Schema::hasColumn($tabla, 'isr_pct'))     $oc->isr_pct     = $isrEnabled ? $isrPctReq : 0;
            if (\Schema::hasColumn($tabla, 'isr_monto'))   $oc->isr_monto   = $isr2;
            if (\Schema::hasColumn($tabla, 'isr_manual'))  $oc->isr_manual  = $isrEnabled ? $isrManualHidden : null;

            $oc->monto = $totalOC2;
            $oc->saveQuietly();

            return $oc;
        });

        return redirect()->route('oc.show', $orden)->with('ok', 'Orden creada.');
    }

    /* ===================== Editar ===================== */

    public function edit(OrdenCompra $oc)
    {
        $this->authorizeCompany($oc);
        $tenantId = $this->tenantId();

        // 🔹 Colaboradores ACTIVOS + el solicitante actual aunque esté inactivo (EDIT)
        $colaboradores = Colaborador::where('empresa_tenant_id', $tenantId)
            ->where(function ($q) use ($oc) {
                $q->where('activo', 1)              // todos los activos
                ->orWhere('id', $oc->solicitante_id); // + el que ya está asignado
            })
            ->orderBy('nombre')
            ->orderBy('apellidos')
            ->get(['id', 'nombre', 'apellidos']);

        // 🔹 Proveedores del tenant actual (activos + el proveedor actual aunque esté inactivo)
        $proveedores = Proveedor::where('empresa_tenant_id', $tenantId)
            ->where(function ($q) use ($oc) {
                $q->where('activo', 1)
                ->orWhere('id', $oc->proveedor_id);   // Incluye el actual aunque esté inactivo
            })
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'rfc', 'ciudad', 'estado']);

        return view('oc.edit', [
            'oc'            => $oc->load('detalles'),
            'colaboradores' => $colaboradores,
            'proveedores'   => $proveedores,
            'detalles'      => $oc->detalles,
        ]);
    }

    public function update(Request $request, OrdenCompra $oc, OrdenCompraFolioService $folios)
    {
        $this->authorizeCompany($oc);

        $tabla = (new OrdenCompra)->getTable();

        $puedeCambiarFolio = auth()->user()->hasRole('Administrador')
            || auth()->user()->can('oc.edit_prefix');

        // Validación cabecera
        $data = $request->validate([
            'numero_orden'   => [$puedeCambiarFolio ? 'required' : 'sometimes', 'string', 'max:50', Rule::unique($tabla, 'numero_orden')->ignore($oc->id)],
            'fecha'          => ['required', 'date'],
            'solicitante_id' => ['required', 'exists:colaboradores,id'],
            'proveedor_id'   => ['required', 'exists:proveedores,id'],
            'descripcion'    => ['nullable', 'string'],
            'notas'          => ['nullable','string','max:2000'],
            'factura'        => ['nullable', 'string', 'max:100'],
            'iva_porcentaje' => ['nullable','numeric','min:0','max:100'],
            // ==== ISR (cabecera solo para validar entrada) ====
            'isr_enabled'    => ['nullable', 'in:1'],
            'isr_pct'        => ['nullable','numeric','min:0','max:100'],
            'isr'            => ['nullable','numeric','min:0'], // monto ISR (cuando manual)
        ]);

        // Validación partidas
        $request->validate([
            'items'            => ['required','array','min:1'],
            'items.*.id'       => ['nullable','integer','min:1'],
            'items.*.cantidad' => ['required','numeric','min:0.001'],
            'items.*.unidad'   => ['required','string','max:50'],
            'items.*.concepto' => ['required','string','max:500'],
            'items.*.moneda'   => ['required','string','max:10'],
            'items.*.precio'   => ['required','numeric','min:0'],
        ]);

        $tenantId = $this->tenantId();

        DB::transaction(function () use ($request, $oc, $tabla, $data, $tenantId, $puedeCambiarFolio, $folios) {

            /* ========== FOLIO ========== */
            $mueveAtras = false;

            if ($puedeCambiarFolio && !empty($data['numero_orden'])) {
                if (preg_match('/(\d+)$/', $data['numero_orden'], $m)) {
                    $requestedSeq = (int) $m[1];
                    $currentNext = $folios->peekNext($tenantId);

                    if ($requestedSeq >= $currentNext) {
                        $folios->bumpTo($tenantId, $requestedSeq);
                    } else {
                        $mueveAtras = true;
                    }

                    if (\Schema::hasColumn($tabla, 'seq')) {
                        $data['seq'] = $requestedSeq;
                    }
                }
            } else {
                unset($data['numero_orden']);
            }

            /* ========== PROVEEDOR ========== */
            if (\Schema::hasColumn($tabla, 'proveedor')) {
                $prov = Proveedor::find($data['proveedor_id']);
                $data['proveedor'] = $prov?->nombre ?? '';
            }

            /* ========== Normalizar IVA% ========== */
            $rawIva = $request->input('iva_porcentaje');

            if ($rawIva === null || $rawIva === '') {
                $data['iva_porcentaje'] = null;
            } else {
                $data['iva_porcentaje'] = (float)$rawIva;
            }

            $ivaPctOC = $data['iva_porcentaje'];

            /* ============================================================
            PERMISOS PARA MANUAL (IVA e ISR)
            ============================================================ */
            $canManual = auth()->user()->hasRole('Administrador')
                        || auth()->user()->hasRole('Compras IVA');

            /* ============================================================
            IVA MANUAL (tu bloque actual, sin cambios funcionales)
            ============================================================ */
            $ivaManualMonto = (float)$request->input('iva');
            $ivaManualFlag  = $request->input('iva_manual');

            if (!$canManual) {

                if ($ivaPctOC == 0) {

                    $ivaPctOC = 0;
                    $ivaManualMonto = 0;

                    $request->merge([
                        'iva_porcentaje' => 0,
                        'iva'            => 0,
                        'iva_manual'     => null,
                    ]);

                    $usarIvaManual = false;
                } else {

                    $subtotalTmp = 0;
                    foreach ($request->items as $r) {
                        $subtotalTmp += ((float)$r['cantidad'] * (float)$r['precio']);
                    }

                    $ivaManualMonto = round($subtotalTmp * ($ivaPctOC / 100), 4);

                    $request->merge([
                        'iva_porcentaje' => $ivaPctOC,
                        'iva'            => $ivaManualMonto,
                        'iva_manual'     => null,
                    ]);

                    $usarIvaManual = false;
                }
            } else {
                $usarIvaManual = ($ivaPctOC == 0);
            }

            /* ============================================================
            ISR: detectar escenario (OFF / AUTO / MANUAL) + permisos
            ============================================================ */
            $isrOn   = $request->boolean('isr_enabled'); // si no viene el check => false
            $isrPct  = (float) ($request->input('isr_pct') ?? 0);
            $isrMontoManual = (float) ($request->input('isr') ?? 0);

            // Si NO está activo -> forzar 0
            if (!$isrOn) {
                $isrPct = 0;
                $isrMontoManual = 0;

                // para evitar arrastres raros en request
                $request->merge([
                    'isr_pct'     => 0,
                    'isr'         => 0,
                    'isr_manual'  => null,
                    'isr_enabled' => null,
                ]);

                $usarIsrManual = false;
                $usarIsrAuto   = false;
            } else {
                // Está activo
                if ($isrPct > 0) {
                    // AUTO
                    $usarIsrAuto   = true;
                    $usarIsrManual = false;
                } else {
                    // MANUAL (pct = 0)
                    if (!$canManual) {
                        // sin permiso => forzar 0
                        $isrPct = 0;
                        $isrMontoManual = 0;

                        $request->merge([
                            'isr_pct'    => 0,
                            'isr'        => 0,
                            'isr_manual' => null,
                        ]);

                        $usarIsrManual = false;
                        $usarIsrAuto   = false;
                    } else {
                        $usarIsrManual = true;
                        $usarIsrAuto   = false;
                    }
                }
            }

            /* ============================================================
            PROCESAR PARTIDAS (IVA + ISR por fila)
            ============================================================ */
            $existentes = $oc->detalles()->get()->keyBy('id');
            $vistos = [];

            $subtotalOC = 0;
            $temp = [];

            foreach ($request->items as $row) {
                $id = $row['id'] ?? null;

                $cantidad = (float) $row['cantidad'];
                $precio   = (float) $row['precio'];
                $subtotal = round($cantidad * $precio, 4);

                $temp[] = [
                    'id'       => $id,
                    'cantidad' => $cantidad,
                    'unidad'   => $row['unidad'],
                    'concepto' => $row['concepto'],
                    'moneda'   => $row['moneda'],
                    'precio'   => $precio,
                    'subtotal' => $subtotal,
                ];

                $subtotalOC += $subtotal;
            }

            // ====== IVA total OC ======
            if ($usarIvaManual) {
                $ivaMontoOC = (float)$ivaManualMonto;
            } else {
                $ivaMontoOC = round($subtotalOC * (($ivaPctOC ?? 0) / 100), 4);
            }

            // ====== ISR total OC ======
            if (!$isrOn) {
                $isrMontoOC = 0;
            } elseif ($usarIsrAuto) {
                $isrMontoOC = round($subtotalOC * ($isrPct / 100), 4);
            } elseif ($usarIsrManual) {
                $isrMontoOC = round($isrMontoManual, 4);
            } else {
                $isrMontoOC = 0;
            }

            foreach ($temp as $d) {

                // ================= IVA por fila =================
                if ($usarIvaManual) {
                    $ivaMonto = $subtotalOC > 0
                        ? round(($d['subtotal'] / $subtotalOC) * (float)$ivaMontoOC, 4)
                        : 0;
                    $ivaPctFila = 0;
                } else {
                    $ivaPctFila = (float)($ivaPctOC ?? 16);
                    $ivaMonto   = round($d['subtotal'] * ($ivaPctFila / 100), 4);
                }

                // ================= ISR por fila =================
                if (!$isrOn) {
                    $isrPctFila  = 0;
                    $isrMontoFila = 0;
                } elseif ($usarIsrAuto) {
                    $isrPctFila  = (float)$isrPct;
                    $isrMontoFila = round($d['subtotal'] * ($isrPctFila / 100), 4);
                } elseif ($usarIsrManual) {
                    $isrPctFila  = 0;
                    $isrMontoFila = ($subtotalOC > 0)
                        ? round(($d['subtotal'] / $subtotalOC) * (float)$isrMontoOC, 4)
                        : 0;
                } else {
                    $isrPctFila  = 0;
                    $isrMontoFila = 0;
                }

                // ================= Total fila =================
                $totalFila = round($d['subtotal'] + $ivaMonto - $isrMontoFila, 4);

                $payload = [
                    'cantidad'  => $d['cantidad'],
                    'unidad'    => $d['unidad'],
                    'concepto'  => $d['concepto'],
                    'moneda'    => $d['moneda'],
                    'precio'    => $d['precio'],
                    'importe'   => $d['subtotal'],

                    'iva_pct'   => $ivaPctFila,
                    'iva_monto' => $ivaMonto,
                    'isr_pct'   => $isrPctFila,
                    'isr_monto' => $isrMontoFila,

                    'subtotal'  => $d['subtotal'],
                    'total'     => $totalFila,
                ];

                if ($d['id'] && $existentes->has($d['id'])) {
                    $existentes[$d['id']]->update($payload);
                    $vistos[] = $d['id'];
                } else {
                    $oc->detalles()->create($payload);
                }
            }

            $toDelete = $existentes->keys()->diff($vistos);
            foreach ($toDelete as $delId) {
                $existentes[$delId]->delete();
            }

            // ===== Totales cabecera (2 decimales, consistente con show/pdf/index) =====
            $subtotal2 = round($subtotalOC, 2);
            $iva2      = round($ivaMontoOC, 2);
            $isr2      = round($isrMontoOC, 2);

            $totalOC2  = round($subtotal2 + $iva2 - $isr2, 2);

            if (\Schema::hasColumn($tabla, 'subtotal'))  $data['subtotal']  = $subtotal2;
            if (\Schema::hasColumn($tabla, 'iva_monto')) $data['iva_monto'] = $iva2;

            // (Opcional) si tienes columnas en cabecera:
            if (\Schema::hasColumn($tabla, 'isr_pct'))   $data['isr_pct']   = $isrOn ? ($usarIsrAuto ? $isrPct : 0) : 0;
            if (\Schema::hasColumn($tabla, 'isr_monto')) $data['isr_monto'] = $isr2;

            $data['monto']      = $totalOC2;
            $data['updated_by'] = auth()->id();

            $oc->fill($data);
            $oc->save();

            if ($mueveAtras) {
                $folios->reconcileToDbMax($tenantId);
            }
        });

        return redirect()->route('oc.show', $oc)->with('updated', true);
    }

    /**
     * Resuelve la ruta de Chrome/Chromium según el entorno.
     */
    protected function resolveChromePath(): ?string
    {
        // 1) Primero, lo que venga en el .env (servidor)
        if ($path = env('BROWSERSHOT_CHROME_PATH')) {
            if (is_file($path) || is_link($path)) {
                return $path;
            }
        }

        // 2) Luego, variable CHROME_PATH (por si la usas en local)
        if ($path = env('CHROME_PATH')) {
            if (is_file($path) || is_link($path)) {
                return $path;
            }
        }

        // 3) Rutas por defecto según sistema operativo
        if (PHP_OS_FAMILY === 'Windows') {
            $candidates = [
                'C:\Program Files\Google\Chrome\Application\chrome.exe',
                'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
                'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',
            ];
        } else {
            // Linux (VPS)
            $candidates = [
                '/usr/bin/chromium-browser',
                '/usr/bin/chromium',
                '/usr/bin/google-chrome',
            ];
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate) || is_link($candidate)) {
                return $candidate;
            }
        }

        // Si no encontramos ninguno, devolvemos null y dejamos que Browsershot intente autodetectar
        return null;
    }


    /* ===================== Vistas y PDF ===================== */

    public function show(OrdenCompra $oc)
    {
        $this->authorizeCompany($oc);
        $empresa = auth()->user()->empresa ?? null;

        // ✅ Trae el usuario que la creó (created_by)
        $oc->loadMissing(['creator:id,name']);

        return view('oc.show', compact('oc', 'empresa'));
    }

    public function destroy(OrdenCompra $oc)
    {
        $this->authorizeCompany($oc);
        $oc->delete();

        return redirect()->route('oc.index')->with('deleted', true);
    }

    protected function authorizeCompany(OrdenCompra $oc): void
    {
        if ((int) $oc->empresa_tenant_id !== $this->tenantId()) {
            abort(403);
        }
    }

    /* ===================== Helpers ===================== */

    /** Inicial de nombre + inicial de primer apellido (prefijo visible) */
    protected function makePrefix($user): string
    {
        $nombre = trim($user->nombre ?? '');
        $iniNombre = $nombre !== '' ? mb_strtoupper(mb_substr($nombre, 0, 1)) : '';

        $primerApellido = '';
        if (!empty($user->apellido_paterno)) {
            $primerApellido = trim($user->apellido_paterno);
        } elseif (!empty($user->apellidos)) {
            $primerApellido = trim(explode(' ', trim($user->apellidos))[0] ?? '');
        } elseif (!empty($user->apellido)) {
            $primerApellido = trim(explode(' ', trim($user->apellido))[0] ?? '');
        }

        if ($primerApellido === '' && isset($user->name)) {
            $parts = preg_split('/\s+/', trim($user->name));
            if (is_array($parts) && count($parts) >= 2) {
                $primerApellido = $parts[count($parts) - 2];
                if ($iniNombre === '') {
                    $iniNombre = mb_strtoupper(mb_substr($parts[0], 0, 1));
                }
            }
        }
        if ($iniNombre === '' && isset($user->name)) {
            $iniNombre = mb_strtoupper(mb_substr($user->name, 0, 1));
        }

        $iniApellido = $primerApellido !== '' ? mb_strtoupper(mb_substr($primerApellido, 0, 1)) : 'X';

        return $iniNombre . $iniApellido;
    }

    /** (opcional) siguiente global no bloqueante por prefijo */
    protected function nextConsecutiveGlobal(string $prefix, int $tenantId): string
    {
        $tabla = (new OrdenCompra)->getTable();

        $row = DB::table($tabla)
            ->where('empresa_tenant_id', $tenantId)
            ->selectRaw("numero_orden, CAST(SUBSTRING_INDEX(numero_orden,'-',-1) AS UNSIGNED) AS suf")
            ->orderByRaw("suf DESC")
            ->first();

        $next = $row ? ((int) $row->suf + 1) : 1;

        return sprintf('%s-%04d', $prefix, $next);
    }

        public function pdfOpen(OrdenCompra $oc)
    {
        $this->authorizeCompany($oc);

        $oc->loadMissing('creator');
        
        $html = view('oc.pdf_sheet', compact('oc'))->render();

        // Tomamos la ruta desde .env (BROWSERSHOT_CHROME_PATH o CHROME_PATH)
        $chromePath = env('CHROME_PATH', env('BROWSERSHOT_CHROME_PATH'));

        $shot = Browsershot::html($html)
            ->noSandbox()
            ->showBackground()
            ->emulateMedia('screen')
            ->format('A4')
            ->margins(10, 10, 10, 10)
            ->timeout(120000)
            ->waitUntil('load')
            ->setOption('args', [
                '--disable-gpu',
                '--disable-dev-shm-usage',
                '--no-first-run',
                '--no-default-browser-check',
                '--disable-extensions',
                '--disable-setuid-sandbox',
                '--no-zygote',
            ]);

        if ($chromePath && is_file($chromePath)) {
            $shot->setChromePath($chromePath);
        }

        $pdf = $shot->pdf();

        $raw = (string) ($oc->numero_orden ?? $oc->seq ?? $oc->id);

        // solo dígitos (YR-0032 -> 0032)
        $digits = preg_replace('/\D+/', '', $raw) ?: '0';

        // OC-0001 (4 dígitos)
        $folio = str_pad($digits, 4, '0', STR_PAD_LEFT);

        $filename = "OC-{$folio}.pdf";

        return response($pdf)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$filename.'"');
    }

    public function pdfDownload(OrdenCompra $oc)
    {
        $this->authorizeCompany($oc);

         $oc->loadMissing('creator');

        $html       = view('oc.pdf_sheet', compact('oc'))->render();
        $chromePath = $this->resolveChromePath();

        $shot = Browsershot::html($html)
            ->noSandbox()
            ->showBackground()
            ->emulateMedia('screen')
            ->format('A4')
            ->margins(10, 10, 10, 10)
            ->timeout(120000)
            ->waitUntil('load')
            ->setOption('args', [
                '--disable-gpu',
                '--disable-dev-shm-usage',
                '--no-first-run',
                '--no-default-browser-check',
                '--disable-extensions',
            ]);

        if ($chromePath) {
            $shot->setChromePath($chromePath);
        }

        $pdf = $shot->pdf();

        $filename = 'oc-'.($oc->numero_orden ?? Str::uuid()).'.pdf';

        return response($pdf)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    /* ===== Utilidad opcional para admins ===== */
    public function adjustCounter(Request $request, OrdenCompraFolioService $folios)
    {
        $this->authorize('oc.adjust_counter');
        $data = $request->validate(['next_seq' => ['required','integer','min:1']]);
        // Queremos que la próxima sea next_seq, por eso fijamos last_seq = next_seq - 1
        $folios->setNextSeq($this->tenantId(), (int)$data['next_seq']);
        return back()->with('ok', "Siguiente consecutivo fijado a {$data['next_seq']}");
    }

    public function updateEstado(Request $request, OrdenCompra $oc)
    {
        $user = Auth::user();

        // Solo Administrador o Compras Superior pueden cambiar estado
        if (!$user->hasAnyRole(['Administrador', 'Compras Superior'])) {
            abort(403, 'No tienes permisos para cambiar el estado de una orden.');
        }

        $data = $request->validate([
            'estado' => ['required', Rule::in(OrdenCompra::ESTADOS)]
        ]);

        $oc->estado = $data['estado'];
        if (Schema::hasColumn($oc->getTable(), 'updated_by')) {
            $oc->updated_by = $user->id;
        }
        $oc->save();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'ok'     => true,
                'estado' => $oc->estado,
                'label'  => $oc->estado_label,
                'class'  => $oc->estado_class,
                'msg'    => 'Estado actualizado correctamente.',
            ]);
        }

        return back()->with('updated', true);
    }

    public function updateRecepcion(Request $request, OrdenCompra $oc)
{
    $this->authorizeCompany($oc); // 🔐 multi-tenant

    $user = Auth::user();

    // Administrador, Compras Superior, Compras y Compras IVA
    if (!$user->hasAnyRole([
        'Administrador',
        'Compras Superior',
        'Compras',
        'Compras IVA',
    ])) {
        abort(403, 'No tienes permisos para cambiar la recepción de una orden.');
    }

    $data = $request->validate([
        'recepcion' => ['required', Rule::in(OrdenCompra::RECEPCIONES)],
    ]);

    $old = $oc->recepcion;

    $oc->recepcion = $data['recepcion'];

    if (Schema::hasColumn($oc->getTable(), 'updated_by')) {
        $oc->updated_by = $user->id;
    }

    $oc->save();

    OcLog::create([
        'orden_compra_id' => $oc->id,
        'type'            => 'recepcion_changed',
        'data'            => ['from' => $old, 'to' => $oc->recepcion],
        'user_id'         => $user->id,
    ]);

    return response()->json([
        'ok'        => true,
        'recepcion' => $oc->recepcion,
        'label'     => $oc->recepcion_label,
        'class'     => $oc->recepcion_class,
        'msg'       => 'Recepción actualizada correctamente.',
    ]);
}


}
